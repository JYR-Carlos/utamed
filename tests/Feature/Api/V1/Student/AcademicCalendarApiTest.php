<?php

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Student\DashboardEstudiante;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(DatabaseTransactions::class);

function crearEstudianteCalendar(array $usuarioAttrs = [], array $estudianteAttrs = []): array
{
    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test Calendar',
        'codigo_carrera' => 19998,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $usuario = Usuario::factory()->create(array_merge([
        'rut' => '12.345.678-5',
        'nombre1' => 'ESTUDIANTE',
        'nombre2' => 'CALENDARIO',
        'apellido1' => 'PRUEBA',
        'apellido2' => 'TEST',
        'email' => 'cal.test@alumnos.uta.cl',
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

function crearEstructuraCursoCalendar(int $semestre, int $agno, string $cod = 'MED-CAL'): array
{
    static $contador = 100;
    $contador++;

    $plan = Plan::first() ?? Plan::create([
        'codigo_plan' => 'MED-CAL-PLAN',
        'nombre' => 'Plan Calendar Test',
        'id_carrera' => 1,
        'id_contexto' => 1,
    ]);

    $asignatura = Asignatura::create([
        'cod_asignatura' => $cod,
        'nombre' => "Asignatura $cod",
        'creditos_sct' => 5,
        'horas_catedra' => 2,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 4,
        'horas_autonomas' => 6,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $asigPlan = AsignacionPlan::create([
        'id_plan' => $plan->id_plan,
        'id_asignatura' => $asignatura->id_asignatura,
        'semestre_planificado' => $semestre,
        'agno_planificado' => 1,
    ]);

    $curso = Curso::create([
        'cod_curso' => 50000 + $contador,
        'nombre' => "Curso $cod",
        'semestre_real' => $semestre,
        'agno_real' => $agno,
        'id_asignacion_plan' => $asigPlan->id_asignacion_plan,
        'id_contexto' => 1,
        'id_docente_titular' => 1,
        'estado_interno' => 'ABIERTO',
        'fecha_inicio' => Carbon::parse("$agno-03-01"),
        'fecha_fin' => Carbon::parse("$agno-07-30"),
    ]);

    $tipoComp = TipoComponente::first() ?? TipoComponente::create([
        'tipo' => 'Cátedra',
        'id_contexto' => 1,
    ]);

    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoComp->id_tipo_componente,
        'genera_acta' => false,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'id_contexto' => 1,
    ]);

    return [$curso, $componente, $asignatura];
}

function crearActividadCalendar(
    Componente $componente,
    string $nombre,
    string $fechaLimite,
    bool $visible = true
): Actividad {
    $unidad = \App\Models\Curso\Unidad::firstOrCreate(
        ['id_curso' => $componente->id_curso, 'num_unidad' => 1],
        ['nombre' => 'Unidad 1', 'id_contexto' => 1]
    );

    return Actividad::create([
        'id_componente' => $componente->id_componente,
        'id_unidad' => $unidad->id_unidad,
        'nombre' => $nombre,
        'visible' => $visible,
        'fecha_limite' => $fechaLimite,
        'tipo_actividad' => 'SUMATIVA',
        'tipo_entrega' => 'Con entrega',
        'ponderacion' => 20.0,
        'exigencia' => 60.0,
        'id_contexto' => 1,
    ]);
}

test('it rechaza peticion no autenticada con 401', function () {
    $response = $this->getJson('/api/v1/estudiante/calendario');
    $response->assertUnauthorized();
});

test('it rechaza con 403 a usuario sin rol o perfil estudiante', function () {
    $usuario = Usuario::factory()->create([
        'rut' => '11.111.111-1',
        'email' => 'docente.cal@uta.cl',
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    Docente::factory()->create(['id_usuario' => $usuario->id_usuario]);

    $rolDocenteId = Rol::where('nombre', 'Docente Titular')->value('id_rol') ?? 1;
    UsuarioRolAsignacion::create([
        'id_usuario' => $usuario->id_usuario,
        'id_rol' => $rolDocenteId,
        'id_contexto' => 1,
        'asignado_por' => $usuario->id_usuario,
        'creado_por' => $usuario->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    Sanctum::actingAs($usuario);

    $response = $this->getJson('/api/v1/estudiante/calendario');
    $response->assertForbidden();
});

test('it rechaza con 423 si la password esta expirada', function () {
    [$usuario] = crearEstudianteCalendar([
        'fecha_cambio_passhash' => now()->subDays(200),
    ]);

    Sanctum::actingAs($usuario);

    $response = $this->getJson('/api/v1/estudiante/calendario');
    $response->assertStatus(423);
});

test('it retorna actividades del periodo vigente por defecto (Modo 2)', function () {
    [$usuario, $estudiante] = crearEstudianteCalendar();
    Sanctum::actingAs($usuario);

    $periodoActual = DashboardEstudiante::periodoActual();
    [$cursoVigente, $compVigente] = crearEstructuraCursoCalendar(
        $periodoActual['semestre'],
        $periodoActual['agno'],
        'MED-VIG'
    );

    // Inscribir estudiante
    InscripcionCurso::create([
        'id_curso' => $cursoVigente->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);

    $act1 = crearActividadCalendar($compVigente, 'Control 1', '2026-11-10 12:00:00');
    $act2 = crearActividadCalendar($compVigente, 'Control 2', '2026-11-20 12:00:00');
    // Actividad oculta no debe aparecer
    crearActividadCalendar($compVigente, 'Oculta', '2026-11-15 12:00:00', false);

    $response = $this->getJson('/api/v1/estudiante/calendario');

    $response->assertOk()
        ->assertJsonPath('data.periodo.semestre', $periodoActual['semestre'])
        ->assertJsonPath('data.periodo.agno', $periodoActual['agno'])
        ->assertJsonPath('data.periodo.es_historico', false)
        ->assertJsonPath('data.agrupado', false)
        ->assertJsonPath('data.otras_actividades', []);

    $actividades = $response->json('data.actividades');
    expect($actividades)->toHaveCount(2);
    expect($actividades[0]['id_actividad'])->toBe($act1->id_actividad);
    expect($actividades[0]['fecha_limite'])->toBe('2026-11-10');
    expect($actividades[1]['id_actividad'])->toBe($act2->id_actividad);
    expect($actividades[1]['fecha_limite'])->toBe('2026-11-20');
});

test('it retorna historial completo con periodo=todos (Modo 1)', function () {
    [$usuario, $estudiante] = crearEstudianteCalendar();
    Sanctum::actingAs($usuario);

    [$curso1, $comp1] = crearEstructuraCursoCalendar(1, 2025, 'MED-HIST1');
    [$curso2, $comp2] = crearEstructuraCursoCalendar(2, 2026, 'MED-HIST2');

    InscripcionCurso::create([
        'id_curso' => $curso1->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);
    InscripcionCurso::create([
        'id_curso' => $curso2->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);

    $act1 = crearActividadCalendar($comp1, 'Entrega 2025', '2025-05-10 10:00:00');
    $act2 = crearActividadCalendar($comp2, 'Entrega 2026', '2026-10-15 10:00:00');

    $response = $this->getJson('/api/v1/estudiante/calendario?periodo=todos');

    $response->assertOk()
        ->assertJsonPath('data.periodo.semestre', null)
        ->assertJsonPath('data.periodo.agno', null)
        ->assertJsonPath('data.periodo.es_historico', true)
        ->assertJsonPath('data.agrupado', false)
        ->assertJsonPath('data.otras_actividades', []);

    $actividades = $response->json('data.actividades');
    expect(count($actividades))->toBeGreaterThanOrEqual(2);

    $ids = collect($actividades)->pluck('id_actividad')->all();
    expect($ids)->toContain($act1->id_actividad);
    expect($ids)->toContain($act2->id_actividad);
});

test('it filtra por curso aislado id_curso (Modo 3) con guardrail IDOR', function () {
    [$usuario, $estudiante] = crearEstudianteCalendar();
    Sanctum::actingAs($usuario);

    [$cursoPropio, $compPropio] = crearEstructuraCursoCalendar(2, 2026, 'MED-PROP');
    [$cursoAjeno, $compAjeno] = crearEstructuraCursoCalendar(2, 2026, 'MED-AJEN');

    InscripcionCurso::create([
        'id_curso' => $cursoPropio->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);

    $actPropia = crearActividadCalendar($compPropio, 'Act Propia', '2026-11-12 10:00:00');
    crearActividadCalendar($compAjeno, 'Act Ajena', '2026-11-13 10:00:00');

    // 1. Acceso a curso propio
    $response = $this->getJson('/api/v1/estudiante/calendario?id_curso=' . $cursoPropio->id_curso);
    $response->assertOk()
        ->assertJsonPath('data.agrupado', false)
        ->assertJsonPath('data.otras_actividades', []);

    $acts = $response->json('data.actividades');
    expect($acts)->toHaveCount(1);
    expect($acts[0]['id_actividad'])->toBe($actPropia->id_actividad);

    // 2. IDOR: curso existente pero no inscrito -> 403 Forbidden
    $responseAjeno = $this->getJson('/api/v1/estudiante/calendario?id_curso=' . $cursoAjeno->id_curso);
    $responseAjeno->assertForbidden()
        ->assertJson(['message' => 'No estás inscrito en este curso.']);

    // 3. 404: curso inexistente
    $response404 = $this->getJson('/api/v1/estudiante/calendario?id_curso=9999999');
    $response404->assertNotFound();
});

test('it agrupa entregas con id_curso e incluir_otros=true (Modo 4)', function () {
    [$usuario, $estudiante] = crearEstudianteCalendar();
    Sanctum::actingAs($usuario);

    [$curso1, $comp1] = crearEstructuraCursoCalendar(2, 2026, 'MED-GRP1');
    [$curso2, $comp2] = crearEstructuraCursoCalendar(2, 2026, 'MED-GRP2');

    InscripcionCurso::create([
        'id_curso' => $curso1->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);
    InscripcionCurso::create([
        'id_curso' => $curso2->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);

    $actCurso1 = crearActividadCalendar($comp1, 'Tarea C1', '2026-11-14 12:00:00');
    $actCurso2 = crearActividadCalendar($comp2, 'Tarea C2', '2026-11-16 12:00:00');

    $response = $this->getJson(
        '/api/v1/estudiante/calendario?id_curso=' . $curso1->id_curso . '&incluir_otros=true'
    );

    $response->assertOk()
        ->assertJsonPath('data.agrupado', true);

    $actividades = $response->json('data.actividades');
    $otrasActividades = $response->json('data.otras_actividades');

    expect($actividades)->toHaveCount(1);
    expect($actividades[0]['id_actividad'])->toBe($actCurso1->id_actividad);
    expect($actividades[0]['id_curso'])->toBe($curso1->id_curso);

    $idsOtras = collect($otrasActividades)->pluck('id_actividad')->all();
    expect($idsOtras)->toContain($actCurso2->id_actividad);
});

test('it serializa fechas estrictamente en formato Y-m-d', function () {
    [$usuario, $estudiante] = crearEstudianteCalendar();
    Sanctum::actingAs($usuario);

    [$curso, $comp] = crearEstructuraCursoCalendar(2, 2026, 'MED-FMT');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'id_contexto' => 1,
    ]);

    crearActividadCalendar($comp, 'Control Fechas', '2026-11-25 18:30:00');

    $response = $this->getJson('/api/v1/estudiante/calendario?id_curso=' . $curso->id_curso);
    $response->assertOk();

    $item = $response->json('data.actividades.0');
    expect($item['fecha_limite'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($item['fecha_limite'])->toBe('2026-11-25');
});
