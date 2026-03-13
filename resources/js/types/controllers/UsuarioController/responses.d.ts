/**
 * Type definitions for UsuarioController responses
 * 
 * @see app/Http/Controllers/Admin/UsuarioController.php
 */

/**
 * Respuesta de GET /admin/usuarios/{usuario}/permissions
 * Retorna todas las asignaciones de roles y permisos especiales activos del usuario
 * 
 * @see UsuarioController::getUserPermissions()
 */
export interface GetUserPermissionsResponse {
  /** Array de asignaciones de roles activas del usuario */
  roles: UserRoleAssignment[];
  
  /** Array de permisos especiales activos del usuario */
  special_permissions: UserSpecialPermissionAssignment[];
}

/**
 * Asignación de rol activa para un usuario
 * Representa una fila de usuario_rol_asignacion con datos relacionados cargados
 */
export interface UserRoleAssignment {
  /** ID único de la asignación de rol (usuario_rol_asignacion.id_ura) */
  id_ura: number;
  
  /** ID del rol asignado */
  id_rol: number;
  
  /** Nombre del rol (ej: "Docente", "Coordinador de Carrera") */
  nombre: string;
  
  /** ID del contexto donde se aplica el rol (null = contexto global) */
  id_contexto: number;
  
  /** Nombre legible del contexto (ej: "Carrera de Ingeniería", "Curso - MAT101") */
  contexto_display: string;
  
  /** Nombre del curso si el contexto es de tipo curso */
  curso_nombre: string | null;
  
  /** Fecha planificada de inicio de la asignación (ISO 8601) */
  fecha_inicio_planificada: string;
  
  /** Fecha planificada de fin de la asignación (ISO 8601) */
  fecha_fin_planificada: string;
  
  /** Nombre completo del usuario que creó la asignación */
  creado_por_nombre: string;
}

/**
 * Permiso especial activo para un usuario
 * Representa una fila de usuario_permiso_especial con datos relacionados cargados
 */
export interface UserSpecialPermissionAssignment {
  /** ID único de la asignación de permiso especial (usuario_permiso_especial.id_upe) */
  id_upe?: number;
  
  /** ID del permiso especial asignado */
  id_permiso: number;
  
  /** Slug del permiso (ej: 'carreras:crear', 'cursos:editar') */
  slug: string | null;
  
  /** Nombre legible del permiso */
  nombre: string | null;
  
  /** ID del contexto donde se aplica el permiso (null = contexto global) */
  id_contexto: number;
  
  /** Nombre legible del contexto (ej: "Carrera de Ingeniería", "Curso - MAT101") */
  contexto_display: string | null;
  
  /** Nombre del curso si el contexto es de tipo curso */
  curso_nombre: string | null;
  
  /** Si el permiso está permitido (true) o denegado (false) */
  esta_permitido: boolean;
  
  /** Si el usuario puede delegar este permiso a otros usuarios */
  puede_delegar: boolean;
}
