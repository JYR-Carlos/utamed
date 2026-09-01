<?php

/**
 * Integration Test: Context Validation en PermissionAssignmentBuilder + PermissionContextConstraints
 *
 * Prueba la validación de compatibilidad permiso↔contexto en dos capas:
 *   - OPCIÓN 2 (Fail-fast): Validación temprana en on(), onAllCurrentInstances(), inContext()
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
use App\Enums\ContextType;
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
  TipoContexto::firstOrCreate(
    ['categoria' => 'curso'],
    ['tabla_referenciada' => 'curso']
  );

  // ---- Contexto global ----
  $tipoSystem = TipoContexto::where('categoria', 'global')->first();
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

  test('retorna tipos válidos para permisos de carreras (GLOBAL + CARRERA + DEPARTAMENTO + FACULTAD)', function () {
    // carreras:ver acepta el contexto propio y toda la cadena de ancestros contextuales
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CARRERAS_VER);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CARRERA);
    expect($types)->toContain(ContextType::DEPARTAMENTO);
    expect($types)->toContain(ContextType::FACULTAD);
    expect($types)->not->toContain(ContextType::CURSO); // CURSO no es ancestro de CARRERA
  });

  test('retorna tipos válidos para permisos de facultades (GLOBAL + FACULTAD)', function () {
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::FACULTADES_VER);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::FACULTAD);
    expect($types)->not->toContain(ContextType::CARRERA);
    expect($types)->not->toContain(ContextType::CURSO);
  });

  test('retorna tipos válidos para permisos de cursos (GLOBAL + CURSO + cadena de ancestros)', function () {
    // cursos:ver acepta el contexto propio y toda la cadena: carrera → departamento → facultad
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CURSOS_VER);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CURSO);
    expect($types)->toContain(ContextType::CARRERA);
    expect($types)->toContain(ContextType::DEPARTAMENTO);
    expect($types)->toContain(ContextType::FACULTAD);
  });

  test('permisos de usuarios solo admiten contexto GLOBAL', function () {
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::USUARIOS_VER);
    expect($types)->toBe([ContextType::GLOBAL]);
  });

  test('permiso wildcard (*) solo admite contexto GLOBAL', function () {
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::GLOBAL_WILDCARD);
    expect($types)->toBe([ContextType::GLOBAL]);
  });

  test('carreras:crear admite GLOBAL + DEPARTAMENTO (parent_action)', function () {
    // carreras:crear → el parent contextual de carrera es departamento
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CARRERAS_CREAR);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::DEPARTAMENTO);
    expect($types)->not->toContain(ContextType::CARRERA);
    expect($types)->not->toContain(ContextType::FACULTAD); // facultad no es el parent directo
  });

  test('cursos:crear admite GLOBAL + CARRERA (parent_action)', function () {
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CURSOS_CREAR);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CARRERA);
    expect($types)->not->toContain(ContextType::CURSO);
  });

  test('permisos con sub-recursos heredan el contexto del padre y sus ancestros', function () {
    // cursos/inscripciones:ver → GLOBAL + CURSO + cadena de ancestros (carrera, departamento, facultad)
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CURSOS_INSCRIPCIONES_VER);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CURSO);
    expect($types)->toContain(ContextType::CARRERA); // ancestro contextual de curso

    // carreras/planes:editar → GLOBAL + CARRERA + cadena de ancestros (departamento, facultad)
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CARRERAS_PLANES_EDITAR);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CARRERA);
    expect($types)->toContain(ContextType::FACULTAD); // ancestro contextual de carrera
  });

  test('wildcards de módulo heredan contexto del nodo y sus ancestros', function () {
    // cursos:* → GLOBAL + CURSO + cadena ancestros
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CURSOS_ALL);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CURSO);
    expect($types)->toContain(ContextType::CARRERA); // ancestro contextual de curso
    expect($types)->toContain(ContextType::FACULTAD); // ancestro contextual

    // facultades:* → GLOBAL + FACULTAD (facultad no tiene ancestros contextuales)
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::FACULTADES_ALL);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::FACULTAD);
  });
});

// ============================================================================
// GRUPO 2: PermissionContextConstraints — isValidAssignment()
// ============================================================================

describe('PermissionContextConstraints — isValidAssignment()', function () {

  test('carreras:ver es válido en contexto CARRERA', function () {
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_VER, ContextType::CARRERA))->toBeTrue();
  });

  test('carreras:ver es válido en contexto GLOBAL', function () {
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_VER, ContextType::GLOBAL))->toBeTrue();
  });

  test('carreras:ver NO es válido en contexto CURSO (no es ancestro de carrera)', function () {
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_VER, ContextType::CURSO))->toBeFalse();
  });

  test('carreras:ver es válido en contextos ancestros (DEPARTAMENTO, FACULTAD)', function () {
    // Nueva regla: se puede asignar un permiso en el contexto del padre
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_VER, ContextType::DEPARTAMENTO))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_VER, ContextType::FACULTAD))->toBeTrue();
  });

  test('cursos:ver es válido en contexto CURSO y en sus ancestros contextuales', function () {
    // Nueva regla: se permite asignar permisos de hijos a padres contextuales
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CURSOS_VER, ContextType::CURSO))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CURSOS_VER, ContextType::CARRERA))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CURSOS_VER, ContextType::DEPARTAMENTO))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CURSOS_VER, ContextType::FACULTAD))->toBeTrue();
  });

  test('usuarios:ver solo es válido en GLOBAL', function () {
    expect(PermissionContextConstraints::isValidAssignment(Permissions::USUARIOS_VER, ContextType::GLOBAL))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::USUARIOS_VER, ContextType::CARRERA))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::USUARIOS_VER, ContextType::FACULTAD))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::USUARIOS_VER, ContextType::CURSO))->toBeFalse();
  });

  test('carreras:crear es válido en DEPARTAMENTO (parent_action) pero no en CARRERA ni FACULTAD', function () {
    // carreras:crear → parent contextual es departamento (no facultad)
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_CREAR, ContextType::DEPARTAMENTO))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_CREAR, ContextType::CARRERA))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_CREAR, ContextType::FACULTAD))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 3: PermissionContextConstraints — invalidAssignmentMessage()
// ============================================================================

describe('PermissionContextConstraints — invalidAssignmentMessage()', function () {

  test('el mensaje incluye el slug del permiso', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage(Permissions::CARRERAS_VER, ContextType::CURSO);
    expect($msg)->toContain("'carreras:ver'");
  });

  test('el mensaje incluye el tipo de contexto inválido', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage(Permissions::CARRERAS_VER, ContextType::CURSO);
    expect($msg)->toContain("'curso'");
  });

  test('el mensaje incluye los tipos válidos', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage(Permissions::CARRERAS_VER, ContextType::CURSO);
    expect($msg)->toContain('global');
    expect($msg)->toContain('carrera');
    expect($msg)->toContain('departamento');
    expect($msg)->toContain('facultad');
  });

  test('el mensaje es legible y descriptivo', function () {
    $msg = PermissionContextConstraints::invalidAssignmentMessage(Permissions::USUARIOS_VER, ContextType::FACULTAD);
    expect($msg)->toContain('no puede asignarse');
    expect($msg)->toContain('Tipos de contexto válidos');
  });
});

// ============================================================================
// GRUPO 4: PermissionContextConstraints — getInvalidTypes()
// ============================================================================

describe('PermissionContextConstraints — getInvalidTypes()', function () {

  test('retorna vacío cuando todos los tipos son válidos', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes(Permissions::CARRERAS_VER, [ContextType::GLOBAL , ContextType::CARRERA]);
    expect($invalid)->toBeEmpty();
  });

  test('retorna los tipos inválidos cuando hay mezcla', function () {
    // carreras:ver acepta GLOBAL, CARRERA, DEPARTAMENTO, FACULTAD — no acepta CURSO
    $invalid = PermissionContextConstraints::getInvalidTypes(Permissions::CARRERAS_VER, [ContextType::GLOBAL , ContextType::CURSO, ContextType::FACULTAD]);
    expect($invalid)->toContain(ContextType::CURSO);       // inválido: no es ancestro de carrera
    expect($invalid)->not->toContain(ContextType::GLOBAL); // válido
    expect($invalid)->not->toContain(ContextType::FACULTAD); // ahora válido (ancestro contextual)
  });

  test('retorna todos cuando ninguno es válido', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes(Permissions::USUARIOS_VER, [ContextType::CARRERA, ContextType::FACULTAD, ContextType::CURSO]);
    expect($invalid)->toHaveCount(3);
  });

  test('maneja array vacío correctamente', function () {
    $invalid = PermissionContextConstraints::getInvalidTypes(Permissions::CARRERAS_VER, []);
    expect($invalid)->toBeEmpty();
  });
});

// ============================================================================
// GRUPO 5: PermissionContextConstraints — areAllTypesValid()
// ============================================================================

describe('PermissionContextConstraints — areAllTypesValid()', function () {

  test('retorna true cuando todos los tipos son válidos', function () {
    expect(PermissionContextConstraints::areAllTypesValid(Permissions::CARRERAS_VER, [ContextType::GLOBAL , ContextType::CARRERA]))->toBeTrue();
  });

  test('retorna false si al menos uno es inválido', function () {
    expect(PermissionContextConstraints::areAllTypesValid(Permissions::CARRERAS_VER, [ContextType::GLOBAL , ContextType::CURSO]))->toBeFalse();
  });

  test('retorna true para array vacío', function () {
    expect(PermissionContextConstraints::areAllTypesValid(Permissions::CARRERAS_VER, []))->toBeTrue();
  });

  test('retorna false cuando ninguno es válido', function () {
    expect(PermissionContextConstraints::areAllTypesValid(Permissions::USUARIOS_VER, [ContextType::CARRERA, ContextType::CURSO, ContextType::FACULTAD]))->toBeFalse();
  });
});

// ============================================================================
// GRUPO 6: PermissionContextConstraints — diagnoseAssignment()
// ============================================================================

describe('PermissionContextConstraints — diagnoseAssignment()', function () {

  test('retorna diagnóstico válido para asignación correcta', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment(Permissions::CARRERAS_VER, ContextType::CARRERA);

    expect($diag)->toBeArray();
    expect($diag['slug'])->toBe('carreras:ver');
    expect($diag['contextType'])->toBe('carrera');
    expect($diag['valid'])->toBeTrue();
    expect($diag['validTypes'])->toContain('carrera');
    expect($diag['message'])->toContain('✓');
  });

  test('retorna diagnóstico inválido para asignación incorrecta', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment(Permissions::CARRERAS_VER, ContextType::CURSO);

    expect($diag['valid'])->toBeFalse();
    expect($diag['message'])->toContain('no puede asignarse');
    expect($diag['validTypes'])->toContain('carrera');
    expect($diag['validTypes'])->not->toContain('curso');
  });

  test('incluye todos los campos requeridos en la estructura', function () {
    $diag = PermissionContextConstraints::diagnoseAssignment(Permissions::USUARIOS_VER, ContextType::GLOBAL);

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

  test('on() lanza InvalidArgumentException si el contexto es incompatible (usuarios:ver + facultad)', function () {
    // usuarios:ver solo acepta GLOBAL, por lo tanto facultad es incompatible
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::USUARIOS_VER)
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
    // facultades:ver no acepta CARRERA ni DEPARTAMENTO
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->on([$this->facultad, $this->carrera]) // carrera no es válida para facultades:ver
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
        ->givePermission(Permissions::FACULTADES_VER)
        ->on($this->carrera); // carrera no es válida para facultades:ver
      $this->fail('Se esperaba InvalidArgumentException');
    } catch (\InvalidArgumentException $e) {
      expect($e->getMessage())->toContain('facultades:ver');
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
// GRUPO 8: PermissionAssignmentBuilder — Validación temprana en onAllCurrentInstances() (OPCIÓN 2)
// ============================================================================

describe('PermissionAssignmentBuilder — Validación temprana en onAllCurrentInstances()', function () {

  test('onAllCurrentInstances() acepta tipo compatible (carreras:ver + CARRERA)', function () {
    $result = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->onAllCurrentInstances(ContextualModelType::CARRERA)
      ->save();

    expect($result)->not->toBeEmpty();
  });

  test('onAllCurrentInstances() acepta tipo compatible (facultades:editar + FACULTAD)', function () {
    $result = $this->recipient
      ->givePermission(Permissions::FACULTADES_EDITAR)
      ->onAllCurrentInstances(ContextualModelType::FACULTAD)
      ->save();

    expect($result)->not->toBeEmpty();
  });

  test('onAllCurrentInstances() lanza InvalidArgumentException si el tipo es incompatible (carreras:ver + CURSO)', function () {
    // CURSO no es ancestro de CARRERA → incompatible con carreras:ver
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::CARRERAS_VER)
        ->onAllCurrentInstances(ContextualModelType::CURSO)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('onAllCurrentInstances() lanza InvalidArgumentException si tipo incompatible (facultades:ver + CARRERA)', function () {
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->onAllCurrentInstances(ContextualModelType::CARRERA)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('onAllCurrentInstances() lanza InvalidArgumentException para permisos GLOBAL-only (usuarios:ver + CARRERA)', function () {
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::USUARIOS_VER)
        ->onAllCurrentInstances(ContextualModelType::CARRERA)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('el mensaje de error de onAllCurrentInstances() es descriptivo', function () {
    try {
      $this->recipient
        ->givePermission(Permissions::USUARIOS_VER)
        ->onAllCurrentInstances(ContextualModelType::FACULTAD);
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
    // facultades:ver solo acepta GLOBAL + FACULTAD, no acepta CARRERA
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->inContext($this->carrera->id_contexto)
    )->toThrow(\InvalidArgumentException::class);
  });

  test('inContext() con array de IDs valida cada uno individualmente', function () {
    // facultades:ver NO acepta contexto de carrera
    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->inContext([$this->facultad->id_contexto, $this->carrera->id_contexto])
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
// GRUPO 10: PermissionAssignmentBuilder — onEveryInstance() (OPCIÓN 2)
// ============================================================================

describe('PermissionAssignmentBuilder — onEveryInstance()', function () {

  test('onEveryInstance() funciona para permisos GLOBAL-only (usuarios:ver)', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::USUARIOS_VER)
      ->onEveryInstance()
      ->save();

    $globalContextId = app(GlobalContextService::class)->getContextId();
    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
    expect($upe->id_contexto)->toBe($globalContextId);
  });

  test('onEveryInstance() funciona para permisos que aceptan GLOBAL entre otros', function () {
    // carreras:ver acepta GLOBAL + CARRERA
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->onEveryInstance()
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
        ->givePermission(Permissions::FACULTADES_VER) // solo acepta GLOBAL + FACULTAD
        ->on($this->carrera) // carrera es incompatible
        ->save();
    } catch (\InvalidArgumentException) {
      // Esperado
    }

    $countAfter = UsuarioPermisoEspecial::where('id_usuario', $this->recipient->id_usuario)->count();
    expect($countAfter)->toBe($countBefore);
  });

  test('PermissionContextConstraints::isValidAssignment coincide con validación del builder', function () {
    // Verificar que la validación estática y la del builder dan el mismo resultado
    $isValid = PermissionContextConstraints::isValidAssignment(Permissions::CARRERAS_VER, ContextType::CARRERA);
    expect($isValid)->toBeTrue();

    // Y que el builder acepta lo mismo
    $upe = $this->recipient
      ->givePermission(Permissions::CARRERAS_VER)
      ->on($this->carrera)
      ->save();
    expect($upe)->toBeInstanceOf(UsuarioPermisoEspecial::class);
  });

  test('PermissionContextConstraints::isValidAssignment FALSE coincide con rechazo del builder', function () {
    // facultades:ver no acepta CARRERA → isValidAssignment retorna false y el builder lanza excepción
    $isValid = PermissionContextConstraints::isValidAssignment(Permissions::FACULTADES_VER, ContextType::CARRERA);
    expect($isValid)->toBeFalse();

    expect(
      fn() =>
      $this->recipient
        ->givePermission(Permissions::FACULTADES_VER)
        ->on($this->carrera)
    )->toThrow(\InvalidArgumentException::class);
  });
});

// ============================================================================
// GRUPO 13: Edge Cases — Casos borde y configuraciones especiales
// ============================================================================

describe('Edge Cases', function () {

  test('permiso con contexto GLOBAL puede asignarse via onEveryInstance', function () {
    $upe = $this->recipient
      ->givePermission(Permissions::USUARIOS_CREAR)
      ->onEveryInstance()
      ->save();

    expect($upe->id_contexto)->toBe($this->contextoGlobal_id);
  });

  test('wildcard global (*) solo acepta contexto global', function () {
    // Permissions::GLOBAL_WILDCARD = '*' → validContextTypesFor('*') = [ContextType::GLOBAL]
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::GLOBAL_WILDCARD);
    expect($types)->toBe([ContextType::GLOBAL]);
    expect(PermissionContextConstraints::isValidAssignment(Permissions::GLOBAL_WILDCARD, ContextType::CARRERA))->toBeFalse();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::GLOBAL_WILDCARD, ContextType::GLOBAL))->toBeTrue();
  });

  test('getInvalidTypes maneja tipos mixtos correctamente', function () {
    // usuarios:ver solo acepta GLOBAL — todos los demás son inválidos
    $invalid = PermissionContextConstraints::getInvalidTypes(Permissions::USUARIOS_VER, [
      ContextType::GLOBAL ,       // válido
      ContextType::CARRERA,       // inválido
      ContextType::DEPARTAMENTO,  // inválido
      ContextType::FACULTAD,      // inválido
    ]);

    expect($invalid)->toHaveCount(3);
    expect(array_values($invalid))->toEqualCanonicalizing([ContextType::CARRERA, ContextType::DEPARTAMENTO, ContextType::FACULTAD]);
  });

  test('permisos de sub-recurso profundo validan correctamente', function () {
    // cursos/actividades/grupos:crear → GLOBAL + CURSO + cadena de ancestros
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR);
    expect($types)->toContain(ContextType::GLOBAL);
    expect($types)->toContain(ContextType::CURSO);
    expect($types)->toContain(ContextType::CARRERA); // ancestro contextual de curso
    expect($types)->toContain(ContextType::FACULTAD); // ancestro contextual

    expect(PermissionContextConstraints::isValidAssignment(Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR, ContextType::CURSO))->toBeTrue();
    expect(PermissionContextConstraints::isValidAssignment(Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR, ContextType::CARRERA))->toBeTrue();
  });

  test('permisos de programas de curso validan en contexto CURSO', function () {
    $types = PermissionContextConstraints::validContextTypesFor(Permissions::CURSOS_PROGRAMAS_AGREGAR);
    expect($types)->toContain(ContextType::CURSO);
    expect($types)->toContain(ContextType::GLOBAL);
  });
});

// ============================================================================
// GRUPO 14: Tablas de verdad — Matriz completa permiso x contexto
// ============================================================================

describe('Matriz de compatibilidad — Permisos representativos x Tipos de contexto', function () {

  /**
   * Verifica una matriz completa de combinaciones permiso/contexto.
   * 
   * Formato: [Permissions case, contextType, expectedValid]
   */
  $matrix = [
    // Permisos de carreras (acepta propio + ancestros: departamento, facultad)
    [Permissions::CARRERAS_VER, ContextType::CARRERA, true],
    [Permissions::CARRERAS_VER, ContextType::GLOBAL , true],
    [Permissions::CARRERAS_VER, ContextType::DEPARTAMENTO, true],  // ancestro contextual de carrera
    [Permissions::CARRERAS_VER, ContextType::FACULTAD, true],  // ancestro contextual de carrera
    [Permissions::CARRERAS_VER, ContextType::CURSO, false], // curso NO es ancestro de carrera

    // Permisos de carreras:crear (parent_action → departamento)
    [Permissions::CARRERAS_CREAR, ContextType::DEPARTAMENTO, true],
    [Permissions::CARRERAS_CREAR, ContextType::GLOBAL , true],
    [Permissions::CARRERAS_CREAR, ContextType::CARRERA, false],
    [Permissions::CARRERAS_CREAR, ContextType::FACULTAD, false], // facultad no es el parent directo
    [Permissions::CARRERAS_CREAR, ContextType::CURSO, false],

    // Permisos de facultades (solo propio, no tiene ancestros contextuales)
    [Permissions::FACULTADES_VER, ContextType::FACULTAD, true],
    [Permissions::FACULTADES_VER, ContextType::GLOBAL , true],
    [Permissions::FACULTADES_VER, ContextType::CARRERA, false],
    [Permissions::FACULTADES_VER, ContextType::CURSO, false],

    // Permisos de cursos (acepta propio + toda la cadena ancestral)
    [Permissions::CURSOS_VER, ContextType::CURSO, true],
    [Permissions::CURSOS_VER, ContextType::GLOBAL , true],
    [Permissions::CURSOS_VER, ContextType::CARRERA, true],  // ancestro contextual de curso
    [Permissions::CURSOS_VER, ContextType::DEPARTAMENTO, true],  // ancestro contextual
    [Permissions::CURSOS_VER, ContextType::FACULTAD, true],  // ancestro contextual

    // Permisos de cursos:crear (parent_action → carrera)
    [Permissions::CURSOS_CREAR, ContextType::CARRERA, true],
    [Permissions::CURSOS_CREAR, ContextType::GLOBAL , true],
    [Permissions::CURSOS_CREAR, ContextType::CURSO, false],
    [Permissions::CURSOS_CREAR, ContextType::FACULTAD, false],

    // Permisos de usuarios (solo GLOBAL)
    [Permissions::USUARIOS_VER, ContextType::GLOBAL , true],
    [Permissions::USUARIOS_VER, ContextType::CARRERA, false],
    [Permissions::USUARIOS_VER, ContextType::FACULTAD, false],
    [Permissions::USUARIOS_VER, ContextType::CURSO, false],

    // Permisos de departamentos (acepta propio + ancestros: facultad)
    [Permissions::DEPARTAMENTOS_VER, ContextType::DEPARTAMENTO, true],
    [Permissions::DEPARTAMENTOS_VER, ContextType::GLOBAL , true],
    [Permissions::DEPARTAMENTOS_VER, ContextType::FACULTAD, true],  // ancestro contextual de departamento
    [Permissions::DEPARTAMENTOS_VER, ContextType::CARRERA, false], // carrera no es ancestro de departamento

    // Wildcard
    [Permissions::GLOBAL_WILDCARD, ContextType::GLOBAL , true],
    [Permissions::GLOBAL_WILDCARD, ContextType::CARRERA, false],
    [Permissions::GLOBAL_WILDCARD, ContextType::FACULTAD, false],
  ];

  foreach ($matrix as [$permission, $contextType, $expectedValid]) {
    $label = $expectedValid ? '✓' : '✗';
    test("{$label} {$permission->value} × {$contextType->value} → " . ($expectedValid ? 'válido' : 'inválido'), function () use ($permission, $contextType, $expectedValid) {
      expect(PermissionContextConstraints::isValidAssignment($permission, $contextType))->toBe($expectedValid);
    });
  }
});
