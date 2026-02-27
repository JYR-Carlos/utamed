/**
 * Type definitions for Permission system
 */

export interface Permission {
    id_permiso: number;
    slug: string;
    esta_permitido: boolean;
    puede_delegar?: boolean;
    source?: string;
}

export interface PermissionResult {
    id_usuario: number;
    id_contexto: number;
    id_permiso: number;
    slug: string;
    esta_permitido: boolean;
    tipo_asignacion: string;
    puede_delegar: boolean;
}
