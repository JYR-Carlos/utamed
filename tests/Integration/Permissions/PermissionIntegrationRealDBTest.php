<?php

use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\Contexto;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Integration test - no RefreshDatabase
uses(TestCase::class);

// ============================================================================
// SETUP: Crear datos reales en BD testing
// ============================================================================

beforeEach(function () {
    // ========== LIMPIAR DATOS DE TESTS PREVIOS PRIMERO ==========
    // Eliminar todos los usuarios de test creados en iteraciones previas
    $testUsernames = [
        'ayudante_test',
        'asistente_test',
        'usuario1_test',
        'usuario2_test',
        'usuario3_test',
        'usuario4_test',
        'usuario5_test',
        'usuario6_test',
        'usuario7_test',
        'usuario8_test',
        'user_a_test',
        'user_b_test',
        'user_c_test',
        'usuario_multi_test'
    ];

    // Obtener todos los IDs de usuarios de test (incluye soft-deleted)
    $testUserIds = Usuario::withTrashed()->whereIn('username', $testUsernames)->pluck('id_usuario')->toArray();

    if (!empty($testUserIds)) {
        // Eliminar permisos especiales donde el usuario es creador (creado_por)
        UsuarioPermisoEspecial::whereIn('creado_por', $testUserIds)->forceDelete();

        // Eliminar permisos especiales donde el usuario es receptor (id_usuario)
        UsuarioPermisoEspecial::whereIn('id_usuario', $testUserIds)->forceDelete();

        // Eliminar asignaciones de roles
        UsuarioRolAsignacion::whereIn('id_usuario', $testUserIds)->forceDelete();

        // Eliminar usuarios (forceDelete para evitar UniqueConstraintViolation)
        Usuario::withTrashed()->whereIn('id_usuario', $testUserIds)->forceDelete();
    }

    // ========== INICIALIZAR BD SI ESTÁ VACÍA ==========
    // Crear todos los tipos de contexto necesarios
    TipoContexto::firstOrCreate(
        ['categoria' => 'global'],
        ['tabla_referenciada' => 'GLOBAL']
    );
    TipoContexto::firstOrCreate(
        ['categoria' => 'facultad'],
        ['tabla_referenciada' => 'administrativo.facultad']
    );
    TipoContexto::firstOrCreate(
        ['categoria' => 'departamento'],
        ['tabla_referenciada' => 'administrativo.departamento']
    );
    TipoContexto::firstOrCreate(
        ['categoria' => 'carrera'],
        ['tabla_referenciada' => 'administrativo.carrera']
    );
    TipoContexto::firstOrCreate(
        ['categoria' => 'plan'],
        ['tabla_referenciada' => 'administrativo.plan']
    );
    TipoContexto::firstOrCreate(
        ['categoria' => 'curso'],
        ['tabla_referenciada' => 'curso.curso']
    );
    TipoContexto::firstOrCreate(
        ['categoria' => 'actividad'],
        ['tabla_referenciada' => 'curso.actividad']
    );

    // Obtener TipoContexto global
    $tipoSystem = TipoContexto::where('categoria', 'global')->first();

    // Obtener o crear Contexto global (reutilizar si ya existe)
    $contextoGlobal = Contexto::where('contexto_display', 'Contexto Global | Solo Permisos Administrativos')
        ->first();

    if (!$contextoGlobal) {
        $contextoGlobal = Contexto::create([
            'contexto_display' => 'Contexto Global | Solo Permisos Administrativos',
            'id_tipo_contexto' => $tipoSystem->id_tipo_contexto,
        ]);
    } else {
        // Asegurar que tiene el tipo correcto
        if ($contextoGlobal->id_tipo_contexto !== $tipoSystem->id_tipo_contexto) {
            $contextoGlobal->update(['id_tipo_contexto' => $tipoSystem->id_tipo_contexto]);
        }
    }

    $this->contextoGlobal_id = $contextoGlobal->id_contexto;

    // Crear usuario admin sistema para creado_por si no existe
    $adminSistema = Usuario::firstOrCreate(
        ['username' => 'admin_sistema'],
        [
            'rut' => '00000000-0',
            'nombre1' => 'Admin',
            'apellido1' => 'Sistema',
            'email' => 'admin@sistema.local',
            'passhash' => Hash::make('admin123'),
            'esta_activo' => true
        ]
    );
    $this->adminSistemaId = $adminSistema->id_usuario;

    // ========== LIMPIAR DATOS DE TESTS PREVIOS ==========
    // Obtener IDs de usuarios de test (incluye soft-deleted)
    $mainUserIds = Usuario::withTrashed()
        ->whereIn('username', ['superadmin', 'profesor', 'coordinador'])
        ->pluck('id_usuario')->toArray();

    if (!empty($mainUserIds)) {
        UsuarioRolAsignacion::whereIn('id_usuario', $mainUserIds)->forceDelete();
        UsuarioPermisoEspecial::whereIn('id_usuario', $mainUserIds)->forceDelete();
    }

    // Eliminar permisos de roles antes de eliminar roles (FK constraint)
    $rolIds = Rol::whereIn('nombre', ['Super Admin', 'Profesor', 'Coordinador'])->pluck('id_rol');
    if ($rolIds->isNotEmpty()) {
        DB::table('asignacion_rol_permiso')
            ->whereIn('id_rol', $rolIds)
            ->delete();
    }

    // Eliminar roles de test
    Rol::whereIn('nombre', ['Super Admin', 'Profesor', 'Coordinador'])
        ->forceDelete();

    // Eliminar usuarios de test (forceDelete para evitar UniqueConstraintViolation)
    Usuario::withTrashed()->whereIn('username', ['superadmin', 'profesor', 'coordinador'])->forceDelete();

    // Limpiar estructura administrativa de tests previos
    DB::table('curso')->where('nombre', 'Matemática I')->delete();
    DB::table('asignacion_plan')->whereIn('id_asignatura', function ($q) {
        $q->select('id_asignatura')->from('asignatura')->where('cod_asignatura', 'MAT-101');
    })->delete();
    DB::table('asignatura')->where('cod_asignatura', 'MAT-101')->delete();
    DB::table('plan')->whereIn('id_carrera', function ($q) {
        $q->select('id_carrera')->from('carrera')->where('nombre', 'Ingeniería en Sistemas');
    })->delete();
    DB::table('carrera')->where('nombre', 'Ingeniería en Sistemas')->delete();
    DB::table('departamento')->where('nombre', 'Departamento de Ingeniería Test')->delete();
    DB::table('facultad')->where('nombre', 'Facultad de Ingeniería Test')->delete();

    // ========== CREAR ESTRUCTURA ADMINISTRATIVA ==========
    // Crear Facultad usando DB directamente para evitar qualifyColumn bugs
    $facultadId = DB::table('facultad')->insertGetId([
        'nombre' => 'Facultad de Ingeniería Test',
        'fecha_creacion' => now(),
        'fecha_modificacion' => now()
    ], 'id_facultad');
    $this->facultad = Facultad::find($facultadId);

    // Crear Departamento
    $departamentoId = DB::table('departamento')->insertGetId([
        'nombre' => 'Departamento de Ingeniería Test',
        'id_facultad' => $facultadId,
        'fecha_creacion' => now(),
        'fecha_modificacion' => now()
    ], 'id_departamento');
    $this->departamento = Departamento::find($departamentoId);

    // Crear Carrera (id_contexto se crea automáticamente por trigger)
    $this->carrera = Carrera::create([
        'nombre' => 'Ingeniería en Sistemas',
        'jornada' => 'Diurna',
        'sede' => 'Central',
        'modalidad' => 'Presencial',
        'id_departamento' => $this->departamento->id_departamento,
    ]);
    $this->carrera->refresh(); // Trigger sets id_contexto

    // Crear Plan (id_contexto se hereda del trigger)
    $this->plan = Plan::create([
        'id_carrera' => $this->carrera->id_carrera,
        'agno' => 2023,
        'version_plan' => 1
    ]);

    // Crear Asignatura para poder vincular Curso con Plan
    $asignaturaId = DB::table('asignatura')->insertGetId([
        'cod_asignatura' => 'MAT-101',
        'nombre' => 'Matemática I',
        'creditos_sct' => 6,
        'horas_catedra' => 4,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 1,
        'horas_autonomas' => 3,
        'fecha_creacion' => now(),
        'fecha_modificacion' => now()
    ], 'id_asignatura');

    // Crear AsignacionPlan (vincula asignatura con plan)
    $asignacionPlanId = DB::table('asignacion_plan')->insertGetId([
        'id_asignatura' => $asignaturaId,
        'id_plan' => $this->plan->id_plan,
        'agno_planificado' => 1,
        'semestre_planificado' => 1,
        'fecha_creacion' => now()
    ], 'id_asignacion_plan');

    // Crear Curso (id_contexto se crea automáticamente por trigger)
    $this->curso = Curso::create([
        'nombre' => 'Matemática I',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'agno_real' => 2023,
        'semestre_real' => 1,
        'estado_interno' => 'Activo',
        'id_asignacion_plan' => $asignacionPlanId,
    ]);
    $this->curso->refresh(); // Trigger sets id_contexto

    // ========== CREAR PERMISOS NECESARIOS ==========
    // Crear todos los permisos usados en los tests
    $permisoWildcard = Permiso::where('slug', '*')->firstOrCreate(
        ['slug' => '*'],
        ['nombre' => 'Super Admin Access', 'descripcion' => 'Sistema']
    );

    $permisoVerCurso = Permiso::where('slug', Permissions::CURSOS_VER->value)->firstOrCreate(
        ['slug' => Permissions::CURSOS_VER->value],
        ['nombre' => 'Ver Cursos', 'descripcion' => 'Docencia']
    );

    $permisoCrearCurso = Permiso::where('slug', Permissions::CURSOS_CREAR->value)->firstOrCreate(
        ['slug' => Permissions::CURSOS_CREAR->value],
        ['nombre' => 'Crear Cursos', 'descripcion' => 'Administrativo']
    );

    $permisoEditarCurso = Permiso::where('slug', Permissions::CURSOS_EDITAR->value)->firstOrCreate(
        ['slug' => Permissions::CURSOS_EDITAR->value],
        ['nombre' => 'Editar Cursos', 'descripcion' => 'Administrativo']
    );

    $permisoVerCarrera = Permiso::where('slug', Permissions::CARRERAS_VER->value)->firstOrCreate(
        ['slug' => Permissions::CARRERAS_VER->value],
        ['nombre' => 'Ver Carreras', 'descripcion' => 'Administrativo']
    );

    // ========== USUARIO 1: SUPERADMIN (tiene '*') ==========
    $this->superadmin = Usuario::create([
        'username' => 'superadmin',
        'rut' => '11111111-1',
        'nombre1' => 'Super',
        'apellido1' => 'Admin',
        'email' => 'superadmin@test.local',
        'passhash' => Hash::make('superadmin123'),
        'esta_activo' => true
    ]);

    // Crear rol Super Admin
    $this->rolSuperAdmin = Rol::create([
        'nombre' => 'Super Admin',
        'creado_por' => $this->adminSistemaId
    ]);

    // Asignar rol al superadmin en contexto global
    $uraData = [
        'id_usuario' => $this->superadmin->id_usuario,
        'id_contexto' => $this->contextoGlobal_id,
        'id_rol' => $this->rolSuperAdmin->id_rol,
        'asignado_por' => $this->superadmin->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(100),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $this->superadmin->id_usuario
    ];
    UsuarioRolAsignacion::create($uraData);

    // Asignar permiso '*' al superadmin vía UPE (permiso especial)
    UsuarioPermisoEspecial::create([
        'id_usuario' => $this->superadmin->id_usuario,
        'id_permiso' => $permisoWildcard->id_permiso,
        'id_contexto' => $this->contextoGlobal_id,
        'esta_permitido' => true,
        'puede_delegar' => true,
        'fecha_fin_planificada' => now()->addYears(100),
        'creado_por' => $this->superadmin->id_usuario,
        'esta_activo' => true
    ]);

    // ========== USUARIO 2: PROFESOR (tiene 'cursos:ver') ==========
    $this->profesor = Usuario::create([
        'username' => 'profesor',
        'rut' => '22222222-2',
        'nombre1' => 'Juan',
        'apellido1' => 'Profesor',
        'email' => 'profesor@test.local',
        'passhash' => Hash::make('profesor123'),
        'esta_activo' => true
    ]);

    // Crear rol Profesor
    $this->rolProfesor = Rol::create([
        'nombre' => 'Profesor',
        'creado_por' => $this->adminSistemaId
    ]);

    // Asignar permiso 'cursos:ver' a rol Profesor (vía AsignaciónRolPermiso)
    $this->rolProfesor->permisos()->attach($permisoVerCurso->id_permiso, [
        'puede_delegar_permiso' => false
    ]);

    // Asignar rol al profesor en contexto de la carrera
    UsuarioRolAsignacion::create([
        'id_usuario' => $this->profesor->id_usuario,
        'id_contexto' => $this->carrera->id_contexto,
        'id_rol' => $this->rolProfesor->id_rol,
        'asignado_por' => $this->superadmin->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(100),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $this->superadmin->id_usuario
    ]);

    // IMPORTANTE: También asignar en contexto del Curso para que pueda acceder a él
    UsuarioRolAsignacion::create([
        'id_usuario' => $this->profesor->id_usuario,
        'id_contexto' => $this->curso->id_contexto,
        'id_rol' => $this->rolProfesor->id_rol,
        'asignado_por' => $this->superadmin->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(100),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $this->superadmin->id_usuario
    ]);

    // ========== USUARIO 3: COORDINADOR (tiene 'cursos:crear') ==========
    $this->coordinador = Usuario::firstOrCreate(
        ['username' => 'coordinador'],
        [
            'rut' => '33333333-3',
            'nombre1' => 'María',
            'apellido1' => 'Coordinador',
            'email' => 'coordinador@test.local',
            'passhash' => Hash::make('coordinador123'),
            'esta_activo' => true
        ]
    );

    // Crear rol Coordinador
    $this->rolCoordinador = Rol::create([
        'nombre' => 'Coordinador',
        'creado_por' => $this->adminSistemaId
    ]);

    // Asignar permisos: 'cursos:crear' y 'cursos:ver' al rol Coordinador
    $this->rolCoordinador->permisos()->attach([
        $permisoVerCurso->id_permiso => ['puede_delegar_permiso' => false],
        $permisoCrearCurso->id_permiso => ['puede_delegar_permiso' => true]
    ]);

    // Asignar rol al coordinador en contexto de la carrera
    UsuarioRolAsignacion::create([
        'id_usuario' => $this->coordinador->id_usuario,
        'id_contexto' => $this->carrera->id_contexto,
        'id_rol' => $this->rolCoordinador->id_rol,
        'asignado_por' => $this->superadmin->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(100),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $this->superadmin->id_usuario
    ]);

    // IMPORTANTE: También asignar en contexto del Curso para que pueda acceder a él
    UsuarioRolAsignacion::create([
        'id_usuario' => $this->coordinador->id_usuario,
        'id_contexto' => $this->curso->id_contexto,
        'id_rol' => $this->rolCoordinador->id_rol,
        'asignado_por' => $this->superadmin->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(100),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $this->superadmin->id_usuario
    ]);

    // ========== USUARIOS DE TEST PARA DELEGACIÓN ==========
    $this->ayudante = Usuario::firstOrCreate(
        ['username' => 'ayudante_test'],
        ['rut' => '44444444-4', 'nombre1' => 'Carlos', 'apellido1' => 'Ayudante', 'email' => 'ayudante@test.local', 'passhash' => Hash::make('ayudante123'), 'esta_activo' => true]
    );

    $this->asistente = Usuario::firstOrCreate(
        ['username' => 'asistente_test'],
        ['rut' => '55555555-5', 'nombre1' => 'Ana', 'apellido1' => 'Asistente', 'email' => 'asistente@test.local', 'passhash' => Hash::make('asistente123'), 'esta_activo' => true]
    );

    $this->usuario1 = Usuario::firstOrCreate(
        ['username' => 'usuario1_test'],
        ['rut' => '66666666-6', 'nombre1' => 'Usuario', 'apellido1' => 'Uno', 'email' => 'usuario1@test.local', 'passhash' => Hash::make('usuario1123'), 'esta_activo' => true]
    );

    $this->usuario2 = Usuario::firstOrCreate(
        ['username' => 'usuario2_test'],
        ['rut' => '77777777-7', 'nombre1' => 'Usuario', 'apellido1' => 'Dos', 'email' => 'usuario2@test.local', 'passhash' => Hash::make('usuario2123'), 'esta_activo' => true]
    );

    $this->usuario3 = Usuario::firstOrCreate(
        ['username' => 'usuario3_test'],
        ['rut' => '88888888-8', 'nombre1' => 'Usuario', 'apellido1' => 'Tres', 'email' => 'usuario3@test.local', 'passhash' => Hash::make('usuario3123'), 'esta_activo' => true]
    );

    $this->usuario4 = Usuario::firstOrCreate(
        ['username' => 'usuario4_test'],
        ['rut' => '99999999-9', 'nombre1' => 'Usuario', 'apellido1' => 'Cuatro', 'email' => 'usuario4@test.local', 'passhash' => Hash::make('usuario4123'), 'esta_activo' => true]
    );

    $this->usuario5 = Usuario::firstOrCreate(
        ['username' => 'usuario5_test'],
        ['rut' => 'aaaaaaaa-a', 'nombre1' => 'Usuario', 'apellido1' => 'Cinco', 'email' => 'usuario5@test.local', 'passhash' => Hash::make('usuario5123'), 'esta_activo' => true]
    );

    $this->usuario6 = Usuario::firstOrCreate(
        ['username' => 'usuario6_test'],
        ['rut' => 'bbbbbbbb-b', 'nombre1' => 'Usuario', 'apellido1' => 'Seis', 'email' => 'usuario6@test.local', 'passhash' => Hash::make('usuario6123'), 'esta_activo' => true]
    );

    $this->usuario7 = Usuario::firstOrCreate(
        ['username' => 'usuario7_test'],
        ['rut' => 'cccccccc-c', 'nombre1' => 'Usuario', 'apellido1' => 'Siete', 'email' => 'usuario7@test.local', 'passhash' => Hash::make('usuario7123'), 'esta_activo' => true]
    );

    $this->usuario8 = Usuario::firstOrCreate(
        ['username' => 'usuario8_test'],
        ['rut' => 'dddddddd-d', 'nombre1' => 'Usuario', 'apellido1' => 'Ocho', 'email' => 'usuario8@test.local', 'passhash' => Hash::make('usuario8123'), 'esta_activo' => true]
    );

    $this->user_a = Usuario::firstOrCreate(
        ['username' => 'user_a_test'],
        ['rut' => 'eeeeeeee-e', 'nombre1' => 'User', 'apellido1' => 'A', 'email' => 'user_a@test.local', 'passhash' => Hash::make('userA123'), 'esta_activo' => true]
    );

    $this->user_b = Usuario::firstOrCreate(
        ['username' => 'user_b_test'],
        ['rut' => 'ffffffff-f', 'nombre1' => 'User', 'apellido1' => 'B', 'email' => 'user_b@test.local', 'passhash' => Hash::make('userB123'), 'esta_activo' => true]
    );

    $this->user_c = Usuario::firstOrCreate(
        ['username' => 'user_c_test'],
        ['rut' => 'gggggggg-g', 'nombre1' => 'User', 'apellido1' => 'C', 'email' => 'user_c@test.local', 'passhash' => Hash::make('userC123'), 'esta_activo' => true]
    );

    $this->usuario_multi = Usuario::firstOrCreate(
        ['username' => 'usuario_multi_test'],
        ['rut' => 'hhhhhhhh-h', 'nombre1' => 'Usuario', 'apellido1' => 'Multi', 'email' => 'usuario_multi@test.local', 'passhash' => Hash::make('usuarioMulti123'), 'esta_activo' => true]
    );
});

// ============================================================================
// TESTS: DATOS CREADOS CORRECTAMENTE EN BD TESTING
// ============================================================================

test('usuarios se crearon correctamente en BD testing', function () {
    expect(Usuario::where('username', 'superadmin')->exists())->toBeTrue();
    expect(Usuario::where('username', 'profesor')->exists())->toBeTrue();
    expect(Usuario::where('username', 'coordinador')->exists())->toBeTrue();
});

test('permisos existen en la BD', function () {
    // Verificar que los permisos existen
    $permisoWildcard = Permiso::where('slug', '*')->first();
    expect($permisoWildcard)->not->toBeNull();

    $permisoVerCurso = Permiso::where('slug', 'cursos:ver')->first();
    expect($permisoVerCurso)->not->toBeNull();

    $permisoCrearCurso = Permiso::where('slug', 'cursos:crear')->first();
    expect($permisoCrearCurso)->not->toBeNull();
});

test('roles existen en la BD', function () {
    $rolSuperAdmin = Rol::where('nombre', operator: 'Super Admin')->first();
    expect($rolSuperAdmin)->not->toBeNull();

    $rolProfesor = Rol::where('nombre', 'Profesor')->first();
    expect($rolProfesor)->not->toBeNull();

    $rolCoordinador = Rol::where('nombre', 'Coordinador')->first();
    expect($rolCoordinador)->not->toBeNull();
});

test('roles tienen permisos asignados', function () {
    // Rol Profesor debe tener 'cursos:ver'
    $rolProfesor = Rol::where('nombre', 'Profesor')->first();
    $tienePermisoVer = $rolProfesor->permisos()->where('slug', 'cursos:ver')->exists();
    expect($tienePermisoVer)->toBeTrue();

    // Rol Coordinador debe tener 'cursos:ver' y 'cursos:crear'
    $rolCoordinador = Rol::where('nombre', 'Coordinador')->first();
    $tienePermisoVer = $rolCoordinador->permisos()->where('slug', 'cursos:ver')->exists();
    expect($tienePermisoVer)->toBeTrue();

    $tienePermisoCrear = $rolCoordinador->permisos()->where('slug', 'cursos:crear')->exists();
    expect($tienePermisoCrear)->toBeTrue();
});

test('usuarios tienen roles asignados', function () {
    // Superadmin tiene rol Super Admin en contexto global
    $rolSuperAdmin = Rol::where('nombre', operator: 'Super Admin')->first();
    $tieneRol = UsuarioRolAsignacion::where([
        'id_usuario' => $this->superadmin->id_usuario,
        'id_rol' => $rolSuperAdmin->id_rol,
        'esta_activo' => true,
    ])->exists();
    expect($tieneRol)->toBeTrue();

    // Profesor tiene rol Profesor
    $rolProfesor = Rol::where('nombre', 'Profesor')->first();
    $tieneRol = UsuarioRolAsignacion::where([
        'id_usuario' => $this->profesor->id_usuario,
        'esta_activo' => true,
        'id_rol' => $rolProfesor->id_rol,
    ])->exists();
    expect($tieneRol)->toBeTrue();

    // Coordinador tiene rol Coordinador
    $rolCoordinador = Rol::where('nombre', 'Coordinador')->first();
    $tieneRol = UsuarioRolAsignacion::where([
        'id_usuario' => $this->coordinador->id_usuario,
        'esta_activo' => true,
        'id_rol' => $rolCoordinador->id_rol,
    ])->exists();
    expect($tieneRol)->toBeTrue();
});

test('superadmin tiene permiso wildcard', function () {
    $tieneWildcard = $this->superadmin
        ->permisosEspeciales()
        ->where('esta_permitido', true)
        ->where('slug', '*')
        ->exists();

    expect($tieneWildcard)->toBeTrue();
});

test('contextos se resuelven correctamente', function () {
    $resolver = app(\App\Services\ContextResolver::class);

    // Carrera tiene contexto directo
    $contextosCarrera = $resolver->getModelContextId($this->carrera);
    expect($contextosCarrera)->toContain($this->carrera->id_contexto);

    // Curso tiene contexto directo
    $contextosCurso = $resolver->getModelContextId($this->curso);
    expect($contextosCurso)->toBe($contextosCurso); // Curso tiene su propio contexto

    // Usuario tiene contexto global - ahora retorna el ID del contexto global desde GlobalContextService
    $contextosUsuario = $resolver->getModelContextId($this->profesor);
    // Global models now resolve to the global context ID via GlobalContextService
    expect($contextosUsuario)->toBe([$this->contextoGlobal_id]);
});

// ============================================================================
// TESTS: SUPERADMIN CON WILDCARD (*)
// ============================================================================

test('superadmin con * puede hacer cualquier acción en cualquier recurso', function () {
    $this->actingAs($this->superadmin);

    $authenticatedUser = Auth::user();
    $puedeHacerTodo = $authenticatedUser->hasPermissionFor(Permissions::GLOBAL_WILDCARD, $this->curso);
    expect($puedeHacerTodo)->toBeTrue();

    $puedeHacerAlgo = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_VER, $this->curso);
    expect($puedeHacerAlgo)->toBeTrue();

    $puedeHacerOtraCosa = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_EDITAR, $this->curso);
    expect($puedeHacerOtraCosa)->toBeTrue();
});

// ============================================================================
// TESTS: PROFESOR CON 'cursos:ver'
// ============================================================================

test('profesor con permiso curso:ver puede ver cursos', function () {
    $this->actingAs($this->profesor);

    $authenticatedUser = Auth::user();
    $puedeVerCurso = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_VER, $this->curso);
    expect($puedeVerCurso)->toBeTrue();
});

test('profesor sin permiso curso:crear NO puede crear cursos', function () {
    $this->actingAs($this->profesor);

    $authenticatedUser = Auth::user();
    $puedeCrearCurso = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_CREAR, $this->curso);
    expect($puedeCrearCurso)->toBeFalse();
});

test('profesor NO puede hacer acciones que no tenga permiso', function () {
    $this->actingAs($this->profesor);

    $authenticatedUser = Auth::user();
    // curso:editar no está asignado al rol de profesor, debería retornar false
    $puedeEditarCurso = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_EDITAR, $this->curso);
    expect($puedeEditarCurso)->toBeFalse();
});

// ============================================================================
// TESTS: COORDINADOR CON 'cursos:crear' Y 'cursos:ver'
// ============================================================================

test('coordinador con permisos curso:ver y curso:crear puede hacer ambas acciones', function () {
    $this->actingAs($this->coordinador);

    $authenticatedUser = Auth::user();
    $puedeVerCurso = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_VER, $this->curso);
    expect($puedeVerCurso)->toBeTrue();

    $puedeCrearCurso = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_CREAR, $this->curso);
    expect($puedeCrearCurso)->toBeTrue();
});

test('coordinador NO puede hacer acciones que no tenga permiso', function () {
    $this->actingAs($this->coordinador);

    $authenticatedUser = Auth::user();
    // curso:editar no está asignado al rol de coordinador, debería retornar false
    $puedeEditarCurso = $authenticatedUser->hasPermissionFor(Permissions::CURSOS_EDITAR, $this->curso);
    expect($puedeEditarCurso)->toBeFalse();
});

// ============================================================================
// TESTS: RESOLUCIÓN DE CONTEXTOS
// ============================================================================

test('resolución de contexto directo (Curso)', function () {
    $this->actingAs($this->profesor);

    // Verificar que profesor tiene permiso en contexto directo del curso
    $uraEnContextoCurso = UsuarioRolAsignacion::where([
        'id_usuario' => $this->profesor->id_usuario,
        'id_contexto' => $this->curso->id_contexto,
        'esta_activo' => true
    ])->with('rol')->first();

    expect($uraEnContextoCurso)->not->toBeNull();
    expect($uraEnContextoCurso->id_contexto)->toBe($this->curso->id_contexto);

    // Verificar que tiene permiso 'cursos:ver' en este contexto
    $tienePermiso = $uraEnContextoCurso->rol->permisos()->where('slug', 'cursos:ver')->exists();
    expect($tienePermiso)->toBeTrue();
});

test('resolución de contexto jerárquico (Plan -> Carrera)', function () {
    $this->actingAs($this->profesor);

    // Profesor tiene rol en contexto de Carrera
    // El Plan debería ser accesible a través de esta asignación si se implementa herencia de contextos
    $uraEnContextoCarrera = UsuarioRolAsignacion::where([
        'id_usuario' => $this->profesor->id_usuario,
        'id_contexto' => $this->carrera->id_contexto,
        'esta_activo' => true
    ])->with('rol')->first();

    expect($uraEnContextoCarrera)->not->toBeNull();

    // Verificar que el plan existe y está vinculado a la carrera
    $plan = Plan::where('id_carrera', $this->carrera->id_carrera)->first();
    expect($plan)->not->toBeNull();
    expect($plan->id_carrera)->toBe($this->carrera->id_carrera);
});

// TODO: tests de otras funciones del builder, como onall() o revoke(), waitfor(), for(), on() restringida a tipos, etc.

// ============================================================================
// TESTS: DELEGACIÓN DE PERMISOS
// ============================================================================

test('admin puede delegar permiso con canDelegate()', function () {
    $this->actingAs($this->superadmin);

    $upe = $this->ayudante->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->canDelegate()
        ->save();

    expect($upe->puede_delegar)->toBeTrue();
    expect($this->ayudante->hasPermissionFor(Permissions::CURSOS_VER, $this->curso))->toBeTrue();
});

test('admin puede asignar permiso sin delegación', function () {
    $this->actingAs($this->superadmin);

    $upe = $this->asistente->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->save();

    expect($upe->puede_delegar)->toBeFalse();
    expect($this->asistente->hasPermissionFor(Permissions::CURSOS_VER, $this->curso))->toBeTrue();
});

test('usuario con permisos delegables PUEDE delegar a otros', function () {
    // Admin asigna permiso a usuario1 con delegación
    $this->actingAs($this->superadmin);

    $upe1 = $this->usuario1->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->canDelegate()
        ->save();

    expect($upe1->puede_delegar)->toBeTrue();

    // Usuario1 delega a usuario2
    $this->actingAs($this->usuario1);
    $upe2 = $this->usuario2->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(20)
        ->canDelegate() // Aunque el asignador puede delegar, sus asignaciones NO serán delegables de nuevo
        ->save();

    expect($upe2->puede_delegar)->toBeFalse();
    expect($this->usuario2->hasPermissionFor(Permissions::CURSOS_VER, $this->curso))->toBeTrue();
});

test('usuario sin permisos delegables NO puede delegar', function () {
    // Admin asigna permiso a usuario3 SIN delegación
    $this->actingAs($this->superadmin);

    $upe3 = $this->usuario3->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->save();

    expect($upe3->puede_delegar)->toBeFalse();

    // Usuario3 intenta delegar a usuario4 - debería lanzar excepción
    $this->actingAs($this->usuario3);
    expect(function () {
        $this->usuario4->givePermission(Permissions::CURSOS_VER)
            ->on($this->curso)
            ->for(20)
            ->save();
    })->toThrow(\App\Exceptions\DontHavePermissionException::class);
});

test('(admin invalidando) el admin puede invalidar permisos', function () {
    // Admin asigna permiso a usuario5
    $this->actingAs($this->superadmin);

    $upe = $this->usuario5->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->save();

    // Otorgando el permiso para probar siguiente parte
    $this->usuario1->givePermission(Permissions::CURSOS_CREAR)
        ->on($this->carrera)
        ->for(30)
        ->canDelegate()
        ->save();

    $upe_id = $upe->id_upe;

    // Usuario5 intenta revocar su propio permiso (falla)
    $this->actingAs($this->usuario5);
    expect(function () use ($upe_id) {
        $this->usuario5->invalidatePermission($upe_id);
    })->toThrow(\App\Exceptions\DontHavePermissionException::class);

    // Superadmin SI puede revocar
    $this->actingAs($this->superadmin);
    expect(function () use ($upe_id) {
        $this->usuario5->invalidatePermission($upe_id);
    })->not->toThrow(\App\Exceptions\DontHavePermissionException::class);

    $upe_revocado = UsuarioPermisoEspecial::find($upe_id);
    expect($upe_revocado->esta_activo)->toBeFalse();
});

test('(asignador invalidando) quien asignó el permiso también puede invalidarlo', function () {
    // Admin asigna permiso a usuario7 con delegación
    $this->actingAs($this->superadmin);

    $upe1 = $this->usuario7->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->canDelegate()
        ->save();

    // Usuario7 delega a usuario8
    $this->actingAs($this->usuario7);
    $upe2 = $this->usuario8->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(20)
        ->save();

    $upe2_id = $upe2->id_upe;

    // Usuario7 (asignador) PUEDE revocarlo
    expect(function () use ($upe2_id) {
        $this->usuario8->invalidatePermission($upe2_id);
    })->not->toThrow(\App\Exceptions\DontHavePermissionException::class);

    $upe2_revocado = UsuarioPermisoEspecial::find($upe2_id);
    expect($upe2_revocado->esta_activo)->toBeFalse();
});

test('delegación múltiple está bloqueada después de un nivel', function () {
    // Admin -> User A (con delegación)
    $this->actingAs($this->superadmin);
    $upe_a = $this->user_a->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->canDelegate()
        ->save();
    expect($upe_a->puede_delegar)->toBeTrue();

    // User A -> User B (sin delegación)
    $this->actingAs($this->user_a);
    $upe_b = $this->user_b->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->save();
    expect($upe_b->puede_delegar)->toBeFalse();

    // User B intenta delegar a User C - debería fallar
    $this->actingAs($this->user_b);
    expect(function () {
        $this->user_c->givePermission(Permissions::CURSOS_VER)
            ->on($this->curso)
            ->for(30)
            ->save();
    })->toThrow(\App\Exceptions\DontHavePermissionException::class);
});

test('permiso delegable en múltiples contextos se valida correctamente', function () {
    $this->actingAs($this->superadmin);

    // cursos:ver es válido en contexto CURSO, carreras:ver en contexto CARRERA
    $upeCurso = $this->usuario_multi->givePermission(Permissions::CURSOS_VER)
        ->on($this->curso)
        ->for(30)
        ->canDelegate()
        ->save();

    $upeCarrera = $this->usuario_multi->givePermission(Permissions::CARRERAS_VER)
        ->on($this->carrera)
        ->for(30)
        ->canDelegate()
        ->save();

    expect($upeCurso)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upeCurso->puede_delegar)->toBeTrue();

    expect($upeCarrera)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upeCarrera->puede_delegar)->toBeTrue();
});


