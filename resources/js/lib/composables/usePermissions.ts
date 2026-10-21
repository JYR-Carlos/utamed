/**
 * Hook: usePermissions()
 *
 * Proporciona acceso a los permisos del usuario actual de forma reactiva.
 * Los permisos se cargan desde `page.props.auth.user.permissions` (proporcionado por Inertia/Laravel).
 *
 * IMPORTANTE: Este hook es solo para UX (mostrar/ocultar botones).
 * El servidor SIEMPRE valida y rechaza requests sin permisos (HTTP 403).
 *
 * @module lib/composables/usePermissions
 *
 * @example
 * ```svelte
 * <script>
 *   import { usePermissions } from '@/lib/composables/usePermissions';
 *
 *   const { can, cannot } = usePermissions();
 * </script>
 *
 * {#if can('cursos:create')}
 *   <button onclick={openCreateModal}>Crear Curso</button>
 * {/if}
 *
 * {#if cannot('cursos:delete')}
 *   <button disabled>No puedes eliminar</button>
 * {/if}
 * ```
 */

import { page as pageStore } from '@inertiajs/svelte';
import { get } from 'svelte/store';
import type { Permission } from '@/types/permissions.types';

/**
 * Hook para validar permisos del usuario actual.
 *
 * @returns {Object} Objeto con funciones de validación de permisos
 * @returns {Function} can - Valida si el usuario tiene un permiso
 * @returns {Function} cannot - Valida si el usuario NO tiene un permiso
 * @returns {Permission[]} userPermissions - Array de permisos del usuario
 */
export function usePermissions() {
    let isSuperAdmin = false;
    let userPermissions: Permission[] = [];
    let permissionSet = new Set<string>();

    try {
        const props = get(pageStore).props as any;
        const auth = props?.auth;
        isSuperAdmin = Boolean(auth?.is_super_admin);

        if (Array.isArray(auth?.permissions)) {
            userPermissions = auth.permissions as Permission[];
            permissionSet = new Set(userPermissions);
        }
    } catch (error) {
        console.error('[usePermissions] Error reading permissions:', error);
    }

    /**
     * Valida si el usuario tiene un permiso específico (O(1)).
     */
    function can(action: Permission | string): boolean {
        if (isSuperAdmin || permissionSet.has('*')) {
            return true;
        }

        return permissionSet.has(action as string);
    }

    /**
     * Inverso de can(): valida si el usuario NO tiene un permiso.
     *
     * @param {Permission | string} action - La acción a validar
     * @returns {boolean} true si el usuario NO tiene el permiso
     *
     * @example
     * ```ts
     * if (cannot('cursos:delete')) {
     *   showError('No tienes permiso para eliminar');
     * }
     * ```
     */
    function cannot(action: Permission | string): boolean {
        return !can(action);
    }

    /**
     * Valida múltiples permisos con lógica AND (todos deben cumplirse)
     *
     * @param {(Permission | string)[]} actions - Array de acciones
     * @returns {boolean} true si tiene TODOS los permisos
     *
     * @example
     * ```ts
     * if (canAll(['cursos:read', 'cursos/programa:create'])) {
     *   showFeature();
     * }
     * ```
     */
    function canAll(actions: (Permission | string)[]): boolean {
        return actions.every((action) => can(action));
    }

    /**
     * Valida múltiples permisos con lógica OR (al menos uno debe cumplirse)
     *
     * @param {(Permission | string)[]} actions - Array de acciones
     * @returns {boolean} true si tiene AL MENOS UN permiso
     *
     * @example
     * ```ts
     * if (canAny(['cursos:create', 'cursos:update'])) {
     *   showEditSection();
     * }
     * ```
     */
    function canAny(actions: (Permission | string)[]): boolean {
        return actions.some((action) => can(action));
    }

    return {
        /**
         * Validar un permiso
         * @function
         */
        can,

        /**
         * Validar negación de permiso
         * @function
         */
        cannot,

        /**
         * Validar múltiples permisos (AND)
         * @function
         */
        canAll,

        /**
         * Validar múltiples permisos (OR)
         * @function
         */
        canAny,

        /**
         * Array de permisos del usuario
         * @readonly
         */
        userPermissions,
    };
}
