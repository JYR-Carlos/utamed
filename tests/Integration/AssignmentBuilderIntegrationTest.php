<?php

/**
 * Integration Test: PermissionAssignmentBuilder + RoleAssignmentBuilder + AssignsPermissions trait
 *
 * Prueba la API fluida de asignacion de permisos y roles sobre BD real.
 *
 * Cubre:
 *   - PermissionAssignmentBuilder: on(), onAll(), for(), waitFor(), revoke(), canDelegate(), save()
 *   - RoleAssignmentBuilder:       on(), onAll(), for(), waitFor(), save()
 *   - AssignsPermissions:          givePermission(), giveRole(), invalidatePermission(), invalidateRole()
 *   - Auto-save via __destruct
 *   - Flag $saved (no doble persistencia)
 *
 * No usa RefreshDatabase: limpieza manual en beforeEach.
 */

use Carbon\Carbon;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Usuario;
use App\Enums\ContextualModelType;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\Authorization\PermissionAssignmentBuilder;
use App\Services\Authorization\RoleAssignmentBuilder;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

// ============================================================================
// SETUP
// ============================================================================

beforeEach(function () {
  // ---- Seed tipo_contexto (la BD de testing no ejecuta inserts) ----
  TipoContexto::firstOrCreate(
    ['categoria' => 'global'],
    ['tabla_referenciada' => 'GLOBAL']
  );
  TipoContexto::firstOrCreate(
    ['categoria' => 'facultad'],
    ['tabla_referenciada' => 'facultad']
  );
  TipoContexto::firstOrCreate(
    ['categoria' => 'carrera'],
    ['tabla_referenciada' => 'carrera']
  );
  TipoContexto::firstOrCreate(
    ['categoria' => 'departamento'],
    ['tabla_referenciada' => 'departamento']
  );

  // ---- Contexto global (requerido por FK de algunos registros) ----
  $tipoGlobal = TipoContexto::where('categoria', 'global')->first();
  $contextoGlobal = DB::transaction(fn() => Contexto::firstOrCreate(
    ['contexto_display' => 'Contexto Global | Solo Permisos Administrativos'],
    ['id_tipo_contexto' => $tipoGlobal->id_tipo_contexto]
  ));
  $this->contextoGlobal_id = $contextoGlobal->id_contexto;

  // ---- Usuario sistema para FK creado_por ----
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
  $testUsernames = ['ab_actor', 'ab_recipient'];
  $testUserIds = Usuario::withTrashed()->whereIn('username', $testUsernames)->pluck('id_usuario');

  if ($testUserIds->isNotEmpty()) {
    UsuarioPermisoEspecial::whereIn('creado_por', $testUserIds)->delete();
    UsuarioPermisoEspecial::whereIn('id_usuario', $testUserIds)->delete();
    UsuarioRolAsignacion::whereIn('id_usuario', $testUserIds)->delete();
    Usuario::withTrashed()->whereIn('id_usuario', $testUserIds)->forceDelete();
  }

  // Also force-delete stale soft-deleted users with the RUTs we'll use
  Usuario::withTrashed()->whereIn('rut', ['81111111-1', '82222222-2'])->forceDelete();

  $testRolNames = ['AB Test Rol'];
  $testRolIds = Rol::withTrashed()->whereIn('nombre', $testRolNames)->pluck('id_rol');
  if ($testRolIds->isNotEmpty()) {
    UsuarioRolAsignacion::whereIn('id_rol', $testRolIds)->delete();
    Rol::withTrashed()->whereIn('id_rol', $testRolIds)->forceDelete();
  }

  DB::table('carrera')->where('nombre', 'AB Carrera A')->orWhere('nombre', 'AB Carrera B')->delete();
  DB::table('departamento')->where('nombre', 'AB Departamento')->delete();
  DB::table('facultad')->where('nombre', 'AB Facultad')->delete();

  // ---- Crear estructura administrativa ----
  $facultadId = DB::table('facultad')->insertGetId([
    'nombre' => 'AB Facultad',
    'fecha_creacion' => now(),
    'fecha_modificacion' => now(),
  ], 'id_facultad');
  $this->facultad = Facultad::find($facultadId);

  $departamentoId = DB::table('departamento')->insertGetId([
    'nombre' => 'AB Departamento',
    'id_facultad' => $facultadId,
    'fecha_creacion' => now(),
    'fecha_modificacion' => now(),
  ], 'id_departamento');

  // Dos carreras para probar onAll()
  $this->carreraA = Carrera::create([
    'nombre' => 'AB Carrera A',
    'jornada' => 'Diurna',
    'sede' => 'Central',
    'modalidad' => 'Presencial',
    'id_departamento' => $departamentoId,
  ]);
  $this->carreraA->refresh(); // trigger genera id_contexto

  $this->carreraB = Carrera::create([
    'nombre' => 'AB Carrera B',
    'jornada' => 'Vespertina',
    'sede' => 'Norte',
    'modalidad' => 'Semipresencial',
    'id_departamento' => $departamentoId,
  ]);
  $this->carreraB->refresh();

  // ---- Permiso y Rol de prueba ----
  // Usar Permissions enum reales (compatibles con contexto CARRERA)
  $this->permiso = Permissions::CARRERAS_VER;
  $this->permisoOtro = Permissions::CARRERAS_EDITAR;

  // Asegurar que los Permiso records existen en la BD
  $this->permisoModel = Permiso::firstOrCreate(
    ['slug' => Permissions::CARRERAS_VER->value],
    ['nombre' => 'Ver Carreras', 'descripcion' => 'Test AB']
  );
  Permiso::firstOrCreate(
    ['slug' => Permissions::CARRERAS_EDITAR->value],
    ['nombre' => 'Editar Carreras', 'descripcion' => 'Test AB']
  );
  Permiso::firstOrCreate(
    ['slug' => Permissions::FACULTADES_VER->value],
    ['nombre' => 'Ver Facultades', 'descripcion' => 'Test AB']
  );
  $permisoWildcard = Permiso::firstOrCreate(
    ['slug' => '*'],
    ['nombre' => 'Super Admin Access', 'descripcion' => 'Acceso total']
  );

  $this->rol = Rol::create([
    'nombre' => 'AB Test Rol',
    'creado_por' => $this->adminSistemaId,
  ]);

  // ---- Usuarios ----
  $this->actor = Usuario::create([
    'username' => 'ab_actor',
    'rut' => '81111111-1',
    'nombre1' => 'AB',
    'apellido1' => 'Actor',
    'email' => 'ab_actor@test.local',
    'passhash' => Hash::make('pass'),
    'esta_activo' => true,
  ]);

  $this->recipient = Usuario::create([
    'username' => 'ab_recipient',
    'rut' => '82222222-2',
    'nombre1' => 'AB',
    'apellido1' => 'Recipient',
    'email' => 'ab_recipient@test.local',
    'passhash' => Hash::make('pass'),
    'esta_activo' => true,
  ]);

  // Dar permiso wildcard al actor (super admin) para que pueda asignar permisos
  UsuarioPermisoEspecial::create([
    'id_usuario' => $this->actor->id_usuario,
    'id_permiso' => $permisoWildcard->id_permiso,
    'id_contexto' => $this->contextoGlobal_id,
    'esta_permitido' => true,
    'puede_delegar' => true,
    'fecha_inicio_planificada' => now(),
    'fecha_fin_planificada' => now()->addYears(100),
    'creado_por' => $this->adminSistemaId,
    'esta_activo' => true,
    'fue_borrado' => false,
  ]);

  // Autenticar como el actor para todos los tests
  $this->actingAs($this->actor);
});

// ============================================================================
// GRUPO 1: PermissionAssignmentBuilder — on() y campos base
// ============================================================================

describe('PermissionAssignmentBuilder — on() y campos base', function () {

  test('givePermission()->on()->save() crea un UPE con GRANT por defecto', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_upe)->not()->toBeNull();
    expect($upe->id_usuario)->toBe($this->recipient->id_usuario);
    expect($upe->id_permiso)->toBe($this->permisoModel->id_permiso);
    expect($upe->id_contexto)->toBe($this->carreraA->id_contexto);
    expect($upe->esta_permitido)->toBeTrue();
    expect($upe->puede_delegar)->toBeFalse();
    expect($upe->esta_activo)->toBeTrue();
    expect($upe->fue_borrado)->toBeFalse();
    expect($upe->creado_por)->toBe($this->actor->id_usuario);
  });

  test('on() acepta cualquier modelo que implemente HasContext', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::FACULTADES_VER)
      ->on($this->facultad)
      ->save();

    expect($upe->id_contexto)->toBe($this->facultad->id_contexto);
  });

  test('on() acepta un arreglo de recursos y crea un UPE por cada uno', function () {
    $result = $this->recipient
      ->givePermission($this->permiso)
      ->on([$this->carreraA, $this->carreraB])
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result)->toHaveCount(2);
    expect($result->pluck('id_contexto')->all())->toContain($this->carreraA->id_contexto);
    expect($result->pluck('id_contexto')->all())->toContain($this->carreraB->id_contexto);
  });

  test('on() con arreglo y on() individual acumulan sin duplicados', function () {
    $result = $this->recipient
      ->givePermission($this->permiso)
      ->on([$this->carreraA, $this->carreraB])
      ->inGlobalContext()
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result)->toHaveCount(3);
  });

  test('on() con arreglo que contiene duplicados no crea registros duplicados', function () {
    $result = $this->recipient
      ->givePermission($this->permiso)
      ->on([$this->carreraA, $this->carreraA])
      ->save();

    // Misma instancia dos veces → solo 1 UPE
    expect($result)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });

  test('llamadas multiples a on() acumulan contextos y devuelve Collection', function () {
    $result = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->on($this->carreraB)
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result)->toHaveCount(2);

    $contextIds = $result->pluck('id_contexto')->sort()->values()->all();
    expect($contextIds)->toContain($this->carreraA->id_contexto);
    expect($contextIds)->toContain($this->carreraB->id_contexto);
  });

  test('save() lanza InvalidArgumentException si no se especificó contexto', function () {
    expect(
      fn() => $this->recipient
        ->givePermission($this->permiso)
        ->save()
    )->toThrow(\InvalidArgumentException::class);
  });
});

// ============================================================================
// GRUPO 2: PermissionAssignmentBuilder — revoke() y canDelegate()
// ============================================================================

describe('PermissionAssignmentBuilder — revoke() y canDelegate()', function () {

  test('revoke() crea UPE con esta_permitido = false (DENY)', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->revoke()
      ->save();

    expect($upe->esta_permitido)->toBeFalse();
  });

  test('canDelegate() crea UPE con puede_delegar = true', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->canDelegate()
      ->save();

    expect($upe->puede_delegar)->toBeTrue();
  });

  test('revoke() y canDelegate() son independientes y acumulables', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->revoke()
      ->canDelegate()
      ->save();

    expect($upe->esta_permitido)->toBeFalse();
    expect($upe->puede_delegar)->toBeTrue();
  });
});

// ============================================================================
// GRUPO 3: PermissionAssignmentBuilder — for() y waitFor()
// ============================================================================

describe('PermissionAssignmentBuilder — for() y waitFor()', function () {

  test('for(30) establece fecha_fin 30 dias despues del inicio', function () {
    $before = Carbon::now();

    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->for(30)
      ->save();

    $after = Carbon::now();

    expect($upe->fecha_fin_planificada)->not()->toBeNull();

    $fin = Carbon::parse($upe->fecha_fin_planificada);
    // Debe estar entre (now+30) con margen de 1 segundo
    expect($fin->betweenIncluded($before->copy()->addDays(30), $after->copy()->addDays(30)))->toBeTrue();
  });

  test('sin for() la fecha_fin_planificada tiene un valor por defecto lejano (100 anios)', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->save();

    $fin = Carbon::parse($upe->fecha_fin_planificada);
    // Debe estar ~100 anios en el futuro
    expect($fin->year)->toBeGreaterThanOrEqual(Carbon::now()->addYears(99)->year);
  });

  test('waitFor(5) difiere el inicio 5 dias', function () {
    $before = Carbon::now();

    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->waitFor(5)
      ->save();

    $after = Carbon::now();

    $inicio = Carbon::parse($upe->fecha_inicio_planificada);
    expect($inicio->betweenIncluded($before->copy()->addDays(5), $after->copy()->addDays(5)))->toBeTrue();
  });

  test('for(30)->waitFor(5) preserva la duracion de 30 dias desde el inicio diferido', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->for(30)
      ->waitFor(5)
      ->save();

    $inicio = Carbon::parse($upe->fecha_inicio_planificada);
    $fin = Carbon::parse($upe->fecha_fin_planificada);

    // La diferencia entre inicio y fin debe ser ~30 dias
    expect((int) round($inicio->diffInDays($fin)))->toBe(30);
    // El inicio debe ser ~5 dias desde ahora
    expect((int) round(Carbon::now()->diffInDays($inicio)))->toBe(5);
  });

  test('waitFor()->for() produce el mismo resultado que for()->waitFor()', function () {
    // Primero for() luego waitFor()
    $upe1 = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->for(30)
      ->waitFor(5)
      ->save();

    // Primero waitFor() luego for()
    $upe2 = $this->recipient
      ->givePermission($this->permisoOtro)
      ->on($this->carreraB)
      ->waitFor(5)
      ->for(30)
      ->save();

    $inicio1 = Carbon::parse($upe1->fecha_inicio_planificada);
    $inicio2 = Carbon::parse($upe2->fecha_inicio_planificada);
    $fin1 = Carbon::parse($upe1->fecha_fin_planificada);
    $fin2 = Carbon::parse($upe2->fecha_fin_planificada);

    // Ambos deben tener duracion de 30 dias desde un inicio ~5 dias adelante
    expect((int) round($inicio1->diffInDays($fin1)))->toBe(30);
    expect((int) round($inicio2->diffInDays($fin2)))->toBe(30);
    expect((int) round(Carbon::now()->diffInDays($inicio1)))->toBe(5);
    expect((int) round(Carbon::now()->diffInDays($inicio2)))->toBe(5);
  });
});

// ============================================================================
// GRUPO 4: PermissionAssignmentBuilder — onAll()
// ============================================================================

describe('PermissionAssignmentBuilder — onAll()', function () {

  test('onAll(ContextualModelType::CARRERA) crea un UPE por cada contexto del tipo Carrera', function () {
    $tipoId = TipoContexto::where('categoria', 'carrera')->value('id_tipo_contexto');
    $totalCarrerasAB = $tipoId ? Contexto::where('id_tipo_contexto', $tipoId)->count() : 0;

    // Garantizamos al menos 2 (las que creamos en beforeEach)
    expect($totalCarrerasAB)->toBeGreaterThanOrEqual(2);

    $result = $this->recipient
      ->givePermission($this->permiso)
      ->onAll(ContextualModelType::CARRERA)
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->count())->toBe($totalCarrerasAB);

    // Todos los registros pertenecen al recipient
    expect($result->pluck('id_usuario')->unique()->all())->toBe([$this->recipient->id_usuario]);
  });

  test('onAll() y on() son acumulables', function () {
    $tipoId = TipoContexto::where('categoria', 'carrera')->value('id_tipo_contexto');
    $totalCarreras = $tipoId ? Contexto::where('id_tipo_contexto', $tipoId)->count() : 0;
    $expectedTotal = $totalCarreras + 1; // + el contexto global

    $result = $this->recipient
      ->givePermission($this->permiso)
      ->onAll(ContextualModelType::CARRERA)
      ->inGlobalContext()
      ->save();

    expect($result->count())->toBe($expectedTotal);
  });
});

// ============================================================================
// GRUPO 5: PermissionAssignmentBuilder — auto-save y flag $saved
// ============================================================================

describe('PermissionAssignmentBuilder — auto-save y doble persistencia', function () {

  test('auto-save via destruct persiste el UPE al salir del scope', function () {
    $recipientId = $this->recipient->id_usuario;

    // unset() fuerza __destruct inmediatamente
    $builder = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->for(30);

    $countBefore = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();
    unset($builder); // dispara __destruct → save()
    $countAfter = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();

    expect($countAfter)->toBe($countBefore + 1);
  });

  test('cadena sin asignar persiste automaticamente al final del statement', function () {
    $recipientId = $this->recipient->id_usuario;
    $countBefore = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();

    // No se asigna a variable → se destruye al final del punto y coma
    $this->recipient->givePermission($this->permiso)->on($this->carreraA);

    $countAfter = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();
    expect($countAfter)->toBe($countBefore + 1);
  });

  test('save() explicito mas __destruct no duplica el registro', function () {
    $recipientId = $this->recipient->id_usuario;

    $builder = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA);

    $builder->save();
    $countAfterSave = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();

    unset($builder); // __destruct debe detectar $saved = true y no persistir de nuevo
    $countAfterDestruct = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();

    expect($countAfterDestruct)->toBe($countAfterSave);
  });

  test('builder sin contexto no persiste nada en __destruct', function () {
    $recipientId = $this->recipient->id_usuario;
    $countBefore = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();

    $builder = $this->recipient->givePermission($this->permiso);
    unset($builder); // sin contexto → __destruct no hace nada

    $countAfter = UsuarioPermisoEspecial::where('id_usuario', $recipientId)->count();
    expect($countAfter)->toBe($countBefore);
  });
});

// ============================================================================
// GRUPO 6: RoleAssignmentBuilder — on() y campos base
// ============================================================================

describe('RoleAssignmentBuilder — on() y campos base', function () {

  test('giveRole()->on()->save() crea un URA con los campos correctos', function () {
    $ura = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->save();

    expect($ura)->toBeInstanceOf(UsuarioRolAsignacion::class);
    expect($ura->id_ura)->not()->toBeNull();
    expect($ura->id_usuario)->toBe($this->recipient->id_usuario);
    expect($ura->id_rol)->toBe($this->rol->id_rol);
    expect($ura->id_contexto)->toBe($this->carreraA->id_contexto);
    expect($ura->asignado_por)->toBe($this->actor->id_usuario);
    expect($ura->creado_por)->toBe($this->actor->id_usuario);
    expect($ura->esta_activo)->toBeTrue();
    expect($ura->fue_eliminado)->toBeFalse();
    expect($ura->fecha_fin_real)->toBeNull();
  });

  test('llamadas multiples a on() crean multiples URA', function () {
    $result = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->on($this->carreraB)
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result)->toHaveCount(2);
  });

  test('on() acepta un arreglo de recursos y crea un URA por cada uno', function () {
    $result = $this->recipient
      ->giveRole($this->rol)
      ->on([$this->carreraA, $this->carreraB])
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result)->toHaveCount(2);
    expect($result->pluck('id_contexto')->all())->toContain($this->carreraA->id_contexto);
    expect($result->pluck('id_contexto')->all())->toContain($this->carreraB->id_contexto);
  });

  test('on() con arreglo produce el mismo resultado que llamadas encadenadas en URA', function () {
    $fromArray = $this->recipient
      ->giveRole($this->rol)
      ->on([$this->carreraA, $this->carreraB])
      ->save();

    $contextIds = $fromArray->pluck('id_contexto')->sort()->values()->all();
    expect($contextIds)->toContain($this->carreraA->id_contexto);
    expect($contextIds)->toContain($this->carreraB->id_contexto);
    expect($fromArray)->toHaveCount(2);
  });

  test('save() lanza InvalidArgumentException si no se especificó contexto', function () {
    expect(
      fn() => $this->recipient
        ->giveRole($this->rol)
        ->save()
    )->toThrow(\InvalidArgumentException::class);
  });
});

// ============================================================================
// GRUPO 7: RoleAssignmentBuilder — for() y waitFor()
// ============================================================================

describe('RoleAssignmentBuilder — for() y waitFor()', function () {

  test('for(60) establece fecha_fin 60 dias despues del inicio', function () {
    $before = Carbon::now();

    $ura = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->for(60)
      ->save();

    $after = Carbon::now();

    $fin = Carbon::parse($ura->fecha_fin_planificada);
    expect($fin->betweenIncluded($before->copy()->addDays(60), $after->copy()->addDays(60)))->toBeTrue();
  });

  test('for(30)->waitFor(7) mantiene duracion y difiere el inicio', function () {
    $ura = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->for(30)
      ->waitFor(7)
      ->save();

    $inicio = Carbon::parse($ura->fecha_inicio_planificada);
    $fin = Carbon::parse($ura->fecha_fin_planificada);

    expect((int) round($inicio->diffInDays($fin)))->toBe(30);
    expect((int) round(Carbon::now()->diffInDays($inicio)))->toBe(7);
  });

  test('auto-save del RoleAssignmentBuilder via __destruct', function () {
    $recipientId = $this->recipient->id_usuario;
    $countBefore = UsuarioRolAsignacion::where('id_usuario', $recipientId)->count();

    $this->recipient->giveRole($this->rol)->on($this->carreraA);

    $countAfter = UsuarioRolAsignacion::where('id_usuario', $recipientId)->count();
    expect($countAfter)->toBe($countBefore + 1);
  });
});

// ============================================================================
// GRUPO 8: RoleAssignmentBuilder — onAll()
// ============================================================================

describe('RoleAssignmentBuilder — onAll()', function () {

  test('onAll(ContextualModelType::CARRERA) crea un URA por cada contexto del tipo Carrera', function () {
    $tipoId = TipoContexto::where('categoria', 'carrera')->value('id_tipo_contexto');
    $totalCarreras = $tipoId ? Contexto::where('id_tipo_contexto', $tipoId)->count() : 0;

    $result = $this->recipient
      ->giveRole($this->rol)
      ->onAll(ContextualModelType::CARRERA)
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->count())->toBe($totalCarreras);
  });
});

// ============================================================================
// GRUPO 9: AssignsPermissions — invalidatePermission() y invalidateRole()
// ============================================================================

describe('AssignsPermissions — invalidatePermission() e invalidateRole()', function () {

  test('invalidatePermission() cierra el UPE: fecha_fin_real=now, esta_activo=false', function () {
    // Crear UPE de prueba
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->save();

    expect($upe->esta_activo)->toBeTrue();
    expect($upe->fecha_fin_real)->toBeNull();

    $before = Carbon::now()->startOfSecond();
    $this->recipient->invalidatePermission($upe->id_upe, $this->actor);
    $after = Carbon::now()->addSecond();

    $upe->refresh();

    expect($upe->esta_activo)->toBeFalse();
    $fin = Carbon::parse($upe->fecha_fin_real);
    expect($fin->betweenIncluded($before, $after))->toBeTrue();
    expect($upe->eliminado_por)->toBe($this->actor->id_usuario);
  });

  test('invalidateRole() cierra el URA: fecha_fin_real=now, esta_activo=false', function () {
    $ura = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->save();

    expect($ura->esta_activo)->toBeTrue();
    expect($ura->fecha_fin_real)->toBeNull();

    $before = Carbon::now()->startOfSecond();
    $this->recipient->invalidateRole($ura->id_ura, $this->actor);
    $after = Carbon::now()->addSecond();

    $ura->refresh();

    expect($ura->esta_activo)->toBeFalse();
    $fin = Carbon::parse($ura->fecha_fin_real);
    expect($fin->betweenIncluded($before, $after))->toBeTrue();
    expect($ura->eliminado_por)->toBe($this->actor->id_usuario);
  });

  test('invalidatePermission() lanza ModelNotFoundException para ID inexistente', function () {
    expect(fn() => $this->recipient->invalidatePermission(999999999, $this->actor))
      ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
  });

  test('invalidateRole() lanza ModelNotFoundException para ID inexistente', function () {
    expect(fn() => $this->recipient->invalidateRole(999999999, $this->actor))
      ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
  });
});

// ============================================================================
// GRUPO 10: givePermission() y giveRole() — actor por defecto (Auth::user())
// ============================================================================

describe('AssignsPermissions — actor por defecto via Auth::user()', function () {

  test('givePermission() sin actor usa Auth::user() como actor', function () {
    $this->actingAs($this->actor);

    $upe = $this->recipient
      ->givePermission($this->permiso)   // sin $actor explicito
      ->on($this->carreraA)
      ->save();

    expect($upe->creado_por)->toBe($this->actor->id_usuario);
  });

  test('giveRole() sin actor usa Auth::user() como actor', function () {
    $this->actingAs($this->actor);

    $ura = $this->recipient
      ->giveRole($this->rol)  // sin $actor explicito
      ->on($this->carreraA)
      ->save();

    expect($ura->creado_por)->toBe($this->actor->id_usuario);
  });

  test('invalidatePermission() sin actor usa Auth::user()', function () {
    $upe = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->save();

    $this->actingAs($this->actor);
    $this->recipient->invalidatePermission($upe->id_upe); // sin $actor explicito

    $upe->refresh();
    expect($upe->eliminado_por)->toBe($this->actor->id_usuario);
  });

  test('invalidateRole() sin actor usa Auth::user()', function () {
    $ura = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->save();

    $this->actingAs($this->actor);
    $this->recipient->invalidateRole($ura->id_ura); // sin $actor explicito

    $ura->refresh();
    expect($ura->eliminado_por)->toBe($this->actor->id_usuario);
  });
});

// ============================================================================
// GRUPO 11: RoleAssignmentBuilder devuelto por giveRole() es el tipo correcto
// ============================================================================

describe('Tipos de retorno de los builders', function () {

  test('givePermission() devuelve una instancia de PermissionAssignmentBuilder', function () {
    $builder = $this->recipient->givePermission($this->permiso);
    expect($builder)->toBeInstanceOf(PermissionAssignmentBuilder::class);
    // destruir sin guardar (sin contexto)
    $builder->__destruct();
  });

  test('giveRole() devuelve una instancia de RoleAssignmentBuilder', function () {
    $builder = $this->recipient->giveRole($this->rol);
    expect($builder)->toBeInstanceOf(RoleAssignmentBuilder::class);
    unset($builder);
  });

  test('save() con un solo contexto devuelve instancia directa del modelo UPE', function () {
    $result = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->save();

    expect($result)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });

  test('save() con varios contextos devuelve Collection de modelos UPE', function () {
    $result = $this->recipient
      ->givePermission($this->permiso)
      ->on($this->carreraA)
      ->on($this->carreraB)
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    $result->each(fn($item) => expect($item)->toBeInstanceOf(UsuarioPermisoEspecial::class));
  });

  test('save() con un solo contexto devuelve instancia directa del modelo URA', function () {
    $result = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->save();

    expect($result)->toBeInstanceOf(UsuarioRolAsignacion::class);
  });

  test('save() con varios contextos devuelve Collection de modelos URA', function () {
    $result = $this->recipient
      ->giveRole($this->rol)
      ->on($this->carreraA)
      ->on($this->carreraB)
      ->save();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    $result->each(fn($item) => expect($item)->toBeInstanceOf(UsuarioRolAsignacion::class));
  });
});
