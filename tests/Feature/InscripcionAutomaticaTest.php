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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

test('inscribe automaticamente alumnos desde la intranet creando usuario con contrasena temporal si no existe', function () {

    // 1. Crear jerarquía académica real en BD UTAMED
    $facultad = Facultad::firstOrCreate(['nombre' => 'Facultad de Ciencias Test'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(['nombre' => 'Departamento de Computación Test'], ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]);
    $carrera = Carrera::firstOrCreate(['nombre' => 'Ingeniería Civil Informática Test'], ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]);
    $plan = Plan::firstOrCreate(['id_carrera' => $carrera->id_carrera, 'agno_plan' => 2020, 'version_plan' => 1], ['id_contexto' => 1]);
    $asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'INF101_T'],
        [
            'nombre'            => 'Programación I Test',
            'creditos_sct'      => 6,
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

    // Crear usuario admin/docente titular y su contexto
    $usuarioDocente = Usuario::firstOrCreate(
        ['rut' => '11111111-1'],
        [
            'username'    => 'docente_test_auto',
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'Carlos',
            'apellido1'   => 'Docente',
            'esta_activo' => true,
        ]
    );
    $docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

    // Asignar rol SuperAdmin para pasar middleware is_admin
    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $usuarioDocente->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        [
            'id_usuario'  => $usuarioDocente->id_usuario,
            'id_rol'      => $rolSuperAdmin->id_rol,
            'id_contexto' => 1,
        ],
        [
            'asignado_por'             => $usuarioDocente->id_usuario,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada'    => now()->addYears(100),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $usuarioDocente->id_usuario,
        ]
    );

    $contextoCurso = Contexto::create(['contexto_display' => 'Curso INF101-A Test ' . uniqid()]);
    $curso = Curso::create([
        'cod_curso'          => rand(99000, 99999),
        'nombre'             => 'Programación I Sección A Test',
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
    $contextoComponente = Contexto::create(['contexto_display' => 'Componente Cátedra Test']);
    $componente = Componente::create([
        'genera_acta'                       => true,
        'porcentaje_aprobacion'             => 60,
        'aprobacion_obligatoria'            => true,
        'porcentaje_asistencia_obligatoria' => 75,
        'id_tipo_componente'                => $tipoCatedra->id_tipo_componente,
        'id_curso'                          => $curso->id_curso,
        'id_contexto'                       => $contextoComponente->id_contexto,
    ]);

    // 2. MOCKEAR ÚNICAMENTE el servicio de la Intranet (OracleDataService)
    $insId = rand(500000, 999999);
    $alumRut = rand(20000000, 29999999);

    $mockOracleService = Mockery::mock();

    $mockOracleService->shouldReceive('traer_cur_codigos')
        ->once()
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: 202610000351,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'A'
            ),
        ]));

    $mockOracleService->shouldReceive('traer_ins_id')
        ->once()
        ->with([202610000351])
        ->andReturn(collect([
            new InscripcionData(ins_id: $insId, alum_rut: $alumRut),
        ]));

    $mockOracleService->shouldReceive('traer_alumno')
        ->once()
        ->with($alumRut)
        ->andReturn(new AlumnoIntranetData(
            alum_rut: $alumRut,
            alum_digito: 'K',
            alum_nombre: 'MARIA',
            alum_apellido_pat: 'GONZALEZ',
            alum_apellido_mat: 'LOPEZ'
        ));

    app()->instance('OracleDataService', $mockOracleService);

    // Autenticar como usuario docente
    $this->actingAs($usuarioDocente);

    // 3. Ejecutar la llamada HTTP al endpoint de inscripción automática
    $response = $this->postJson("/admin/cursos/{$curso->id_curso}/inscripcion-automatica");

    // 4. Assertions del Endpoint y la respuesta JSON
    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_procesados', 1)
        ->assertJsonPath('data.alumnos_creados', 1)
        ->assertJsonPath('data.ya_inscritos', 1);


    // 5. Assertions reales en la Base de Datos PostgreSQL
    // Verificar que el Usuario se creó en BD con la contraseña temporal por defecto (RUT como password)
    $usuarioCreado = Usuario::where('username', (string)$alumRut)->first();
    expect($usuarioCreado)->not->toBeNull();
    expect($usuarioCreado->rut)->toBe("{$alumRut}-K");
    expect($usuarioCreado->nombre1)->toBe('MARIA');
    expect($usuarioCreado->apellido1)->toBe('GONZALEZ');
    expect(Hash::check((string)$alumRut, $usuarioCreado->passhash))->toBeTrue();

    // Verificar que el Estudiante se creó vinculado a la carrera
    $estudiante = Estudiante::where('id_usuario', $usuarioCreado->id_usuario)->first();
    expect($estudiante)->not->toBeNull();
    expect($estudiante->id_carrera)->toBe($carrera->id_carrera);

    // Verificar inscripción a nivel Curso (con cod_inscripcion_uta)
    $inscripcionCurso = InscripcionCurso::where('id_curso', $curso->id_curso)
        ->where('id_estudiante', $estudiante->id_estudiante)
        ->first();
    expect($inscripcionCurso)->not->toBeNull();
    expect($inscripcionCurso->cod_inscripcion_uta)->toBe((string)$insId);

    // Verificar inscripción a nivel Componente (con cod_inscripcion_curso_uta)
    $inscripcionComponente = InscripcionComponente::where('id_componente', $componente->id_componente)
        ->where('id_estudiante', $estudiante->id_estudiante)
        ->first();
    expect($inscripcionComponente)->not->toBeNull();
    expect((int)$inscripcionComponente->cod_inscripcion_curso_uta)->toBe($insId);

    $curso->refresh();
    $componente->refresh();

    $rolEstudiante = Rol::where('nombre', 'Estudiante')->first();
    expect($rolEstudiante)->not->toBeNull();

    $rolEnCurso = UsuarioRolAsignacion::where('id_usuario', $usuarioCreado->id_usuario)
        ->where('id_contexto', $curso->id_contexto)
        ->where('id_rol', $rolEstudiante->id_rol)
        ->where('esta_activo', true)
        ->exists();
    expect($rolEnCurso)->toBeTrue();

    $rolEnComponente = UsuarioRolAsignacion::where('id_usuario', $usuarioCreado->id_usuario)
        ->where('id_contexto', $componente->id_contexto)
        ->where('id_rol', $rolEstudiante->id_rol)
        ->where('esta_activo', true)
        ->exists();
    expect($rolEnComponente)->toBeTrue();
});

test('cuando el alumno ya existe en la bd no consulta la intranet para crearlo y conecta normalmente con ins_id', function () {
    // 1. Crear jerarquía académica real en BD UTAMED
    $facultad = Facultad::firstOrCreate(['nombre' => 'Facultad de Ciencias Test'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(['nombre' => 'Departamento de Computación Test'], ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]);
    $carrera = Carrera::firstOrCreate(['nombre' => 'Ingeniería Civil Informática Test'], ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]);
    $plan = Plan::firstOrCreate(['id_carrera' => $carrera->id_carrera, 'agno_plan' => 2020, 'version_plan' => 1], ['id_contexto' => 1]);
    $asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'INF101_T'],
        [
            'nombre'            => 'Programación I Test',
            'creditos_sct'      => 6,
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

    // Crear usuario admin/docente titular
    $usuarioDocente = Usuario::firstOrCreate(
        ['rut' => '11111111-1'],
        [
            'username'    => 'docente_test_auto',
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'Carlos',
            'apellido1'   => 'Docente',
            'esta_activo' => true,
        ]
    );
    $docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $usuarioDocente->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        [
            'id_usuario'  => $usuarioDocente->id_usuario,
            'id_rol'      => $rolSuperAdmin->id_rol,
            'id_contexto' => 1,
        ],
        [
            'asignado_por'             => $usuarioDocente->id_usuario,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada'    => now()->addYears(100),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $usuarioDocente->id_usuario,
        ]
    );

    $contextoCurso = Contexto::create(['contexto_display' => 'Curso INF101-B Test ' . uniqid()]);
    $curso = Curso::create([
        'cod_curso'          => rand(99000, 99999),
        'nombre'             => 'Programación I Sección B Test',
        'indice_grupo'       => 2,
        'fecha_inicio'       => now()->toDateString(),
        'fecha_fin'          => now()->addMonths(5)->toDateString(),
        'semestre_real'      => 1,
        'agno_real'          => 2026,
        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
        'id_contexto'        => $contextoCurso->id_contexto,
        'id_docente_titular' => $docente->id_docente,
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'CATEDRA']);
    $contextoComponente = Contexto::create(['contexto_display' => 'Componente Cátedra B Test']);
    $componente = Componente::create([
        'genera_acta'                       => true,
        'porcentaje_aprobacion'             => 60,
        'aprobacion_obligatoria'            => true,
        'porcentaje_asistencia_obligatoria' => 75,
        'id_tipo_componente'                => $tipoCatedra->id_tipo_componente,
        'id_curso'                          => $curso->id_curso,
        'id_contexto'                       => $contextoComponente->id_contexto,
    ]);

    // 2. CREAR PREVIAMENTE AL ESTUDIANTE EN LA BD LOCAL
    $alumRut = rand(30000000, 39999999);
    $insId = rand(500000, 999999);

    $usuarioExistente = Usuario::create([
        'username'    => (string)$alumRut,
        'passhash'    => bcrypt('password_existente'),
        'rut'         => "{$alumRut}-5",
        'nombre1'     => 'Pedro',
        'apellido1'   => 'Existente',
        'esta_activo' => true,
    ]);
    $estudianteExistente = Estudiante::create([
        'id_usuario' => $usuarioExistente->id_usuario,
        'id_carrera' => $carrera->id_carrera,
    ]);

    $totalUsuariosAntes = Usuario::count();
    $totalEstudiantesAntes = Estudiante::count();

    // 3. MOCKEAR Intranet: `traer_alumno` NO DEBE SER INVOCADO porque el alumno ya existe localmente
    $mockOracleService = Mockery::mock();

    $mockOracleService->shouldReceive('traer_cur_codigos')
        ->once()
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: 202610000352,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'B'
            ),
        ]));

    $mockOracleService->shouldReceive('traer_ins_id')
        ->once()
        ->with([202610000352])
        ->andReturn(collect([
            new InscripcionData(ins_id: $insId, alum_rut: $alumRut),
        ]));

    // `traer_alumno` NUNCA debe ejecutarse
    $mockOracleService->shouldNotReceive('traer_alumno');

    app()->instance('OracleDataService', $mockOracleService);

    $this->actingAs($usuarioDocente);

    // 4. Ejecutar la llamada HTTP
    $response = $this->postJson("/admin/cursos/{$curso->id_curso}/inscripcion-automatica");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_procesados', 1)
        ->assertJsonPath('data.alumnos_creados', 0);


    // 5. Verificar que NO se crearon usuarios ni estudiantes duplicados
    expect(Usuario::count())->toBe($totalUsuariosAntes);
    expect(Estudiante::count())->toBe($totalEstudiantesAntes);

    // 6. Verificar que el estudiante existente fue enlazado normalmente con el ins_id
    $inscripcionCurso = InscripcionCurso::where('id_curso', $curso->id_curso)
        ->where('id_estudiante', $estudianteExistente->id_estudiante)
        ->first();
    expect($inscripcionCurso)->not->toBeNull();
    expect($inscripcionCurso->cod_inscripcion_uta)->toBe((string)$insId);

    $inscripcionComponente = InscripcionComponente::where('id_componente', $componente->id_componente)
        ->where('id_estudiante', $estudianteExistente->id_estudiante)
        ->first();
    expect($inscripcionComponente)->not->toBeNull();
    expect((int)$inscripcionComponente->cod_inscripcion_curso_uta)->toBe($insId);
});

test('inscribe automaticamente 10 alumnos simultaneamente (5 preexistentes y 5 nuevos creados desde intranet)', function () {
    // 1. Crear jerarquía académica real en BD UTAMED
    $facultad = Facultad::firstOrCreate(['nombre' => 'Facultad de Ciencias Test'], ['id_contexto' => 1]);
    $departamento = Departamento::firstOrCreate(['nombre' => 'Departamento de Computación Test'], ['id_facultad' => $facultad->id_facultad, 'id_contexto' => 1]);
    $carrera = Carrera::firstOrCreate(['nombre' => 'Ingeniería Civil Informática Test'], ['id_departamento' => $departamento->id_departamento, 'id_contexto' => 1]);
    $plan = Plan::firstOrCreate(['id_carrera' => $carrera->id_carrera, 'agno_plan' => 2020, 'version_plan' => 1], ['id_contexto' => 1]);
    $asignatura = Asignatura::firstOrCreate(
        ['cod_asignatura' => 'INF101_T'],
        [
            'nombre'            => 'Programación I Test',
            'creditos_sct'      => 6,
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

    $usuarioDocente = Usuario::firstOrCreate(
        ['rut' => '11111111-1'],
        [
            'username'    => 'docente_test_auto',
            'passhash'    => bcrypt('password'),
            'nombre1'     => 'Carlos',
            'apellido1'   => 'Docente',
            'esta_activo' => true,
        ]
    );
    $docente = Docente::firstOrCreate(['id_usuario' => $usuarioDocente->id_usuario]);

    $rolSuperAdmin = Rol::firstOrCreate(['nombre' => 'SuperAdmin'], ['creado_por' => $usuarioDocente->id_usuario]);
    UsuarioRolAsignacion::firstOrCreate(
        [
            'id_usuario'  => $usuarioDocente->id_usuario,
            'id_rol'      => $rolSuperAdmin->id_rol,
            'id_contexto' => 1,
        ],
        [
            'asignado_por'             => $usuarioDocente->id_usuario,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada'    => now()->addYears(100),
            'esta_activo'              => true,
            'fue_eliminado'            => false,
            'creado_por'               => $usuarioDocente->id_usuario,
        ]
    );

    $contextoCurso = Contexto::create(['contexto_display' => 'Curso INF101-C Batch Test ' . uniqid()]);
    $curso = Curso::create([
        'cod_curso'          => rand(99000, 99999),
        'nombre'             => 'Programación I Sección C Batch Test',
        'indice_grupo'       => 3,
        'fecha_inicio'       => now()->toDateString(),
        'fecha_fin'          => now()->addMonths(5)->toDateString(),
        'semestre_real'      => 1,
        'agno_real'          => 2026,
        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
        'id_contexto'        => $contextoCurso->id_contexto,
        'id_docente_titular' => $docente->id_docente,
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'CATEDRA']);
    $contextoComponente = Contexto::create(['contexto_display' => 'Componente Cátedra C Batch Test']);
    $componente = Componente::create([
        'genera_acta'                       => true,
        'porcentaje_aprobacion'             => 60,
        'aprobacion_obligatoria'            => true,
        'porcentaje_asistencia_obligatoria' => 75,
        'id_tipo_componente'                => $tipoCatedra->id_tipo_componente,
        'id_curso'                          => $curso->id_curso,
        'id_contexto'                       => $contextoComponente->id_contexto,
    ]);

    // 2. Generar 10 alumnos (5 pre-existentes en BD y 5 nuevos que vendrán de Intranet)
    $rutsExistentes = [40000001, 40000002, 40000003, 40000004, 40000005];
    $rutsNuevos = [40000006, 40000007, 40000008, 40000009, 40000010];

    foreach ($rutsExistentes as $rut) {
        $u = Usuario::create([
            'username'    => (string)$rut,
            'passhash'    => bcrypt("password_{$rut}"),
            'rut'         => "{$rut}-K",
            'nombre1'     => "AlumnoExistente_{$rut}",
            'apellido1'   => 'Test',
            'esta_activo' => true,
        ]);
        Estudiante::create([
            'id_usuario' => $u->id_usuario,
            'id_carrera' => $carrera->id_carrera,
        ]);
    }

    $inscripcionesIntranet = collect();
    $curCodigo = 202610000999;
    $rutsTodos = array_merge($rutsExistentes, $rutsNuevos);

    foreach ($rutsTodos as $idx => $rut) {
        $insId = 700000 + $idx;
        $inscripcionesIntranet->push(new InscripcionData(ins_id: $insId, alum_rut: $rut));
    }

    // 3. Mockear Intranet
    $mockOracleService = Mockery::mock();

    $mockOracleService->shouldReceive('traer_cur_codigos')
        ->once()
        ->andReturn(collect([
            new ComponenteCursoData(
                cur_codigo: $curCodigo,
                curso_tipo_asig: TipoAsignatura::Catedra,
                curso_grupo_asig: 'C'
            ),
        ]));

    $mockOracleService->shouldReceive('traer_ins_id')
        ->once()
        ->with([$curCodigo])
        ->andReturn($inscripcionesIntranet);

    // traer_alumno debe llamarse SOLO 5 veces (para los ruts nuevos 40000006 a 40000010)
    foreach ($rutsNuevos as $rutNuevo) {
        $mockOracleService->shouldReceive('traer_alumno')
            ->once()
            ->with($rutNuevo)
            ->andReturn(new AlumnoIntranetData(
                alum_rut: $rutNuevo,
                alum_digito: 'K',
                alum_nombre: "AlumnoNuevo_{$rutNuevo}",
                alum_apellido_pat: 'Perez',
                alum_apellido_mat: 'Gomez'
            ));
    }

    app()->instance('OracleDataService', $mockOracleService);

    $this->actingAs($usuarioDocente);

    // 4. Ejecutar endpoint HTTP de inscripción automática
    $response = $this->postJson("/admin/cursos/{$curso->id_curso}/inscripcion-automatica");

    // 5. Assertions del Endpoint
    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_procesados', 10)
        ->assertJsonPath('data.alumnos_creados', 5);


    // 6. Assertions reales en BD PostgreSQL
    $curso->refresh();
    $componente->refresh();
    $rolEstudiante = Rol::where('nombre', 'Estudiante')->first();

    foreach ($rutsTodos as $idx => $rut) {
        $insId = 700000 + $idx;

        $usr = Usuario::where('username', (string)$rut)->first();
        expect($usr)->not->toBeNull();

        $est = Estudiante::where('id_usuario', $usr->id_usuario)->first();
        expect($est)->not->toBeNull();

        // Inscripción a nivel curso
        $insCurso = InscripcionCurso::where('id_curso', $curso->id_curso)
            ->where('id_estudiante', $est->id_estudiante)
            ->first();
        expect($insCurso)->not->toBeNull();
        expect($insCurso->cod_inscripcion_uta)->toBe((string)$insId);

        // Inscripción a nivel componente
        $insComp = InscripcionComponente::where('id_componente', $componente->id_componente)
            ->where('id_estudiante', $est->id_estudiante)
            ->first();
        expect($insComp)->not->toBeNull();
        expect((int)$insComp->cod_inscripcion_curso_uta)->toBe($insId);

        // Asignación de rol activa en curso y componente
        expect(UsuarioRolAsignacion::where('id_usuario', $usr->id_usuario)->where('id_contexto', $curso->id_contexto)->where('id_rol', $rolEstudiante->id_rol)->where('esta_activo', true)->exists())->toBeTrue();
        expect(UsuarioRolAsignacion::where('id_usuario', $usr->id_usuario)->where('id_contexto', $componente->id_contexto)->where('id_rol', $rolEstudiante->id_rol)->where('esta_activo', true)->exists())->toBeTrue();
    }
});

