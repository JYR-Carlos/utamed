<?php

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

function crearEstudianteActividades(array $usuarioAttrs = [], array $estudianteAttrs = []): array
{
    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $usuario = Usuario::factory()->create(array_merge([
        'rut' => '12.345.678-5',
        'nombre1' => 'JUAN',
        'nombre2' => 'CARLOS',
        'apellido1' => 'PÉREZ',
        'apellido2' => 'GONZÁLEZ',
        'email' => 'juan.perez@alumnos.uta.cl',
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ], $usuarioAttrs));

    $estudiante = Estudiante::factory()->create(array_merge([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'agno_ingreso' => 2024,
    ], $estudianteAttrs));

    UsuarioRolAsignacion::create([
        'id_usuario' => $usuario->id_usuario,
        'id_rol' => Rol::where('nombre', 'Estudiante')->value('id_rol'),
        'id_contexto' => 1,
        'asignado_por' => $usuario->id_usuario,
        'creado_por' => $usuario->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    return [$usuario, $estudiante, $carrera];
}

function crearComponenteTest(int $idCurso, int $idTipoComponente): Componente
{
    return Componente::create([
        'id_curso' => $idCurso,
        'id_tipo_componente' => $idTipoComponente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);
}

function crearActividadTest(array $attrs): Actividad
{
    $idCurso = Componente::where('id_componente', $attrs['id_componente'])->value('id_curso');
    $unidad = Unidad::firstOrCreate([
        'id_curso' => $idCurso,
        'num_unidad' => 1,
    ], [
        'nombre' => 'Unidad I',
    ]);

    return Actividad::create(array_merge([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'tipo_entrega' => 'CON_ENTREGA',
        'es_grupal' => false,
        'ponderacion' => 20.0,
        'exigencia' => 60.0,
        'id_unidad' => $unidad->id_unidad,
    ], $attrs));
}

function crearEstructuraCursoActividades(Carrera $carrera): array
{
    static $contador = 1;
    $contador++;

    $plan = Plan::create([
        'id_carrera' => $carrera->id_carrera,
        'agno_plan' => 2024,
        'version_plan' => 1,
    ]);

    $asignatura = Asignatura::create([
        'cod_asignatura' => 'MED-' . $contador . '-' . rand(100, 999),
        'nombre' => 'Medicina Interna I',
        'creditos_sct' => 6,
        'horas_catedra' => 2,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 1,
        'horas_autonomas' => 3,
        'descripcion' => 'Tipo: FE',
    ]);

    $asignacion = AsignacionPlan::create([
        'id_plan' => $plan->id_plan,
        'id_asignatura' => $asignatura->id_asignatura,
        'agno_planificado' => 2024,
        'semestre_planificado' => 2,
    ]);

    $curso = Curso::factory()->create([
        'nombre' => 'Medicina Interna I',
        'cod_curso' => 1000 + $contador,
        'indice_grupo' => 1,
        'semestre_real' => 2,
        'agno_real' => 2026,
        'id_asignacion_plan' => $asignacion->id_asignacion_plan,
        'fecha_inicio' => Carbon::parse('2026-08-01'),
        'fecha_fin' => Carbon::parse('2026-12-15'),
    ]);

    return [$curso, $asignatura, $plan];
}

it('rechaza con 401 si no hay token de autenticacion', function () {
    $this->getJson('/api/v1/estudiante/cursos/1/actividades')
        ->assertStatus(401);

    $this->getJson('/api/v1/estudiante/cursos/1/actividades/1')
        ->assertStatus(401);

    $this->getJson('/api/v1/estudiante/cursos/1/actividades/1/enunciado')
        ->assertStatus(401);
});

it('rechaza con 403 si el usuario no tiene rol o perfil estudiante', function () {
    $usuario = Usuario::factory()->create([
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $this->actingAs($usuario)
        ->getJson('/api/v1/estudiante/cursos/1/actividades')
        ->assertStatus(403);
});

it('rechaza con 403 Forbidden cuando el estudiante no esta inscrito en el curso', function () {
    [$usuario, , $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades")
        ->assertStatus(403)
        ->assertJsonPath('message', 'No estás inscrito en este curso.');
});

it('rechaza con 403 Forbidden cuando la inscripcion del alumno esta en estado RETIRADO', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'RETIRADO',
    ]);

    $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades")
        ->assertStatus(403)
        ->assertJsonPath('message', 'No estás inscrito en este curso.');
});

it('rechaza con 404 Not Found si la actividad pertenece a otro curso diferente (anti-IDOR)', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso1] = crearEstructuraCursoActividades($carrera);
    [$curso2] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso1->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoComp = TipoComponente::first() ?? TipoComponente::create(['tipo' => 'Cátedra']);

    // Componente y actividad del curso 2
    $compCurso2 = crearComponenteTest($curso2->id_curso, $tipoComp->id_tipo_componente);

    $actividadCurso2 = crearActividadTest([
        'nombre' => 'Actividad Curso 2',
        'id_componente' => $compCurso2->id_componente,
        'visible' => true,
    ]);

    // Intenta acceder desde curso 1 a actividad de curso 2
    $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso1->id_curso}/actividades/{$actividadCurso2->id_actividad}")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Actividad no encontrada en este curso.');

    $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso1->id_curso}/actividades/{$actividadCurso2->id_actividad}/enunciado")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Actividad no encontrada en este curso.');
});

it('rechaza con 403 Forbidden si la actividad tiene visible = false', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoComp = TipoComponente::first() ?? TipoComponente::create(['tipo' => 'Cátedra']);
    $comp = crearComponenteTest($curso->id_curso, $tipoComp->id_tipo_componente);

    $actividad = crearActividadTest([
        'nombre' => 'Actividad Oculta',
        'id_componente' => $comp->id_componente,
        'visible' => false,
    ]);

    $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}")
        ->assertStatus(403)
        ->assertJsonPath('message', 'Esta actividad no está disponible.');
});

it('retorna listado de actividades filtrando por componentes y calculando totales', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::where('tipo', 'Cátedra')->first();
    $tipoTaller = TipoComponente::where('tipo', 'Taller')->first();
    $tipoLab = TipoComponente::where('tipo', 'Laboratorio')->first();

    $compCatedra = crearComponenteTest($curso->id_curso, $tipoCatedra->id_tipo_componente);
    $compTaller = crearComponenteTest($curso->id_curso, $tipoTaller->id_tipo_componente);
    $compLab = crearComponenteTest($curso->id_curso, $tipoLab->id_tipo_componente);

    // El estudiante está inscrito en Cátedra y Taller (no en Laboratorio)
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compTaller->id_componente,
    ]);

    // Actividades
    $act1 = crearActividadTest([
        'nombre' => 'Caso Cátedra Sumativa',
        'id_componente' => $compCatedra->id_componente,
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'tipo_entrega' => 'CON_ENTREGA',
        'es_grupal' => true,
        'max_integrantes' => 3,
        'ponderacion' => 30.0,
        'fecha_limite' => now()->addDays(5)->format('Y-m-d'),
    ]);

    $act2 = crearActividadTest([
        'nombre' => 'Taller 1 Formativa',
        'id_componente' => $compTaller->id_componente,
        'visible' => true,
        'tipo_actividad' => TipoActividad::FORMATIVA,
        'tipo_entrega' => 'SIN_ENTREGA',
        'es_grupal' => false,
        'max_integrantes' => 1,
        'fecha_limite' => now()->addDays(10)->format('Y-m-d'),
    ]);

    // Actividad de Laboratorio (ajena al alumno, no debe aparecer)
    crearActividadTest([
        'nombre' => 'Laboratorio Ajeno',
        'id_componente' => $compLab->id_componente,
        'visible' => true,
    ]);

    // Actividad no visible de Cátedra (no debe aparecer)
    crearActividadTest([
        'nombre' => 'Oculta Cátedra',
        'id_componente' => $compCatedra->id_componente,
        'visible' => false,
    ]);

    $res = $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades")
        ->assertStatus(200);

    $res->assertJsonPath('data.curso.id_curso', $curso->id_curso)
        ->assertJsonPath('data.totales.total', 2)
        ->assertJsonPath('data.totales.sumativas', 1)
        ->assertJsonPath('data.totales.formativas', 1)
        ->assertJsonPath('data.totales.pendientes_entrega', 1);

    $actividades = $res->json('data.actividades');
    expect($actividades)->toHaveCount(2);
    expect($actividades[0]['nombre'])->toBe('Caso Cátedra Sumativa');
    expect($actividades[0]['con_entrega'])->toBeTrue();
    expect($actividades[0]['es_grupal'])->toBeTrue();
    expect($actividades[1]['nombre'])->toBe('Taller 1 Formativa');
    expect($actividades[1]['con_entrega'])->toBeFalse();
});

it('retorna detalle de actividad con compañeros de grupo, fechas con holgura y rubrica asignada', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoComp = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $comp = crearComponenteTest($curso->id_curso, $tipoComp->id_tipo_componente);

    $uDocente = Usuario::factory()->create([
        'nombre1' => 'CARLOS',
        'apellido1' => 'VALENZUELA',
        'email' => 'carlos.valenzuela@uta.cl',
        'esta_activo' => true,
    ]);
    $docente = Docente::factory()->create([
        'id_usuario' => $uDocente->id_usuario,
    ]);

    DocenteComponente::create([
        'id_docente' => $docente->id_docente,
        'id_componente' => $comp->id_componente,
        'es_titular' => true,
    ]);

    $unidad = Unidad::create([
        'id_curso' => $curso->id_curso,
        'nombre' => 'Unidad I: Cardiología',
        'num_unidad' => 1,
    ]);

    $actividad = Actividad::create([
        'nombre' => 'Caso Clínico 1',
        'descripcion' => 'Análisis de caso práctico grupal.',
        'id_componente' => $comp->id_componente,
        'id_unidad' => $unidad->id_unidad,
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'tipo_entrega' => 'CON_ENTREGA',
        'es_grupal' => true,
        'max_integrantes' => 3,
        'ponderacion' => 25.0,
        'exigencia' => 60.0,
        'fecha_limite' => '2026-11-20',
        'nro_dias_adicionales_para_bloqueo' => 2,
    ]);

    // Crear grupo para la actividad con compañero
    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo A1',
        'estado' => 'ACTIVA',
        'nro_dias_adicionales_para_bloqueo_personal' => 1,
    ]);

    // Estudiante actual
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    // Compañero
    [$userComp, $estudianteComp] = crearEstudianteActividades([
        'rut' => '13.456.789-2',
        'nombre1' => 'MARIA',
        'apellido1' => 'ROJAS',
        'email' => 'maria.rojas@alumnos.uta.cl',
    ]);
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudianteComp->id_estudiante,
    ]);

    // Rúbrica
    $rubrica = Rubrica::create([
        'id_actividad' => $actividad->id_actividad,
        'estado_rubrica' => EstadoRubrica::POSTULADA,
        'rubrica' => [
            'columnas' => [
                ['id' => 'col1', 'nombre' => 'Insuficiente', 'puntos' => 0],
                ['id' => 'col2', 'nombre' => 'Excelente', 'puntos' => 20],
            ],
            'niveles' => [
                [
                    'id' => 'niv1',
                    'nombre' => 'Diagnóstico',
                    'descripcion' => 'Criterio diagnóstico',
                    'ponderacion' => 100,
                    'escalas' => [
                        ['id' => 'esc1', 'puntos' => 0, 'criterio' => 'Mal'],
                        ['id' => 'esc2', 'puntos' => 20, 'criterio' => 'Bien'],
                    ],
                ],
            ],
        ],
    ]);

    $res = $this->actingAs($usuario)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}")
        ->assertStatus(200);

    $res->assertJsonPath('data.actividad.id_actividad', $actividad->id_actividad)
        ->assertJsonPath('data.actividad.nombre', 'Caso Clínico 1')
        ->assertJsonPath('data.actividad.dias_holgura_base', 2)
        ->assertJsonPath('data.actividad.dias_holgura_personal', 1)
        ->assertJsonPath('data.actividad.plazo_final_con_holgura', '2026-11-23')
        ->assertJsonPath('data.actividad.equipo_docente.0.nombre', 'CARLOS VALENZUELA')
        ->assertJsonPath('data.grupo.nombre_grupo', 'Grupo A1')
        ->assertJsonPath('data.grupo.es_individual', false)
        ->assertJsonPath('data.rubrica.id_rubrica', $rubrica->id_rubrica)
        ->assertJsonPath('data.archivo_enunciado', null);

    $integrantes = $res->json('data.grupo.integrantes');
    expect($integrantes)->toHaveCount(2);
    expect($integrantes[0]['es_usuario_actual'])->toBeTrue();
    expect($integrantes[0]['id_estudiante'])->toBe($estudiante->id_estudiante);
    expect($integrantes[1]['es_usuario_actual'])->toBeFalse();
    expect($integrantes[1]['id_estudiante'])->toBe($estudianteComp->id_estudiante);
});

it('permite descargar el archivo de enunciado oficial con encabezados correctos', function () {
    Storage::fake('local_archives');
    config(['files.storage.disk' => 'local_archives']);

    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoComp = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $comp = crearComponenteTest($curso->id_curso, $tipoComp->id_tipo_componente);

    // Archivo de enunciado físico y registro
    $rutaEnStorage = 'enunciados/test_pauta.pdf';
    Storage::disk('local_archives')->put($rutaEnStorage, '%PDF-1.4 Fake PDF Content');

    $archivo = Archivo::create([
        'nombre_original' => 'Pauta_Caso_Clinico.pdf',
        'ruta_fisica' => $rutaEnStorage,
        'mime_type' => 'application/pdf',
        'peso_bytes' => 12345,
        'extension' => 'pdf',
    ]);

    $actividad = crearActividadTest([
        'nombre' => 'Caso con Enunciado',
        'id_componente' => $comp->id_componente,
        'visible' => true,
        'uuid_archivo_enunciado' => $archivo->uuid_archivo,
    ]);

    // 1. Descarga como attachment por defecto
    $resDescarga = $this->actingAs($usuario)
        ->get("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/enunciado");

    $resDescarga->assertStatus(200);
    $resDescarga->assertHeader('Content-Type', 'application/pdf');
    expect($resDescarga->headers->get('Content-Disposition'))->toContain('attachment');
    expect($resDescarga->headers->get('Content-Disposition'))->toContain('Pauta_Caso_Clinico.pdf');

    // 2. Visualización inline con ver=1
    $resInline = $this->actingAs($usuario)
        ->get("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/enunciado?ver=1");

    $resInline->assertStatus(200);
    $resInline->assertHeader('Content-Type', 'application/pdf');
    $resInline->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($resInline->headers->get('Content-Disposition'))->toContain('inline');
});

it('retorna 404 al descargar enunciado si la actividad no tiene archivo asociado', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteActividades();
    [$curso] = crearEstructuraCursoActividades($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoComp = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $comp = crearComponenteTest($curso->id_curso, $tipoComp->id_tipo_componente);

    $actividad = crearActividadTest([
        'nombre' => 'Caso sin Enunciado',
        'id_componente' => $comp->id_componente,
        'visible' => true,
        'uuid_archivo_enunciado' => null,
    ]);

    $this->actingAs($usuario)
        ->get("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/enunciado")
        ->assertStatus(404)
        ->assertSee('Esta actividad no tiene enunciado asociado.');
});
