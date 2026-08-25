<?php

use App\Services\Authorization\WildcardMatcher;
use App\Support\Permissions;

// ============================================================================
// TESTS DE MATCHING BÁSICO
// ============================================================================

test('match exacto retorna true', function () {
    expect(WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::FACULTADES_VER))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_EDITAR, Permissions::CURSOS_EDITAR))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_DESHABILITAR, Permissions::USUARIOS_DESHABILITAR))->toBeTrue();
});

test('match diferente retorna false', function () {
    expect(WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::FACULTADES_EDITAR))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::CURSOS_VER, Permissions::FACULTADES_VER))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_CREAR, Permissions::CURSOS_CREAR))->toBeFalse();
});

// ============================================================================
// TESTS DE WILDCARD GLOBAL (*)
// ============================================================================

test('wildcard global (*) coincide con cualquier slug', function () {
    expect(WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_EDITAR, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_DESHABILITAR, Permissions::GLOBAL_WILDCARD))->toBeTrue();
});

test('isGlobalWildcard detecta * correctamente', function () {
    expect(WildcardMatcher::isGlobalWildcard(Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::isGlobalWildcard(Permissions::FACULTADES_ALL))->toBeFalse();
    expect(WildcardMatcher::isGlobalWildcard(Permissions::FACULTADES_VER))->toBeFalse();
});

// ============================================================================
// TESTS DE WILDCARD DE RECURSO (recurso:*)
// ============================================================================

test('wildcard de recurso coincide con cualquier acción del mismo recurso', function () {
    expect(WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::FACULTADES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::FACULTADES_EDITAR, Permissions::FACULTADES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::FACULTADES_ELIMINAR, Permissions::FACULTADES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::FACULTADES_CREAR, Permissions::FACULTADES_ALL))->toBeTrue();
});

test('wildcard de recurso NO coincide con otros recursos', function () {
    expect(WildcardMatcher::matches(Permissions::CURSOS_VER, Permissions::FACULTADES_ALL))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_EDITAR, Permissions::CURSOS_ALL))->toBeFalse();
});

test('isResourceWildcard detecta recurso:* correctamente', function () {
    expect(WildcardMatcher::isResourceWildcard(Permissions::FACULTADES_ALL))->toBeTrue();
    expect(WildcardMatcher::isResourceWildcard(Permissions::CURSOS_ALL))->toBeTrue();
    expect(WildcardMatcher::isResourceWildcard(Permissions::GLOBAL_WILDCARD))->toBeFalse();
    expect(WildcardMatcher::isResourceWildcard(Permissions::FACULTADES_VER))->toBeFalse();
});

// ============================================================================
// TESTS DE EXTRACCIÓN DE PARTES
// ============================================================================

test('extractResource obtiene la parte del recurso correctamente', function () {
    expect(WildcardMatcher::extractResource(Permissions::FACULTADES_VER))->toBe('facultades');
    expect(WildcardMatcher::extractResource(Permissions::CURSOS_EDITAR))->toBe('cursos');
    expect(WildcardMatcher::extractResource(Permissions::USUARIOS_ALL))->toBe('usuarios');
    expect(WildcardMatcher::extractResource(Permissions::GLOBAL_WILDCARD))->toBe('*');
});

test('extractAction obtiene la parte de la acción correctamente', function () {
    expect(WildcardMatcher::extractAction(Permissions::FACULTADES_VER))->toBe('ver');
    expect(WildcardMatcher::extractAction(Permissions::CURSOS_EDITAR))->toBe('editar');
    expect(WildcardMatcher::extractAction(Permissions::USUARIOS_ALL))->toBe('*');
    expect(WildcardMatcher::extractAction(Permissions::GLOBAL_WILDCARD))->toBeNull();
});

// ============================================================================
// TESTS DE CONVERSIÓN A WILDCARD
// ============================================================================

test('toResourceWildcard convierte slug a wildcard de recurso', function () {
    expect(WildcardMatcher::toResourceWildcard(Permissions::FACULTADES_VER))->toBe(Permissions::FACULTADES_ALL->value);
    expect(WildcardMatcher::toResourceWildcard(Permissions::CURSOS_EDITAR))->toBe(Permissions::CURSOS_ALL->value);
    expect(WildcardMatcher::toResourceWildcard(Permissions::USUARIOS_DESHABILITAR))->toBe(Permissions::USUARIOS_ALL->value);
});

test('toResourceWildcard con wildcard global retorna el mismo', function () {
    expect(WildcardMatcher::toResourceWildcard(Permissions::GLOBAL_WILDCARD))->toBe(Permissions::GLOBAL_WILDCARD->value);
});

test('toResourceWildcard con wildcard de recurso retorna el mismo', function () {
    expect(WildcardMatcher::toResourceWildcard(Permissions::FACULTADES_ALL))->toBe(Permissions::FACULTADES_ALL->value);
});

// ============================================================================
// TESTS DE PRIORIDAD
// ============================================================================

test('getPriority retorna 1 para match exacto', function () {
    expect(WildcardMatcher::getPriority(Permissions::FACULTADES_VER, Permissions::FACULTADES_VER))->toBe(1);
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_EDITAR, Permissions::CURSOS_EDITAR))->toBe(1);
});

test('getPriority retorna 2 para wildcard de recurso', function () {
    expect(WildcardMatcher::getPriority(Permissions::FACULTADES_VER, Permissions::FACULTADES_ALL))->toBe(2);
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_EDITAR, Permissions::CURSOS_ALL))->toBe(2);
});

test('getPriority retorna 4 para wildcard global', function () {
    expect(WildcardMatcher::getPriority(Permissions::FACULTADES_VER, Permissions::GLOBAL_WILDCARD))->toBe(4);
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_EDITAR, Permissions::GLOBAL_WILDCARD))->toBe(4);
});

test('getPriority retorna 999 para slugs no relacionados', function () {
    // getPriority se usa DESPUÉS de verificar matches(), 
    // solo retorna 999 si no hay match exacto, ni es wildcard
    expect(WildcardMatcher::getPriority(Permissions::FACULTADES_VER, Permissions::CURSOS_VER))->toBe(999);
    expect(WildcardMatcher::getPriority(Permissions::FACULTADES_VER, Permissions::CURSOS_EDITAR))->toBe(999);
});

// ============================================================================
// TESTS DE CASOS REALES COMBINADOS
// ============================================================================

test('caso real: admin de facultad con wildcard puede hacer todo en facultad', function () {
    // Admin tiene permiso 'facultad:*'
    $tienePermiso = WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::FACULTADES_ALL);
    expect($tienePermiso)->toBeTrue();

    $tienePermiso = WildcardMatcher::matches(Permissions::FACULTADES_EDITAR, Permissions::FACULTADES_ALL);
    expect($tienePermiso)->toBeTrue();

    $tienePermiso = WildcardMatcher::matches(Permissions::FACULTADES_ELIMINAR, Permissions::FACULTADES_ALL);
    expect($tienePermiso)->toBeTrue();

    // Pero NO puede hacer cosas en otros recursos
    $tienePermiso = WildcardMatcher::matches(Permissions::CURSOS_VER, Permissions::FACULTADES_ALL);
    expect($tienePermiso)->toBeFalse();
});

test('caso real: superadmin con * puede hacer todo', function () {
    // Superadmin tiene permiso '*'
    expect(WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_EDITAR, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_DESHABILITAR, Permissions::GLOBAL_WILDCARD))->toBeTrue();
});

test('caso real: usuario con permiso específico solo puede esa acción', function () {
    // Usuario solo tiene Permissions::FACULTADES_VER
    expect(WildcardMatcher::matches(Permissions::FACULTADES_VER, Permissions::FACULTADES_VER))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::FACULTADES_EDITAR, Permissions::FACULTADES_VER))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::CURSOS_VER, Permissions::FACULTADES_VER))->toBeFalse();
});

// ============================================================================
// TESTS DE SLUGS ANIDADOS - MATCH EXACTO
// ============================================================================

test('match exacto de slug anidado retorna true', function () {
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_INSCRIPCIONES_VER))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::ACTIVIDADES_GRUPOS_CREAR, Permissions::ACTIVIDADES_GRUPOS_CREAR))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1))->toBeTrue();
});

test('match exacto de slug anidado con acción diferente retorna false', function () {
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_VER, Permissions::USUARIOS_PERMISOS_ROLES_CREAR))->toBeFalse();
});

test('slug anidado no coincide con slug plano del mismo nombre base', function () {
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_VER))->toBeFalse();
});

// ============================================================================
// TESTS DE SLUGS ANIDADOS - WILDCARD DE MISMO RECURSO
// ============================================================================

test('wildcard de recurso anidado coincide con cualquier acción del mismo recurso', function () {
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_INSCRIPCIONES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES, Permissions::CURSOS_INSCRIPCIONES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::ACTIVIDADES_GRUPOS_CREAR, Permissions::ACTIVIDADES_GRUPOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, Permissions::CURSOS_PROGRAMAS_MODIFICAR_ALL))->toBeTrue();
});

test('wildcard de recurso anidado NO coincide con otro recurso del mismo nivel', function () {
    expect(WildcardMatcher::matches(Permissions::COMPONENTES_VER, Permissions::CURSOS_INSCRIPCIONES_ALL))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_INDIVIDUALES_VER_DISPONIBLES, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBeFalse();
});

// ============================================================================
// TESTS DE SLUGS ANIDADOS - WILDCARD ANCESTRO (herencia)
// ============================================================================

test('wildcard de recurso padre hereda a hijos directos', function () {
    // 'cursos:*' debe matchear cualquier acción en cualquier subrecurso de cursos
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_UNIDADES_CREAR, Permissions::CURSOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_UNIDADES_EDITAR, Permissions::CURSOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_PROGRAMAS_AGREGAR, Permissions::CURSOS_ALL))->toBeTrue();
});

test('wildcard de recurso padre hereda a nietos y profundidades arbitrarias', function () {
    expect(WildcardMatcher::matches(Permissions::ACTIVIDADES_GRUPOS_VER, Permissions::ACTIVIDADES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_INDIVIDUALES_VER_DISPONIBLES, Permissions::USUARIOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::ACTIVIDADES_GRUPOS_CREAR, Permissions::ACTIVIDADES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, Permissions::CURSOS_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, Permissions::CURSOS_PROGRAMAS_ALL))->toBeTrue();
});

test('wildcard de recurso padre NO hereda en dirección inversa', function () {
    // 'cursos/inscripciones:*' NO debe matchear 'cursos:ver' (cursos es el padre, no el hijo)
    expect(WildcardMatcher::matches(Permissions::CURSOS_VER, Permissions::CURSOS_INSCRIPCIONES_ALL))->toBeFalse();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_VER, Permissions::USUARIOS_PERMISOS_ALL))->toBeFalse();
});

test('wildcard de recurso padre NO hereda a recursos del mismo nivel con prefijo similar', function () {
    // Nota: Los slugs 'cursos_extra:ver' y 'cursosv2/inscripciones:ver' no existen en el enum
    // Por lo que no pueden ser probados directamente con el método matches que espera Permissions
    // Sin embargo, isDescendantResource acepta strings
    expect(WildcardMatcher::isDescendantResource('cursos_extra', 'cursos'))->toBeFalse();
    expect(WildcardMatcher::isDescendantResource('cursosv2/inscripciones', 'cursos'))->toBeFalse();
});

test('wildcard de recurso plano NO matchea recursos anidados (solo hereda con :*)', function () {
    // 'cursos:ver' (acción específica, no wildcard) NO matchea 'cursos/inscripciones:ver'
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_VER))->toBeFalse();
});

// ============================================================================
// TESTS DE EXTRACCIÓN CON SLUGS ANIDADOS
// ============================================================================

test('extractResource soporta rutas anidadas', function () {
    expect(WildcardMatcher::extractResource(Permissions::CURSOS_INSCRIPCIONES_VER))->toBe('cursos/inscripciones');
    expect(WildcardMatcher::extractResource(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR))->toBe('usuarios/permisos/roles');
    expect(WildcardMatcher::extractResource(Permissions::ACTIVIDADES_GRUPOS_ALL))->toBe('actividades/grupos');
    expect(WildcardMatcher::extractResource(Permissions::CURSOS_PROGRAMAS_MODIFICAR_ALL))->toBe('cursos/programas/modificar');
});

test('extractAction soporta slugs anidados', function () {
    expect(WildcardMatcher::extractAction(Permissions::CURSOS_INSCRIPCIONES_VER))->toBe('ver');
    expect(WildcardMatcher::extractAction(Permissions::CURSOS_INSCRIPCIONES_ALL))->toBe('*');
    expect(WildcardMatcher::extractAction(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR))->toBe('gestionar');
    expect(WildcardMatcher::extractAction(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1))->toBe('modulo_1');
});

test('extractResourceSegments retorna los segmentos de la ruta', function () {
    expect(WildcardMatcher::extractResourceSegments(Permissions::CURSOS_INSCRIPCIONES_VER))->toBe(['cursos', 'inscripciones']);
    expect(WildcardMatcher::extractResourceSegments(Permissions::ACTIVIDADES_GRUPOS_CREAR))->toBe(['actividades', 'grupos']);
    expect(WildcardMatcher::extractResourceSegments(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1))->toBe(['cursos', 'programas', 'modificar']);
    expect(WildcardMatcher::extractResourceSegments(Permissions::FACULTADES_VER))->toBe(['facultades']);
    expect(WildcardMatcher::extractResourceSegments(Permissions::GLOBAL_WILDCARD))->toBe(['*']);
});

test('toResourceWildcard preserva la ruta anidada completa', function () {
    expect(WildcardMatcher::toResourceWildcard(Permissions::CURSOS_INSCRIPCIONES_VER))->toBe(Permissions::CURSOS_INSCRIPCIONES_ALL->value);
    expect(WildcardMatcher::toResourceWildcard(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR))->toBe(Permissions::USUARIOS_PERMISOS_ROLES_ALL->value);
    expect(WildcardMatcher::toResourceWildcard(Permissions::CURSOS_INSCRIPCIONES_ALL))->toBe(Permissions::CURSOS_INSCRIPCIONES_ALL->value);
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
    expect(WildcardMatcher::isDescendantResource('cursosv2/sub', 'cursos'))->toBeFalse();
});

// ============================================================================
// TESTS DE PRIORIDAD CON SLUGS ANIDADOS
// ============================================================================

test('getPriority retorna 1 para match exacto anidado', function () {
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_INSCRIPCIONES_VER))->toBe(1);
    expect(WildcardMatcher::getPriority(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR))->toBe(1);
});

test('getPriority retorna 2 para wildcard del mismo recurso anidado', function () {
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_INSCRIPCIONES_ALL))->toBe(2);
    expect(WildcardMatcher::getPriority(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBe(2);
});

test('getPriority retorna 3 para wildcard de recurso ancestro', function () {
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::CURSOS_ALL))->toBe(3);
    expect(WildcardMatcher::getPriority(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, Permissions::CURSOS_ALL))->toBe(3);
    expect(WildcardMatcher::getPriority(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_ALL))->toBe(3);
    expect(WildcardMatcher::getPriority(Permissions::ACTIVIDADES_GRUPOS_CREAR, Permissions::ACTIVIDADES_ALL))->toBe(3);
});

// ============================================================================
// TESTS CASOS REALES CON SLUGS ANIDADOS
// ============================================================================

test('caso real: director puede gestionar todo en cursos con cursos:*', function () {
    // Director tiene permiso 'cursos:*'
    foreach ([
        Permissions::CURSOS_INSCRIPCIONES_VER,
        Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS,
        Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES,
        Permissions::CURSOS_UNIDADES_VER,
        Permissions::CURSOS_UNIDADES_CREAR,
        Permissions::CURSOS_UNIDADES_EDITAR,
        Permissions::CURSOS_PROGRAMAS_VER_TODOS,
        Permissions::CURSOS_PROGRAMAS_AGREGAR,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1,
    ] as $slug) {
        expect(WildcardMatcher::matches($slug, Permissions::CURSOS_ALL))
            ->toBeTrue("Se esperaba match de '{$slug->value}' con " . Permissions::CURSOS_ALL->value);
    }
});

test('caso real: admin de permisos puede gestionar roles con usuarios/permisos/roles:*', function () {
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_VER, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_CREAR, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBeTrue();
    // Pero NO puede gestionar permisos individuales
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_INDIVIDUALES_GESTIONAR, Permissions::USUARIOS_PERMISOS_ROLES_ALL))->toBeFalse();
});

test('caso real: wildcard global matchea slugs anidados', function () {
    expect(WildcardMatcher::matches(Permissions::CURSOS_INSCRIPCIONES_VER, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::ACTIVIDADES_GRUPOS_CREAR, Permissions::GLOBAL_WILDCARD))->toBeTrue();
    expect(WildcardMatcher::matches(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, Permissions::GLOBAL_WILDCARD))->toBeTrue();
});

// ============================================================================
// TESTS DE GENERACIÓN DE PATRONES
// ============================================================================

test('generatePermissionPatterns genera patrones para slug simple', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns(Permissions::FACULTADES_VER);
    expect($patterns)->toBe([
        Permissions::FACULTADES_VER->value,      // exacto
        Permissions::FACULTADES_ALL->value,      // wildcard recurso
        Permissions::GLOBAL_WILDCARD->value,     // wildcard global
    ]);
});

test('generatePermissionPatterns genera patrones para slug anidado 2 niveles', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns(Permissions::CURSOS_INSCRIPCIONES_VER);
    expect($patterns)->toBe([
        Permissions::CURSOS_INSCRIPCIONES_VER->value,     // exacto
        Permissions::CURSOS_INSCRIPCIONES_ALL->value,     // wildcard mismo recurso
        Permissions::CURSOS_ALL->value,                   // wildcard ancestro
        Permissions::GLOBAL_WILDCARD->value,              // wildcard global
    ]);
});

test('generatePermissionPatterns genera patrones para slug anidado 3 niveles', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns(Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1);
    expect($patterns)->toBe([
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1->value,      // exacto
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_ALL->value,           // wildcard mismo recurso
        Permissions::CURSOS_PROGRAMAS_ALL->value,                     // wildcard ancestro 1
        Permissions::CURSOS_ALL->value,                               // wildcard ancestro 2
        Permissions::GLOBAL_WILDCARD->value,                          // wildcard global
    ]);
});

test('generatePermissionPatterns genera patrones para slug anidado profundo', function () {
    // Nota: Solo probamos con slugs que existen en el enum
    $patterns = WildcardMatcher::generatePermissionPatterns(Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR);
    expect($patterns)->toBe([
        Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR->value,       // exacto
        Permissions::USUARIOS_PERMISOS_ROLES_ALL->value,             // wildcard mismo recurso
        Permissions::USUARIOS_PERMISOS_ALL->value,                   // wildcard ancestro 1
        Permissions::USUARIOS_ALL->value,                            // wildcard ancestro 2
        Permissions::GLOBAL_WILDCARD->value,                         // wildcard global
    ]);
});

test('generatePermissionPatterns con wildcard global', function () {
    $patterns = WildcardMatcher::generatePermissionPatterns(Permissions::GLOBAL_WILDCARD);
    expect($patterns)->toBe([
        Permissions::GLOBAL_WILDCARD->value,       // exacto (que es global)
    ]);
});

test('todos los patrones generados coinciden con el permiso solicitado', function () {
    $requested = Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1;
    $patterns = WildcardMatcher::generatePermissionPatterns($requested);
    $requestedValue = $requested->value;

    // Todos los patrones deben matchear el permiso solicitado
    foreach ($patterns as $pattern) {
        // Convert pattern back to enum if it's a valid enum value
        $permissionEnum = Permissions::tryFrom($pattern);
        if ($permissionEnum) {
            expect(WildcardMatcher::matches($requested, $permissionEnum))
                ->toBeTrue("Se esperaba match de {$requestedValue} con {$pattern}");
        }
    }
});

test('patrones generados están en orden de especificidad descendente', function () {
    $requested = Permissions::CURSOS_INSCRIPCIONES_VER;
    $patterns = WildcardMatcher::generatePermissionPatterns($requested);
    $requestedValue = $requested->value;

    $prevPriority = -1;
    foreach ($patterns as $pattern) {
        // Convert pattern back to enum if it's a valid enum value
        $permissionEnum = Permissions::tryFrom($pattern);
        if ($permissionEnum) {
            $priority = WildcardMatcher::getPriority($requested, $permissionEnum);
            expect($priority >= $prevPriority)->toBeTrue("Patrón {$pattern} con prioridad {$priority} no está en orden");
            $prevPriority = $priority;
        }
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

    $requested = Permissions::CURSOS_INSCRIPCIONES_VER;
    $grantPattern = Permissions::CURSOS_ALL;          // prioridad 3
    $denyPattern = Permissions::CURSOS_INSCRIPCIONES_ALL;   // prioridad 2

    $grantPriority = WildcardMatcher::getPriority($requested, $grantPattern);
    $denyPriority = WildcardMatcher::getPriority($requested, $denyPattern);

    expect($denyPriority < $grantPriority)->toBeTrue('El permiso DENY específico debe tener mayor prioridad que el GRANT general');
});

test('caso real: exacta siempre gana', function () {
    $requested = Permissions::CURSOS_INSCRIPCIONES_VER;

    $exactPriority = WildcardMatcher::getPriority($requested, Permissions::CURSOS_INSCRIPCIONES_VER);
    $wildcardPriority = WildcardMatcher::getPriority($requested, Permissions::CURSOS_INSCRIPCIONES_ALL);
    $ancestorPriority = WildcardMatcher::getPriority($requested, Permissions::CURSOS_ALL);
    $globalPriority = WildcardMatcher::getPriority($requested, Permissions::GLOBAL_WILDCARD);

    expect($exactPriority < $wildcardPriority)->toBeTrue();
    expect($exactPriority < $ancestorPriority)->toBeTrue();
    expect($exactPriority < $globalPriority)->toBeTrue();
});

