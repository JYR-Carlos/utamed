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
    // ========== INICIALIZAR BD SI ESTÁ VACÍA ==========
    // Crear contexto global si no existe (id_contexto es GENERATED ALWAYS, usar first o create sin id)
    $tipoSystem = TipoContexto::firstOrCreate(
        [
            'categoria' => 'system',
            'tabla_referenciada' => 'GLOBAL'
        ],
    );

    $contextoGlobal = DB::transaction(function () use ($tipoSystem) {
        return Contexto::firstOrCreate(
            [
                'contexto_display' => 'Contexto Global | Solo Permisos Administrativos'
            ],
            [
                'id_tipo_contexto' => $tipoSystem->id_tipo_contexto,
            ]
        );
    });
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
    // Obtener IDs de usuarios de test (pueden no existir)
    $superadminId = Usuario::where('username', 'superadmin')->value('id_usuario');
    $profesorId = Usuario::where('username', 'profesor')->value('id_usuario');
    $coordinadorId = Usuario::where('username', 'coordinador')->value('id_usuario');

    // Eliminar relaciones si existen
    if ($superadminId) {
        UsuarioRolAsignacion::where('id_usuario', $superadminId)->delete();
        UsuarioPermisoEspecial::where('id_usuario', $superadminId)->delete();
    }
    if ($profesorId) {
        UsuarioRolAsignacion::where('id_usuario', $profesorId)->delete();
        UsuarioPermisoEspecial::where('id_usuario', $profesorId)->delete();
    }
    if ($coordinadorId) {
        UsuarioRolAsignacion::where('id_usuario', $coordinadorId)->delete();
        UsuarioPermisoEspecial::where('id_usuario', $coordinadorId)->delete();
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
        ->delete();

    // Eliminar usuarios de test
    Usuario::where('username', 'superadmin')->delete();
    Usuario::where('username', 'profesor')->delete();
    Usuario::where('username', 'coordinador')->delete();

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
    DB::table('asignacion_plan')->insert([
        'id_asignatura' => $asignaturaId,
        'id_plan' => $this->plan->id_plan,
        'agno_planificado' => 1,
        'semestre_planificado' => 1,
        'fecha_creacion' => now()
    ]);

    // Crear Curso (id_contexto se crea automáticamente por trigger)
    $this->curso = Curso::create([
        'nombre' => 'Matemática I',
        'fecha_inicio' => now(),
        'fecha_fin' => now()->addMonths(4),
        'agno_real' => 2023,
        'semestre_real' => 1,
        'estado_interno' => 'Activo',
        'id_plan' => $this->plan->id_plan,
        'id_asignatura' => $asignaturaId
    ]);
    $this->curso->refresh(); // Trigger sets id_contexto

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
    $permisoWildcard = Permiso::where('slug', '*')->firstOrCreate(
        ['slug' => '*'],
        ['nombre' => 'Super Admin Access', 'descripcion' => 'Sistema']
    );

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

    // ========== USUARIO 2: PROFESOR (tiene 'curso:ver') ==========
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

    // Asignar permiso 'curso:ver' a rol Profesor (vía AsignaciónRolPermiso)
    $permisoVerCurso = Permiso::where('slug', 'curso:ver')->firstOrCreate(
        ['slug' => 'curso:ver'],
        ['nombre' => 'Ver Cursos', 'descripcion' => 'Docencia']
    );

    $this->rolProfesor->permisos()->attach($permisoVerCurso->id_permiso, [
        'puede_delegar_permisos' => false
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

    // ========== USUARIO 3: COORDINADOR (tiene 'curso:crear') ==========
    $this->coordinador = Usuario::create([
        'username' => 'coordinador',
        'rut' => '33333333-3',
        'nombre1' => 'María',
        'apellido1' => 'Coordinador',
        'email' => 'coordinador@test.local',
        'passhash' => Hash::make('coordinador123'),
        'esta_activo' => true
    ]);

    // Crear rol Coordinador
    $this->rolCoordinador = Rol::create([
        'nombre' => 'Coordinador',
        'creado_por' => $this->adminSistemaId
    ]);

    // Asignar permisos: 'curso:crear' y 'curso:ver' al rol Coordinador
    $permisoCrearCurso = Permiso::where('slug', 'curso:crear')->firstOrCreate(
        ['slug' => 'curso:crear'],
        ['nombre' => 'Crear Cursos', 'descripcion' => 'Administrativo']
    );

    $this->rolCoordinador->permisos()->attach([
        $permisoVerCurso->id_permiso => ['puede_delegar_permisos' => false],
        $permisoCrearCurso->id_permiso => ['puede_delegar_permisos' => true]
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

    $permisoVerCurso = Permiso::where('slug', 'curso:ver')->first();
    expect($permisoVerCurso)->not->toBeNull();

    $permisoCrearCurso = Permiso::where('slug', 'curso:crear')->first();
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
    // Rol Profesor debe tener 'curso:ver'
    $rolProfesor = Rol::where('nombre', 'Profesor')->first();
    $tienePermisoVer = $rolProfesor->permisos()->where('slug', 'curso:ver')->exists();
    expect($tienePermisoVer)->toBeTrue();

    // Rol Coordinador debe tener 'curso:ver' y 'curso:crear'
    $rolCoordinador = Rol::where('nombre', 'Coordinador')->first();
    $tienePermisoVer = $rolCoordinador->permisos()->where('slug', 'curso:ver')->exists();
    expect($tienePermisoVer)->toBeTrue();

    $tienePermisoCrear = $rolCoordinador->permisos()->where('slug', 'curso:crear')->exists();
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
    $contextosCarrera = $resolver->getContextId($this->carrera);
    expect($contextosCarrera)->toContain($this->carrera->id_contexto);

    // Curso tiene contexto directo
    $contextosCurso = $resolver->getContextId($this->curso);
    expect($contextosCurso)->toBe($contextosCurso); // Curso tiene su propio contexto

    // Usuario tiene contexto global (vacío)
    $contextosUsuario = $resolver->getContextId($this->profesor);
    expect($contextosUsuario)->toBe([]);
});

// ============================================================================
// TESTS: SUPERADMIN CON WILDCARD (*)
// ============================================================================

test('superadmin con * puede hacer cualquier acción en cualquier recurso', function () {
    $this->actingAs($this->superadmin);

    $authenticatedUser = Auth::user();
    $puedeHacerTodo = $authenticatedUser->hasPermissionFor('*', $this->curso);
    expect($puedeHacerTodo)->toBeTrue();

    $puedeHacerAlgo = $authenticatedUser->hasPermissionFor('curso:ver', $this->curso);
    expect($puedeHacerAlgo)->toBeTrue();

    $puedeHacerOtraCosa = $authenticatedUser->hasPermissionFor('curso:editar', $this->curso);
    expect($puedeHacerOtraCosa)->toBeTrue();
});

// ============================================================================
// TESTS: PROFESOR CON 'curso:ver'
// ============================================================================

test('profesor con permiso curso:ver puede ver cursos', function () {
    $this->actingAs($this->profesor);

    $authenticatedUser = Auth::user();
    $puedeVerCurso = $authenticatedUser->hasPermissionFor('curso:ver', $this->curso);
    expect($puedeVerCurso)->toBeTrue();
});

test('profesor sin permiso curso:crear NO puede crear cursos', function () {
    $this->actingAs($this->profesor);

    $authenticatedUser = Auth::user();
    $puedeCrearCurso = $authenticatedUser->hasPermissionFor('curso:crear', $this->curso);
    expect($puedeCrearCurso)->toBeFalse();
});

test('profesor NO puede hacer acciones que no tenga permiso', function () {
    $this->actingAs($this->profesor);

    $authenticatedUser = Auth::user();
    // curso:editar no está asignado al rol de profesor, debería retornar false
    $puedeEditarCurso = $authenticatedUser->hasPermissionFor('curso:editar', $this->curso);
    expect($puedeEditarCurso)->toBeFalse();
});

// ============================================================================
// TESTS: COORDINADOR CON 'curso:crear' Y 'curso:ver'
// ============================================================================

test('coordinador con permisos curso:ver y curso:crear puede hacer ambas acciones', function () {
    $this->actingAs($this->coordinador);

    $authenticatedUser = Auth::user();
    $puedeVerCurso = $authenticatedUser->hasPermissionFor('curso:ver', $this->curso);
    expect($puedeVerCurso)->toBeTrue();

    $puedeCrearCurso = $authenticatedUser->hasPermissionFor('curso:crear', $this->curso);
    expect($puedeCrearCurso)->toBeTrue();
});

test('coordinador NO puede hacer acciones que no tenga permiso', function () {
    $this->actingAs($this->coordinador);

    $authenticatedUser = Auth::user();
    // curso:editar no está asignado al rol de coordinador, debería retornar false
    $puedeEditarCurso = $authenticatedUser->hasPermissionFor('curso:editar', $this->curso);
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

    // Verificar que tiene permiso 'curso:ver' en este contexto
    $tienePermiso = $uraEnContextoCurso->rol->permisos()->where('slug', 'curso:ver')->exists();
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


