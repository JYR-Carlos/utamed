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
    /**
     * Array de permisos del usuario actual.
     * Viene de: page.props.auth.user.permissions
     * Ej: ['cursos:read', 'cursos:create', 'cursos/equipo:view']
     */
    const userPermissions = ((): Permission[] => {
        try {
            const props = get(pageStore).props as any;
            const auth = props?.auth;
            // Super admin gets wildcard permission
            if (auth?.is_super_admin) {
                return ['*'] as Permission[];
            }
            if (!auth?.permissions || !Array.isArray(auth.permissions)) {
                return [];
            }
            return auth.permissions as Permission[];
        } catch (error) {
            console.error('[usePermissions] Error reading permissions:', error);
            return [];
        }
    })();

    /**
     * Valida si el usuario tiene un permiso.
     * Soporta wildcards:
     * - '*' = acceso total
     * - 'cursos:*' = acceso a cualquier acción en cursos
     * - 'cursos/equipo:*' = acceso a cualquier acción en equipo
     *
     * @param {Permission | string} action - La acción a validar (ej: 'cursos:create')
     * @returns {boolean} true si el usuario tiene el permiso
     *
     * @example
     * ```ts
     * can('cursos:create')      // true si tiene ese permiso específico
     * can('cursos:update')      // true si tiene ese permiso o 'cursos:*' o '*'
     * ```
     */
    function can(action: Permission | string): boolean {
        if (!userPermissions || userPermissions.length === 0) {
            return false;
        }

        // Wildcard global: '*' permite todo
        if (userPermissions.includes('*' as Permission)) {
            return true;
        }

        // Permiso exacto
        if (userPermissions.includes(action as Permission)) {
            return true;
        }

        // Wildcard nivel recurso
        // Ej: action = 'cursos:create' , permiso = 'cursos:*' → true
        const [resource, subresource] = action.split('/');
        if (subresource) {
            // Es una acción anidada: 'cursos/equipo:create'
            // Validar 'cursos/equipo:*' o 'cursos:*'
            if (userPermissions.includes(`${resource}/${subresource}:*` as Permission)) {
                return true;
            }
            if (userPermissions.includes(`${resource}:*` as Permission)) {
                return true;
            }
        } else {
            // Es una acción de nivel superior: 'cursos:create'
            // Validar 'cursos:*'
            const [res] = action.split(':');
            if (res && userPermissions.includes(`${res}:*` as Permission)) {
                return true;
            }
        }

        return false;
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
