<?php

use App\Services\Authorization\WildcardMatcher;

// ============================================================================
// TESTS DE MATCHING BÁSICO
// ============================================================================

test('match exacto retorna true', function () {
    expect(WildcardMatcher::matches('facultad:ver', 'facultad:ver'))->toBeTrue();
    expect(WildcardMatcher::matches('curso:editar', 'curso:editar'))->toBeTrue();
    expect(WildcardMatcher::matches('estudiante:eliminar', 'estudiante:eliminar'))->toBeTrue();
});

test('match diferente retorna false', function () {
    expect(WildcardMatcher::matches('facultad:ver', 'facultad:editar'))->toBeFalse();
    expect(WildcardMatcher::matches('curso:ver', 'facultad:ver'))->toBeFalse();
    expect(WildcardMatcher::matches('estudiante:crear', 'curso:crear'))->toBeFalse();
});

// ============================================================================
// TESTS DE WILDCARD GLOBAL (*)
// ============================================================================

test('wildcard global (*) coincide con cualquier slug', function () {
    expect(WildcardMatcher::matches('facultad:ver', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('curso:editar', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('estudiante:eliminar', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('cualquier:cosa', '*'))->toBeTrue();
});

test('isGlobalWildcard detecta * correctamente', function () {
    expect(WildcardMatcher::isGlobalWildcard('*'))->toBeTrue();
    expect(WildcardMatcher::isGlobalWildcard('facultad:*'))->toBeFalse();
    expect(WildcardMatcher::isGlobalWildcard('facultad:ver'))->toBeFalse();
});

// ============================================================================
// TESTS DE WILDCARD DE RECURSO (recurso:*)
// ============================================================================

test('wildcard de recurso coincide con cualquier acción del mismo recurso', function () {
    expect(WildcardMatcher::matches('facultad:ver', 'facultad:*'))->toBeTrue();
    expect(WildcardMatcher::matches('facultad:editar', 'facultad:*'))->toBeTrue();
    expect(WildcardMatcher::matches('facultad:eliminar', 'facultad:*'))->toBeTrue();
    expect(WildcardMatcher::matches('facultad:crear', 'facultad:*'))->toBeTrue();
});

test('wildcard de recurso NO coincide con otros recursos', function () {
    expect(WildcardMatcher::matches('curso:ver', 'facultad:*'))->toBeFalse();
    expect(WildcardMatcher::matches('estudiante:editar', 'curso:*'))->toBeFalse();
    expect(WildcardMatcher::matches('plan:eliminar', 'carrera:*'))->toBeFalse();
});

test('isResourceWildcard detecta recurso:* correctamente', function () {
    expect(WildcardMatcher::isResourceWildcard('facultad:*'))->toBeTrue();
    expect(WildcardMatcher::isResourceWildcard('curso:*'))->toBeTrue();
    expect(WildcardMatcher::isResourceWildcard('*'))->toBeFalse();
    expect(WildcardMatcher::isResourceWildcard('facultad:ver'))->toBeFalse();
});

// ============================================================================
// TESTS DE EXTRACCIÓN DE PARTES
// ============================================================================

test('extractResource obtiene la parte del recurso correctamente', function () {
    expect(WildcardMatcher::extractResource('facultad:ver'))->toBe('facultad');
    expect(WildcardMatcher::extractResource('curso:editar'))->toBe('curso');
    expect(WildcardMatcher::extractResource('estudiante:*'))->toBe('estudiante');
    expect(WildcardMatcher::extractResource('*'))->toBe('*');
});

test('extractAction obtiene la parte de la acción correctamente', function () {
    expect(WildcardMatcher::extractAction('facultad:ver'))->toBe('ver');
    expect(WildcardMatcher::extractAction('curso:editar'))->toBe('editar');
    expect(WildcardMatcher::extractAction('estudiante:*'))->toBe('*');
    expect(WildcardMatcher::extractAction('*'))->toBeNull();
});

// ============================================================================
// TESTS DE CONVERSIÓN A WILDCARD
// ============================================================================

test('toResourceWildcard convierte slug a wildcard de recurso', function () {
    expect(WildcardMatcher::toResourceWildcard('facultad:ver'))->toBe('facultad:*');
    expect(WildcardMatcher::toResourceWildcard('curso:editar'))->toBe('curso:*');
    expect(WildcardMatcher::toResourceWildcard('estudiante:eliminar'))->toBe('estudiante:*');
});

test('toResourceWildcard con wildcard global retorna el mismo', function () {
    expect(WildcardMatcher::toResourceWildcard('*'))->toBe('*');
});

test('toResourceWildcard con wildcard de recurso retorna el mismo', function () {
    expect(WildcardMatcher::toResourceWildcard('facultad:*'))->toBe('facultad:*');
});

// ============================================================================
// TESTS DE PRIORIDAD
// ============================================================================

test('getPriority retorna 1 para match exacto', function () {
    expect(WildcardMatcher::getPriority('facultad:ver', 'facultad:ver'))->toBe(1);
    expect(WildcardMatcher::getPriority('curso:editar', 'curso:editar'))->toBe(1);
});

test('getPriority retorna 2 para wildcard de recurso', function () {
    expect(WildcardMatcher::getPriority('facultad:ver', 'facultad:*'))->toBe(2);
    expect(WildcardMatcher::getPriority('curso:editar', 'curso:*'))->toBe(2);
});

test('getPriority retorna 3 para wildcard global', function () {
    expect(WildcardMatcher::getPriority('facultad:ver', '*'))->toBe(3);
    expect(WildcardMatcher::getPriority('curso:editar', '*'))->toBe(3);
});

test('getPriority retorna 999 para slugs no relacionados', function () {
    // getPriority se usa DESPUÉS de verificar matches(), 
    // solo retorna 999 si no hay match exacto, ni es wildcard
    expect(WildcardMatcher::getPriority('facultad:ver', 'curso:ver'))->toBe(999);
    expect(WildcardMatcher::getPriority('facultad:ver', 'curso:editar'))->toBe(999);
});

// ============================================================================
// TESTS DE CASOS REALES COMBINADOS
// ============================================================================

test('caso real: admin de facultad con wildcard puede hacer todo en facultad', function () {
    // Admin tiene permiso 'facultad:*'
    $tienePermiso = WildcardMatcher::matches('facultad:ver', 'facultad:*');
    expect($tienePermiso)->toBeTrue();
    
    $tienePermiso = WildcardMatcher::matches('facultad:editar', 'facultad:*');
    expect($tienePermiso)->toBeTrue();
    
    $tienePermiso = WildcardMatcher::matches('facultad:eliminar', 'facultad:*');
    expect($tienePermiso)->toBeTrue();
    
    // Pero NO puede hacer cosas en otros recursos
    $tienePermiso = WildcardMatcher::matches('curso:ver', 'facultad:*');
    expect($tienePermiso)->toBeFalse();
});

test('caso real: superadmin con * puede hacer todo', function () {
    // Superadmin tiene permiso '*'
    expect(WildcardMatcher::matches('facultad:ver', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('curso:editar', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('estudiante:eliminar', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('cualquier:accion', '*'))->toBeTrue();
});

test('caso real: usuario con permiso específico solo puede esa acción', function () {
    // Usuario solo tiene 'facultad:ver'
    expect(WildcardMatcher::matches('facultad:ver', 'facultad:ver'))->toBeTrue();
    expect(WildcardMatcher::matches('facultad:editar', 'facultad:ver'))->toBeFalse();
    expect(WildcardMatcher::matches('curso:ver', 'facultad:ver'))->toBeFalse();
});
