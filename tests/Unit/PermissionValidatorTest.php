<?php

use App\Services\Authorization\PermissionValidator;
use App\Services\ContextResolver;
use App\Services\Authorization\GlobalContextService;
use App\Support\Permissions;
use App\Enums\ContextType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Cargar stubs
require_once __DIR__ . '/../Stubs/CarreraStub.php';
require_once __DIR__ . '/../Stubs/UsuarioStub.php';

use App\Models\Administrativo\Carrera as CarreraStub;
use App\Models\Usuario\Usuario as UsuarioStub;

// ============================================================================
// SETUP Y HELPERS
// ============================================================================

beforeEach(function () {
    // Mock de GlobalContextService para evitar consultas a BD
    Mockery::close();

    $globalContextMock = Mockery::mock(GlobalContextService::class);
    $globalContextMock->shouldReceive('getContextId')
        ->andReturn(1);

    app()->instance(GlobalContextService::class, $globalContextMock);

    // La caché de permisos usa el store `array` que fija phpunit.xml, y Laravel
    // lo recrea entre tests. Antes se mockeaba la fachada Cache con get/put; el
    // validador ahora usa Cache::remember, así que un mock parcial fallaría.
    Cache::flush();
});

afterEach(function () {
    Mockery::close();
});

/**
 * Crear un base mock que soporta encadenamiento de métodos
 * Retorna self para todos los métodos de construcción de query
 */
function createConnectionMock()
{
    $mock = Mockery::mock();
    $mock->shouldReceive('table')->andReturnSelf();
    $mock->shouldReceive('join')->andReturnSelf();
    $mock->shouldReceive('select')->andReturnSelf();
    $mock->shouldReceive('where')->andReturnSelf();
    $mock->shouldReceive('whereIn')->andReturnSelf();
    $mock->shouldReceive('orderByRaw')->andReturnSelf();
    $mock->shouldReceive('distinct')->andReturnSelf();
    $mock->shouldReceive('limit')->andReturnSelf();
    return $mock;
}

// ============================================================================
// TESTS DE SUPERADMIN GLOBAL
// ============================================================================

test('superadmin con permiso * en contexto global puede hacer todo', function () {
    // Instanciar un usuario con ID 1
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 1;

    // Crear mock de la conexión a BD preconfigurado con métodos básicos
    $connectionMock = createConnectionMock();

    // hasSuperAdminPermission() usa ->exists() returns true para superadmin
    $connectionMock->shouldReceive('exists')->andReturn(true);

    // Inyectar el mock en la fachada DB
    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver (no se usa en este test pero es requerido por constructor)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);

    // Verificar que el superadmin puede hacer cualquier acción
    expect(
        $validator->validate($usuario, Permissions::FACULTADES_VER)
    )->toBeTrue();
    expect(
        $validator->validate($usuario, Permissions::CURSOS_EDITAR)
    )->toBeTrue();
});

// ============================================================================
// TESTS DE PERMISOS EXACTOS
// ============================================================================

test('usuario con permiso exacto puede realizar esa acción', function () {
    // Instanciar un usuario con ID 2
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 2;

    // Crear mock de la conexión a BD preconfigurado
    $connectionMock = createConnectionMock();

    // isSuperAdmin() retorna false (no es superadmin)
    $connectionMock->shouldReceive('exists')
        ->andReturn(false);

    // checkSpecialPermission() usa get() y retorna una colección con el permiso exacto
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([
            (object) ['slug' => 'facultades:ver', 'esta_permitido' => true]
        ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver para retornar el contexto del recurso (id_contexto = 5)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([1]);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([5]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(5)
        ->andReturn([
            ['id_contexto' => 5, 'categoria' => ContextType::FACULTAD],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    // Instanciar el validador y el recurso (facultad con id_contexto = 5)
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $facultad = new CarreraStub(5);

    // Verificar que el usuario puede ver la facultad (tiene permiso exacto 'facultad:ver' en UPE)
    expect($validator->validate($usuario, Permissions::FACULTADES_VER, $facultad))->toBeTrue();
});

// ============================================================================
// TESTS DE PRIORIDAD UPE > URA
// ============================================================================

test('UPE con DENY sobrescribe permiso de URA', function () {
    // Instanciar un usuario con ID 4 que tiene DENY en UPE pero GRANT en URA
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 4;

    // Crear mock de la conexión a BD preconfigurado
    $connectionMock = createConnectionMock();

    // isSuperAdmin() retorna false (no es superadmin)
    $connectionMock->shouldReceive('exists')
        ->andReturn(false);

    // checkSpecialPermission() retorna DENY en UPE (misma variable $facultad que se usa debajo)
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([
            (object) ['slug' => 'facultades:editar', 'esta_permitido' => false]
        ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver para retornar el contexto del recurso (id_contexto = 15)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([1]);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([15]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(15)
        ->andReturn([
            ['id_contexto' => 15, 'categoria' => ContextType::FACULTAD],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    // Instanciar el validador y el recurso
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $facultad = new CarreraStub(15);

    // Verificar que el usuario NO puede editar (DENY en UPE tiene prioridad sobre cualquier permiso de rol)
    expect($validator->validate($usuario, Permissions::FACULTADES_EDITAR, $facultad))->toBeFalse();
});

// ============================================================================
// TESTS DE CASOS REALES
// ============================================================================

test('caso real: profesor puede ver cursos de su departamento', function () {
    // Instanciar un profesor con ID 100
    $profesor = new UsuarioStub();
    $profesor->id_usuario = 100;

    // Crear mock de la conexión a BD preconfigurado
    $connectionMock = createConnectionMock();

    // isSuperAdmin() retorna false (no es superadmin)
    $connectionMock->shouldReceive('exists')
        ->andReturn(false, true); // false for isSuperAdmin, true for checkRolePermission

    // checkSpecialPermission() retorna colección vacía (no hay UPE)
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver para retornar el contexto del curso (id_contexto = 50)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([1]);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([50]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(50)
        ->andReturn([
            ['id_contexto' => 50, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    // Instanciar el validador y el recurso (curso)
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $curso = new CarreraStub(50);

    // Verificar que el profesor puede ver el curso (tiene permiso 'curso:ver' por su rol en ese contexto)
    expect($validator->validate($profesor, Permissions::CURSOS_VER, $curso))->toBeTrue();
});

test('caso real: estudiante NO puede eliminar inscripciones', function () {
    // Instanciar un estudiante con ID 102 (rol sin permisos de eliminación)
    $estudiante = new UsuarioStub();
    $estudiante->id_usuario = 102;

    // Crear mock de la conexión a BD preconfigurado
    $connectionMock = createConnectionMock();

    // isSuperAdmin() y checkRolePermission() retornan false
    $connectionMock->shouldReceive('exists')
        ->andReturn(false, false);

    // checkSpecialPermission() retorna colección vacía
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver para retornar el contexto de la inscripción (id_contexto = 70)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([1]);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([70]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(70)
        ->andReturn([
            ['id_contexto' => 70, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    // Instanciar el validador y el recurso (inscripción)
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $inscripcion = new CarreraStub(70);

    // Verificar que el estudiante NO puede eliminar inscripciones (no tiene permiso)
    expect(
        $validator->validate(
            $estudiante,
            Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES,
            $inscripcion
        )
    )->toBeFalse();
});

// ============================================================================
// TESTS DE HERENCIA DE PERMISOS DESDE PADRE CONTEXTUAL
// ============================================================================

test('herencia: permiso cursos:ver asignado en carrera aplica a un curso hijo', function () {
    // Caso: usuario tiene 'cursos:ver' en contexto CARRERA (15)
    // Valida: acceso a un CURSO (50) que es hijo de esa carrera
    // Esperado: TRUE (el permiso del padre hereda al hijo)
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 5;

    $connectionMock = createConnectionMock();

    // isSuperAdmin → false; checkRolePermission(ctx 50) → false
    $connectionMock->shouldReceive('exists')
        ->andReturn(false);

    // checkSpecialPermission(ctx 50) → null (sin UPE)
    // checkSpecialPermission(ctx 15) → GRANT (permiso del padre)
    $connectionMock->shouldReceive('get')
        ->andReturn(
            collect([]),
            collect([(object) ['slug' => 'cursos:ver', 'esta_permitido' => true]])
        );

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([50]);
    // La función SQL retorna cadena completa: curso→carrera→global
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(50)
        ->andReturn([
            ['id_contexto' => 50, 'nivel' => 0, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 15, 'nivel' => 1, 'categoria' => ContextType::CARRERA],
            ['id_contexto' => 1,  'nivel' => 2, 'categoria' => ContextType::GLOBAL],
        ]);

    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $curso = new CarreraStub(50);

    expect($validator->validate($usuario, Permissions::CURSOS_VER, $curso))->toBeTrue();
});

test('herencia: permiso cursos:ver asignado en departamento aplica a un curso nieto', function () {
    // Caso: usuario tiene 'cursos:ver' vía ROL en contexto DEPARTAMENTO (8)
    // Valida: acceso a un CURSO (50), nieto del departamento
    // Esperado: TRUE (el permiso del abuelo hereda al nieto)
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 6;

    $connectionMock = createConnectionMock();

    // Llamadas a exists():
    // 1. isSuperAdmin → false
    // 2. checkRolePermission(ctx 50) → false
    // 3. checkRolePermission(ctx 15) → false
    // 4. checkRolePermission(ctx 8)  → TRUE (tiene rol en departamento)
    $connectionMock->shouldReceive('exists')
        ->andReturn(false, false, false, true);

    // checkSpecialPermission → null en todos los contextos (sin UPE)
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([50]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(50)
        ->andReturn([
            ['id_contexto' => 50, 'nivel' => 0, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 15, 'nivel' => 1, 'categoria' => ContextType::CARRERA],
            ['id_contexto' => 8,  'nivel' => 2, 'categoria' => ContextType::DEPARTAMENTO],
            ['id_contexto' => 1,  'nivel' => 3, 'categoria' => ContextType::GLOBAL],
        ]);

    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $curso = new CarreraStub(50);

    expect($validator->validate($usuario, Permissions::CURSOS_VER, $curso))->toBeTrue();
});

test('herencia: cursos:crear en departamento NO aplica (tipo de contexto inválido para ese permiso)', function () {
    // Caso: usuario tiene 'cursos:crear' en contexto DEPARTAMENTO (8)
    // cursos:crear solo es válido en ['global', 'carrera'] — departamento es inválido
    // expandWithAncestors() filtra y solo retorna contextos de tipo 'global' y 'carrera'
    // El contexto 8 (departamento) no estará en el target → falla la validación
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 7;

    $connectionMock = createConnectionMock();

    // isSuperAdmin → false; role(ctx 15) → false; role(ctx 1) → false
    $connectionMock->shouldReceive('exists')
        ->andReturn(false);

    // Sin UPE en ningún contexto
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([50]);
    // Cadena completa incluyendo departamento (donde el usuario TIENE el permiso)
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(50)
        ->andReturn([
            ['id_contexto' => 50, 'nivel' => 0, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 15, 'nivel' => 1, 'categoria' => ContextType::CARRERA],
            ['id_contexto' => 8,  'nivel' => 2, 'categoria' => ContextType::DEPARTAMENTO], // ← filtrado fuera
            ['id_contexto' => 1,  'nivel' => 3, 'categoria' => ContextType::GLOBAL],
        ]);

    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $curso = new CarreraStub(50);

    // cursos:crear en departamento no aplica (tipo inválido), debe retornar false
    expect($validator->validate($usuario, Permissions::CURSOS_CREAR, $curso))->toBeFalse();
});

test('herencia: DENY explícito en hijo revoca GRANT del padre (hijo gana)', function () {
    // Caso: usuario tiene DENY vía UPE en CURSO (50) pero GRANT vía UPE en CARRERA (15)
    // Esperado: FALSE — el DENY del contexto más específico prevalece sobre el GRANT del padre
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 8;

    $connectionMock = createConnectionMock();

    // isSuperAdmin → false; no más exists() porque el DENY corta el loop
    $connectionMock->shouldReceive('exists')
        ->andReturn(false);

    // checkSpecialPermission(ctx 50) retorna DENY → return false inmediatamente
    // (no se llega a consultar ctx 15)
    $connectionMock->shouldReceive('get')
        ->andReturn(
            collect([(object) ['slug' => 'cursos:ver', 'esta_permitido' => false]])
        );

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([50]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(50)
        ->andReturn([
            ['id_contexto' => 50, 'nivel' => 0, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 15, 'nivel' => 1, 'categoria' => ContextType::CARRERA],
            ['id_contexto' => 1,  'nivel' => 2, 'categoria' => ContextType::GLOBAL],
        ]);

    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $curso = new CarreraStub(50);

    expect($validator->validate($usuario, Permissions::CURSOS_VER, $curso))->toBeFalse();
});

// ============================================================================
// MATRIZ GRANT/DENY CON MÚLTIPLES COINCIDENCIAS (A-2)
// ============================================================================
//
// checkSpecialPermission() puede recibir varias filas UPE que coinciden a la vez
// con el permiso pedido (exacta, wildcard de recurso, wildcard global). La
// implementación anterior decidía por el ORDEN de las filas y no por su
// especificidad, así que el veredicto dependía de cómo Postgres devolviera el
// resultset. Estos tests fijan la regla: gana la menor prioridad y, en empate,
// gana el DENY.

/**
 * Monta un validador cuyo UPE devuelve exactamente las filas indicadas.
 *
 * @param array<int, array{0: string, 1: bool}> $filasUpe pares [slug, esta_permitido]
 */
function validatorConUpe(array $filasUpe): PermissionValidator
{
    $connectionMock = createConnectionMock();

    // isSuperAdmin() → false, y checkRolePermission() → false: sólo decide el UPE.
    $connectionMock->shouldReceive('exists')->andReturn(false);

    $connectionMock->shouldReceive('get')->andReturn(
        collect(array_map(
            fn(array $fila) => (object) ['slug' => $fila[0], 'esta_permitido' => $fila[1]],
            $filasUpe
        ))
    );

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([1]);
    $contextResolver->shouldReceive('getModelContextId')->andReturn([5]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')->with(5)
        ->andReturn([
            ['id_contexto' => 5, 'categoria' => ContextType::FACULTAD],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL],
        ]);

    return new PermissionValidator($contextResolver, app(GlobalContextService::class));
}

test('match exacto DENY vence al wildcard de recurso GRANT', function () {
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 20;

    $validator = validatorConUpe([
        ['facultades:*', true],    // prioridad 2
        ['facultades:ver', false], // prioridad 1 → gana
    ]);

    expect($validator->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeFalse();
});

test('match exacto GRANT vence al wildcard de recurso DENY', function () {
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 21;

    $validator = validatorConUpe([
        ['facultades:*', false],  // prioridad 2
        ['facultades:ver', true], // prioridad 1 → gana
    ]);

    expect($validator->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeTrue();
});

test('wildcard de recurso DENY vence al wildcard global GRANT', function () {
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 22;

    $validator = validatorConUpe([
        ['*', true],             // prioridad 4
        ['facultades:*', false], // prioridad 2 → gana
    ]);

    expect($validator->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeFalse();
});

test('wildcard de recurso GRANT vence al wildcard global DENY', function () {
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 23;

    $validator = validatorConUpe([
        ['*', false],           // prioridad 4
        ['facultades:*', true], // prioridad 2 → gana
    ]);

    expect($validator->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeTrue();
});

test('el veredicto no depende del orden en que llegan las filas', function (int $idUsuario, array $filas) {
    // Mismo conjunto de filas en distinto orden: el resultado debe ser idéntico
    // (el DENY exacto, prioridad 1). Éste es exactamente el bug A-2: antes ganaba
    // la última fila iterada, así que el veredicto lo fijaba Postgres.
    //
    // Cada caso usa un id_usuario distinto para que la caché de permisos no
    // pueda dar por bueno un veredicto calculado en el caso anterior.
    $usuario = new UsuarioStub();
    $usuario->id_usuario = $idUsuario;

    expect(validatorConUpe($filas)->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeFalse();
})->with([
    'especifico primero' => [240, [['facultades:ver', false], ['facultades:*', true], ['*', true]]],
    'generico primero'   => [241, [['*', true], ['facultades:*', true], ['facultades:ver', false]]],
    'intercalado'        => [242, [['facultades:*', true], ['facultades:ver', false], ['*', true]]],
]);

test('en empate de prioridad gana el DENY', function () {
    // Dos asignaciones UPE del mismo slug con veredictos opuestos: misma
    // especificidad, así que se resuelve por el lado seguro.
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 26;

    $validator = validatorConUpe([
        ['facultades:ver', true],
        ['facultades:ver', false],
    ]);

    expect($validator->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeFalse();
});

test('un slug malformado en la BD no anula al resto de coincidencias', function () {
    // La fila corrupta se descarta con log y el veredicto lo da la fila válida.
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 27;

    $validator = validatorConUpe([
        ['esto-no-es-un-slug-valido', false],
        ['facultades:ver', true],
    ]);

    expect($validator->validate($usuario, Permissions::FACULTADES_VER, new CarreraStub(5)))
        ->toBeTrue();
});
