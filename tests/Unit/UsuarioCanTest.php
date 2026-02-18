<?php

use App\Services\Authorization\PermissionValidator;
use App\Services\ContextResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// Cargar stubs
require_once __DIR__ . '/../Stubs/CarreraStub.php';
require_once __DIR__ . '/../Stubs/UsuarioStub.php';

use App\Models\Administrativo\Carrera as CarreraStub;
use App\Models\Usuario\Usuario as UsuarioStub;

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Crear un mock de conexión a BD preconfigurado con los métodos básicos
 */
function createDbConnectionMock()
{
    $mock = Mockery::mock();
    $mock->shouldReceive('table')->andReturnSelf();
    $mock->shouldReceive('join')->andReturnSelf();
    $mock->shouldReceive('select')->andReturnSelf();
    $mock->shouldReceive('where')->andReturnSelf();
    $mock->shouldReceive('whereIn')->andReturnSelf();
    $mock->shouldReceive('orderByRaw')->andReturnSelf();
    return $mock;
}

/**
 * Autenticar un usuario stub en el sistema Auth de Laravel
 */
function authenticateUser($usuario)
{
    Auth::shouldReceive('user')->andReturn($usuario);
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('id')->andReturn($usuario->id_usuario);
}

// ============================================================================
// SETUP Y HELPERS
// ============================================================================

beforeEach(function () {
    // Mock del Cache (sin caché en tests)
    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('put')->andReturn(true);
});

// ============================================================================
// TESTS DE Auth::user()->can() CON SLUGS DE PERMISOS (SIN POLICY)
// ============================================================================

test('Auth::user()->can() con slug de permiso usa PermissionValidator como fallback', function () {
    // Crear y autenticar usuario con ID 10
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 10;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD preconfigurado
    $connectionMock = createDbConnectionMock();

    // Simular llamadas a BD:
    // 1ra: loadGlobalContextId() obtiene el contexto global
    // 2da: checkSpecialPermission() encuentra permiso en UPE
    $connectionMock->shouldReceive('first')->andReturn(
        (object) ['id_contexto' => 1], // loadGlobalContextId
        (object) ['esta_permitido' => true] // checkSpecialPermission
    );

    // isSuperAdmin() retorna false
    $connectionMock->shouldReceive('exists')->andReturn(false);

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([5]);

    // Mock del Gate para simular que NO hay Policy registrada
    Gate::shouldReceive('getPolicyFor')->andReturn(null);

    // Bind del ContextResolver en el contenedor
    app()->instance(ContextResolver::class, $contextResolver);

    // Crear recurso
    $facultad = new CarreraStub(5);

    // Verificar que Auth::user()->can() funciona con slug de permiso
    expect(Auth::user()->can('facultad:ver', $facultad))->toBeTrue();
});

test('Auth::user()->can() con wildcard global (*) detecta superadmin', function () {
    // Crear y autenticar usuario superadmin
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 1;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular que es superadmin (tiene permiso '*' en contexto global)
    $connectionMock->shouldReceive('first')->once()->andReturn((object) ['id_contexto' => 1]);
    $connectionMock->shouldReceive('exists')->andReturn(true); // isSuperAdmin

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    app()->instance(ContextResolver::class, $contextResolver);

    // Verificar que can('*') funciona correctamente
    expect(Auth::user()->can('*'))->toBeTrue();
});

test('Auth::user()->can() sin recurso usa contexto del usuario actual', function () {
    // Crear y autenticar usuario superadmin
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 1;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular que es superadmin
    $connectionMock->shouldReceive('first')->once()->andReturn((object) ['id_contexto' => 1]);
    $connectionMock->shouldReceive('exists')->andReturn(true); // isSuperAdmin

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    app()->instance(ContextResolver::class, $contextResolver);

    // Verificar que superadmin puede hacer cualquier acción sin pasar recurso
    expect(Auth::user()->can('facultad:crear'))->toBeTrue();
    expect(Auth::user()->can('curso:eliminar'))->toBeTrue();
});

// ============================================================================
// TESTS DE can() CON PERMISOS DENEGADOS
// ============================================================================

test('Auth::user()->can() retorna false cuando no tiene permiso', function () {
    // Crear y autenticar usuario sin permisos
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 50;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular que no tiene permisos
    $connectionMock->shouldReceive('first')->andReturn(
        (object) ['id_contexto' => 1], // loadGlobalContextId
        null // checkSpecialPermission (no UPE)
    );
    $connectionMock->shouldReceive('exists')->andReturn(false, false); // no superadmin, no rol

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([10]);

    // Mock del Gate
    Gate::shouldReceive('getPolicyFor')->andReturn(null);

    app()->instance(ContextResolver::class, $contextResolver);

    $facultad = new CarreraStub(10);

    // Verificar que el usuario NO puede eliminar (sin permisos)
    expect(Auth::user()->can('facultad:eliminar', $facultad))->toBeFalse();
});

// ============================================================================
// TESTS DE can() CON UPE DENY
// ============================================================================

test('Auth::user()->can() respeta DENY de UPE', function () {
    // Crear y autenticar usuario con DENY explícito
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 20;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular DENY en UPE
    $connectionMock->shouldReceive('first')->andReturn(
        (object) ['id_contexto' => 1], // loadGlobalContextId
        (object) ['esta_permitido' => false] // checkSpecialPermission (DENY)
    );
    $connectionMock->shouldReceive('exists')->andReturn(false); // no superadmin

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([8]);

    // Mock del Gate
    Gate::shouldReceive('getPolicyFor')->andReturn(null);

    app()->instance(ContextResolver::class, $contextResolver);

    $curso = new CarreraStub(8);

    // Verificar que el DENY se respeta
    expect(Auth::user()->can('curso:editar', $curso))->toBeFalse();
});

// ============================================================================
// TESTS DE can() CON HABILIDADES ESTÁNDAR (NO SLUGS)
// ============================================================================

test('Auth::user()->can() con habilidad estándar sin Policy retorna false', function () {
    // Crear y autenticar usuario
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 30;
    authenticateUser($usuario);

    $facultad = new CarreraStub(5);

    // can() con habilidad estándar ('view') sin Policy debe retornar false
    // porque parent::can() retornará false y no es un slug con ':'
    expect(Auth::user()->can('view', $facultad))->toBeFalse();
    expect(Auth::user()->can('create'))->toBeFalse();
});

// ============================================================================
// TESTS DE CASOS REALES CON Auth::user()
// ============================================================================

test('caso real: profesor autenticado puede ver cursos de su departamento', function () {
    // Crear y autenticar profesor
    $profesor = new UsuarioStub();
    $profesor->id_usuario = 100;
    authenticateUser($profesor);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    $connectionMock->shouldReceive('first')->andReturn(
        (object) ['id_contexto' => 1], // loadGlobalContextId
        null // checkSpecialPermission (no UPE)
    );
    $connectionMock->shouldReceive('exists')->andReturn(
        false, // isSuperAdmin
        true   // checkRolePermission (tiene por rol)
    );

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([50]);

    // Mock del Gate
    Gate::shouldReceive('getPolicyFor')->andReturn(null);

    app()->instance(ContextResolver::class, $contextResolver);

    $curso = new CarreraStub(50);

    // Verificar usando Auth::user()->can()
    expect(Auth::user()->can('curso:ver', $curso))->toBeTrue();
});

test('caso real: estudiante autenticado NO puede eliminar inscripciones', function () {
    // Crear y autenticar estudiante
    $estudiante = new UsuarioStub();
    $estudiante->id_usuario = 102;
    authenticateUser($estudiante);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    $connectionMock->shouldReceive('first')->andReturn(
        (object) ['id_contexto' => 1],
        null
    );
    $connectionMock->shouldReceive('exists')->andReturn(false, false);

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getContextId')->andReturn([70]);

    // Mock del Gate
    Gate::shouldReceive('getPolicyFor')->andReturn(null);

    app()->instance(ContextResolver::class, $contextResolver);

    $inscripcion = new CarreraStub(70);

    // Verificar usando Auth::user()->can()
    expect(Auth::user()->can('inscripcion:eliminar', $inscripcion))->toBeFalse();
});

// ============================================================================
// TEST DE INTEGRACIÓN: can() RESPETA POLICIES REGISTRADAS
// ============================================================================

test('Auth::user()->can() respeta decisión de Policy cuando está registrada', function () {
    // Crear y autenticar usuario
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 40;
    authenticateUser($usuario);

    $facultad = new CarreraStub(5);

    // Simular que hay una Policy registrada que deniega
    $mockPolicy = Mockery::mock('FacultadPolicy');
    Gate::shouldReceive('getPolicyFor')->with($facultad)->andReturn($mockPolicy);

    // parent::can() retornará false (Policy deniega)
    // can() debe respetar esa decisión y NO hacer fallback a PermissionValidator

    // Como hay Policy, can() debe retornar false sin intentar PermissionValidator
    expect(Auth::user()->can('facultad:editar', $facultad))->toBeFalse();
});
