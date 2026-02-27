/**
 * Frontend Permission Validator
 * 
 * Valida permisos del usuario en el frontend usando la misma lógica de wildcards
 * que el backend (WildcardMatcher).
 * 
 * Uso:
 * import { hasPermission } from '@/services/permissionValidator';
 * 
 * if (hasPermission(userPermissions, 'cursos/programas:crear')) {
 *   // Usuario puede crear programas
 * }
 */

import type { Permission } from '@/types/permissions.types';

const SEPARATOR = ':';
const RESOURCE_SEPARATOR = '/';
const GLOBAL_WILDCARD = '*';

/**
 * Extraer el recurso del permiso slug (parte antes de ':')
 * 'facultad:editar' → 'facultad'
 * 'cursos/inscripciones:ver' → 'cursos/inscripciones'
 * 'cursos/programas:*' → 'cursos/programas'
 */
function extractResource(slug: string): string {
    if (slug === GLOBAL_WILDCARD) {
        return GLOBAL_WILDCARD;
    }
    const parts = slug.split(SEPARATOR, 2);
    return parts[0] ?? '';
}

/**
 * Extraer la acción del slug (parte después de ':')
 * 'facultad:editar' → 'editar'
 * 'cursos/inscripciones:ver' → 'ver'
 * 'facultad:*' → '*'
 */
function extractAction(slug: string): string | null {
    if (slug === GLOBAL_WILDCARD) {
        return null;
    }
    const parts = slug.split(SEPARATOR, 2);
    return parts[1] ?? null;
}

/**
 * Verificar si es wildcard global ('*')
 */
function isGlobalWildcard(slug: string): boolean {
    return slug === GLOBAL_WILDCARD;
}

/**
 * Verificar si es wildcard de recurso ('recurso:*' o 'recurso/sub:*')
 */
function isResourceWildcard(slug: string): boolean {
    if (isGlobalWildcard(slug)) {
        return false;
    }
    const action = extractAction(slug);
    return action === GLOBAL_WILDCARD;
}

/**
 * Verificar si un recurso es descendiente (hijo, nieto, etc.) de otro
 * 'cursos/inscripciones' es descendiente de 'cursos' ✓
 * 'cursos/actividades/grupos' es descendiente de 'cursos' ✓
 * 'cursos' es descendiente de 'cursos' ✗
 */
function isDescendantResource(requestedResource: string, ancestorResource: string): boolean {
    if (requestedResource === ancestorResource) {
        return false;
    }
    return requestedResource.startsWith(ancestorResource + RESOURCE_SEPARATOR);
}

/**
 * Verificar si un slug solicitado coincide con un patrón de permiso
 * 'cursos/inscripciones:ver' coincide con 'cursos/inscripciones:*' ✓
 * 'cursos/inscripciones:ver' coincide con 'cursos:*' ✓
 * 'cursos/inscripciones:ver' coincide con '*' ✓
 * 'cursos/inscripciones:ver' coincide con 'cursos/inscripciones:ver' ✓
 */
function matches(requestedSlug: string, patternSlug: string): boolean {
    // Match exacto
    if (requestedSlug === patternSlug) {
        return true;
    }

    // Wildcard global
    if (isGlobalWildcard(patternSlug)) {
        return true;
    }

    // Wildcard de recurso
    if (isResourceWildcard(patternSlug)) {
        const requestedResource = extractResource(requestedSlug);
        const patternResource = extractResource(patternSlug);

        // Match exacto del recurso (mismo nivel)
        if (requestedResource === patternResource) {
            return true;
        }

        // Match ancestro: el patrón cubre todos los descendientes
        // 'cursos:*' matchea 'cursos/inscripciones:ver'
        return isDescendantResource(requestedResource, patternResource);
    }

    return false;
}

/**
 * Generar todos los patrones válidos para un slug solicitado
 * 'cursos/inscripciones/alumno:ver' genera:
 * [
 *   'cursos/inscripciones/alumno:ver'   (exacto)
 *   'cursos/inscripciones/alumno:*'     (wildcard mismo recurso)
 *   'cursos/inscripciones:*'            (ancestro wildcard)
 *   'cursos:*'                          (ancestro padre wildcard)
 *   '*'                                 (wildcard global)
 * ]
 */
function generatePermissionPatterns(slug: string): string[] {
    const resource = extractResource(slug);
    const action = extractAction(slug);

    const patterns: string[] = [];

    // 1. Match exacto
    patterns.push(slug);

    // 2. Wildcard del mismo recurso (recurso:*)
    if (action !== null) {
        patterns.push(resource + SEPARATOR + GLOBAL_WILDCARD);
    }

    // 3. Wildcards ancestros (recurso_padre:*, etc.)
    const segments = resource.split(RESOURCE_SEPARATOR);

    if (segments.length > 1) {
        for (let i = segments.length - 1; i > 0; i--) {
            const ancestorPath = segments.slice(0, i).join(RESOURCE_SEPARATOR);
            patterns.push(ancestorPath + SEPARATOR + GLOBAL_WILDCARD);
        }
    }

    // 4. Wildcard global
    patterns.push(GLOBAL_WILDCARD);

    return [...new Set(patterns)]; // Remover duplicados
}

/**
 * Validar si un usuario tiene permiso para ejecutar una acción
 * 
 * @param userPermissions Array de permisos del usuario (desde API)
 * @param requestedSlug Permiso solicitado (ej: 'cursos/programas:ver')
 * @param mustBeAllowed Si true, solo retorna true si esta_permitido === true
 * @returns boolean true si tiene permiso
 * 
 * Ejemplo:
 * const hasViewPerm = hasPermission(userPerms, 'cursos/programas:ver');
 * const canDelegate = hasPermission(userPerms, 'cursos/programas:crear', true) && 
 *                     userPerms.find(p => p.slug === 'cursos/programas:*')?.puede_delegar;
 */
export function hasPermission(
    userPermissions: Permission[],
    requestedSlug: string,
    mustBeAllowed: boolean = true
): boolean {
    if (!userPermissions || userPermissions.length === 0) {
        return false;
    }

    // Generar todos los patrones válidos para el permiso solicitado
    const patterns = generatePermissionPatterns(requestedSlug);

    // Buscar si algún patrón coincide con un permiso del usuario
    for (const pattern of patterns) {
        for (const userPerm of userPermissions) {
            if (matches(requestedSlug, userPerm.slug)) {
                // Si debe estar permitido explícitamente, verificar esta_permitido
                if (mustBeAllowed && userPerm.esta_permitido !== true) {
                    continue;
                }

                return true;
            }
        }
    }

    return false;
}

/**
 * Validar múltiples permisos (ANY - al menos uno)
 * 
 * @param userPermissions Array de permisos del usuario
 * @param requestedSlugs Array de slugs de permisos
 * @returns boolean true si tiene AL MENOS UNO de los permisos
 */
export function hasAnyPermission(
    userPermissions: Permission[],
    requestedSlugs: string[]
): boolean {
    return requestedSlugs.some((slug) => hasPermission(userPermissions, slug));
}

/**
 * Validar múltiples permisos (ALL - todos)
 * 
 * @param userPermissions Array de permisos del usuario
 * @param requestedSlugs Array de slugs de permisos
 * @returns boolean true si tiene TODOS los permisos
 */
export function hasAllPermissions(
    userPermissions: Permission[],
    requestedSlugs: string[]
): boolean {
    return requestedSlugs.every((slug) => hasPermission(userPermissions, slug));
}

/**
 * Validar permiso con soporte para alias comunes
 * Mapea acciones comunes del dominio a slugs de permisos
 * 
 * @param userPermissions Array de permisos del usuario
 * @param action Acción a validar: 'create', 'read', 'update', 'delete', etc.
 * @param resource Recurso: 'programa', 'syllabus', etc.
 * @returns boolean true si tiene permiso
 */
export function can(
    userPermissions: Permission[],
    action: string,
    resource: string = 'programa'
): boolean {
    // Mapear acciones comunes a slugs de permisos
    const actionMap: Record<string, string> = {
        create: 'crear',
        read: 'ver',
        update: 'editar',
        delete: 'eliminar',
    };

    const mappedAction = actionMap[action] || action;

    // Construir slug del permiso
    const slug = `cursos/${resource}:${mappedAction}`;

    return hasPermission(userPermissions, slug);
}
