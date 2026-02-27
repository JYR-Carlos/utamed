<?php

/**
 * Integration Test: Context Validation en PermissionAssignmentBuilder + PermissionContextConstraints
 *
 * Prueba la validación de compatibilidad permiso↔contexto en dos capas:
 *   - OPCIÓN 2 (Fail-fast): Validación temprana en on(), onAll(), inContext()
 *   - OPCIÓN 1 (Pre-persist): Validación en save() via validateContextCompatibility()
 *
 * También cubre PermissionContextConstraints:
 *   - validContextTypesFor()
 *   - isValidAssignment()
 *   - invalidAssignmentMessage()
 *   - getInvalidTypes()
 *   - areAllTypesValid()
 *   - diagnoseAssignment()
 *
 * No usa RefreshDatabase: limpieza manual en beforeEach.
 */

use Carbon\Carbon;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\TipoContexto;
use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Enums\ContextualModelType;
use App\Support\Permissions;
use App\Services\Authorization\PermissionAssignmentBuilder;
use App\Services\Authorization\PermissionContextConstraints;
use App\Services\Authorization\GlobalContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

// ============================================================================
// SETUP
// ============================================================================

beforeEach(function () {
  // ---- Seed tipo_contexto ----
  TipoContexto::firstOrCreate(
    ['categoria' => 'system'],
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
  TipoContexto::firstOrCreate(
    ['categoria' => 'curso'],
    ['tabla_referenciada' => 'curso']
  );

  // ---- Contexto global ----
  $tipoSystem = TipoContexto::where('categoria', 'system')->first();
  $contextoGlobal = DB::transaction(fn() => Contexto::firstOrCreate(
    ['contexto_display' => 'Contexto Global | Solo Permisos Administrativos'],
    ['id_tipo_contexto' => $tipoSystem->id_tipo_contexto]
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

  // ---- Limpiar datos de tests anteriores (con forceDelete para SoftDeletes) ----
  $testUsernames = ['cv_actor', 'cv_recipient'];
  $testUserIds = Usuario::withTrashed()->whereIn('username', $testUsernames)->pluck('id_usuario');

  if ($testUserIds->isNotEmpty()) {
    UsuarioPermisoEspecial::whereIn('creado_por', $testUserIds)->delete();
    UsuarioPermisoEspecial::whereIn('id_usuario', $testUserIds)->delete();
    UsuarioRolAsignacion::whereIn('id_usuario', $testUserIds)->delete();
    Usuario::withTrashed()->whereIn('id_usuario', $testUserIds)->forceDelete();
  }

  // Limpiar estructura de test previa
  DB::table('carrera')->where('nombre', 'LIKE', 'CV %')->delete();
  DB::table('departamento')->where('nombre', 'LIKE', 'CV %')->delete();
  DB::table('facultad')->where('nombre', 'LIKE', 'CV %')->delete();

  // ---- Crear estructura administrativa ----
  $facultadId = DB::table('facultad')->insertGetId([
    'nombre' => 'CV Facultad Test',
    'fecha_creacion' => now(),
    'fecha_modificacion' => now(),
  ], 'id_facultad');
  $this->facultad = Facultad::find($facultadId);

  $departamentoId = DB::table('departamento')->insertGetId([
    'nombre' => 'CV Departamento Test',
    'id_facultad' => $facultadId,
    'fecha_creacion' => now(),
    'fecha_modificacion' => now(),
  ], 'id_departamento');
  $this->departamento = Departamento::find($departamentoId);

  $this->carrera = Carrera::create([
    'nombre' => 'CV Carrera Test',
    'jornada' => 'Diurna',
    'sede' => 'Central',
    'modalidad' => 'Presencial',
    'id_departamento' => $departamentoId,
  ]);
  $this->carrera->refresh();

  // ---- Usuarios ----
  // Actor con permiso wildcard (super admin) para poder asignar permisos
  $this->actor = Usuario::create([
    'username' => 'cv_actor',
    'rut' => '91111111-1',
    'nombre1' => 'CV',
    'apellido1' => 'Actor',
    'email' => 'cv_actor@test.local',
    'passhash' => Hash::make('pass'),
    'esta_activo' => true,
  ]);

  $this->recipient = Usuario::create([
    'username' => 'cv_recipient',
    'rut' => '92222222-2',
    'nombre1' => 'CV',
    'apellido1' => 'Recipient',
    'email' => 'cv_recipient@test.local',
    'passhash' => Hash::make('pass'),
    'esta_activo' => true,
  ]);

  // Dar permiso wildcard al actor (super admin) para bypasear validateActorAuthorization
  $permisoWildcard = Permiso::firstOrCreate(
    ['slug' => '*'],
    ['nombre' => 'Super Admin Access', 'descripcion' => 'Acceso total']
  );

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

  // Asegurar permisos reales existen en BD
  foreach ([
    'carreras:ver',
    'carreras:editar',
    'carreras:crear',
    'facultades:ver',
    'facultades:editar',
    'cursos:ver',
    'cursos:editar',
    'cursos:crear',
    'usuarios:ver',
    'usuarios:editar',
    'usuarios:crear',
    'departamentos:ver',
    'departamentos:editar',
    'carreras:*',
  ] as $slug) {
    Permiso::firstOrCreate(
      ['slug' => $slug],
      ['nombre' => ucfirst(str_replace([':', '/'], [' - ', ' > '], $slug)), 'descripcion' => "Permiso: {$slug}"]
    );
  }

  // Autenticar como actor
  $this->actingAs($this->actor);
});

// ============================================================================
// GRUPO 1: PermissionContextConstraints — validContextTypesFor()
// ============================================================================

describe('PermissionContextConstraints — validContextTypesFor()', function () {

  test('retorna tipos válidos para permisos de carreras (GLOBAL + CARRERA)', function () {
    $types = PermissionContextConstraints::validContextTypesFor('carreras:ver');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CARRERA');
    expect($types)->not->toContain('CURSO');
    expect($types)->not->toContain('FACULTAD');
  });

  test('retorna tipos válidos para permisos de facultades (GLOBAL + FACULTAD)', function () {
    $types = PermissionContextConstraints::validContextTypesFor('facultades:ver');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('FACULTAD');
    expect($types)->not->toContain('CARRERA');
    expect($types)->not->toContain('CURSO');
  });

  test('retorna tipos válidos para permisos de cursos (GLOBAL + CURSO)', function () {
    $types = PermissionContextConstraints::validContextTypesFor('cursos:ver');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CURSO');
    expect($types)->not->toContain('CARRERA');
    expect($types)->not->toContain('FACULTAD');
  });

  test('permisos de usuarios solo admiten contexto GLOBAL', function () {
    $types = PermissionContextConstraints::validContextTypesFor('usuarios:ver');
    expect($types)->toBe(['GLOBAL']);
  });

  test('permiso wildcard (*) solo admite contexto GLOBAL', function () {
    $types = PermissionContextConstraints::validContextTypesFor('*');
    expect($types)->toBe(['GLOBAL']);
  });

  test('carreras:crear admite GLOBAL + FACULTAD (parent_action)', function () {
    $types = PermissionContextConstraints::validContextTypesFor('carreras:crear');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('FACULTAD');
    expect($types)->not->toContain('CARRERA');
  });

  test('cursos:crear admite GLOBAL + CARRERA (parent_action)', function () {
    $types = PermissionContextConstraints::validContextTypesFor('cursos:crear');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CARRERA');
    expect($types)->not->toContain('CURSO');
  });

  test('slug desconocido retorna fallback GLOBAL', function () {
    $types = PermissionContextConstraints::validContextTypesFor('slug:inexistente');
    expect($types)->toBe(['GLOBAL']);
  });

  test('permisos con sub-recursos heredan el contexto del padre', function () {
    // cursos/inscripciones:ver → GLOBAL + CURSO
    $types = PermissionContextConstraints::validContextTypesFor('cursos/inscripciones:ver');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CURSO');

    // carreras/planes:editar → GLOBAL + CARRERA
    $types = PermissionContextConstraints::validContextTypesFor('carreras/planes:editar');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CARRERA');
  });

  test('wildcards de módulo heredan contexto del nodo', function () {
    // cursos:* → GLOBAL + CURSO
    $types = PermissionContextConstraints::validContextTypesFor('cursos:*');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CURSO');

    // facultades:* → GLOBAL + FACULTAD
    $types = PermissionContextConstraints::validContextTypesFor('facultades:*');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('FACULTAD');
  });
});

// ============================================================================
// GRUPO 2: PermissionContextConstraints — isValidAssignment()
// ============================================================================

describe('PermissionContextConstraints — isValidAssignment()', function () {

  test('carreras:ver es válido en contexto CARRERA', function () {
    expect(PermissionContextConstraints::isValidAssignment('carreras:ver', 'CARRERA'))->toBeTrue();
  });

  test('carreras:ver es válido en contexto GLOBAL', function () {
    expect(PermissionContextConstraints::isValidAssignment('carreras:ver', 'GLOBAL'))->toBeTrue();
  });

  test('carreras:ver NO es válido en contexto CURSO', function () {
    expect(PermissionContextConstraints::isValidAssignment('carreras:ver', 'CURSO'))->toBeFalse();
  });

  test('carreras:ver NO es válido en contexto FACULTAD', function () {
    expect(PermissionContextConstraints::isValidAssignment('carreras:ver', 'FACULTAD'))->toBeFalse();
  });

  test('cursos:ver es válido en contexto CURSO pero no CARRERA', function () {
    expect(PermissionContextConstraints::isValidAssignment('cursos:ver', 'CURSO'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('cursos:ver', 'CARRERA'))->toBeFalse();
  });

  test('usuarios:ver solo es válido en GLOBAL', function () {
    expect(PermissionContextConstraints::isValidAssignment('usuarios:ver', 'GLOBAL'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('usuarios:ver', 'CARRERA'))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment('usuarios:ver', 'FACULTAD'))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment('usuarios:ver', 'CURSO'))->toBeFalse();
  });

  test('la comparación es case-insensitive (recibe minúsculas)', function () {
    expect(PermissionContextConstraints::isValidAssignment('carreras:ver', 'carrera'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('facultades:ver', 'facultad'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('cursos:ver', 'curso'))->toBeTrue();
  });

  test('carreras:crear es válido en FACULTAD (parent_action) pero no en CARRERA', function () {
    expect(PermissionContextConstraints::isValidAssignment('carreras:crear', 'FACULTAD'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('carreras:crear', 'CARRERA'))->toBeFalse();
  });

  test('isValidAssignment normaliza "system" como GLOBAL (categoria DB del contexto global)', function () {
    // tipo_contexto.categoria = 'system' para el contexto global en la BD
    // El config usa 'GLOBAL' como tipo válido → normalizeContextType('system') = 'GLOBAL'
    expect(PermissionContextConstraints::isValidAssignment('usuarios:ver', 'system'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('*', 'system'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('carreras:ver', 'system'))->toBeTrue(); // carreras:ver acepta GLOBAL
    expect(PermissionContextConstraints::isValidAssignment('cursos:ver', 'system'))->toBeTrue();   // cursos:ver acepta GLOBAL
  });
});

// ============================================================================
// GRUPO 2.5: PermissionContextConstraints — normalizeContextType()
// ============================================================================

describe('PermissionContextConstraints — normalizeContextType()', function () {

  test('normaliza "system" a "GLOBAL"', function () {
    expect(PermissionContextConstraints::normalizeContextType('system'))->toBe('GLOBAL');
    expect(PermissionContextConstraints::normalizeContextType('SYSTEM'))->toBe('GLOBAL');
    expect(PermissionContextConstraints::normalizeContextType('System'))->toBe('GLOBAL');
  });

  test('normaliza tipos de contexto regulares a mayúsculas sin alias', function () {
    expect(PermissionContextConstraints::normalizeContextType('carrera'))->toBe('CARRERA');
    expect(PermissionContextConstraints::normalizeContextType('facultad'))->toBe('FACULTAD');
    expect(PermissionContextConstraints::normalizeContextType('curso'))->toBe('CURSO');
    expect(PermissionContextConstraints::normalizeContextType('departamento'))->toBe('DEPARTAMENTO');
  });

  test('tipos ya en mayúsculas pasan sin cambio', function () {
    expect(PermissionContextConstraints::normalizeContextType('GLOBAL'))->toBe('GLOBAL');
    expect(PermissionContextConstraints::normalizeContextType('CARRERA'))->toBe('CARRERA');
  });
});

// ============================================================================
// GRUPO 3: PermissionContextConstraints — invalidAssignmentMessage()
// ============================================================================

describe('PermissionContextConstraints — invalidAssignmentMessage()', function () {

  test('el mensaje incluye el slug del permiso', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage('carreras:ver', 'CURSO');
    expect($msg)->toContain("'carreras:ver'");
  });

  test('el mensaje incluye el tipo de contexto inválido', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage('carreras:ver', 'CURSO');
    expect($msg)->toContain("'CURSO'");
  });

  test('el mensaje incluye los tipos válidos', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage('carreras:ver', 'CURSO');
    expect($msg)->toContain('GLOBAL');
    expect($msg)->toContain('CARRERA');
  });

  test('el mensaje es legible y descriptivo', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage('usuarios:ver', 'FACULTAD');
    expect($msg)->toContain('no puede asignarse');
    expect($msg)->toContain('Tipos de contexto válidos');
  });
});

// ============================================================================
// GRUPO 4: PermissionContextConstraints — getInvalidTypes()
// ============================================================================

describe('PermissionContextConstraints — getInvalidTypes()', function () {

  test('retorna vacío cuando todos los tipos son válidos', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes('carreras:ver', ['GLOBAL', 'CARRERA']);
    expect($invalid)->toBeEmpty();
  });

  test('retorna los tipos inválidos cuando hay mezcla', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes('carreras:ver', ['GLOBAL', 'CURSO', 'FACULTAD']);
    expect($invalid)->toContain('CURSO');
    expect($invalid)->toContain('FACULTAD');
    expect($invalid)->not->toContain('GLOBAL');
  });

  test('retorna todos cuando ninguno es válido', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes('usuarios:ver', ['CARRERA', 'FACULTAD', 'CURSO']);
    expect($invalid)->toHaveCount(3);
  });

  test('maneja array vacío correctamente', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes('carreras:ver', []);
    expect($invalid)->toBeEmpty();
  });

  test('es case-insensitive con los tipos', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes('carreras:ver', ['carrera', 'global']);
    expect($invalid)->toBeEmpty();
  });
});

// ============================================================================
// GRUPO 5: PermissionContextConstraints — areAllTypesValid()
// ============================================================================

describe('PermissionContextConstraints — areAllTypesValid()', function () {

  test('retorna true cuando todos los tipos son válidos', function () {
    expect(PermissionContextConstraints::areAllTypesValid('carreras:ver', ['GLOBAL', 'CARRERA']))->toBeTrue();
  });

  test('retorna false si al menos uno es inválido', function () {
    expect(PermissionContextConstraints::areAllTypesValid('carreras:ver', ['GLOBAL', 'CURSO']))->toBeFalse();
  });

  test('retorna true para array vacío', function () {
    expect(PermissionContextConstraints::areAllTypesValid('carreras:ver', []))->toBeTrue();
  });

  test('retorna false cuando ninguno es válido', function () {
    expect(PermissionContextConstraints::areAllTypesValid('usuarios:ver', ['CARRERA', 'CURSO', 'FACULTAD']))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 6: PermissionContextConstraints — diagnoseAssignment()
// ============================================================================

describe('PermissionContextConstraints — diagnoseAssignment()', function () {

  test('retorna diagnóstico válido para asignación correcta', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment('carreras:ver', 'CARRERA');

    expect($diag)->toBeArray();
    expect($diag['slug'])->toBe('carreras:ver');
    expect($diag['contextType'])->toBe('CARRERA');
    expect($diag['valid'])->toBeTrue();
    expect($diag['validTypes'])->toContain('CARRERA');
    expect($diag['message'])->toContain('✓');
  });

  test('retorna diagnóstico inválido para asignación incorrecta', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment('carreras:ver', 'CURSO');

    expect($diag['valid'])->toBeFalse();
    expect($diag['message'])->toContain('no puede asignarse');
    expect($diag['validTypes'])->toContain('CARRERA');
    expect($diag['validTypes'])->not->toContain('CURSO');
  });

  test('incluye todos los campos requeridos en la estructura', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment('usuarios:ver', 'GLOBAL');

    expect($diag)->toHaveKeys(['slug', 'contextType', 'valid', 'validTypes', 'message']);
  });
});

// ============================================================================
// GRUPO 7: PermissionAssignmentBuilder — Validación temprana en on() (OPCIÓN 2)
// ============================================================================

describe('PermissionAssignmentBuilder — Validación temprana en on()', function () {

  test('on() acepta recurso con contexto compatible (carreras:ver + carrera)', function () {
    // carreras:ver acepta CARRERA → no debe lanzar excepción
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->on($this->carrera)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_contexto)->toBe($this->carrera->id_contexto);
  });

  test('on() acepta recurso con contexto compatible (facultades:ver + facultad)', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::FACULTADES_VER)
      ->on($this->facultad)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_contexto)->toBe($this->facultad->id_contexto);
  });

  test('on() lanza InvalidArgumentException si el contexto es incompatible (carreras:ver + facultad)', function () {
    // carreras:ver acepta GLOBAL + CARRERA, NO FACULTAD
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->on($this->facultad)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('on() lanza InvalidArgumentException si el contexto es incompatible (facultades:ver + carrera)', function () {
    // facultades:ver acepta GLOBAL + FACULTAD, NO CARRERA
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->on($this->carrera)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('on() con array detecta incompatibilidad en el primer recurso inválido', function () {
    // carreras:ver no acepta FACULTAD
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->on([$this->facultad, $this->carrera])
    )->toThrow(\InvalidArgumentException::class);
  });

  test('on() con array de recursos compatibles no lanza excepción', function () {
    // Crear segunda carrera para probar array
    $carreraB = Carrera::create([
      'nombre' => 'CV Carrera B',
      'jornada' => 'Vespertina',
      'sede' => 'Norte',
      'modalidad' => 'Semipresencial',
      'id_departamento' => $this->departamento->id_departamento,
    ]);
    $carreraB->refresh();

    $result = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->on([$this->carrera, $carreraB])
      ->save();

    expect($result)->toHaveCount(2);
  });

  test('el mensaje de error de on() incluye información del permiso y contexto', function () {
    try {
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->on($this->facultad);
      $this->fail('Se esperaba InvalidArgumentException');
    } catch (\InvalidArgumentException $e) {
      expect($e->getMessage())->toContain('carreras:ver');
      expect($e->getMessage())->toContain('Tipos de contexto válidos');
    }
  });

  test('carreras:* (wildcard) acepta CARRERA como contexto', function () {
    // carreras:* → GLOBAL + CARRERA
    // Esto prueba que el wildcard de carreras funciona en el contexto correspondiente
    // La Carrera tiene un tipo de contexto 'carrera', que getContextType retorna como 'carrera'
    // isValidAssignment('carreras:*', 'carrera') → normalizeContextType → 'CARRERA' → coincide
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_ALL)
      ->on($this->carrera)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });
});

// ============================================================================
// GRUPO 8: PermissionAssignmentBuilder — Validación temprana en onAll() (OPCIÓN 2)
// ============================================================================

describe('PermissionAssignmentBuilder — Validación temprana en onAll()', function () {

  test('onAll() acepta tipo compatible (carreras:ver + CARRERA)', function () {
    $result = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->onAll(ContextualModelType::CARRERA)
      ->save();

    expect($result)->not->toBeEmpty();
  });

  test('onAll() acepta tipo compatible (facultades:editar + FACULTAD)', function () {
    $result = $this->recipient
      ->givePermission(Permissions::FACULTADES_EDITAR)
      ->onAll(ContextualModelType::FACULTAD)
      ->save();

    expect($result)->not->toBeEmpty();
  });

  test('onAll() lanza InvalidArgumentException si el tipo es incompatible (carreras:ver + FACULTAD)', function () {
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->onAll(ContextualModelType::FACULTAD)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('onAll() lanza InvalidArgumentException si tipo incompatible (facultades:ver + CARRERA)', function () {
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->onAll(ContextualModelType::CARRERA)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('onAll() lanza InvalidArgumentException para permisos GLOBAL-only (usuarios:ver + CARRERA)', function () {
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::USUARIOS_VER)
        ->onAll(ContextualModelType::CARRERA)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('el mensaje de error de onAll() es descriptivo', function () {
    try {
      $this->recipient
        ->givePermission(Permissions::USUARIOS_VER)
        ->onAll(ContextualModelType::FACULTAD);
      $this->fail('Se esperaba InvalidArgumentException');
    } catch (\InvalidArgumentException $e) {
      expect($e->getMessage())->toContain('usuarios:ver');
      expect($e->getMessage())->toContain('Tipos de contexto válidos');
    }
  });
});

// ============================================================================
// GRUPO 9: PermissionAssignmentBuilder — Validación temprana en inContext() (OPCIÓN 2)
// ============================================================================

describe('PermissionAssignmentBuilder — Validación temprana en inContext()', function () {

  test('inContext() acepta contexto global para permisos de usuarios', function () {
    // usuarios:ver solo acepta GLOBAL → pasar el contexto global debe funcionar
    $upe = $this->recipient
      ->givePermission(Permissions::USUARIOS_VER)
      ->inContext($this->contextoGlobal_id)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_contexto)->toBe($this->contextoGlobal_id);
  });

  test('inContext() acepta ID de contexto compatible', function () {
    // carreras:ver acepta CARRERA
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->inContext($this->carrera->id_contexto)
      ->save();

    expect($upe->id_contexto)->toBe($this->carrera->id_contexto);
  });

  test('inContext() lanza InvalidArgumentException si el contexto es de tipo incompatible', function () {
    // carreras:ver acepta GLOBAL + CARRERA, NO FACULTAD
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->inContext($this->facultad->id_contexto)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('inContext() con array de IDs valida cada uno individualmente', function () {
    // carreras:ver NO acepta contexto de facultad
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->inContext([$this->carrera->id_contexto, $this->facultad->id_contexto])
    )->toThrow(\InvalidArgumentException::class);
  });

  test('inContext() con array de IDs compatibles no lanza excepción', function () {
    // Ambos son contexto GLOBAL y carrera → carreras:ver los acepta
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->inContext([$this->carrera->id_contexto])
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });
});

// ============================================================================
// GRUPO 10: PermissionAssignmentBuilder — inGlobalContext() (OPCIÓN 2)
// ============================================================================

describe('PermissionAssignmentBuilder — inGlobalContext()', function () {

  test('inGlobalContext() funciona para permisos GLOBAL-only (usuarios:ver)', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::USUARIOS_VER)
      ->inGlobalContext()
      ->save();

    $globalContextId = app(GlobalContextService::class)->getContextId();
    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_contexto)->toBe($globalContextId);
  });

  test('inGlobalContext() funciona para permisos que aceptan GLOBAL entre otros', function () {
    // carreras:ver acepta GLOBAL + CARRERA
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->inGlobalContext()
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });
});

// ============================================================================
// GRUPO 11: PermissionAssignmentBuilder — validateContextCompatibility() en save() (OPCIÓN 1)
// ============================================================================

describe('PermissionAssignmentBuilder — validateContextCompatibility() en save()', function () {

  test('save() pasa validación cuando contexts son compatibles', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_EDITAR)
      ->on($this->carrera)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });

  test('save() con inContext de contexto global para permiso GLOBAL-only pasa', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::USUARIOS_EDITAR)
      ->inContext($this->contextoGlobal_id)
      ->save();

    expect($upe->id_contexto)->toBe($this->contextoGlobal_id);
    expect($upe->esta_permitido)->toBeTrue();
  });

  test('save() persiste multiples UPE cuando todos los contextos son compatibles', function () {
    $carreraB = Carrera::create([
      'nombre' => 'CV Carrera Save',
      'jornada' => 'Vespertina',
      'sede' => 'Sur',
      'modalidad' => 'Presencial',
      'id_departamento' => $this->departamento->id_departamento,
    ]);
    $carreraB->refresh();

    $result = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->on([$this->carrera, $carreraB])
      ->save();

    expect($result)->toHaveCount(2);
    $result->each(fn($upe) => expect($upe->esta_permitido)->toBeTrue());
  });
});

// ============================================================================
// GRUPO 12: Consistency — Validación temprana y pre-persist coinciden
// ============================================================================

describe('Consistency — Ambas capas coinciden en resultado', function () {

  test('permiso válido pasa ambas validaciones sin errores', function () {
    // carreras:ver en contexto CARRERA
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->on($this->carrera)
      ->for(30)
      ->save();

    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_usuario)->toBe($this->recipient->id_usuario);
    expect($upe->id_contexto)->toBe($this->carrera->id_contexto);
  });

  test('permiso inválido es rechazado tempranamente (nunca llega a save)', function () {
    // Como on() lanza excepción antes de que se pueda llamar save(),
    // el UPE nunca se crea
    $countBefore = UsuarioPermisoEspecial::where('id_usuario', $this->recipient->id_usuario)->count();

    try {
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->on($this->facultad)
        ->save();
    } catch (\InvalidArgumentException) {
      // Esperado
    }

    $countAfter = UsuarioPermisoEspecial::where('id_usuario', $this->recipient->id_usuario)->count();
    expect($countAfter)->toBe($countBefore);
  });

  test('PermissionContextConstraints::isValidAssignment coincide con validación del builder', function () {
    // Verificar que la validación estática y la del builder dan el mismo resultado
    $isValid = PermissionContextConstraints::isValidAssignment('carreras:ver', 'carrera');
    expect($isValid)->toBeTrue();

    // Y que el builder acepta lo mismo
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->on($this->carrera)
      ->save();
    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });

  test('PermissionContextConstraints::isValidAssignment FALSE coincide con rechazo del builder', function () {
    $isValid = PermissionContextConstraints::isValidAssignment('carreras:ver', 'facultad');
    expect($isValid)->toBeFalse();

    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->on($this->facultad)
    )->toThrow(\InvalidArgumentException::class);
  });
});

// ============================================================================
// GRUPO 13: Edge Cases — Casos borde y configuraciones especiales
// ============================================================================

describe('Edge Cases — Comportamiento en escenarios límite', function () {

  test('permiso con contexto GLOBAL puede asignarse via inGlobalContext', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::USUARIOS_CREAR)
      ->inGlobalContext()
      ->save();

    expect($upe->id_contexto)->toBe($this->contextoGlobal_id);
  });

  test('wildcard global (*) solo acepta contexto global', function () {
    // Permissions::GLOBAL_WILDCARD = '*' → validContextTypesFor('*') = ['GLOBAL']
    $types = PermissionContextConstraints::validContextTypesFor('*');
    expect($types)->toBe(['GLOBAL']);
    expect(PermissionContextConstraints::isValidAssignment('*', 'CARRERA'))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment('*', 'GLOBAL'))->toBeTrue();
  });

  test('diagnoseAssignment retorna estructura completa para edge cases', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment('slug:no_existe', 'TIPO_RANDOM');

    expect($diag['slug'])->toBe('slug:no_existe');
    expect($diag['contextType'])->toBe('TIPO_RANDOM');
    // Slug desconocido → fallback a GLOBAL → TIPO_RANDOM no es GLOBAL → inválido
    expect($diag['valid'])->toBeFalse();
    expect($diag['validTypes'])->toBe(['GLOBAL']);
  });

  test('getInvalidTypes maneja tipos mixtos correctamente', function () {
    // cursos:ver acepta GLOBAL + CURSO
    $invalid = PermissionContextConstraints::getInvalidTypes('cursos:ver', [
      'GLOBAL',       // válido
      'CURSO',        // válido
      'CARRERA',      // inválido
      'DEPARTAMENTO', // inválido
      'FACULTAD',     // inválido
    ]);

    expect($invalid)->toHaveCount(3);
    expect(array_values($invalid))->toEqualCanonicalizing(['CARRERA', 'DEPARTAMENTO', 'FACULTAD']);
  });

  test('permisos de sub-recurso profundo validan correctamente', function () {
    // cursos/actividades/grupos:crear → GLOBAL + CURSO
    $types = PermissionContextConstraints::validContextTypesFor('cursos/actividades/grupos:crear');
    expect($types)->toContain('GLOBAL');
    expect($types)->toContain('CURSO');
    expect($types)->not->toContain('CARRERA');

    expect(PermissionContextConstraints::isValidAssignment('cursos/actividades/grupos:crear', 'CURSO'))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment('cursos/actividades/grupos:crear', 'CARRERA'))->toBeFalse();
  });

  test('permisos de programas de curso validan en contexto CURSO', function () {
    $types = PermissionContextConstraints::validContextTypesFor('cursos/programas:agregar');
    expect($types)->toContain('CURSO');
    expect($types)->toContain('GLOBAL');
  });
});

// ============================================================================
// GRUPO 14: Tablas de verdad — Matriz completa permiso × contexto
// ============================================================================

describe('Matriz de compatibilidad — Permisos representativos × Tipos de contexto', function () {

  /**
   * Verifica una matriz completa de combinaciones permiso/contexto.
   * 
   * Formato: [slug, contextType, expectedValid]
   */
  $matrix = [
    // Permisos de carreras
    ['carreras:ver', 'CARRERA', true],
    ['carreras:ver', 'GLOBAL', true],
    ['carreras:ver', 'FACULTAD', false],
    ['carreras:ver', 'CURSO', false],
    ['carreras:ver', 'DEPARTAMENTO', false],

    // Permisos de carreras:crear (parent_action → FACULTAD)
    ['carreras:crear', 'FACULTAD', true],
    ['carreras:crear', 'GLOBAL', true],
    ['carreras:crear', 'CARRERA', false],
    ['carreras:crear', 'CURSO', false],

    // Permisos de facultades
    ['facultades:ver', 'FACULTAD', true],
    ['facultades:ver', 'GLOBAL', true],
    ['facultades:ver', 'CARRERA', false],
    ['facultades:ver', 'CURSO', false],

    // Permisos de cursos
    ['cursos:ver', 'CURSO', true],
    ['cursos:ver', 'GLOBAL', true],
    ['cursos:ver', 'CARRERA', false],
    ['cursos:ver', 'FACULTAD', false],

    // Permisos de cursos:crear (parent_action → CARRERA)
    ['cursos:crear', 'CARRERA', true],
    ['cursos:crear', 'GLOBAL', true],
    ['cursos:crear', 'CURSO', false],
    ['cursos:crear', 'FACULTAD', false],

    // Permisos de usuarios (solo GLOBAL)
    ['usuarios:ver', 'GLOBAL', true],
    ['usuarios:ver', 'CARRERA', false],
    ['usuarios:ver', 'FACULTAD', false],
    ['usuarios:ver', 'CURSO', false],

    // Permisos de departamentos
    ['departamentos:ver', 'DEPARTAMENTO', true],
    ['departamentos:ver', 'GLOBAL', true],
    ['departamentos:ver', 'CARRERA', false],
    ['departamentos:ver', 'FACULTAD', false],

    // Wildcard
    ['*', 'GLOBAL', true],
    ['*', 'CARRERA', false],
    ['*', 'FACULTAD', false],
  ];

  foreach ($matrix as [$slug, $contextType, $expectedValid]) {
    $label = $expectedValid ? '✓' : '✗';
    test("{$label} {$slug} × {$contextType} → " . ($expectedValid ? 'válido' : 'inválido'), function () use ($slug, $contextType, $expectedValid) {
      expect(PermissionContextConstraints::isValidAssignment($slug, $contextType))->toBe($expectedValid);
    });
  }
});
