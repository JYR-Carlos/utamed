<?php

use App\Enums\DB\TipoActividad;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Grupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Programa;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearEstudianteRendimiento(array $usuarioAttrs = [], array $estudianteAttrs = []): array
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

function crearCursoRendimiento(array $cursoAttrs = []): Curso
{
    $carrera = Carrera::first();
    $plan = Plan::create([
        'id_carrera' => $carrera->id_carrera,
        'agno_plan' => 2024,
        'version_plan' => 1,
    ]);

    $asignatura = Asignatura::create([
        'cod_asignatura' => 'MED-101',
        'nombre' => 'Medicina Interna I',
        'creditos_sct' => 6,
        'horas_catedra' => 2,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 1,
        'horas_autonomas' => 3,
        'descripcion' => 'Tipo: FE',
    ]);

    $asigPlan = AsignacionPlan::create([
        'id_plan' => $plan->id_plan,
        'id_asignatura' => $asignatura->id_asignatura,
        'agno_planificado' => 2024,
        'semestre_planificado' => 2,
    ]);

    return Curso::factory()->create(array_merge([
        'nombre' => 'Medicina Interna I - Sección 1',
        'cod_curso' => 1011,
        'indice_grupo' => 1,
        'semestre_real' => 1,
        'agno_real' => 2026,
        'id_asignacion_plan' => $asigPlan->id_asignacion_plan,
    ], $cursoAttrs));
}

it('rechaza peticion no autenticada con 401', function () {
    $curso = crearCursoRendimiento();

    $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento")
        ->assertStatus(401);
});

it('rechaza con 403 a usuario sin rol o perfil estudiante', function () {
    $curso = crearCursoRendimiento();

    $usuarioDocente = Usuario::factory()->create([
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $token = $usuarioDocente->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento")
        ->assertStatus(403);
});

it('retorna 404 cuando el curso solicitado no existe', function () {
    [$usuario] = crearEstudianteRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/estudiante/cursos/999999/rendimiento')
        ->assertStatus(404);
});

it('rechaza con 403 Forbidden cuando el alumno no esta inscrito en el curso', function () {
    [$usuario] = crearEstudianteRendimiento();
    $curso = crearCursoRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento")
        ->assertStatus(403)
        ->assertJson([
            'message' => 'No estás inscrito en este curso.',
        ]);
});

it('rechaza con 403 Forbidden cuando el alumno tiene estado de inscripcion RETIRADO', function () {
    [$usuario, $estudiante] = crearEstudianteRendimiento();
    $curso = crearCursoRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'RETIRADO',
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento")
        ->assertStatus(403)
        ->assertJson([
            'message' => 'No estás inscrito en este curso.',
        ]);
});

it('retorna 200 con la estructura completa de rendimiento individual y excluye promedio_curso', function () {
    [$usuario, $estudiante] = crearEstudianteRendimiento();
    $curso = crearCursoRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    $inscripcionComp = InscripcionComponente::create([
        'id_componente' => $componente->id_componente,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'curso' => ['id_curso', 'nombre', 'cod_curso', 'asignatura_nombre'],
                'mi_promedio',
                'asistencia_minima',
                'asistencia_global' => ['presentes', 'total', 'porcentaje', 'alerta_asistencia'],
                'totales' => ['actividades_sumativas', 'actividades_evaluadas', 'ponderacion_evaluada'],
                'componentes' => [
                    '*' => [
                        'id_componente',
                        'tipo',
                        'mi_promedio',
                        'actividades_sumativas',
                        'actividades_evaluadas',
                        'ponderacion_evaluada',
                        'syllabus' => ['porcentaje', 'aprobacion_obligatoria'],
                        'exigencia',
                        'asistencia' => ['presentes', 'total', 'porcentaje', 'minimo_exigido', 'alerta_asistencia'],
                        'actividades',
                    ],
                ],
            ],
        ]);

    // Asegurar que NINGÚN nivel del payload contenga 'promedio_curso' (DoD)
    expect($response->json('data'))->not->toHaveKey('promedio_curso');
    foreach ($response->json('data.componentes') as $comp) {
        expect($comp)->not->toHaveKey('promedio_curso');
    }
});

it('calcula correctamente mi_promedio ponderado y notas de actividades sumativas', function () {
    [$usuario, $estudiante] = crearEstudianteRendimiento();
    $curso = crearCursoRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    InscripcionComponente::create([
        'id_componente' => $componente->id_componente,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    $unidad = Unidad::create([
        'id_curso' => $curso->id_curso,
        'num_unidad' => 1,
        'nombre' => 'Unidad 1',
    ]);

    // Actividad 1 evaluada: ponderación 30%, nota 6.0
    $actividad1 = Actividad::create([
        'id_componente' => $componente->id_componente,
        'id_unidad' => $unidad->id_unidad,
        'nombre' => 'Certamen 1',
        'tipo_actividad' => TipoActividad::SUMATIVA->value,
        'tipo_entrega' => 'ONLINE',
        'ponderacion' => 30.0,
        'visible' => true,
        'fecha_limite' => now()->subDays(5),
    ]);

    $asigGrupo1 = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad1->id_actividad,
        'nombre_grupo' => 'G1',
        'nota' => 5.0,
    ]);
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $asigGrupo1->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
        'nota_individual' => 6.0,
    ]);

    // Actividad 2 evaluada: ponderación 20%, nota 5.0
    $actividad2 = Actividad::create([
        'id_componente' => $componente->id_componente,
        'id_unidad' => $unidad->id_unidad,
        'nombre' => 'Certamen 2',
        'tipo_actividad' => TipoActividad::SUMATIVA->value,
        'tipo_entrega' => 'ONLINE',
        'ponderacion' => 20.0,
        'visible' => true,
        'fecha_limite' => now()->subDay(),
    ]);

    $asigGrupo2 = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad2->id_actividad,
        'nombre_grupo' => 'G2',
        'nota' => 5.0,
    ]);
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $asigGrupo2->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
        'nota_individual' => null, // Debería tomar 5.0 de la nota grupal
    ]);

    // Actividad 3 sin evaluar: ponderación 50%
    $actividad3 = Actividad::create([
        'id_componente' => $componente->id_componente,
        'id_unidad' => $unidad->id_unidad,
        'nombre' => 'Examen',
        'tipo_actividad' => TipoActividad::SUMATIVA->value,
        'tipo_entrega' => 'ONLINE',
        'ponderacion' => 50.0,
        'visible' => true,
        'fecha_limite' => now()->addMonth(),
    ]);

    // Promedio esperado = (6.0 * 30 + 5.0 * 20) / (30 + 20) = (180 + 100) / 50 = 280 / 50 = 5.6
    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento");

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['mi_promedio'])->toBe(5.6);
    expect($data['totales']['actividades_sumativas'])->toBe(3);
    expect($data['totales']['actividades_evaluadas'])->toBe(2);

    $comp = $data['componentes'][0];
    expect($comp['mi_promedio'])->toBe(5.6);
    expect($comp['ponderacion_evaluada'])->toEqual(50); // (30 + 20) / 100 * 100 = 50%
    expect($comp['actividades'])->toHaveCount(3);

    expect($comp['actividades'][0]['nombre'])->toBe('Certamen 1');
    expect($comp['actividades'][0]['nota'])->toEqual(6.0);
    expect($comp['actividades'][0]['es_evaluada'])->toBeTrue();

    expect($comp['actividades'][1]['nombre'])->toBe('Certamen 2');
    expect($comp['actividades'][1]['nota'])->toEqual(5.0);
    expect($comp['actividades'][1]['es_evaluada'])->toBeTrue();

    expect($comp['actividades'][2]['nombre'])->toBe('Examen');
    expect($comp['actividades'][2]['nota'])->toBeNull();
    expect($comp['actividades'][2]['es_evaluada'])->toBeFalse();
});

it('calcula la asistencia y activa alerta_asistencia cuando el porcentaje es menor a 70', function () {
    [$usuario, $estudiante] = crearEstudianteRendimiento();
    $curso = crearCursoRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    $inscripcionComp = InscripcionComponente::create([
        'id_componente' => $componente->id_componente,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    // 10 sesiones totales: 6 presentes (60%) -> Alerta activa (< 70%)
    for ($i = 0; $i < 6; $i++) {
        DB::table('curso.asistencia')->insert([
            'id_inscripcion_componente' => $inscripcionComp->id_inscripcion_componente,
            'dia' => now()->subDays($i + 1)->toDateString(),
            'hora_inicio' => '08:30:00',
            'hora_fin' => '10:00:00',
            'esta_presente' => true,
        ]);
    }
    for ($i = 6; $i < 10; $i++) {
        DB::table('curso.asistencia')->insert([
            'id_inscripcion_componente' => $inscripcionComp->id_inscripcion_componente,
            'dia' => now()->subDays($i + 1)->toDateString(),
            'hora_inicio' => '08:30:00',
            'hora_fin' => '10:00:00',
            'esta_presente' => false,
        ]);
    }

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento");

    $response->assertStatus(200);

    $asistenciaGlobal = $response->json('data.asistencia_global');
    expect($asistenciaGlobal['presentes'])->toBe(6);
    expect($asistenciaGlobal['total'])->toBe(10);
    expect($asistenciaGlobal['porcentaje'])->toBe(60);
    expect($asistenciaGlobal['alerta_asistencia'])->toBeTrue();

    $asistenciaComp = $response->json('data.componentes.0.asistencia');
    expect($asistenciaComp['presentes'])->toBe(6);
    expect($asistenciaComp['total'])->toBe(10);
    expect($asistenciaComp['porcentaje'])->toBe(60);
    expect($asistenciaComp['alerta_asistencia'])->toBeTrue();
});

it('desactiva alerta_asistencia cuando el porcentaje es igual o superior al 70', function () {
    [$usuario, $estudiante] = crearEstudianteRendimiento();
    $curso = crearCursoRendimiento();
    $token = $usuario->createToken('test')->plainTextToken;

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    $inscripcionComp = InscripcionComponente::create([
        'id_componente' => $componente->id_componente,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    // 10 sesiones totales: 8 presentes (80%) -> Sin alerta
    for ($i = 0; $i < 8; $i++) {
        DB::table('curso.asistencia')->insert([
            'id_inscripcion_componente' => $inscripcionComp->id_inscripcion_componente,
            'dia' => now()->subDays($i + 1)->toDateString(),
            'hora_inicio' => '08:30:00',
            'hora_fin' => '10:00:00',
            'esta_presente' => true,
        ]);
    }
    for ($i = 8; $i < 10; $i++) {
        DB::table('curso.asistencia')->insert([
            'id_inscripcion_componente' => $inscripcionComp->id_inscripcion_componente,
            'dia' => now()->subDays($i + 1)->toDateString(),
            'hora_inicio' => '08:30:00',
            'hora_fin' => '10:00:00',
            'esta_presente' => false,
        ]);
    }

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/rendimiento");

    $response->assertStatus(200);

    $asistenciaGlobal = $response->json('data.asistencia_global');
    expect($asistenciaGlobal['porcentaje'])->toBe(80);
    expect($asistenciaGlobal['alerta_asistencia'])->toBeFalse();
});
