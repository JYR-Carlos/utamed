<?php

use App\DTOs\External\AlumnoIntranetData;
use App\DTOs\External\ComponenteCursoData;
use App\DTOs\External\InscripcionData;
use App\Enums\External\TipoAsignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\IntranetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * Helper para armar la jerarquía académica mínima en PostgreSQL
 */
function setupContextoCursoParaSincronizacion(): array
{
    $facultad = Facultad::firstOrCreate(['nombre' => 'Facultad de Ingeniería Test ' . uniqid()], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(['nombre' => 'Departamento Test ' . uniqid()], ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]);
    $carrera = Carrera::firstOrCreate(['nombre' => 'Carrera Test ' . uniqid()], ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]);
    $plan = Plan::firstOrCreate(['id_carrera' => $carrera->id_carrera, 'agno_plan' => 2024, 'version_plan' => 1], ['id_contexto' => 1]);
    $asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'TEST' . rand(100, 999)],
        [
            'nombre'            => 'Asignatura Sincronizacion Test',
            'creditos_sct'      => 5,
            'horas_catedra'     => 4,
            'horas_taller'      => 0,
            'horas_laboratorio' => 0,
            'horas_dirigidas'   => 2,
            'horas_autonomas'   => 4,
        ]
    );

    $asignacionPlan = AsignacionPlan::firstOrCreate(
        ['id_plan' => $plan->id_plan, 'id_asignatura' => $asignatura->id_asignatura],
        [
            'agno_planificado'     => 1,
            'semestre_planificado' => 1,
            'id_contexto'          => 1,
        ]
    );

    $usuarioAdmin = Usuario::firstOrCreate(
        ['rut' => '99999999-9'],
        [
            'username'              => 'admin_sync_test_' . uniqid(),
            'passhash'              => bcrypt('password123'),
            'nombre1'               => 'Admin',
            'apellido1'             => 'Test',
            'esta_activo'           => true,
        ]
    );
    $usuarioAdmin->fecha_cambio_passhash = now();
    $usuarioAdmin->save();
    $docente = Docente::firstOrCreate(['id_usuario' => $usuarioAdmin->id_usuario]);

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $usuarioAdmin->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        [
            'id_usuario'  => $usuarioAdmin->id_usuario,
            'id_rol'      => $rolSuperAdmin->id_rol,
            'id_contexto' => 1,
        ],
        [
            'asignado_por'             => $usuarioAdmin->id_usuario,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada'    => now()->addYears(10),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $usuarioAdmin->id_usuario,
        ]
    );

    $contextoCurso = Contexto::create(['contexto_display' => 'Curso Sync Test ' . uniqid()]);
    $curso = Curso::create([
        'cod_curso'          => rand(90000, 99999),
        'nombre'             => 'Curso Test Sincronizacion',
        'indice_grupo'       => 1,
        'fecha_inicio'       => now()->toDateString(),
        'fecha_fin'          => now()->addMonths(5)->toDateString(),
        'semestre_real'      => 1,
        'agno_real'          => 2026,
        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
        'id_contexto'        => $contextoCurso->id_contexto,
        'id_docente_titular' => $docente->id_docente,
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'CATEDRA']);
    $contextoComp = Contexto::create(['contexto_display' => 'Componente Catedra Sync Test']);
    $componente = Componente::forceCreate([
        'genera_acta'                       => true,
        'porcentaje_aprobacion'             => 60,
        'aprobacion_obligatoria'            => true,
        'porcentaje_asistencia_obligatoria' => 75,
        'id_tipo_componente'                => $tipoCatedra->id_tipo_componente,
        'id_curso'                          => $curso->id_curso,
        'id_contexto'                       => $contextoComp->id_contexto,
    ]);

    $curso->refresh();
    $componente->refresh();
    $curso->load('componentes.tipoComponente');

    return compact('curso', 'componente', 'usuarioAdmin', 'carrera');
}

/**
 * Helper para crear un alumno con inscripción activa y rol en el curso
 */
function crearAlumnoInscrito(Curso $curso, Carrera $carrera, int $rutNum, string $nombre): array
{
    $usuario = Usuario::forceCreate([
        'rut'                   => "{$rutNum}-K",
        'username'              => (string)$rutNum,
        'passhash'              => bcrypt('password123'),
        'nombre1'               => $nombre,
        'apellido1'             => 'Test',
        'esta_activo'           => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $estudiante = Estudiante::create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
    ]);

    $inscripcion = InscripcionCurso::create([
        'id_curso'            => $curso->id_curso,
        'id_estudiante'       => $estudiante->id_estudiante,
        'cod_inscripcion_uta' => (string)rand(100000, 999999),
        'fecha_inscripcion'   => now()->toDateString(),
        'estado_inscripcion'  => 'INSCRITO',
        'num_intento'         => 1,
    ]);

    $rolEstudiante = Rol::firstOrCreate(['nombre' => 'Estudiante']);
    $asignacion = UsuarioRolAsignacion::create([
        'id_usuario'               => $usuario->id_usuario,
        'id_rol'                   => $rolEstudiante->id_rol,
        'id_contexto'              => $curso->id_contexto,
        'asignado_por'             => $usuario->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada'    => now()->addYears(1),
        'esta_activo'              => true,
        'fue_eliminado'            => false,
        'creado_por'               => $usuario->id_usuario,
    ]);

    return compact('usuario', 'estudiante', 'inscripcion', 'asignacion');
}

test('sincronizarInscripciones marca como RETIRADO y revoca el rol en el curso a quien ya no figura en la intranet (ramo botado)', function () {
    $ctx = setupContextoCursoParaSincronizacion();
    $curso = $ctx['curso'];

    $carrera = $ctx['carrera'];

    // Alumno 1: sigue en el ramo
    $rut1 = 20111111;
    $a1 = crearAlumnoInscrito($curso, $carrera, $rut1, 'ANDRES');

    // Alumno 2: boto el ramo (no aparecera en la Intranet)
    $rut2 = 20222222;
    $a2 = crearAlumnoInscrito($curso, $carrera, $rut2, 'BERNARDO');

    // Mock de Intranet: sólo devuelve al Alumno 1
    $curCodigo = 202610000888;
    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: $curCodigo,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'A'
            ),
        ]));

    $mockOracle->shouldReceive('traer_ins_id')
        ->with([$curCodigo])
        ->andReturn(collect([
            new InscripcionData(ins_id: 111111, alum_rut: $rut1),
        ]));

    $mockOracle->shouldReceive('traer_alumno')
        ->with($rut1)
        ->andReturn(new AlumnoIntranetData(
            alum_rut: $rut1,
            alum_digito: 'K',
            alum_nombre: 'ANDRES',
            alum_apellido_pat: 'TEST',
            alum_apellido_mat: 'UNO'
        ));

    app()->instance('OracleDataService', $mockOracle);

    /** @var IntranetService $intranetService */
    $intranetService = app(IntranetService::class);
    $resultado = $intranetService->sincronizarInscripciones($curso);

    // Validar DTO de resultado
    expect($resultado->retiro_aplicado)->toBeTrue();
    expect($resultado->retirados)->toHaveCount(1);
    expect($resultado->retirados[0]['rut'])->toBe("{$rut2}-K");

    // Verificar en BD: Alumno 1 sigue INSCRITO y con rol activo
    $a1['inscripcion']->refresh();
    expect($a1['inscripcion']->estado_inscripcion)->toBe('INSCRITO');

    $rolActivoA1 = UsuarioRolAsignacion::where('id_usuario', $a1['usuario']->id_usuario)
        ->where('id_contexto', $curso->id_contexto)
        ->where('esta_activo', true)
        ->exists();
    expect($rolActivoA1)->toBeTrue();

    // Verificar en BD: Alumno 2 fue marcado como RETIRADO y su rol fue revocado
    $a2['inscripcion']->refresh();
    expect($a2['inscripcion']->estado_inscripcion)->toBe('RETIRADO');

    $rolActivoA2 = UsuarioRolAsignacion::where('id_usuario', $a2['usuario']->id_usuario)
        ->where('id_contexto', $curso->id_contexto)
        ->where('esta_activo', true)
        ->exists();
    expect($rolActivoA2)->toBeFalse();
});

test('sincronizarInscripciones reactiva y restituye el rol a un alumno previamente RETIRADO que vuelve a figurar en la intranet', function () {
    $ctx = setupContextoCursoParaSincronizacion();
    $curso = $ctx['curso'];
    $carrera = $ctx['carrera'];

    $rut = 20333333;
    $a = crearAlumnoInscrito($curso, $carrera, $rut, 'CAMILA');

    // Dejarlo inicialmente en RETIRADO y sin rol activo
    $a['inscripcion']->update(['estado_inscripcion' => 'RETIRADO']);
    $a['asignacion']->update(['esta_activo' => false]);

    // Mock de Intranet: el alumno Camila vuelve a figurar en el acta
    $curCodigo = 202610000889;
    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: $curCodigo,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'A'
            ),
        ]));

    $mockOracle->shouldReceive('traer_ins_id')
        ->with([$curCodigo])
        ->andReturn(collect([
            new InscripcionData(ins_id: 333333, alum_rut: $rut),
        ]));

    $mockOracle->shouldReceive('traer_alumno')
        ->with($rut)
        ->andReturn(new AlumnoIntranetData(
            alum_rut: $rut,
            alum_digito: 'K',
            alum_nombre: 'CAMILA',
            alum_apellido_pat: 'TEST',
            alum_apellido_mat: 'TRES'
        ));

    app()->instance('OracleDataService', $mockOracle);

    /** @var IntranetService $intranetService */
    $intranetService = app(IntranetService::class);
    $resultado = $intranetService->sincronizarInscripciones($curso);

    expect($resultado->retiro_aplicado)->toBeTrue();
    expect($resultado->reactivados)->toHaveCount(1);
    expect($resultado->reactivados[0]['rut'])->toBe("{$rut}-K");

    // Verificar en BD: vuelve a estar INSCRITO
    $a['inscripcion']->refresh();
    expect($a['inscripcion']->estado_inscripcion)->toBe('INSCRITO');

    // Verificar en BD: vuelve a tener rol activo en el contexto del curso
    $rolEstudiante = Rol::where('nombre', 'Estudiante')->first();
    $rolActivo = UsuarioRolAsignacion::where('id_usuario', $a['usuario']->id_usuario)
        ->where('id_contexto', $curso->id_contexto)
        ->where('id_rol', $rolEstudiante->id_rol)
        ->where('esta_activo', true)
        ->exists();
    expect($rolActivo)->toBeTrue();
});

test('sincronizarInscripciones aborta retiros preventivamente si la lectura de la intranet quedo incompleta', function () {
    $ctx = setupContextoCursoParaSincronizacion();
    $curso = $ctx['curso'];
    $carrera = $ctx['carrera'];

    $rut = 20444444;
    $a = crearAlumnoInscrito($curso, $carrera, $rut, 'DANIEL');

    // Mock de Intranet donde traer_ins_id falla con una excepción (ej: caída de red con Oracle)
    $curCodigo = 202610000890;
    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: $curCodigo,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'A'
            ),
        ]));

    $mockOracle->shouldReceive('traer_ins_id')
        ->with([$curCodigo])
        ->andThrow(new RuntimeException('Conexión con Oracle interrumpida'));

    app()->instance('OracleDataService', $mockOracle);

    /** @var IntranetService $intranetService */
    $intranetService = app(IntranetService::class);
    $resultado = $intranetService->sincronizarInscripciones($curso);

    // Debe abortar el retiro
    expect($resultado->retiro_aplicado)->toBeFalse();
    expect($resultado->retirados)->toBeEmpty();
    expect($resultado->advertencias)->not->toBeEmpty();
    expect($resultado->advertencias[0])->toContain('No se retiró a nadie');

    // El alumno NO debe ser retirado en la base de datos
    $a['inscripcion']->refresh();
    expect($a['inscripcion']->estado_inscripcion)->toBe('INSCRITO');
});

test('sincronizarInscripciones reporta advertencia por inscripciones de componente sin respaldo sin borrarlas', function () {
    $ctx = setupContextoCursoParaSincronizacion();
    $curso = $ctx['curso'];
    $componente = $ctx['componente'];
    $carrera = $ctx['carrera'];

    $rut = 20555555;
    $a = crearAlumnoInscrito($curso, $carrera, $rut, 'ESTEBAN');

    // El trigger en PostgreSQL ya crea la fila en inscripcion_componente.
    // Asignamos el código UTA para verificar que quede registrada.
    InscripcionComponente::where('id_componente', $componente->id_componente)
        ->where('id_estudiante', $a['estudiante']->id_estudiante)
        ->update(['cod_inscripcion_curso_uta' => '555555']);

    // La intranet devuelve lista vacía
    $curCodigo = 202610000891;
    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: $curCodigo,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'A'
            ),
        ]));

    $mockOracle->shouldReceive('traer_ins_id')
        ->with([$curCodigo])
        ->andReturn(collect());

    app()->instance('OracleDataService', $mockOracle);

    /** @var IntranetService $intranetService */
    $intranetService = app(IntranetService::class);
    $resultado = $intranetService->sincronizarInscripciones($curso);

    // Debe reportar componentes sin respaldo
    expect($resultado->componentes_sin_respaldo)->toBe(1);

    // La fila en inscripcion_componente DEBE seguir existiendo (no se borra para preservar notas/asistencia)
    $sigueExistiendo = InscripcionComponente::where('id_componente', $componente->id_componente)
        ->where('id_estudiante', $a['estudiante']->id_estudiante)
        ->exists();
    expect($sigueExistiendo)->toBeTrue();
});

test('endpoint HTTP POST /admin/cursos/{id}/sincronizar-inscripciones valida autorizacion y maneja respuestas JSON e Inertia', function () {
    $ctx = setupContextoCursoParaSincronizacion();
    $curso = $ctx['curso'];
    $usuarioAdmin = $ctx['usuarioAdmin'];

    $curCodigo = 202610000892;
    $mockOracle = Mockery::mock();
    $mockOracle->shouldReceive('traer_cur_codigos')
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: $curCodigo,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'A'
            ),
        ]));
    $mockOracle->shouldReceive('traer_ins_id')
        ->with([$curCodigo])
        ->andReturn(collect());

    app()->instance('OracleDataService', $mockOracle);

    // 1. Usuario no autenticado -> 403 o redirección login
    $respSinAuth = $this->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-inscripciones");
    expect(in_array($respSinAuth->status(), [401, 403]))->toBeTrue();

    // 2. Usuario sin rol de administración global -> 302 Redirección a dashboard (Middleware IsAdmin)
    $usuarioSinPermiso = Usuario::forceCreate([
        'rut'                   => '19999999-9',
        'username'              => 'sin_permiso_' . uniqid(),
        'passhash'              => bcrypt('password123'),
        'nombre1'               => 'Sin',
        'apellido1'             => 'Permiso',
        'esta_activo'           => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $respSinPermiso = $this->actingAs($usuarioSinPermiso)
        ->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-inscripciones");
    $respSinPermiso->assertRedirect(route('dashboard'));

    // 3. Usuario admin autorizado con petición JSON -> 200 OK con payload estructurado
    $respAdminJson = $this->actingAs($usuarioAdmin)
        ->postJson("/admin/cursos/{$curso->id_curso}/sincronizar-inscripciones");

    $respAdminJson->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'inscripcion',
                'retirados',
                'reactivados',
                'componentes_sin_respaldo',
                'retiro_aplicado',
                'advertencias',
            ],
        ]);

    // 4. Usuario admin autorizado con petición Inertia -> 302 Redirect Back con flash message
    $respAdminInertia = $this->actingAs($usuarioAdmin)
        ->from("/admin/cursos/{$curso->id_curso}/inscripciones")
        ->post(
            "/admin/cursos/{$curso->id_curso}/sincronizar-inscripciones",
            [],
            ['X-Inertia' => 'true']
        );

    $respAdminInertia->assertStatus(302)
        ->assertRedirect("/admin/cursos/{$curso->id_curso}/inscripciones")
        ->assertSessionHas('success');
});
