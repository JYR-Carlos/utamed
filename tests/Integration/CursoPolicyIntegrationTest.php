<?php

/**
 * Integration Test: BaseCursoPolicy + HasBasePolicyMethods
 *
 * Prueba las funciones reales de:
 *   - BaseCursoPolicy: viewAny, view, create, update, delete
 *   - HasBasePolicyMethods: before() (bypass superadmin), customXXX() hooks (default false)
 *
 * Usa BD de testing real, sin RefreshDatabase.
 * Flujo: Gate → CursoPolicy → BaseCursoPolicy → PermissionValidator → BD
 */

use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\Contexto;
use App\Policies\CursoPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

// ============================================================================
// SETUP: crear / limpiar datos de test en BD real
// ============================================================================

beforeEach(function () {

  // ---- Contexto global (necesario para UsuarioPermisoEspecial) ----
  $tipoSystem = TipoContexto::firstOrCreate(
    ['categoria' => 'system', 'tabla_referenciada' => 'GLOBAL']
  );
  $contextoGlobal = DB::transaction(fn() => Contexto::firstOrCreate(
    ['contexto_display' => 'Contexto Global | Solo Permisos Administrativos'],
    ['id_tipo_contexto' => $tipoSystem->id_tipo_contexto]
  ));
  $this->contextoGlobal_id = $contextoGlobal->id_contexto;

  // ---- Usuario sistema (necesario para FK creado_por) ----
  $adminSistema = Usuario::firstOrCreate(
    ['username' => 'admin_sistema'],
    [
      'rut' => '00000000-0',
      'nombre1' => 'Admin',
      'apellido1' => 'Sistema',
      'email' => 'admin@sistema.local',
      'passhash' => Hash::make('admin123'),
      'esta_activo' => true,
    ]
  );
  $this->adminSistemaId = $adminSistema->id_usuario;

  // ---- Limpiar datos de tests anteriores ----
  $testUsernames = ['cp_superadmin', 'cp_manager', 'cp_viewer', 'cp_nobody'];
  $testUserIds = Usuario::whereIn('username', $testUsernames)->pluck('id_usuario');

  if ($testUserIds->isNotEmpty()) {
    UsuarioRolAsignacion::whereIn('id_usuario', $testUserIds)->delete();
    UsuarioPermisoEspecial::whereIn('id_usuario', $testUserIds)->delete();
    Usuario::whereIn('id_usuario', $testUserIds)->delete();
  }

  $testRolNames = ['CP SuperAdmin', 'CP Manager', 'CP Viewer'];
  $testRolIds = Rol::whereIn('nombre', $testRolNames)->pluck('id_rol');
  if ($testRolIds->isNotEmpty()) {
    DB::table('asignacion_rol_permiso')->whereIn('id_rol', $testRolIds)->delete();
    Rol::whereIn('id_rol', $testRolIds)->delete();
  }

  DB::table('curso')->where('nombre', 'CP Test Curso')->delete();
  DB::table('asignacion_plan')
    ->whereIn('id_asignatura', fn($q) => $q->select('id_asignatura')->from('asignatura')->where('cod_asignatura', 'CP-101'))
    ->delete();
  DB::table('asignatura')->where('cod_asignatura', 'CP-101')->delete();
  DB::table('plan')->whereIn('id_carrera', fn($q) => $q->select('id_carrera')->from('carrera')->where('nombre', 'CP Test Carrera'))->delete();
  DB::table('carrera')->where('nombre', 'CP Test Carrera')->delete();
  DB::table('departamento')->where('nombre', 'CP Test Departamento')->delete();
  DB::table('facultad')->where('nombre', 'CP Test Facultad')->delete();

  // ---- Estructura administrativa para crear un Curso con contexto ----
  $facultadId = DB::table('facultad')->insertGetId(
    ['nombre' => 'CP Test Facultad', 'fecha_creacion' => now(), 'fecha_modificacion' => now()],
    'id_facultad'
  );

  $departamentoId = DB::table('departamento')->insertGetId([
    'nombre' => 'CP Test Departamento',
    'id_facultad' => $facultadId,
    'fecha_creacion' => now(),
    'fecha_modificacion' => now(),
  ], 'id_departamento');

  $this->carrera = Carrera::create([
    'nombre' => 'CP Test Carrera',
    'jornada' => 'Diurna',
    'sede' => 'Central',
    'modalidad' => 'Presencial',
    'id_departamento' => $departamentoId,
  ]);
  $this->carrera->refresh(); // trigger genera id_contexto

  $this->plan = Plan::create([
    'id_carrera' => $this->carrera->id_carrera,
    'agno' => 2024,
    'version_plan' => 1,
  ]);

  $asignaturaId = DB::table('asignatura')->insertGetId([
    'cod_asignatura' => 'CP-101',
    'nombre' => 'CP Test Asignatura',
    'creditos_sct' => 4,
    'horas_catedra' => 3,
    'horas_taller' => 0,
    'horas_laboratorio' => 0,
    'horas_dirigidas' => 0,
    'horas_autonomas' => 0,
    'fecha_creacion' => now(),
    'fecha_modificacion' => now(),
  ], 'id_asignatura');

  DB::table('asignacion_plan')->insert([
    'id_asignatura' => $asignaturaId,
    'id_plan' => $this->plan->id_plan,
    'agno_planificado' => 1,
    'semestre_planificado' => 1,
    'fecha_creacion' => now(),
  ]);

  // Curso con id_contexto generado por trigger
  $this->curso = Curso::create([
    'nombre' => 'CP Test Curso',
    'fecha_inicio' => now(),
    'fecha_fin' => now()->addMonths(4),
    'agno_real' => 2024,
    'semestre_real' => 1,
    'estado_interno' => 'Activo',
    'id_plan' => $this->plan->id_plan,
    'id_asignatura' => $asignaturaId,
  ]);
  $this->curso->refresh(); // trigger genera id_contexto

  // ---- Permisos del recurso curso ----
  $permSlugs = ['*', 'curso:ver', 'curso:crear', 'curso:editar', 'curso:eliminar'];
  foreach ($permSlugs as $slug) {
    Permiso::firstOrCreate(['slug' => $slug], ['nombre' => $slug, 'descripcion' => 'Test CP']);
  }

  // ---- Helper: crear rol con permisos y asignarlo a un usuario ----
  $crearUsuarioConRol = function (string $username, string $rut, string $email, string $rolNombre, array $permisosSlug, array $contextIds   // asignar el rol en múltiples contextos
  ): Usuario {
    $user = Usuario::create([
      'username' => $username,
      'rut' => $rut,
      'nombre1' => 'Test',
      'apellido1' => 'User',
      'email' => $email,
      'passhash' => Hash::make('pass'),
      'esta_activo' => true,
    ]);

    $rol = Rol::create(['nombre' => $rolNombre, 'creado_por' => $this->adminSistemaId]);

    foreach ($permisosSlug as $slug) {
      $permiso = Permiso::where('slug', $slug)->first();
      $rol->permisos()->attach($permiso->id_permiso, ['puede_delegar_permisos' => false]);
    }

    foreach ($contextIds as $contextId) {
      UsuarioRolAsignacion::create([
        'id_usuario' => $user->id_usuario,
        'id_contexto' => $contextId,
        'id_rol' => $rol->id_rol,
        'asignado_por' => $this->adminSistemaId,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(10),
        'esta_activo' => true,
        'fue_eliminado' => false,
        'creado_por' => $this->adminSistemaId,
      ]);
    }

    return $user;
  };

  // ---- Usuario 1: SUPERADMIN (permiso wildcard '*' en contexto global) ----
  $this->superadmin = Usuario::create([
    'username' => 'cp_superadmin',
    'rut' => '91111111-1',
    'nombre1' => 'CP',
    'apellido1' => 'SuperAdmin',
    'email' => 'cp_superadmin@test.local',
    'passhash' => Hash::make('pass'),
    'esta_activo' => true,
  ]);
  $permisoWildcard = Permiso::where('slug', '*')->first();
  UsuarioPermisoEspecial::create([
    'id_usuario' => $this->superadmin->id_usuario,
    'id_permiso' => $permisoWildcard->id_permiso,
    'id_contexto' => $this->contextoGlobal_id,
    'esta_permitido' => true,
    'puede_delegar' => true,
    'fecha_fin_planificada' => now()->addYears(100),
    'creado_por' => $this->adminSistemaId,
    'esta_activo' => true,
  ]);

  // ---- Usuario 2: MANAGER (todos los permisos de curso) ----
  // Asignado en contexto global (cubre viewAny y create sin parent)
  // y en contexto del curso (cubre view, update, delete sobre ese modelo).
  $this->manager = $crearUsuarioConRol(
    'cp_manager',
    '92222222-2',
    'cp_manager@test.local',
    'CP Manager',
    ['curso:ver', 'curso:crear', 'curso:editar', 'curso:eliminar'],
    [$this->contextoGlobal_id, $this->curso->id_contexto]
  );

  // ---- Usuario 3: VIEWER (solo curso:ver) ----
  // Asignado en contexto global (cubre viewAny) y en contexto del curso (cubre view).
  $this->viewer = $crearUsuarioConRol(
    'cp_viewer',
    '93333333-3',
    'cp_viewer@test.local',
    'CP Viewer',
    ['curso:ver'],
    [$this->contextoGlobal_id, $this->curso->id_contexto]
  );

  // ---- Usuario 4: NOBODY (sin ningún permiso) ----
  $this->nobody = Usuario::create([
    'username' => 'cp_nobody',
    'rut' => '94444444-4',
    'nombre1' => 'CP',
    'apellido1' => 'Nobody',
    'email' => 'cp_nobody@test.local',
    'passhash' => Hash::make('pass'),
    'esta_activo' => true,
  ]);
});

// ============================================================================
// GRUPO 1: before() — Bypass de superadmin (HasBasePolicyMethods)
// ============================================================================

describe('before() — bypass de superadmin', function () {

  test('superadmin puede ver cualquier curso (view) via before()', function () {
    $this->actingAs($this->superadmin);
    expect($this->superadmin->can('view', $this->curso))->toBeTrue();
  });

  test('superadmin puede ver el listado (viewAny) via before()', function () {
    $this->actingAs($this->superadmin);
    expect($this->superadmin->can('viewAny', Curso::class))->toBeTrue();
  });

  test('superadmin puede crear cursos (create) via before()', function () {
    $this->actingAs($this->superadmin);
    expect($this->superadmin->can('create', Curso::class))->toBeTrue();
  });

  test('superadmin puede editar cualquier curso (update) via before()', function () {
    $this->actingAs($this->superadmin);
    expect($this->superadmin->can('update', $this->curso))->toBeTrue();
  });

  test('superadmin puede eliminar cualquier curso (delete) via before()', function () {
    $this->actingAs($this->superadmin);
    expect($this->superadmin->can('delete', $this->curso))->toBeTrue();
  });

  test('isSuperAdmin() retorna true solo para el usuario con wildcard', function () {
    expect($this->superadmin->isSuperAdmin())->toBeTrue();
    expect($this->manager->isSuperAdmin())->toBeFalse();
    expect($this->nobody->isSuperAdmin())->toBeFalse();
  });

  test('before() retorna null para usuario normal (deja continuar evaluación)', function () {
    $policy = new CursoPolicy();
    // before() debe retornar null para usuarios sin wildcard, no false
    $result = $policy->before($this->viewer, 'view');
    expect($result)->toBeNull();
  });

  test('before() retorna true para superadmin', function () {
    $policy = new CursoPolicy();
    $result = $policy->before($this->superadmin, 'view');
    expect($result)->toBeTrue();
  });
});

// ============================================================================
// GRUPO 2: viewAny — "curso:ver" sin modelo
// ============================================================================

describe('viewAny — permiso curso:ver sin modelo específico', function () {

  test('usuario con curso:ver puede listar cursos', function () {
    $this->actingAs($this->viewer);
    expect($this->viewer->can('viewAny', Curso::class))->toBeTrue();
  });

  test('usuario con todos los permisos puede listar cursos', function () {
    $this->actingAs($this->manager);
    expect($this->manager->can('viewAny', Curso::class))->toBeTrue();
  });

  test('usuario sin permisos NO puede listar cursos', function () {
    $this->actingAs($this->nobody);
    expect($this->nobody->can('viewAny', Curso::class))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 3: view — "curso:ver" sobre modelo con contexto
// ============================================================================

describe('view — permiso curso:ver sobre un curso específico', function () {

  test('viewer con curso:ver puede ver el curso', function () {
    $this->actingAs($this->viewer);
    expect($this->viewer->can('view', $this->curso))->toBeTrue();
  });

  test('manager con curso:ver puede ver el curso', function () {
    $this->actingAs($this->manager);
    expect($this->manager->can('view', $this->curso))->toBeTrue();
  });

  test('nobody sin permisos NO puede ver el curso', function () {
    $this->actingAs($this->nobody);
    expect($this->nobody->can('view', $this->curso))->toBeFalse();
  });

  test('el modelo curso tiene id_contexto (necesario para resolución de contexto)', function () {
    expect($this->curso->id_contexto)->not()->toBeNull();
    // getContextId() retorna array con todos los contextos del modelo
    expect($this->curso->getContextId())->toBeArray();
    expect($this->curso->getContextId())->toContain($this->curso->id_contexto);
  });
});

// ============================================================================
// GRUPO 4: create — "curso:crear" con parent opcional
// ============================================================================

describe('create — permiso curso:crear con contexto de parent', function () {

  test('manager con curso:crear puede crear un nuevo curso', function () {
    $this->actingAs($this->manager);
    // sin parent → id_contexto = null → validación en contexto global
    expect($this->manager->can('create', Curso::class))->toBeTrue();
  });

  test('viewer solo con curso:ver NO puede crear cursos', function () {
    $this->actingAs($this->viewer);
    expect($this->viewer->can('create', Curso::class))->toBeFalse();
  });

  test('nobody NO puede crear cursos', function () {
    $this->actingAs($this->nobody);
    expect($this->nobody->can('create', Curso::class))->toBeFalse();
  });

  test('create con parent pasa el contextId del parent al validador', function () {
    // Llamada directa a la policy para verificar que el parent se usa correctamente
    $policy = new CursoPolicy();

    // El manager tiene curso:crear en el contexto del curso (usamos el curso como parent mock)
    $result = $policy->create($this->manager, $this->curso);
    expect($result)->toBeTrue();

    // Nobody nunca tiene permiso
    $resultNobody = $policy->create($this->nobody, $this->curso);
    expect($resultNobody)->toBeFalse();
  });
});

// ============================================================================
// GRUPO 5: update — "curso:editar"
// ============================================================================

describe('update — permiso curso:editar sobre un curso específico', function () {

  test('manager con curso:editar puede editar el curso', function () {
    $this->actingAs($this->manager);
    expect($this->manager->can('update', $this->curso))->toBeTrue();
  });

  test('viewer solo con curso:ver NO puede editar el curso', function () {
    $this->actingAs($this->viewer);
    expect($this->viewer->can('update', $this->curso))->toBeFalse();
  });

  test('nobody NO puede editar el curso', function () {
    $this->actingAs($this->nobody);
    expect($this->nobody->can('update', $this->curso))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 6: delete — "curso:eliminar"
// ============================================================================

describe('delete — permiso curso:eliminar sobre un curso específico', function () {

  test('manager con curso:eliminar puede eliminar el curso', function () {
    $this->actingAs($this->manager);
    expect($this->manager->can('delete', $this->curso))->toBeTrue();
  });

  test('viewer solo con curso:ver NO puede eliminar el curso', function () {
    $this->actingAs($this->viewer);
    expect($this->viewer->can('delete', $this->curso))->toBeFalse();
  });

  test('nobody NO puede eliminar el curso', function () {
    $this->actingAs($this->nobody);
    expect($this->nobody->can('delete', $this->curso))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 7: customXXX hooks (HasBasePolicyMethods — Patrón 1)
// Los hooks default retornan false; solo se ejecutan si la validación base falla
// ============================================================================

describe('customXXX hooks — default false (Patrón 1)', function () {

  test('customViewAny retorna false por defecto', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'customViewAny');
    $method->setAccessible(true);
    expect($method->invoke(new CursoPolicy(), $this->nobody))->toBeFalse();
  });

  test('customView retorna false por defecto', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'customView');
    $method->setAccessible(true);
    expect($method->invoke(new CursoPolicy(), $this->nobody, $this->curso))->toBeFalse();
  });

  test('customCreate retorna false por defecto', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'customCreate');
    $method->setAccessible(true);
    expect($method->invoke(new CursoPolicy(), $this->nobody))->toBeFalse();
  });

  test('customUpdate retorna false por defecto', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'customUpdate');
    $method->setAccessible(true);
    expect($method->invoke(new CursoPolicy(), $this->nobody, $this->curso))->toBeFalse();
  });

  test('customDelete retorna false por defecto', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'customDelete');
    $method->setAccessible(true);
    expect($method->invoke(new CursoPolicy(), $this->nobody, $this->curso))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 8: buildPermissionSlug y resolveContextId (helpers de utilidad)
// ============================================================================

describe('helpers de utilidad (HasBasePolicyMethods)', function () {

  test('buildPermissionSlug genera el slug correcto', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'buildPermissionSlug');
    $method->setAccessible(true);
    $slug = $method->invoke(new CursoPolicy(), 'curso', 'ver');
    expect($slug)->toBe('curso:ver');
  });

  test('resolveContextId retorna el array de contextos del modelo', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'resolveContextId');
    $method->setAccessible(true);
    $contextIds = $method->invoke(new CursoPolicy(), $this->curso);
    expect($contextIds)->toBeArray();
    expect($contextIds)->toContain($this->curso->id_contexto);
  });

  test('resolveContextId retorna null si el modelo es null', function () {
    $method = new ReflectionMethod(CursoPolicy::class, 'resolveContextId');
    $method->setAccessible(true);
    $contextId = $method->invoke(new CursoPolicy(), null);
    expect($contextId)->toBeNull();
  });
});
