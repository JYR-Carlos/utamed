/**
 * ContextType enum — replicates App\Enums\ContextType from backend
 * 
 * Tipos de contexto válidos para asignación de permisos y roles:
 * - GLOBAL: Contexto global que aplica a nivel sistema
 * - ACTIVIDAD: Contexto de actividad académica
 * - CARRERA: Contexto de carrera académica
 * - CURSO: Contexto de curso específico
 * - DEPARTAMENTO: Contexto de departamento académico
 * - FACULTAD: Contexto de facultad académica
 * 
 * **Jerarquía**: ACTIVIDAD → CURSO → CARRERA → DEPARTAMENTO → FACULTAD → GLOBAL
 * 
 * @see App\Enums\ContextType en backend
 */
export enum ContextType {
    GLOBAL = 'global',
    ACTIVIDAD = 'actividad',
    CARRERA = 'carrera',
    CURSO = 'curso',
    DEPARTAMENTO = 'departamento',
    FACULTAD = 'facultad',
}

/**
 * Tipo utilidad para los valores del enum ContextType
 */
export type ContextTypeValue = `${ContextType}`;