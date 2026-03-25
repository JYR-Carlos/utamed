/**
 * Permission constants and utilities
 *
 * Constantes centralizadas para evitar typos y facilitar refactoring.
 * Usando constantes, si el permiso cambia, TypeScript te avisa en todos lados.
 *
 * @module lib/constants/permissions
 *
 * @example
 * ```ts
 * import { PERMISSIONS } from '@/lib/constants/permissions';
 *
 * if (can(PERMISSIONS.CURSOS.CREATE)) {
 *   // Crear curso
 * }
 * ```
 */

import type { Permission } from '@/types/permissions.types';

/**
 * Grouped permission constants for easy access and autocomplete
 */
export const PERMISSIONS = {
    // ═══════════════════════════════════════════════════════════════
    // CURSOS
    // ═══════════════════════════════════════════════════════════════
    CURSOS: {
        CREATE: 'cursos:create' as const satisfies Permission,
        READ: 'cursos:read' as const satisfies Permission,
        UPDATE: 'cursos:update' as const satisfies Permission,
        DELETE: 'cursos:delete' as const satisfies Permission,
        ALL: 'cursos:*' as const satisfies Permission,

        // ─────────────────────────────────────────────────────────────
        // Secciones
        // ─────────────────────────────────────────────────────────────
        SECCIONES: {
            CREATE: 'cursos/secciones:create' as const satisfies Permission,
            READ: 'cursos/secciones:read' as const satisfies Permission,
            UPDATE: 'cursos/secciones:update' as const satisfies Permission,
            DELETE: 'cursos/secciones:delete' as const satisfies Permission,
            ALL: 'cursos/secciones:*' as const satisfies Permission,
        },

        // ─────────────────────────────────────────────────────────────
        // Equipo de Cátedra
        // ─────────────────────────────────────────────────────────────
        EQUIPO: {
            VIEW: 'cursos/equipo:view' as const satisfies Permission,
            MANAGE: 'cursos/equipo:manage' as const satisfies Permission, // RBAC
            EDIT: 'cursos/equipo:edit' as const satisfies Permission, // Sin RBAC
            ALL: 'cursos/equipo:*' as const satisfies Permission,
        },

        // ─────────────────────────────────────────────────────────────
        // Programa / Syllabus
        // ─────────────────────────────────────────────────────────────
        PROGRAMA: {
            CREATE: 'cursos/programa:create' as const satisfies Permission,
            READ: 'cursos/programa:read' as const satisfies Permission,
            UPDATE: 'cursos/programa:update' as const satisfies Permission,
            DELETE: 'cursos/programa:delete' as const satisfies Permission,
            APPROVE: 'cursos/programa:approve' as const satisfies Permission,
            REJECT: 'cursos/programa:reject' as const satisfies Permission,
            ALL: 'cursos/programa:*' as const satisfies Permission,
        },

        // ─────────────────────────────────────────────────────────────
        // Inscripciones
        // ─────────────────────────────────────────────────────────────
        INSCRIPCIONES: {
            READ: 'cursos/inscripciones:read' as const satisfies Permission,
            MANAGE: 'cursos/inscripciones:manage' as const satisfies Permission,
            MYSELF: 'cursos/inscripciones:myself' as const satisfies Permission,
            ALL: 'cursos/inscripciones:*' as const satisfies Permission,
        },
    },

    // ═══════════════════════════════════════════════════════════════
    // DEPARTAMENTOS
    // ═══════════════════════════════════════════════════════════════
    DEPARTAMENTOS: {
        CREATE: 'departamentos:create' as const satisfies Permission,
        READ: 'departamentos:read' as const satisfies Permission,
        UPDATE: 'departamentos:update' as const satisfies Permission,
        DELETE: 'departamentos:delete' as const satisfies Permission,
    },

    // ═══════════════════════════════════════════════════════════════
    // CARRERAS
    // ═══════════════════════════════════════════════════════════════
    CARRERAS: {
        CREATE: 'carreras:create' as const satisfies Permission,
        READ: 'carreras:read' as const satisfies Permission,
        UPDATE: 'carreras:update' as const satisfies Permission,
        DELETE: 'carreras:delete' as const satisfies Permission,
    },

    // ═══════════════════════════════════════════════════════════════
    // Wildcard
    // ═══════════════════════════════════════════════════════════════
    ALL: '*' as const satisfies Permission,
} as const;

/**
 * Tipos exportados para type-safety
 */
export type CursoPermission = typeof PERMISSIONS.CURSOS[keyof typeof PERMISSIONS.CURSOS];
export type DepartamentoPermission = typeof PERMISSIONS.DEPARTAMENTOS[keyof typeof PERMISSIONS.DEPARTAMENTOS];
export type CarreraPermission = typeof PERMISSIONS.CARRERAS[keyof typeof PERMISSIONS.CARRERAS];
