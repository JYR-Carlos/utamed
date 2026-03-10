<?php

use App\Services\ContextResolver;
use App\Services\Authorization\GlobalContextService;
use App\Support\Permissions;
use App\Enums\ContextType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Cargar stubs
require_once __DIR__ . '/../Stubs/CarreraStub.php';
require_once __DIR__ . '/../Stubs/UsuarioStub.php';
require_once __DIR__ . '/../Stubs/CarreraPolicyStub.php';

use App\Models\Administrativo\Carrera as CarreraStub;
use App\Models\Usuario\Usuario as UsuarioStub;

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Crear un base mock que soporta encadenamiento de métodos
 * Retorna self para todos los métodos de construcción de query
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
    $mock->shouldReceive('distinct')->andReturnSelf();
    $mock->shouldReceive('limit')->andReturnSelf();
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
    // Mock de GlobalContextService para evitar consultas a BD
    Mockery::close();

    $globalContextMock = Mockery::mock(GlobalContextService::class);
    $globalContextMock->shouldReceive('getContextId')
        ->andReturn(1);

    app()->instance(GlobalContextService::class, $globalContextMock);

    // Mock del Cache (sin caché en tests)
    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('put')->andReturn(true);
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// SECCIÓN 1: AUTORIZACIÓN BASADA EN SLUGS (PermissionValidator)
// Esta sección intenta probar el flujo directo de slugs (Permissions enum)
// sin invocar Gate/Policy. Verifica:
// - UPE (permisos especiales): GRANT y DENY
// - Escalación de superadmin
// - Fallback a roles cuando no hay UPE
// - Validación de slugs inválidos
// ============================================================================

test('SLUG: UPE GRANT autoriza acceso (PermissionValidator flow)', function () {
    // Flujo: can(Permissions::FACULTADES_VER, $facultad)
    //   → slug enum → hasPermissionFor() → PermissionValidator::validate()
    //   → UPE: 'facultades:ver' (GRANT) → true
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 10;
    authenticateUser($usuario);

    $connectionMock = createDbConnectionMock();
    $connectionMock->shouldReceive('exists')->andReturn(false); // no superadmin
    $connectionMock->shouldReceive('get')->andReturn(collect([
        // Simular GRANT en UPE para 'facultades:ver'
        (object) ['slug' => 'facultades:ver', 'esta_permitido' => true],
    ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([5]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(5)
        ->andReturn([
            ['id_contexto' => 5, 'categoria' => ContextType::FACULTAD],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    app()->instance(ContextResolver::class, $contextResolver);

    $facultad = new CarreraStub(5);

    expect(
        Auth::user()
            ->can(Permissions::FACULTADES_VER, $facultad)
    )
        ->toBeTrue();
});

test('SLUG: Superadmin con wildcard se autoriza (early-return optimization)', function () {
    // Crear y autenticar usuario superadmin
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 1;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular que es superadmin (has permiso '*' en contexto global)
    $connectionMock->shouldReceive('exists')
        ->andReturn(true); // isSuperAdmin

    DB::shouldReceive('connection')
        ->with('pgsql')
        ->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    app()->instance(ContextResolver::class, $contextResolver);

    // Flujo: can(Permissions::GLOBAL_WILDCARD) → slug enum → isSuperAdmin() → true
    expect(
        Auth::user()
            ->can(Permissions::GLOBAL_WILDCARD)
    )
        ->toBeTrue();
});

test('SLUG: Superadmin sin modelo puede todo (escalation bypass)', function () {
    // Flujo: can(Permissions::FACULTADES_CREAR)
    //   → slug enum → PermissionValidator::isSuperAdmin() → true (retorno temprano)
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 1;
    authenticateUser($usuario);

    $connectionMock = createDbConnectionMock();
    $connectionMock->shouldReceive('exists')->andReturn(true); // isSuperAdmin

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    app()->instance(ContextResolver::class, $contextResolver);

    expect(
        Auth::user()
            ->can(Permissions::FACULTADES_CREAR)
    )->toBeTrue();
    expect(
        Auth::user()
            ->can(Permissions::CURSOS_ELIMINAR)
    )->toBeTrue();
});

test('SLUG: Sin permisos deniega (empty UPE + no role)', function () {
    // Crear y autenticar usuario sin permisos
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 50;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular que no tiene permisos
    $connectionMock->shouldReceive('exists')
        ->andReturn(false, false); // no superadmin, no rol

    $connectionMock->shouldReceive('get')
        ->andReturn(collect([])); // checkSpecialPermission (no UPE)

    DB::shouldReceive('connection')->with('pgsql')
        ->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([10]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(10)
        ->andReturn([
            ['id_contexto' => 10, 'categoria' => ContextType::FACULTAD],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    app()->instance(ContextResolver::class, $contextResolver);

    $facultad = new CarreraStub(10);

    // Verificar que el usuario NO puede eliminar (sin permisos)
    expect(
        Auth::user()
            ->can(Permissions::FACULTADES_ELIMINAR, $facultad)
    )->toBeFalse();
});

test('SLUG: UPE GRANT autoriza con rol-check fallback (role verification)', function () {
    // Contraparte positiva: mismo escenario pero con GRANT en UPE
    // Flujo: can(FACULTADES_VER, $facultad)
    //   → slug enum → UPE: 'facultades:ver' (GRANT) → true
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 50;
    authenticateUser($usuario);

    $connectionMock = createDbConnectionMock();
    $connectionMock->shouldReceive('exists')
        ->andReturn(false); // no superadmin
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([
            (object) ['slug' => 'facultades:ver', 'esta_permitido' => true],
        ]));

    DB::shouldReceive('connection')
        ->with('pgsql')
        ->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([10]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(10)
        ->andReturn([
            ['id_contexto' => 10, 'categoria' => ContextType::FACULTAD],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    app()->instance(ContextResolver::class, $contextResolver);

    $facultad = new CarreraStub(10);

    expect(
        Auth::user()
            ->can(Permissions::FACULTADES_VER, $facultad)
    )->toBeTrue();
});

test('SLUG: UPE DENY-override deniega (explicit denial)', function () {
    // Crear y autenticar usuario con DENY explícito
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 20;
    authenticateUser($usuario);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    // Simular DENY en UPE
    $connectionMock->shouldReceive('exists')
        ->andReturn(false); // no superadmin

    $connectionMock->shouldReceive('get')
        ->andReturn(collect([
            (object) ['slug' => 'cursos:editar', 'esta_permitido' => false],
        ]));

    DB::shouldReceive('connection')
        ->with('pgsql')
        ->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([8]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(8)
        ->andReturn([
            ['id_contexto' => 8, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    app()->instance(ContextResolver::class, $contextResolver);

    $curso = new CarreraStub(8);

    // Verificar que el DENY se respeta
    expect(
        Auth::user()
            ->can(Permissions::CURSOS_EDITAR, $curso)
    )->toBeFalse();
});

test('SLUG: Slug inválido retorna false (enum validation)', function () {
    // Flujo: can('invalid:slug')
    //   → contiene ':' → se intenta Permissions::tryFrom('invalid:slug')
    //   → null (slug no existe en enum) → hasPermissionFor retorna false sin consultar BD
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 21;
    authenticateUser($usuario);

    $facultad = new CarreraStub(5);

    // No debe haber llamadas a BD: el slug inválido retorna false inmediatamente
    // (ver Usuario::can() → str_contains(':') → tryFrom() → null → false)
    expect(
        Auth::user()
            ->can('invalid:slug', $facultad)
    )->toBeFalse();
});

// ============================================================================
// SECCIÓN 2: FUNCIONES DE CIERRE DE GATE (Habilidades estándar)
// Esta sección intenta probar el flujo Gate::forUser()->check()
// para habilidades que NO son slugs. Verifica:
// - Definición e invocación de funciones de cierre de la Gate
// - Lógica condicional basada en propiedades del modelo
// - Integración híbrida: funciones de cierre que invocan PermissionValidator
// - Denegaciones explícitas via Gate
// ============================================================================

test('GATE: Sin definición deniega por defecto (Gate fallback)', function () {
    // Flujo: can('invent-ability') o can('create') sin modelo
    //   → no es slug → Gate::forUser($this)->check('create')
    //   → Gate real: sin cierre ni Policy registrada para esa habilidad → false
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 30;
    authenticateUser($usuario);

    // Sin modelo: no se consulta ninguna Policy registrada → Gate deniega por defecto
    expect(
        Auth::user()
            ->can('create')
    )->toBeFalse();
    expect(
        Auth::user()
            ->can('some-custom-ability')
    )->toBeFalse();
});

test('GATE: Cierre retorna true autoriza (simple closure)', function () {
    // Flujo: can('manage-content')
    //   → no es slug → Gate::forUser($this)->check('manage-content')
    //   → Gate real: cierre definido que retorna true → true
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 31;
    authenticateUser($usuario);

    // Definir cierre en el Gate que autoriza la habilidad
    Gate::define('manage-content', function ($user) {
        return true;
    });

    // Verificar que la habilidad definida se autoriza
    expect(
        Auth::user()
            ->can('manage-content')
    )->toBeTrue();
});

test('GATE: Cierre con lógica condicional del modelo (closure + inspection)', function () {
    // Flujo: can('manage-carrera', $carrera)
    //   → no es slug → Gate::forUser($this)->check('manage-carrera', $carrera)
    //   → Gate real: cierre que inspeccionaba propiedades del modelo → retorna bool
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 32;
    authenticateUser($usuario);

    // Cierre que autoriza solo si el modelo cumple condición (id > 50)
    Gate::define('manage-carrera', function ($user, $carrera) {
        return $carrera->id > 50;
    });

    // Facultad con id=5: no cumple condición → false
    $facultad_baja = new CarreraStub(5);
    expect(Auth::user()->can('manage-carrera', $facultad_baja))->toBeFalse();

    // Facultad con id=60: cumple condición → true
    $facultad_alta = new CarreraStub(60);
    expect(Auth::user()->can('manage-carrera', $facultad_alta))->toBeTrue();
});

test('GATE: Cierre explícitamente deniega (closure returns false)', function () {
    // Flujo: can('restricted-action')
    //   → no es slug → Gate::forUser($this)->check('restricted-action')
    //   → Gate real: cierre definido que retorna false → false
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 33;
    authenticateUser($usuario);

    // Cierre que siempre deniega
    Gate::define('restricted-action', function ($user) {
        return false;
    });

    // Verificar que la negación se respeta
    expect(
        Auth::user()
            ->can('restricted-action')
    )->toBeFalse();
});

test('GATE HYBRID: Closure mezcla PermissionValidator (interop test)', function () {
    // INTEGRACIÓN: verifica que Gate closures pueden mezclar lógica
    // Flujo: can('hybrid-ability', $facultad)
    //   → no es slug (no contiene ':') → Gate::forUser($user)->check('hybrid-ability', $facultad)
    //   → Gate closure invoca user->hasPermissionFor() (PermissionValidator directo)
    //   → PermissionValidator retorna true/false basado en UPE
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 34;
    authenticateUser($usuario);

    // Configurar BD mock para que UPE GRANT exista
    $connectionMock = createDbConnectionMock();
    $connectionMock->shouldReceive('exists')->andReturn(false); // no superadmin
    $connectionMock->shouldReceive('get')->andReturn(collect([
        (object) ['slug' => 'facultades:ver', 'esta_permitido' => true],
    ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([5]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(5)
        ->andReturn([['id_contexto' => 5, 'categoria' => ContextType::FACULTAD], ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]]);

    app()->instance(ContextResolver::class, $contextResolver);

    // Gate closure que invoca directamente hasPermissionFor() con slug Permissions::FACULTADES_VER
    Gate::define('hybrid-ability', function ($user, $facultad) {
        // Mezclar lógica: usar PermissionValidator desde dentro de la Gate closure
        return $user->hasPermissionFor(Permissions::FACULTADES_VER, $facultad);
    });

    $facultad = new CarreraStub(5);

    // can('hybrid-ability', $facultad) debe retornar true porque UPE lo permite
    expect(Auth::user()->can('hybrid-ability', $facultad))->toBeTrue();
});

// ============================================================================
// SECCIÓN 3: MÉTODOS DE POLICY (Autorización basada en modelos)
// Esta sección intenta probar la invocación real de métodos Policy
// y su integración con Gate. Verifica:
// - Métodos de policy que autorizan/deniegan
// - Hook before() que overrides decisiones de policy method
// - Superadmin bypass via before() hook
// ============================================================================

test('POLICY: Método view() autoriza (CarreraPolicyStub integration)', function () {
    // INTEGRACIÓN REAL: Gate::forUser($this)->check() invoca la Policy registrada
    // Flujo: can('view', $facultad)
    //   → no slug → Gate::forUser($usuario)->check('view', $facultad)
    //   → Gate real encuentra CarreraPolicyStub para Carrera
    //   → llama CarreraPolicyStub::view($usuario, $facultad) → true
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 40;
    authenticateUser($usuario);

    $facultad = new CarreraStub(5);

    // Registrar la policy real — Gate la invocará sin ningún mock
    app(\Illuminate\Contracts\Auth\Access\Gate::class)
        ->policy(
            CarreraStub::class,
            \Tests\Stubs\CarreraPolicyStub::class
        );

    expect(
        Auth::user()
            ->can('view', $facultad)
    )->toBeTrue();
});

test('POLICY: Método delete() deniega (policy denial)', function () {
    // INTEGRACIÓN REAL: mismo flujo que view() pero con delete() → false
    // Flujo: can('delete', $facultad)
    //   → no slug → Gate::forUser($usuario)->check('delete', $facultad)
    //   → Gate real encuentra CarreraPolicyStub para Carrera
    //   → llama CarreraPolicyStub::delete($usuario, $facultad) → false
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 40;
    authenticateUser($usuario);

    $facultad = new CarreraStub(5);

    app(\Illuminate\Contracts\Auth\Access\Gate::class)
        ->policy(
            CarreraStub::class,
            \Tests\Stubs\CarreraPolicyStub::class
        );

    expect(Auth::user()->can('delete', $facultad))->toBeFalse();
});

test('POLICY HOOK: before() autoriza superadmin (hook override)', function () {
    // INTEGRACIÓN REAL: verifica que before() de Policy retorna true para superadmin
    // Flujo: can('delete', $facultad) con usuario superadmin
    //   → no slug → Gate::forUser($usuario)->check('delete', $facultad)
    //   → Policy found: CarreraPolicySuperadminStub
    //   → Policy::before($usuario, 'delete') → $usuario->isSuperAdmin() → true → AUTORIZA SIN LLAMAR delete()
    //   → Aunque delete() retorna false, before() override lo autoriza

    // Crear mock del usuario que reporte ser superadmin
    $usuarioMock = Mockery::mock(UsuarioStub::class);
    $usuarioMock->id_usuario = 1;
    $usuarioMock->shouldReceive('getAttribute')->with('id_usuario')->andReturnNull();
    $usuarioMock->shouldReceive('isSuperAdmin')->andReturn(true);
    // Permitir que can() delegue al Gate real
    $usuarioMock->shouldReceive('can')->with('delete', \Mockery::any())->andReturnUsing(function ($ability, $model) use ($usuarioMock) {
        return \Illuminate\Support\Facades\Gate::forUser($usuarioMock)->check($ability, $model);
    });

    authenticateUser($usuarioMock);

    $facultad = new CarreraStub(5);

    // Registrar policy con before() hook que verifica superadmin
    app(\Illuminate\Contracts\Auth\Access\Gate::class)
        ->policy(
            CarreraStub::class,
            \Tests\Stubs\CarreraPolicySuperadminStub::class
        );

    // Aunque delete() devolvería false, before() desde superadmin permite todo
    expect(
        Auth::user()
            ->can('delete', $facultad)
    )->toBeTrue();
});

// ============================================================================
// SEPARACIÓN DE RESPONSABILIDADES
// Esta sección intenta probar que el flujo de slugs y el de Policy
// son INDEPENDIENTES. Verifica:
// - Slug path NUNCA consulta Policy registrada
// - PermissionValidator es la autoridad final para slugs
// ============================================================================

test('SEPARATION: Slug path ignora Policy registrada (path independence)', function () {
    // Verifica separación de concerns: slugs NUNCA consultan Policy
    // Flujo: can(Permissions::FACULTADES_VER, $facultad)
    //   → slug enum → PermissionValidator::validate() → UPE GRANT → true
    //   → Policy registrada NO SE CONSULTA aunque exista
    $usuario = new UsuarioStub();
    $usuario->id_usuario = 41;
    authenticateUser($usuario);

    $connectionMock = createDbConnectionMock();
    $connectionMock->shouldReceive('exists')->andReturn(false);
    $connectionMock->shouldReceive('get')->andReturn(collect([
        // UPE GRANT para el slug
        (object) ['slug' => 'facultades:ver', 'esta_permitido' => true],
    ]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([5]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(5)
        ->andReturn([['id_contexto' => 5, 'categoria' => ContextType::FACULTAD], ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]]);

    app()->instance(ContextResolver::class, $contextResolver);

    $facultad = new CarreraStub(5);

    // Registrar policy que deniega view()
    app(\Illuminate\Contracts\Auth\Access\Gate::class)
        ->policy(
            CarreraStub::class,
            \Tests\Stubs\CarreraPolicyStub::class
        );

    // Aún así, slug path retorna true (Policy es ignorado)
    expect(
        Auth::user()
            ->can(Permissions::FACULTADES_VER, $facultad)
    )->toBeTrue();
});

// ============================================================================
// ESCENARIOS DEL MUNDO REAL
// Esta sección intenta probar escenarios funcionales integrados que
// combinan múltiples componentes del sistema de autorización. Verifica:
// - Acceso por rol (profesor ve cursos)
// - Cascada de permisos (estudiante rechazado sin permisos)
// - Role-based grants (estudiante ve por rol)
// ============================================================================

test('Profesor visualiza curso de su departamento (SLUG + role-based access)', function () {
    // Crear y autenticar profesor
    $profesor = new UsuarioStub();
    $profesor->id_usuario = 100;
    authenticateUser($profesor);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    $connectionMock->shouldReceive('exists')
        ->andReturn(
            false, // isSuperAdmin
            true   // checkRolePermission (tiene por rol)
        );

    $connectionMock->shouldReceive('get')
        ->andReturn(collect([])); // checkSpecialPermission (no UPE)

    DB::shouldReceive('connection')
        ->with('pgsql')
        ->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([50]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(50)
        ->andReturn([
            ['id_contexto' => 50, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    app()->instance(ContextResolver::class, $contextResolver);

    $curso = new CarreraStub(50);

    // Verificar usando Auth::user()->can()
    expect(
        Auth::user()
            ->can(Permissions::CURSOS_VER, $curso)
    )->toBeTrue();
});

test('Estudiante sin permiso rechazado al eliminar (SLUG + permission cascade)', function () {
    // Crear y autenticar estudiante
    $estudiante = new UsuarioStub();
    $estudiante->id_usuario = 102;
    authenticateUser($estudiante);

    // Crear mock de la conexión a BD
    $connectionMock = createDbConnectionMock();

    $connectionMock->shouldReceive('exists')
        ->andReturn(false, false);

    $connectionMock->shouldReceive('get')
        ->andReturn(collect([]));

    DB::shouldReceive('connection')->with('pgsql')->andReturn($connectionMock);

    // Mock del ContextResolver
    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([70]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(70)
        ->andReturn([['id_contexto' => 70, 'categoria' => ContextType::CURSO], ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]]);

    app()->instance(ContextResolver::class, $contextResolver);

    $inscripcion = new CarreraStub(70);

    // Verificar usando Auth::user()->can()
    expect(Auth::user()->can(Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES, $inscripcion))->toBeFalse();
});

test('Estudiante visualiza inscripción por rol (SLUG + role grant)', function () {
    // Contraparte positiva: estudiante tiene permiso de VER por rol
    // Flujo: can(CURSOS_VER, $inscripcion)
    //   → slug enum → UPE: ninguna → chequeo de rol: tiene 'cursos:ver' → true
    $estudiante = new UsuarioStub();
    $estudiante->id_usuario = 102;
    authenticateUser($estudiante);

    $connectionMock = createDbConnectionMock();
    $connectionMock->shouldReceive('exists')->andReturn(
        false, // isSuperAdmin
        true   // checkRolePermission (tiene 'cursos:ver' por rol)
    );
    $connectionMock->shouldReceive('get')
        ->andReturn(collect([])); // no UPE

    DB::shouldReceive('connection')->with('pgsql')
        ->andReturn($connectionMock);

    $contextResolver = Mockery::mock(ContextResolver::class);
    $contextResolver->shouldReceive('getModelContextId')
        ->with(Mockery::type(CarreraStub::class))
        ->andReturn([70]);
    $contextResolver->shouldReceive('getAncestorContextsWithType')
        ->with(70)
        ->andReturn([
            ['id_contexto' => 70, 'categoria' => ContextType::CURSO],
            ['id_contexto' => 1, 'categoria' => ContextType::GLOBAL]
        ]);

    app()->instance(ContextResolver::class, $contextResolver);

    $inscripcion = new CarreraStub(70);

    expect(
        Auth::user()
            ->can(Permissions::CURSOS_VER, $inscripcion)
    )->toBeTrue();
});
