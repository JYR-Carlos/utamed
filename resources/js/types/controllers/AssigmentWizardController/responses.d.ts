import type { ContextType } from './permissions.types';

// ============================================================================
// Assignment Wizard Types — Responses from AssignmentWizardController
// ============================================================================

/**
 * Respuesta de GET /admin/assignment/context-types
 * Lista todos los tipos de contexto disponibles para asignar roles/permisos
 * 
 * @see AssignmentWizardController::getContextTypes()
 */
export interface ContextTypeResponse {
    /** Clave única del tipo de contexto (GLOBAL, FACULTAD, CURSO, etc) */
    key: string;
    /** Versión lowercase del enum value (global, facultad, curso, etc) */
    value: string;
    /** Etiqueta legible para mostrar en UI */
    label: string;
    /** Descripción del tipo de contexto */
    description: string;
    /** ID del contexto global (solo para GLOBAL) */
    context_id?: number;
    /** Cantidad total de instancias de este tipo (solo para tipos no-GLOBAL) */
    count?: number;
}

/**
 * Respuesta de GET /admin/assignment/context-types/{type}/objects
 * Retorna los objetos específicos de un tipo de contexto
 * 
 * @see AssignmentWizardController::getContextObjects()
 */
export interface ContextObjectResponse {
    /** ID de la instancia específica (ej: id_carrera, id_curso, etc) */
    id: number;
    /** Nombre legible del objeto */
    label: string;
    /** ID del contexto asociado con esta instancia */
    context_id: number;
}

/**
 * Respuesta de GET /admin/assignment/roles
 * Retorna todos los roles asignables con sus contextos compatibles
 * 
 * @see AssignmentWizardController::getRoles()
 */
export interface RolResponse {
    /** ID único del rol */
    id_rol: number;
    /** Nombre del rol */
    nombre: string;
    /** Tipos de contexto válidos donde este rol puede ser asignado
     *  Array de valores ContextType (ej: [ContextType.GLOBAL, ContextType.CARRERA])
     *  Calculado por getCompatibleContexts() basado en los permisos del rol
     *  
     *  @example
     *    if (rol.valid_assignment_context_types.includes(ContextType.CARRERA)) {
     *      // El rol puede asignarse a nivel de carrera
     *    }
     */
    valid_assignment_context_types: (ContextType)[];
}

/**
 * Respuesta de GET /admin/assignment/roles/{roleId}/detail
 * Obtiene el detalle completo de un rol incluyendo sus permisos
 * 
 * @see AssignmentWizardController::getRoleDetail()
 */
export interface RolDetailResponse {
    /** ID único del rol */
    id_rol: number;
    /** Nombre del rol */
    nombre: string;
    /** Lista de permisos asignados al rol */
    permisos: RolePermissionDetail[];
}

/**
 * Detalle de permiso dentro de un rol
 */
export interface RolePermissionDetail {
    /** ID único del permiso */
    id_permiso: number;
    /** Slug del permiso (ej: 'carreras:crear') */
    slug: string;
    /** Nombre legible del permiso */
    nombre: string;
    /** Descripción del permiso */
    descripcion: string;
    /** Si el rol puede delegar este permiso */
    puede_delegar_permisos: boolean;
}

/**
 * Respuesta de GET /admin/assignment/permissions
 * Retorna todos los permisos disponibles agrupados por módulo
 * 
 * @see AssignmentWizardController::getPermissions()
 */
export type PermissionGroupResponse ={
    /** Módulo/grupo del permiso (ej: 'Carreras', 'Cursos', 'Usuarios') */
    [module: string]: PermissionDetail[];
}

/**
 * Detalle de un permiso individual en la respuesta
 */
export type PermissionDetail = {
    /** ID único del permiso */
    id_permiso: number;
    /** Slug del permiso (ej: 'carreras:crear') */
    slug: string;
    /** Nombre legible del permiso */
    nombre: string;
    /** Descripción del permiso */
    descripcion: string;
    /** Tipos de contexto válidos para este permiso
     *  Array de valores ContextType (ej: [ContextType.GLOBAL, ContextType.CARRERA])
     *  Basado en PermissionContextConstraints::validContextTypesFor()
     *  
     *  @example
     *    if (permission.valid_context_types.includes(ContextType.CURSO)) {
     *      // Este permiso puede asignarse a nivel de curso
     *    }
     */
    valid_context_types: ContextType[];
}