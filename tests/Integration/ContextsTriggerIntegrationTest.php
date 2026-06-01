<?php

namespace Tests\Integration;

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
use Illuminate\Support\Facades\DB;

class ContextsTriggerIntegrationTest extends TestCase
{
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
      ['rut' => '12345678-9'],
      [
        'username' => 'docente_test',
        'email' => 'docente@test.local',
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

  #[Test]
  public function testFacultadCreatesAssociatedContexto(): void
  {
    $facultadId = DB::table('facultad')->insertGetId([
      'nombre' => 'Facultad de Ciencias',
      'fecha_creacion' => now(),
      'fecha_modificacion' => now(),
    ], 'id_facultad');
    $facultad = Facultad::find($facultadId);

    $this->assertNotNull($facultad->id_contexto);
    $contexto = Contexto::find($facultad->id_contexto);
    $this->assertNotNull($contexto);
    $this->assertEquals($this->tipoContextoFacultad->id, $contexto->id_TipoContexto);
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

    $this->assertNotNull($departamento->id_contexto);
    $contexto = Contexto::find($departamento->id_contexto);
    $this->assertNotNull($contexto);
    $this->assertEquals($this->tipoContextoDepartamento->id, $contexto->id_TipoContexto);
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

    $this->assertNotNull($carrera->id_contexto);
    $contexto = Contexto::find($carrera->id_contexto);
    $this->assertNotNull($contexto);
    $this->assertEquals($this->tipoContextoCarrera->id, $contexto->id_TipoContexto);
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

    $this->assertNotNull($curso->id_contexto);
    $contexto = Contexto::find($curso->id_contexto);
    $this->assertNotNull($contexto);
    $this->assertEquals($this->tipoContextoCurso->id, $contexto->id_TipoContexto);
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
    $this->assertNotNull($componente->id_contexto);
    $contexto = Contexto::find($componente->id_contexto);
    $this->assertNotNull($contexto);
    $this->assertEquals($this->tipoContextoComponente->id, $contexto->id_TipoContexto);
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
      'tipo_actividad' => 'SUMATIVA',
      'tipo_entrega' => 'DIGITAL',
      'visible' => true,
      'es_grupal' => false,
      'max_integrantes' => 1,
    ], 'id_actividad');
    $actividad = Actividad::find($actividadId);

    $this->assertNotNull($actividad->id_contexto);
    $contexto = Contexto::find($actividad->id_contexto);
    $this->assertNotNull($contexto);
    $this->assertEquals($this->tipoContextoActividad->id, $contexto->id_TipoContexto);
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
      'tipo_actividad' => 'SUMATIVA',
      'tipo_entrega' => 'DIGITAL',
      'visible' => true,
      'es_grupal' => false,
      'max_integrantes' => 1,
    ], 'id_actividad');
    $actividad = Actividad::find($actividadId);

    // Verificar que todos tienen contextos
    $this->assertNotNull($facultad->id_contexto);
    $this->assertNotNull($departamento->id_contexto);
    $this->assertNotNull($carrera->id_contexto);
    $this->assertNotNull($curso->id_contexto);
    $this->assertNotNull($componente->id_contexto);
    $this->assertNotNull($actividad->id_contexto);

    // Verificar tipos de contexto correctos
    $this->assertEquals($this->tipoContextoFacultad->id, Contexto::find($facultad->id_contexto)->id_TipoContexto);
    $this->assertEquals($this->tipoContextoDepartamento->id, Contexto::find($departamento->id_contexto)->id_TipoContexto);
    $this->assertEquals($this->tipoContextoCarrera->id, Contexto::find($carrera->id_contexto)->id_TipoContexto);
    $this->assertEquals($this->tipoContextoCurso->id, Contexto::find($curso->id_contexto)->id_TipoContexto);
    $this->assertEquals($this->tipoContextoComponente->id, Contexto::find($componente->id_contexto)->id_TipoContexto);
    $this->assertEquals($this->tipoContextoActividad->id, Contexto::find($actividad->id_contexto)->id_TipoContexto);
  }
}
