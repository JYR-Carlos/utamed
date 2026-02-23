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

test('getPriority retorna 4 para wildcard global', function () {
    expect(WildcardMatcher::getPriority('facultad:ver', '*'))->toBe(4);
    expect(WildcardMatcher::getPriority('curso:editar', '*'))->toBe(4);
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

// ============================================================================
// TESTS DE SLUGS ANIDADOS - MATCH EXACTO
// ============================================================================

test('match exacto de slug anidado retorna true', function () {
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'cursos/inscripciones:ver'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:gestionar', 'usuarios/permisos/roles:gestionar'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/actividades/grupos:crear', 'cursos/actividades/grupos:crear'))->toBeTrue();
});

test('match exacto de slug anidado con acción diferente retorna false', function () {
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'cursos/inscripciones:editar'))->toBeFalse();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:ver', 'usuarios/permisos/roles:crear'))->toBeFalse();
});

test('slug anidado no coincide con slug plano del mismo nombre base', function () {
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'cursos:ver'))->toBeFalse();
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'inscripciones:ver'))->toBeFalse();
});

// ============================================================================
// TESTS DE SLUGS ANIDADOS - WILDCARD DE MISMO RECURSO
// ============================================================================

test('wildcard de recurso anidado coincide con cualquier acción del mismo recurso', function () {
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'cursos/inscripciones:*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/inscripciones:eliminar_inscripciones', 'cursos/inscripciones:*'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:gestionar', 'usuarios/permisos/roles:*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/actividades/grupos:crear', 'cursos/actividades/grupos:*'))->toBeTrue();
});

test('wildcard de recurso anidado NO coincide con otro recurso del mismo nivel', function () {
    expect(WildcardMatcher::matches('cursos/secciones:ver', 'cursos/inscripciones:*'))->toBeFalse();
    expect(WildcardMatcher::matches('usuarios/permisos/individuales:ver', 'usuarios/permisos/roles:*'))->toBeFalse();
});

// ============================================================================
// TESTS DE SLUGS ANIDADOS - WILDCARD ANCESTRO (herencia)
// ============================================================================

test('wildcard de recurso padre hereda a hijos directos', function () {
    // 'cursos:*' debe matchear cualquier acción en cualquier subrecrurso de cursos
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'cursos:*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/secciones:crear', 'cursos:*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/unidades:editar', 'cursos:*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/actividades:eliminar', 'cursos:*'))->toBeTrue();
});

test('wildcard de recurso padre hereda a nietos y profundidades arbitrarias', function () {
    expect(WildcardMatcher::matches('cursos/actividades/grupos:ver', 'cursos:*'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:gestionar', 'usuarios:*'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/individuales:ver_disponibles', 'usuarios:*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/actividades/grupos:crear', 'cursos/actividades:*'))->toBeTrue();
});

test('wildcard de recurso padre NO hereda en dirección inversa', function () {
    // 'cursos/inscripciones:*' NO debe matchear 'cursos:ver' (cursos es el padre, no el hijo)
    expect(WildcardMatcher::matches('cursos:ver', 'cursos/inscripciones:*'))->toBeFalse();
    expect(WildcardMatcher::matches('usuarios:ver', 'usuarios/permisos:*'))->toBeFalse();
});

test('wildcard de recurso padre NO hereda a recursos del mismo nivel con prefijo similar', function () {
    // 'cursos:*' NO debe matchear 'cursos_extra:ver' (no es hijo, solo tiene prefijo similar)
    expect(WildcardMatcher::matches('cursos_extra:ver', 'cursos:*'))->toBeFalse();
    expect(WildcardMatcher::matches('cursosV2/inscripciones:ver', 'cursos:*'))->toBeFalse();
});

test('wildcard de recurso plano NO matchea recursos anidados (solo hereda con :*)', function () {
    // 'cursos:ver' (acción específica, no wildcard) NO matchea 'cursos/inscripciones:ver'
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', 'cursos:ver'))->toBeFalse();
});

// ============================================================================
// TESTS DE EXTRACCIÓN CON SLUGS ANIDADOS
// ============================================================================

test('extractResource soporta rutas anidadas', function () {
    expect(WildcardMatcher::extractResource('cursos/inscripciones:ver'))->toBe('cursos/inscripciones');
    expect(WildcardMatcher::extractResource('usuarios/permisos/roles:gestionar'))->toBe('usuarios/permisos/roles');
    expect(WildcardMatcher::extractResource('cursos/actividades/grupos:*'))->toBe('cursos/actividades/grupos');
});

test('extractAction soporta slugs anidados', function () {
    expect(WildcardMatcher::extractAction('cursos/inscripciones:ver'))->toBe('ver');
    expect(WildcardMatcher::extractAction('cursos/inscripciones:*'))->toBe('*');
    expect(WildcardMatcher::extractAction('usuarios/permisos/roles:gestionar'))->toBe('gestionar');
});

test('extractResourceSegments retorna los segmentos de la ruta', function () {
    expect(WildcardMatcher::extractResourceSegments('cursos/inscripciones:ver'))->toBe(['cursos', 'inscripciones']);
    expect(WildcardMatcher::extractResourceSegments('cursos/actividades/grupos:crear'))->toBe(['cursos', 'actividades', 'grupos']);
    expect(WildcardMatcher::extractResourceSegments('facultad:ver'))->toBe(['facultad']);
    expect(WildcardMatcher::extractResourceSegments('*'))->toBe(['*']);
});

test('toResourceWildcard preserva la ruta anidada completa', function () {
    expect(WildcardMatcher::toResourceWildcard('cursos/inscripciones:ver'))->toBe('cursos/inscripciones:*');
    expect(WildcardMatcher::toResourceWildcard('usuarios/permisos/roles:gestionar'))->toBe('usuarios/permisos/roles:*');
    expect(WildcardMatcher::toResourceWildcard('cursos/inscripciones:*'))->toBe('cursos/inscripciones:*');
});

// ============================================================================
// TESTS DE isDescendantResource
// ============================================================================

test('isDescendantResource detecta relación padre-hijo directa', function () {
    expect(WildcardMatcher::isDescendantResource('cursos/inscripciones', 'cursos'))->toBeTrue();
    expect(WildcardMatcher::isDescendantResource('usuarios/permisos', 'usuarios'))->toBeTrue();
});

test('isDescendantResource detecta relación ancestro-nieto', function () {
    expect(WildcardMatcher::isDescendantResource('cursos/actividades/grupos', 'cursos'))->toBeTrue();
    expect(WildcardMatcher::isDescendantResource('usuarios/permisos/roles', 'usuarios'))->toBeTrue();
    expect(WildcardMatcher::isDescendantResource('cursos/actividades/grupos', 'cursos/actividades'))->toBeTrue();
});

test('isDescendantResource retorna false para mismo recurso', function () {
    expect(WildcardMatcher::isDescendantResource('cursos', 'cursos'))->toBeFalse();
    expect(WildcardMatcher::isDescendantResource('cursos/inscripciones', 'cursos/inscripciones'))->toBeFalse();
});

test('isDescendantResource retorna false para dirección inversa', function () {
    expect(WildcardMatcher::isDescendantResource('cursos', 'cursos/inscripciones'))->toBeFalse();
    expect(WildcardMatcher::isDescendantResource('usuarios', 'usuarios/permisos/roles'))->toBeFalse();
});

test('isDescendantResource retorna false para prefijos parciales sin separador', function () {
    expect(WildcardMatcher::isDescendantResource('cursos_extra', 'cursos'))->toBeFalse();
    expect(WildcardMatcher::isDescendantResource('cursosV2/sub', 'cursos'))->toBeFalse();
});

// ============================================================================
// TESTS DE PRIORIDAD CON SLUGS ANIDADOS
// ============================================================================

test('getPriority retorna 1 para match exacto anidado', function () {
    expect(WildcardMatcher::getPriority('cursos/inscripciones:ver', 'cursos/inscripciones:ver'))->toBe(1);
    expect(WildcardMatcher::getPriority('usuarios/permisos/roles:gestionar', 'usuarios/permisos/roles:gestionar'))->toBe(1);
});

test('getPriority retorna 2 para wildcard del mismo recurso anidado', function () {
    expect(WildcardMatcher::getPriority('cursos/inscripciones:ver', 'cursos/inscripciones:*'))->toBe(2);
    expect(WildcardMatcher::getPriority('usuarios/permisos/roles:gestionar', 'usuarios/permisos/roles:*'))->toBe(2);
});

test('getPriority retorna 3 para wildcard de recurso ancestro', function () {
    expect(WildcardMatcher::getPriority('cursos/inscripciones:ver', 'cursos:*'))->toBe(3);
    expect(WildcardMatcher::getPriority('cursos/actividades/grupos:crear', 'cursos:*'))->toBe(3);
    expect(WildcardMatcher::getPriority('usuarios/permisos/roles:gestionar', 'usuarios:*'))->toBe(3);
    expect(WildcardMatcher::getPriority('cursos/actividades/grupos:crear', 'cursos/actividades:*'))->toBe(3);
});

// ============================================================================
// TESTS CASOS REALES CON SLUGS ANIDADOS
// ============================================================================

test('caso real: director puede gestionar todo en cursos con cursos:*', function () {
    // Director tiene permiso 'cursos:*'
    foreach ([
        'cursos/inscripciones:ver',
        'cursos/inscripciones:inscribir_alumnos',
        'cursos/inscripciones:eliminar_inscripciones',
        'cursos/secciones:ver',
        'cursos/secciones:crear',
        'cursos/unidades:ver',
        'cursos/actividades:ver',
        'cursos/actividades:evaluar',
        'cursos/actividades/grupos:ver',
        'cursos/actividades/grupos:crear',
    ] as $slug) {
        expect(WildcardMatcher::matches($slug, 'cursos:*'))
            ->toBeTrue("Se esperaba match de '$slug' con 'cursos:*'");
    }
});

test('caso real: admin de permisos puede gestionar roles con usuarios/permisos/roles:*', function () {
    expect(WildcardMatcher::matches('usuarios/permisos/roles:gestionar', 'usuarios/permisos/roles:*'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:ver', 'usuarios/permisos/roles:*'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:crear', 'usuarios/permisos/roles:*'))->toBeTrue();
    // Pero NO puede gestionar permisos individuales
    expect(WildcardMatcher::matches('usuarios/permisos/individuales:gestionar', 'usuarios/permisos/roles:*'))->toBeFalse();
});

test('caso real: wildcard global matchea slugs anidados', function () {
    expect(WildcardMatcher::matches('cursos/inscripciones:ver', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('usuarios/permisos/roles:gestionar', '*'))->toBeTrue();
    expect(WildcardMatcher::matches('cursos/actividades/grupos:crear', '*'))->toBeTrue();
});

// ============================================================================
// TESTS DE GENERACIÓN DE PATRONES
// ============================================================================

test('generatePermissionPatterns genera patrones para slug simple', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns('facultad:ver');
    expect($patterns)->toBe([
        'facultad:ver',      // exacto
        'facultad:*',        // wildcard recurso
        '*',                 // wildcard global
    ]);
});

test('generatePermissionPatterns genera patrones para slug anidado 2 niveles', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns('cursos/inscripciones:ver');
    expect($patterns)->toBe([
        'cursos/inscripciones:ver',     // exacto
        'cursos/inscripciones:*',       // wildcard mismo recurso
        'cursos:*',                     // wildcard ancestro
        '*',                            // wildcard global
    ]);
});

test('generatePermissionPatterns genera patrones para slug anidado 3 niveles', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns('cursos/actividades/grupos:crear');
    expect($patterns)->toBe([
        'cursos/actividades/grupos:crear',      // exacto
        'cursos/actividades/grupos:*',          // wildcard mismo recurso
        'cursos/actividades:*',                 // wildcard ancestro 1
        'cursos:*',                             // wildcard ancestro 2
        '*',                                    // wildcard global
    ]);
});

test('generatePermissionPatterns genera patrones para slug anidado profundo', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns('usuarios/permisos/roles/miembros:gestionar');
    expect($patterns)->toBe([
        'usuarios/permisos/roles/miembros:gestionar',       // exacto
        'usuarios/permisos/roles/miembros:*',               // wildcard mismo recurso
        'usuarios/permisos/roles:*',                        // wildcard ancestro 1
        'usuarios/permisos:*',                              // wildcard ancestro 2
        'usuarios:*',                                       // wildcard ancestro 3
        '*',                                                // wildcard global
    ]);
});

test('generatePermissionPatterns con wildcard global', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns('*');
    expect($patterns)->toBe([
        '*',       // exacto (que es global)
    ]);
});

test('todos los patrones generados coinciden con el permiso solicitado', function () {
    $requested = 'cursos/actividades/grupos:crear';
    $patterns = WildcardMatcher::generatePermissionPatterns($requested);

    // Todos los patrones deben matchear el permiso solicitado
    foreach ($patterns as $pattern) {
        expect(WildcardMatcher::matches($requested, $pattern))
            ->toBeTrue("Se esperaba match de {$requested} con {$pattern}");
    }
});

test('patrones generados están en orden de especificidad descendente', function () {
    $requested = 'cursos/inscripciones:ver';
    $patterns = WildcardMatcher::generatePermissionPatterns($requested);

    $prevPriority = -1;
    foreach ($patterns as $pattern) {
        $priority = WildcardMatcher::getPriority($requested, $pattern);
        expect($priority >= $prevPriority)->toBeTrue("Patrón {$pattern} con prioridad {$priority} no está en orden");
        $prevPriority = $priority;
    }
});

// ============================================================================
// TESTS CASOS REALES: CONFLICTOS GRANT/DENY CON PRIORIDAD
// ============================================================================

test('caso real: permiso específico DENY gana sobre GRANT ancestro general', function () {
    // Usuario tiene:
    // - cursos:* (GRANT, prioridad 3)
    // - cursos/inscripciones:* (DENY, prioridad 2) ← más específico
    //
    // Solicita: cursos/inscripciones:ver
    // Resultado: DENY gana

    $requested = 'cursos/inscripciones:ver';
    $grantPattern = 'cursos:*';          // prioridad 3
    $denyPattern = 'cursos/inscripciones:*';   // prioridad 2

    $grantPriority = WildcardMatcher::getPriority($requested, $grantPattern);
    $denyPriority = WildcardMatcher::getPriority($requested, $denyPattern);

    expect($denyPriority < $grantPriority)->toBeTrue('El permiso DENY específico debe tener mayor prioridad que el GRANT general');
});

test('caso real: exacta siempre gana', function () {
    $requested = 'cursos/inscripciones:ver';

    $exactPriority = WildcardMatcher::getPriority($requested, 'cursos/inscripciones:ver');
    $wildcardPriority = WildcardMatcher::getPriority($requested, 'cursos/inscripciones:*');
    $ancestorPriority = WildcardMatcher::getPriority($requested, 'cursos:*');
    $globalPriority = WildcardMatcher::getPriority($requested, '*');

    expect($exactPriority < $wildcardPriority)->toBeTrue();
    expect($exactPriority < $ancestorPriority)->toBeTrue();
    expect($exactPriority < $globalPriority)->toBeTrue();
});

