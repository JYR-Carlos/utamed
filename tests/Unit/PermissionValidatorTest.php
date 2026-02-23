<?php

use App\Services\Authorization\PermissionValidator;
use App\Services\ContextResolver;
use App\Services\Authorization\GlobalContextService;
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

    // Mock del Cache (sin caché en tests)
    Cache::shouldReceive('get')->andReturn(null); // No guarda nada en realidad
    Cache::shouldReceive('put')->andReturn(true); // No tira error al intentar guardar en caché
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
    expect($validator->validate($usuario, 'facultad:ver'))->toBeTrue();
    expect($validator->validate($usuario, 'curso:editar'))->toBeTrue();
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
            (object) ['slug' => 'facultad:ver', 'esta_permitido' => true]
        ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver para retornar el contexto del recurso (id_contexto = 5)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([5]);

    // Instanciar el validador y el recurso (facultad con id_contexto = 5)
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $facultad = new CarreraStub(5);

    // Verificar que el usuario puede ver la facultad (tiene permiso exacto 'facultad:ver' en UPE)
    expect($validator->validate($usuario, 'facultad:ver', $facultad))->toBeTrue();
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
            (object) ['slug' => 'facultad:editar', 'esta_permitido' => false]
        ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver para retornar el contexto del recurso (id_contexto = 15)
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([15]);

    // Instanciar el validador y el recurso
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $facultad = new CarreraStub(15);

    // Verificar que el usuario NO puede editar (DENY en UPE tiene prioridad sobre cualquier permiso de rol)
    expect($validator->validate($usuario, 'facultad:editar', $facultad))->toBeFalse();
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
    $contextResolver->shouldReceive('getContextId')->andReturn([50]);

    // Instanciar el validador y el recurso (curso)
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $curso = new CarreraStub(50);

    // Verificar que el profesor puede ver el curso (tiene permiso 'curso:ver' por su rol en ese contexto)
    expect($validator->validate($profesor, 'curso:ver', $curso))->toBeTrue();
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
    $contextResolver->shouldReceive('getContextId')->andReturn([70]);

    // Instanciar el validador y el recurso (inscripción)
    $globalContext = app(GlobalContextService::class);
    $validator = new PermissionValidator($contextResolver, $globalContext);
    $inscripcion = new CarreraStub(70);

    // Verificar que el estudiante NO puede eliminar inscripciones (no tiene permiso)
    expect($validator->validate($estudiante, 'inscripcion:eliminar', $inscripcion))->toBeFalse();
});
