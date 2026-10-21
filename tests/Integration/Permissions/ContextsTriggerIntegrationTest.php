<?php

namespace Tests\Integration\Permissions;

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\Usuario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * Los triggers BEFORE INSERT crean el contexto de cada entidad. No basta con que
 * exista: tiene que tener su tipo y colgar del contexto de su contenedor directo,
 * porque fn_obtener_ids_contexto_ancestros recorre esa cadena para autorizar.
 */
class ContextsTriggerIntegrationTest extends TestCase
{
  use DatabaseTransactions;

  protected Usuario $docenteUsuario;
  protected Docente $docente;
  protected Asignatura $asignatura;
  protected TipoContexto $tipoContextoGlobal;
  protected TipoContexto $tipoContextoFacultad;
  protected TipoContexto $tipoContextoDepartamento;
  protected TipoContexto $tipoContextoCarrera;
  protected TipoContexto $tipoContextoCurso;
  protected TipoContexto $tipoContextoComponente;
  protected TipoContexto $tipoContextoActividad;
  protected TipoComponente $tipoComponente;

  public function setUp(): void
  {
    parent::setUp();

    // Crear tipos de contexto con valores hardcodeados (como en el seed)
    $this->tipoContextoGlobal = TipoContexto::firstOrCreate(
      ['categoria' => 'global'],
      ['tabla_referenciada' => 'GLOBAL']
    );
    $this->tipoContextoFacultad = TipoContexto::firstOrCreate(
      ['categoria' => 'facultad'],
      ['tabla_referenciada' => 'facultad']
    );
    $this->tipoContextoDepartamento = TipoContexto::firstOrCreate(
      ['categoria' => 'departamento'],
      ['tabla_referenciada' => 'departamento']
    );
    $this->tipoContextoCarrera = TipoContexto::firstOrCreate(
      ['categoria' => 'carrera'],
      ['tabla_referenciada' => 'carrera']
    );
    $this->tipoContextoCurso = TipoContexto::firstOrCreate(
      ['categoria' => 'curso'],
      ['tabla_referenciada' => 'curso']
    );

    // Crear tipos para componentes y actividades
    $this->tipoContextoComponente = TipoContexto::firstOrCreate(
      ['categoria' => 'componente'],
      ['tabla_referenciada' => 'componente']
    );
    $this->tipoContextoActividad = TipoContexto::firstOrCreate(
      ['categoria' => 'actividad'],
      ['tabla_referenciada' => 'actividad']
    );

    // Crear usuarios y datos previos
    $this->docenteUsuario = Usuario::firstOrCreate(
      ['rut' => '89123456-7'],
      [
        'username' => 'docente_ctx_test',
        'email' => 'docente_ctx@test.local',
        'passhash' => bcrypt('password'),
        'nombre1' => 'Docente',
        'apellido1' => 'Test',
        'esta_activo' => true,
      ]
    );
    $this->docenteUsuario->refresh();

    $this->docente = Docente::firstOrCreate(
      ['id_usuario' => $this->docenteUsuario->id_usuario],
      [
        'grado' => 'Magister',
        'titulo' => 'Dr. en Computación',
        'cargo' => 'Profesor Titular',
      ]
    );
    $this->docente->refresh();

    $this->asignatura = Asignatura::firstOrCreate(
      ['cod_asignatura' => 'MAT001'],
      [
        'nombre' => 'Matemática Discreta',
        'descripcion' => 'Fundamentos de Matemática Discreta',
        'creditos_sct' => 6,
        'horas_catedra' => 3,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 2,
        'horas_autonomas' => 5,
      ]
    );

    // Crear tipo de componente
    $this->tipoComponente = TipoComponente::firstOrCreate(
      ['id_tipo_componente' => 1],
      ['tipo' => 'CATEDRA']
    );
  }

  /**
   * Verifica el contexto que creó el trigger: existe, es del tipo esperado y su
   * padre es el contexto del contenedor directo.
   */
  private function assertContexto(?int $idContexto, TipoContexto $tipo, ?int $idPadreEsperado): void
  {
    $this->assertNotNull($idContexto, 'la entidad no recibió id_contexto');
    $contexto = Contexto::find($idContexto);
    $this->assertNotNull($contexto, "no existe el contexto {$idContexto}");
    $this->assertSame((int) $tipo->id_tipo_contexto, (int) $contexto->id_tipo_contexto, 'tipo de contexto');
    $this->assertNotNull($contexto->id_contexto_padre, 'contexto sin padre');
    $this->assertSame($idPadreEsperado, (int) $contexto->id_contexto_padre, 'contexto padre');
  }

  private function idContextoGlobal(): int
  {
    return (int) Contexto::where('id_tipo_contexto', $this->tipoContextoGlobal->id_tipo_contexto)
      ->value('id_contexto');
  }

  #[Test]
  public function testFacultadCreatesAssociatedContexto(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');
    $facultad = Facultad::find($facultadId);

    $this->assertContexto($facultad->id_contexto, $this->tipoContextoFacultad, $this->idContextoGlobal());
  }

  #[Test]
  public function testDepartamentoCreatesAssociatedContexto(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
      'id_facultad' => $facultadId,
      'nombre' => 'Departamento de Matemáticas',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_departamento');
    $departamento = Departamento::find($departamentoId);

    $this->assertContexto(
      $departamento->id_contexto,
      $this->tipoContextoDepartamento,
      Facultad::find($facultadId)->id_contexto
    );
  }

  #[Test]
  public function testCarreraCreatesAssociatedContexto(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
      'id_facultad' => $facultadId,
      'nombre' => 'Departamento de Matemáticas',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_departamento');

    $carrera = Carrera::create([
      'id_departamento' => $departamentoId,
      'nombre' => 'Ingeniería en Matemáticas',
    ]);
    $carrera->refresh();

    $this->assertContexto(
      $carrera->id_contexto,
      $this->tipoContextoCarrera,
      Departamento::find($departamentoId)->id_contexto
    );
  }

  #[Test]
  public function testPlanCanBeCreatedWithCarrera(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
      'id_facultad' => $facultadId,
      'nombre' => 'Departamento de Matemáticas',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_departamento');

    $carrera = Carrera::create([
      'id_departamento' => $departamentoId,
      'nombre' => 'Ingeniería en Matemáticas',
    ]);
    $carrera->refresh();

    $plan = Plan::create([
      'id_carrera' => $carrera->id_carrera,
      'agno' => 2024,
    ]);
    $plan->refresh();

    $this->assertNotNull($plan->id_plan);
    $this->assertEquals($carrera->id_carrera, $plan->id_carrera);
  }

  #[Test]
  public function testAsignacionPlanCanBeCreated(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
      'id_facultad' => $facultadId,
      'nombre' => 'Departamento de Matemáticas',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_departamento');

    $carrera = Carrera::create([
      'id_departamento' => $departamentoId,
      'nombre' => 'Ingeniería en Matemáticas',
    ]);
    $carrera->refresh();

    $plan = Plan::create([
      'id_carrera' => $carrera->id_carrera,
      'agno' => 2024,
    ]);
    $plan->refresh();

    $asignacionPlan = AsignacionPlan::create([
      'id_plan' => $plan->id_plan,
      'id_asignatura' => $this->asignatura->id_asignatura,
      'agno_planificado' => 2024,
      'semestre_planificado' => 1,
    ]);
    $asignacionPlan->refresh();

    $this->assertNotNull($asignacionPlan->id_asignacion_plan);
    $this->assertEquals($plan->id_plan, $asignacionPlan->id_plan);
    $this->assertEquals($this->asignatura->id_asignatura, $asignacionPlan->id_asignatura);
  }

  #[Test]
  public function testCursoCreatesAssociatedContexto(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
      'id_facultad' => $facultadId,
      'nombre' => 'Departamento de Matemáticas',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_departamento');

    $carrera = Carrera::create([
      'id_departamento' => $departamentoId,
      'nombre' => 'Ingeniería en Matemáticas',
    ]);
    $carrera->refresh();

    $plan = Plan::create([
      'id_carrera' => $carrera->id_carrera,
      'agno' => 2024,
    ]);
    $plan->refresh();

    $asignacionPlan = AsignacionPlan::create([
      'id_plan' => $plan->id_plan,
      'id_asignatura' => $this->asignatura->id_asignatura,
      'agno_planificado' => 2024,
      'semestre_planificado' => 1,
    ]);
    $asignacionPlan->refresh();

    $cursoId = DB::table('curso')->insertGetId([
      'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
      'id_docente_titular' => $this->docente->id_docente,
      'indice_grupo' => 1,
      'fecha_inicio' => now(),
      'fecha_fin' => now()->addMonths(6),
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_curso');
    $curso = Curso::find($cursoId);

    $this->assertContexto($curso->id_contexto, $this->tipoContextoCurso, $carrera->id_contexto);
  }

  #[Test]
  public function testComponenteCreatesAssociatedContexto(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');

    $departamentoId = DB::table('departamento')->insertGetId([
      'id_facultad' => $facultadId,
      'nombre' => 'Departamento de Matemáticas',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_departamento');

    $carrera = Carrera::create([
      'id_departamento' => $departamentoId,
      'nombre' => 'Ingeniería en Matemáticas',
    ]);
    $carrera->refresh();

    $plan = Plan::create([
      'id_carrera' => $carrera->id_carrera,
      'agno' => 2024,
    ]);
    $plan->refresh();

    $asignacionPlan = AsignacionPlan::create([
      'id_plan' => $plan->id_plan,
      'id_asignatura' => $this->asignatura->id_asignatura,
      'agno_planificado' => 2024,
      'semestre_planificado' => 1,
    ]);
    $asignacionPlan->refresh();

    $cursoId = DB::table('curso')->insertGetId([
      'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
      'id_docente_titular' => $this->docente->id_docente,
      'indice_grupo' => 1,
      'fecha_inicio' => now(),
      'fecha_fin' => now()->addMonths(6),
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_curso');
    $curso = Curso::find($cursoId);

    $componente = Componente::create([
      'id_curso' => $curso->id_curso,
      'id_tipo_componente' => $this->tipoComponente->id_tipo_componente,
      'genera_acta' => true,
      'porcentaje_aprobacion' => 60.00,
      'aprobacion_obligatoria' => true,
      'porcentaje_asistencia_obligatoria' => 75.00,
    ]);

    $componente->refresh();
    $this->assertContexto($componente->id_contexto, $this->tipoContextoComponente, $curso->id_contexto);
  }

  #[Test]
  public function testActividadCreatesAssociatedContexto(): void
  {
    $facultad = Facultad::create([
      'nombre' => 'Facultad de Ciencias',
    ]);
    $departamento = Departamento::create([
      'id_facultad' => $facultad->id_facultad,
      'nombre' => 'Departamento de Matemáticas',
    ]);
    $carrera = Carrera::create([
      'id_departamento' => $departamento->id_departamento,
      'nombre' => 'Ingeniería en Matemáticas',
    ]);
    $plan = Plan::create([
      'id_carrera' => $carrera->id_carrera,
      'agno' => 2024,
    ]);
    $asignacionPlan = AsignacionPlan::create([
      'id_plan' => $plan->id_plan,
      'id_asignatura' => $this->asignatura->id_asignatura,
      'agno_planificado' => 2024,
      'semestre_planificado' => 1,
    ]);
    $asignacionPlan->refresh();

    $cursoId = DB::table('curso')->insertGetId([
      'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
      'id_docente_titular' => $this->docente->id_docente,
      'indice_grupo' => 1,
      'fecha_inicio' => now(),
      'fecha_fin' => now()->addMonths(6),
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_curso');
    $curso = Curso::find($cursoId);

    $componente = Componente::create([
      'id_curso' => $curso->id_curso,
      'id_tipo_componente' => $this->tipoComponente->id_tipo_componente,
      'genera_acta' => true,
      'porcentaje_aprobacion' => 60.00,
      'aprobacion_obligatoria' => true,
      'porcentaje_asistencia_obligatoria' => 75.00,
    ]);
    $componente->refresh();

    $unidad = Unidad::create([
      'id_curso' => $curso->id_curso,
      'num_unidad' => 1,
      'nombre' => 'Unidad 1',
    ]);
    $unidad->refresh();

    $actividadId = DB::table('actividad')->insertGetId([
      'id_componente' => $componente->id_componente,
      'id_unidad' => $unidad->id_unidad,
      'nombre' => 'Actividad de prueba',
      'ponderacion' => 20,
      'tipo_actividad' => 'SUMATIVA',
      'tipo_entrega' => 'DIGITAL',
      'ponderacion' => 10.00,
      'visible' => true,
      'es_grupal' => false,
      'max_integrantes' => 1,
    ], 'id_actividad');
    $actividad = Actividad::find($actividadId);

    // La actividad pertenece al componente (ContextType: ACTIVIDAD → COMPONENTE).
    $this->assertContexto($actividad->id_contexto, $this->tipoContextoActividad, $componente->id_contexto);
  }

  #[Test]
  public function testFullHierarchyCreatesAllRequiredContextos(): void
  {
    // Crear jerarquía completa
    $facultadId = DB::table('facultad')->insertGetId(['nombre' => 'Facultad de Ingeniería', 'fecha_creacion' => now(), 'fecha_modificacion' => now()], 'id_facultad');
    $facultad = Facultad::find($facultadId);

    $departamentoId = DB::table('departamento')->insertGetId(['id_facultad' => $facultadId, 'nombre' => 'Departamento de Sistemas', 'fecha_creacion' => now(), 'fecha_modificacion' => now()], 'id_departamento');
    $departamento = Departamento::find($departamentoId);

    $carrera = Carrera::create(['id_departamento' => $departamentoId, 'nombre' => 'Carrera de Ingeniería en Sistemas']);
    $carrera->refresh();

    $plan = Plan::create(['id_carrera' => $carrera->id_carrera, 'agno' => 2024]);
    $plan->refresh();
    $asignacionPlan = AsignacionPlan::create([
      'id_plan' => $plan->id_plan,
      'id_asignatura' => $this->asignatura->id_asignatura,
      'agno_planificado' => 2024,
      'semestre_planificado' => 1,
    ]);
    $asignacionPlan->refresh();

    $cursoId = DB::table('curso')->insertGetId([
      'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
      'id_docente_titular' => $this->docente->id_docente,
      'indice_grupo' => 1,
      'fecha_inicio' => now(),
      'fecha_fin' => now()->addMonths(6),
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_curso');
    $curso = Curso::find($cursoId);

    $componente = Componente::create([
      'id_curso' => $curso->id_curso,
      'id_tipo_componente' => $this->tipoComponente->id_tipo_componente,
      'genera_acta' => true,
      'porcentaje_aprobacion' => 60.00,
      'aprobacion_obligatoria' => true,
      'porcentaje_asistencia_obligatoria' => 75.00,
    ]);
    $componente->refresh();

    $unidad = Unidad::create([
      'id_curso' => $curso->id_curso,
      'num_unidad' => 1,
      'nombre' => 'Unidad 1',
    ]);
    $unidad->refresh();

    $actividadId = DB::table('actividad')->insertGetId([
      'id_componente' => $componente->id_componente,
      'id_unidad' => $unidad->id_unidad,
      'nombre' => 'Actividad de prueba',
      'ponderacion' => 20,
      'tipo_actividad' => 'SUMATIVA',
      'tipo_entrega' => 'DIGITAL',
      'ponderacion' => 10.00,
      'visible' => true,
      'es_grupal' => false,
      'max_integrantes' => 1,
    ], 'id_actividad');
    $actividad = Actividad::find($actividadId);

    // Cadena completa: cada contexto cuelga del de su contenedor directo, hasta global.
    $this->assertContexto($facultad->id_contexto, $this->tipoContextoFacultad, $this->idContextoGlobal());
    $this->assertContexto($departamento->id_contexto, $this->tipoContextoDepartamento, $facultad->id_contexto);
    $this->assertContexto($carrera->id_contexto, $this->tipoContextoCarrera, $departamento->id_contexto);
    $this->assertContexto($curso->id_contexto, $this->tipoContextoCurso, $carrera->id_contexto);
    $this->assertContexto($componente->id_contexto, $this->tipoContextoComponente, $curso->id_contexto);
    $this->assertContexto($actividad->id_contexto, $this->tipoContextoActividad, $componente->id_contexto);
  }
}
