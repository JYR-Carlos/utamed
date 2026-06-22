/**
 * Type definitions for granular permission system
 *
 * IMPORTANTE: Los permisos se definen por ACCIÓN, no por recurso.
 * Esto permite control fino en el frontend (UX) y coincide con backend (Laravel gate/policy).
 *
 * @module permissions.types
 */

/**
 * Acciones granulares disponibles en el sistema.
 * Cada acción representa una operación específica que requiere validación.
 *
 * Formato:
 * - `recurso:accion` para acciones de un recurso
 * - `recurso/subrecurso:accion` para acciones anidadas
 */
export type Permission =
    // ═══════════════════════════════════════════════════════════════
    // CURSOS (Admin + Docente + Estudiante)
    // ═══════════════════════════════════════════════════════════════
    | 'cursos:create'     // Crear nuevo curso (Admin)
    | 'cursos:read'       // Leer lista cursos (Admin, Docente filtrado, Estudiante filtrado)
    | 'cursos:update'     // Editar curso (Admin)
    | 'cursos:delete'     // Eliminar curso (Admin)

    // ─────────────────────────────────────────────────────────────
    // Secciones de Cursos
    // ─────────────────────────────────────────────────────────────
    | 'cursos/secciones:create'   // Agregar sección al curso (Admin)
    | 'cursos/secciones:read'     // Ver secciones (Admin, Docente, Estudiante)
    | 'cursos/secciones:update'   // Editar docente de sección (Admin + Docente su propia sección)
    | 'cursos/secciones:delete'   // Eliminar sección (Admin)

    // ─────────────────────────────────────────────────────────────
    // Equipo de Cátedra (Docentes auxiliares, ayudantes)
    // ─────────────────────────────────────────────────────────────
    | 'cursos/equipo:view'        // Ver equipo cátedra (Admin, Docente)
    | 'cursos/equipo:manage'      // Gestionar RBAC (asignar roles, permisos especiales) [ADMIN SOLO]
    | 'cursos/equipo:edit'        // Editar equipo SIN RBAC (solo cambiar miembros) [DOCENTE]

    // ─────────────────────────────────────────────────────────────
    // Programa / Syllabus
    // ─────────────────────────────────────────────────────────────
    | 'cursos/programa:create'    // Crear nuevo programa (Admin, Docente)
    | 'cursos/programa:read'      // Ver programa (Admin, Docente, Estudiante)
    | 'cursos/programa:update'    // Editar programa (Admin, Docente)
    | 'cursos/programa:delete'    // Eliminar programa (Admin)
    | 'cursos/programa:approve'   // Aprobar programa (Admin, Supervisor)
    | 'cursos/programa:reject'    // Rechazar programa (Admin, Supervisor)

    // ─────────────────────────────────────────────────────────────
    // Inscripciones de Estudiantes
    // ─────────────────────────────────────────────────────────────
    | 'cursos/inscripciones:read'         // Ver inscripciones (Admin, Docente)
    | 'cursos/inscripciones:manage'       // Crear/editar/eliminar inscripciones (Admin)
    | 'cursos/inscripciones:myself'       // Estudiante lee sus propias inscripciones

    // ═══════════════════════════════════════════════════════════════
    // DEPARTAMENTOS (Admin)
    // ═══════════════════════════════════════════════════════════════
    | 'departamentos:create'
    | 'departamentos:read'
    | 'departamentos:update'
    | 'departamentos:delete'

    // ═══════════════════════════════════════════════════════════════
    // CARRERAS (Admin)
    // ═══════════════════════════════════════════════════════════════
    | 'carreras:create'
    | 'carreras:read'
    | 'carreras:update'
    | 'carreras:delete'

    // ═══════════════════════════════════════════════════════════════
    // Wildcard (Para simplificar, ej: admin = cursos:*)
    // ═══════════════════════════════════════════════════════════════
    | '*'                  // Acceso total a todo
    | 'cursos:*'               // Acceso total a cursos y sub-recursos
    | 'cursos/secciones:*'    // Acceso total a secciones de cursos
    | 'cursos/inscripciones:*' // Acceso total a inscripciones
    | 'cursos/equipo:*'       // Acceso total a equipo de cursos
    | 'cursos/programa:*'     // Acceso total a programas
    ;

/**
 * Permiso estructurado con metadata (para debugging o auditoría)
 */
export interface PermissionRecord {
    /** La acción (ej: 'cursos:create') */
    permission: Permission;
    /** Descripción legible para UI */
    description: string;
    /** Recurso al que aplica (ej: 'cursos', 'cursos/equipo') */
    resource: string;
    /** Acción específica (ej: 'create', 'read', 'update', 'delete') */
    action: string;
}

/**
 * Respuesta del servidor con permisos del usuario actual
 * Se recibe en page.props.auth.user.permissions
 */
export interface UserPermissionSet {
    /** Array de permisos que tiene el usuario */
    permissions: Permission[];
    /** Rol principal del usuario (para debugging) */
    role?: string;
    /** Timestamp de cuándo se asignaron (para caché) */
    grantedAt?: string;
}
