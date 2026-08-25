<?php

use App\Services\ContextResolver;

// Cargar los stubs (se cargan solo en testing)
require_once __DIR__ . '/../../Stubs/CarreraStub.php';
require_once __DIR__ . '/../../Stubs/PlanStub.php';
require_once __DIR__ . '/../../Stubs/AsignacionPlanStub.php';
require_once __DIR__ . '/../../Stubs/UsuarioStub.php';
require_once __DIR__ . '/../../Stubs/CursoStub.php';
require_once __DIR__ . '/../../Stubs/EstudianteStub.php';
require_once __DIR__ . '/../../Stubs/InscripcionCursoStub.php';

// Usar alias para evitar conflictos con los modelos reales
use App\Models\Administrativo\Carrera as CarreraStub;
use App\Models\Administrativo\Plan as PlanStub;
use App\Models\Administrativo\AsignacionPlan as AsignacionPlanStub;
use App\Models\Usuario\Usuario as UsuarioStub;
use App\Models\Curso\Curso as CursoStub;
use App\Models\Usuario\Estudiante as EstudianteStub;
use App\Models\Curso\InscripcionCurso as InscripcionCursoStub;
use App\Services\Authorization\GlobalContextService;

// ============================================================================
// SETUP
// ============================================================================

beforeEach(function () {
    // Mock de GlobalContextService para evitar consultas a BD
    // El ContextResolver depende de esto para modelos globales
    Mockery::close();

    $globalContextMock = Mockery::mock(GlobalContextService::class);
    $globalContextMock->shouldReceive('getContextId')
        ->andReturn(1);

    app()->instance(GlobalContextService::class, $globalContextMock);
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// TESTS DE CONFIGURACIÓN
// ============================================================================



test('mappings se cargan correctamente del archivo', function () {
    $resolver = app(ContextResolver::class);

    $reflection = new ReflectionClass($resolver);
    $method = $reflection->getMethod('loadGeneratedContextMappings');
    $method->setAccessible(true);

    $mappings = $method->invoke($resolver);

    expect($mappings)->toBeArray();
    expect(count($mappings))->toBeGreaterThan(0);

    // Verificar algunos mappings clave
    expect($mappings['Administrativo\\Carrera']['type'])->toBe('direct');
    expect($mappings['Administrativo\\AsignacionPlan']['type'])->toBe('hierarchical');
    expect($mappings['Usuario\\Usuario']['type'])->toBe('global');
});

// ============================================================================
// TESTS DE CONTEXTO DIRECTO
// ============================================================================

test('Carrera (directo) retorna su id_contexto correctamente', function () {
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(42);

    $contextIds = $resolver->getModelContextId($carrera);
    $contextType = $resolver->getModelContextTypes($carrera);

    expect($contextIds)->toBe([42]);
    expect($contextType)->toBe(['carrera']);
});

// ============================================================================
// TESTS DE CONTEXTO JERÁRQUICO
// ============================================================================

test('Plan (jerárquico 1 nivel) resuelve contexto desde Carrera', function () {
    // Jerarquía: Plan → Carrera (direct)
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(123);
    $plan = new PlanStub($carrera);

    $contextIds = $resolver->getModelContextId($plan);
    $contextType = $resolver->getModelContextTypes($plan);

    expect($contextIds)->toBe([123]);
    expect($contextType)->toBe(['carrera']);
});

test('AsignacionPlan (jerárquico 2 niveles) resuelve contexto desde Plan → Carrera', function () {
    // Jerarquía: AsignacionPlan → Plan → Carrera (direct)
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(99);
    $plan = new PlanStub($carrera);
    $asignacionPlan = new AsignacionPlanStub($plan);

    $contextIds = $resolver->getModelContextId($asignacionPlan);
    $contextType = $resolver->getModelContextTypes($asignacionPlan);

    // Debe resolver hasta la Carrera y obtener su id_contexto
    expect($contextIds)->toBe([99]);
    expect($contextType)->toBe(['carrera']);
});


// ============================================================================
// TESTS DE CONTEXTO GLOBAL
// ============================================================================

test('Usuario (global) retorna contexto global', function () {
    // Jerarquía: Usuario (global) → contexto global
    $resolver = app(ContextResolver::class);

    $usuario = new UsuarioStub();

    $contextIds = $resolver->getModelContextId($usuario);
    $contextType = $resolver->getModelContextTypes($usuario);

    // Usuario es global: contexto global (id=1), sin tipos de contexto propios
    expect($contextIds)->toBe([1]);
    expect($contextType)->toBe([]);
});

// ============================================================================
// TESTS DE getParentContextModel
// ============================================================================

test('getParentContextModel retorna el modelo padre de AsignacionPlan', function () {
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(42);
    $plan = new PlanStub($carrera);
    $asignacionPlan = new AsignacionPlanStub($plan);

    $parentModel = $resolver->getParentContextModel($asignacionPlan);

    // El padre debería ser la Carrera (el modelo directo final)
    expect($parentModel)->toBe($carrera);
});

test('getParentContextModel retorna null para modelo directo', function () {
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(42);

    $parentModel = $resolver->getParentContextModel($carrera);

    // Carrera es directo, no tiene padre
    expect($parentModel)->toBeNull();
});

test('getParentContextModel retorna null para modelo global', function () {
    $resolver = app(ContextResolver::class);

    $usuario = new UsuarioStub();

    $parentModel = $resolver->getParentContextModel($usuario);

    // Usuario es global, no tiene padre
    expect($parentModel)->toBeNull();
});

// ============================================================================
// TESTS DE CACHÉ
// ============================================================================

test('clearCache limpia el caché del resolver', function () {
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(42);

    // Primera resolución
    $contextIds1 = $resolver->getModelContextId($carrera);

    // Limpiar caché
    $resolver->clearCache();

    // Segunda resolución
    $contextIds2 = $resolver->getModelContextId($carrera);

    // Ambos deben ser iguales (misma Carrera)
    expect($contextIds1)->toBe([42]);
    expect($contextIds2)->toBe([42]);
})->skip('no implementado aún');

test('invalidateCache invalida el caché de un modelo específico', function () {
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(42);

    // Resolver contexto
    $contextIds = $resolver->getModelContextId($carrera);
    expect($contextIds)->toBe([42]);

    // Invalidar caché de este modelo
    $resolver->invalidateCache($carrera);

    // Debe poder resolver nuevamente sin error
    $contextIds2 = $resolver->getModelContextId($carrera);
    expect($contextIds2)->toBe([42]);
})->skip('no implementado aún');

// ============================================================================
// TESTS DE MULTIPLICIDAD DE CAMINOS
// ============================================================================

test('Curso (directo) retorna su id_contexto directamente a pesar de tener caminos jerárquicos', function () {
    // Jerarquía: Curso (direct) — ignora AsignacionPlan → Plan → Carrera
    $resolver = app(ContextResolver::class);

    $carrera = new CarreraStub(99);
    $plan = new PlanStub($carrera);
    $asignacionPlan = new AsignacionPlanStub($plan);
    $curso = new CursoStub($asignacionPlan);
    $curso->setAttribute('id_contexto', 77);

    $contextIds = $resolver->getModelContextId($curso);
    $contextType = $resolver->getModelContextTypes($curso);

    expect($contextIds)->toBe([77]);
    expect($contextType)->toBe(['curso']);
});

test('Estudiante (global) resuelve al contexto global', function () {
    // Jerarquía: Estudiante (global) → contexto global (ignora Carrera asociada)
    $resolver = app(ContextResolver::class);

    // Estudiante es GLOBAL, por lo que resuelve al contexto global (id_contexto = 1)
    $carrera = new CarreraStub(88);
    $estudiante = new EstudianteStub(null, $carrera);

    $contextIds = $resolver->getModelContextId($estudiante);
    $contextType = $resolver->getModelContextTypes($estudiante);

    // Estudiante es global: contexto global (id=1), sin tipos de contexto propios
    expect($contextIds)->toBe([1]);
    expect($contextType)->toBe([]);
});

test('InscripcionCurso retorna contexto del Curso y Carrera asociada', function () {
    $resolver = app(ContextResolver::class);

    // InscripcionCurso → Curso (hierarchical)
    // Curso tiene id_contexto = 100
    $curso = new CursoStub(null);
    $curso->setAttribute('id_contexto', 100);

    // Estudiante → Carrera (hierarchical)
    // Carrera tiene id_contexto = 200
    $carrera = new CarreraStub(200);
    $estudiante = new EstudianteStub(null, $carrera);

    // InscripcionCurso con ambos caminos disponibles
    $inscripcion = new InscripcionCursoStub($curso, $estudiante);

    // Debe retornar contexto del Curso y Carrera asociada
    $contextIds = $resolver->getModelContextId($inscripcion);

    expect($contextIds)->toBe([100, 200]);
});
