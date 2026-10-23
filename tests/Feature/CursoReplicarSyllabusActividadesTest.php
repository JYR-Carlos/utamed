<?php

use App\Enums\DB\EstadoRubrica;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\ProgramaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->admin = Usuario::firstOrCreate(
        ['rut' => '88888888-8'],
        [
            'username'    => 'admin_replicar_test',
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'Admin',
            'apellido1'   => 'Replicar Test',
            'esta_activo' => true,
        ]
    );
    $this->admin->fecha_cambio_passhash = now();
    $this->admin->fecha_verificacion_email = now();
    $this->admin->save();

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $this->admin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        ['id_usuario' => $this->admin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol, 'id_contexto' => 1],
        [
            'asignado_por'             => $this->admin->id_usuario,
            'fecha_inicio_planificada' => now()->subMinute(),
            'fecha_fin_planificada'    => now()->addYears(100),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $this->admin->id_usuario,
        ]
    );

    Auth::login($this->admin);
});

test('destinos-replicacion retorna unicamente los cursos hermanos de la misma asignatura', function () {
    $plan = AsignacionPlan::with('asignatura')->first();
    $docente = Docente::first() ?? Docente::factory()->create();

    $cursoOrigen = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'id_docente_titular' => $docente->id_docente,
        'agno_real'          => 2026,
        'semestre_real'      => 1,
    ]);

    $cursoHermano1 = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'id_docente_titular' => $docente->id_docente,
        'agno_real'          => 2026,
        'semestre_real'      => 1,
    ]);

    $cursoHermano2 = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'id_docente_titular' => $docente->id_docente,
        'agno_real'          => 2026,
        'semestre_real'      => 2,
    ]);

    // Crear syllabus en el curso origen
    ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->admin);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.cursos.syllabus.destinos-replicacion', $cursoOrigen->id_curso));

    $response->assertOk()
        ->assertJsonStructure([
            'origen' => ['id_curso', 'nombre', 'asignatura', 'total_actividades'],
            'destinos' => [['id_curso', 'nombre', 'tiene_programa']],
        ]);

    $destinosIds = collect($response->json('destinos'))->pluck('id_curso')->all();
    expect($destinosIds)->toContain($cursoHermano1->id_curso)
        ->and($destinosIds)->toContain($cursoHermano2->id_curso)
        ->and($destinosIds)->not->toContain($cursoOrigen->id_curso);
});

test('replicar copia atomicamente el syllabus y las actividades con rubricas a cursos hermanos', function () {
    $plan = AsignacionPlan::with('asignatura')->first();
    $docente = Docente::first() ?? Docente::factory()->create();
    $tipoComp = TipoComponente::first();

    // 1. Curso origen
    $cursoOrigen = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'id_docente_titular' => $docente->id_docente,
        'agno_real'          => 2026,
        'semestre_real'      => 1,
    ]);

    // Syllabus en origen
    $progOrigen = ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->admin);
    $progOrigen->update(['estado' => 'APROBADO']);

    // Componente, unidad y actividad con rúbrica en origen
    $compOrigen = Componente::create([
        'id_curso'                          => $cursoOrigen->id_curso,
        'id_tipo_componente'                => $tipoComp->id_tipo_componente,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => true,
        'porcentaje_aprobacion'             => 50,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    $unidadOrigen = \App\Models\Curso\Unidad::create([
        'id_curso'   => $cursoOrigen->id_curso,
        'num_unidad' => 1,
        'nombre'     => 'Unidad Morfológica 1',
    ]);

    $actOrigen = Actividad::create([
        'nombre'         => 'Evaluación Práctica de Laboratorio',
        'fecha_limite'   => now()->addDays(7),
        'visible'        => true,
        'ponderacion'    => 20,
        'exigencia'      => 60,
        'tipo_actividad' => 'SUMATIVA',
        'tipo_entrega'   => 'ARCHIVO',
        'es_grupal'      => false,
        'es_plantilla'   => false,
        'id_componente'  => $compOrigen->id_componente,
        'id_unidad'      => $unidadOrigen->id_unidad,
    ]);

    $rubricaOrigen = Rubrica::create([
        'id_actividad'   => $actOrigen->id_actividad,
        'estado_rubrica' => EstadoRubrica::POSTULADA,
        'rubrica'        => [
            'criterios' => [
                ['titulo' => 'Destreza procedimental', 'ponderacion' => 100],
            ],
        ],
    ]);

    // 2. Cursos destino ya existentes (con su componente homólogo pero sin syllabus)
    $cursoDestino1 = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'id_docente_titular' => $docente->id_docente,
        'agno_real'          => 2026,
        'semestre_real'      => 1,
    ]);
    Componente::create([
        'id_curso'                          => $cursoDestino1->id_curso,
        'id_tipo_componente'                => $tipoComp->id_tipo_componente,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => true,
        'porcentaje_aprobacion'             => 50,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    $cursoDestino2 = Curso::factory()->create([
        'id_asignacion_plan' => $plan->id_asignacion_plan,
        'id_docente_titular' => $docente->id_docente,
        'agno_real'          => 2026,
        'semestre_real'      => 1,
    ]);
    Componente::create([
        'id_curso'                          => $cursoDestino2->id_curso,
        'id_tipo_componente'                => $tipoComp->id_tipo_componente,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => true,
        'porcentaje_aprobacion'             => 50,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    // 3. Ejecutar replicación vía endpoint administrativo
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.cursos.syllabus.replicar', $cursoOrigen->id_curso), [
            'destinos'        => [$cursoDestino1->id_curso, $cursoDestino2->id_curso],
            'con_actividades' => true,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'con_actividades' => true,
        ]);

    // 4. Validar programa creado en ambos destinos
    foreach ([$cursoDestino1, $cursoDestino2] as $destino) {
        $p = Programa::where('id_curso', $destino->id_curso)->where('es_actual', true)->first();
        expect($p)->not->toBeNull()
            ->and($p->estado)->toBe('BORRADOR')
            ->and($p->revisado_por)->toBeNull()
            ->and($p->fecha_entrega)->toBeNull();

        // Validar actividad copiada en componente homólogo
        $actDestino = Actividad::whereHas('componente', fn($q) => $q->where('id_curso', $destino->id_curso))
            ->where('nombre', 'Evaluación Práctica de Laboratorio')
            ->first();

        expect($actDestino)->not->toBeNull()
            ->and($actDestino->fecha_limite)->toBeNull()
            ->and($actDestino->visible)->toBeFalse()
            ->and((int) $actDestino->ponderacion)->toBe(20);

        // Validar rúbrica analítica copiada
        $rubDestino = Rubrica::where('id_actividad', $actDestino->id_actividad)->first();
        expect($rubDestino)->not->toBeNull()
            ->and($rubDestino->id_rubrica)->not->toBe($rubricaOrigen->id_rubrica)
            ->and($rubDestino->estado_rubrica)->toBe(EstadoRubrica::POSTULADA)
            ->and($rubDestino->rubrica['criterios'][0]['titulo'])->toBe('Destreza procedimental');
    }
});

test('replicar con con_actividades falso solo copia el programa y omite actividades', function () {
    $plan = AsignacionPlan::with('asignatura')->first();
    $tipoComp = TipoComponente::first();

    $cursoOrigen = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);
    $progOrigen = ProgramaService::generateProgramaWithSyllabus($cursoOrigen, $this->admin);

    $compOrigen = Componente::create([
        'id_curso'                          => $cursoOrigen->id_curso,
        'id_tipo_componente'                => $tipoComp->id_tipo_componente,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => true,
        'porcentaje_aprobacion'             => 50,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);
    $unidadOrigen = \App\Models\Curso\Unidad::create([
        'id_curso'   => $cursoOrigen->id_curso,
        'num_unidad' => 1,
        'nombre'     => 'Unidad Origen Test',
    ]);

    Actividad::create([
        'nombre'         => 'Actividad No Replicable',
        'ponderacion'    => 10,
        'tipo_actividad' => 'SUMATIVA',
        'tipo_entrega'   => 'ARCHIVO',
        'es_grupal'      => false,
        'es_plantilla'   => false,
        'id_componente'  => $compOrigen->id_componente,
        'id_unidad'      => $unidadOrigen->id_unidad,
    ]);

    $cursoDestino = Curso::factory()->create(['id_asignacion_plan' => $plan->id_asignacion_plan]);
    Componente::create([
        'id_curso'                          => $cursoDestino->id_curso,
        'id_tipo_componente'                => $tipoComp->id_tipo_componente,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => true,
        'porcentaje_aprobacion'             => 50,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.cursos.syllabus.replicar', $cursoOrigen->id_curso), [
            'destinos'        => [$cursoDestino->id_curso],
            'con_actividades' => false,
        ]);

    $response->assertOk();

    // Programa sí se creó
    $p = Programa::where('id_curso', $cursoDestino->id_curso)->first();
    expect($p)->not->toBeNull();

    // Actividades NO se copiaron
    $actCount = Actividad::whereHas('componente', fn($q) => $q->where('id_curso', $cursoDestino->id_curso))->count();
    expect($actCount)->toBe(0);
});
