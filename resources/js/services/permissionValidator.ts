/**
 * Frontend Permission Validator
 *
 * Los permisos llegan precalculados y expandidos desde el backend (PermissionValidator::expandToConcreteSlugs).
 * La validación en frontend es una comprobación O(1) directa, sin duplicar algoritmos de wildcards o parsing.
 *
 * Uso:
 * import { hasPermission } from '@/services/permissionValidator';
 *
 * if (hasPermission(userPermissions, 'cursos/programas:crear')) {
 *   // Usuario puede crear programas
 * }
 */

export type PermissionItem = string | { slug: string; esta_permitido?: boolean };
export type PermissionContainer = PermissionItem[] | Set<string> | null | undefined;

/**
 * Valida si un usuario tiene un permiso específico.
 *
 * Compatible con:
 * - Set<string> (lookup O(1) directo)
 * - Array de strings ['cursos:ver', ...]
 * - Array de objetos [{ slug: 'cursos:ver', esta_permitido: true }]
 *
 * @param userPermissions Colección de permisos del usuario o contexto
 * @param requestedSlug Permiso solicitado (ej: 'cursos/programas:ver')
 * @returns boolean true si tiene el permiso
 */
export function hasPermission(
  userPermissions: PermissionContainer,
  requestedSlug: string
): boolean {
  if (!userPermissions) {
    return false;
  }

  // Set (acceso O(1) directo)
  if (userPermissions instanceof Set) {
    return userPermissions.has('*') || userPermissions.has(requestedSlug);
  }

  // Array vacío
  if (userPermissions.length === 0) {
    return false;
  }

  // Array de strings
  if (typeof userPermissions[0] === 'string') {
    const list = userPermissions as string[];
    return list.includes('*') || list.includes(requestedSlug);
  }

  // Array de objetos { slug, esta_permitido }
  for (const item of userPermissions as { slug: string; esta_permitido?: boolean }[]) {
    if (item.esta_permitido === false) {
      continue;
    }
    if (item.slug === '*' || item.slug === requestedSlug) {
      return true;
    }
  }

  return false;
}

/**
 * Validar múltiples permisos (ANY - al menos uno)
 */
export function hasAnyPermission(
  userPermissions: PermissionContainer,
  requestedSlugs: string[]
): boolean {
  return requestedSlugs.some((slug) => hasPermission(userPermissions, slug));
}

/**
 * Validar múltiples permisos (ALL - todos)
 */
export function hasAllPermissions(
  userPermissions: PermissionContainer,
  requestedSlugs: string[]
): boolean {
  return requestedSlugs.every((slug) => hasPermission(userPermissions, slug));
}

/**
 * Helper con soporte para alias comunes de cursos
 */
export function can(
  userPermissions: PermissionContainer,
  action: string,
  resource: string = 'programa'
): boolean {
  const actionMap: Record<string, string> = {
    create: 'crear',
    read: 'ver',
    update: 'editar',
    delete: 'eliminar',
  };

  const mappedAction = actionMap[action] || action;
  return hasPermission(userPermissions, `cursos/${resource}:${mappedAction}`);
}
