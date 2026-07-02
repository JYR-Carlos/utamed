/**
 * Tipos y catálogos del módulo Detalle de Malla.
 *
 * Los tipos canónicos viven en @/types/admin.types; aquí se re-exportan y
 * se agrega el catálogo de tipos de ramo (hardcodeado en frontend: debe
 * mantenerse alineado con la tabla de tipos de ramo del backend).
 */
export type { Plan, Asignatura, AsignacionPlan, MallaData } from '@/types/admin.types';

/** Etiquetas por id de tipo de ramo; fuente única para selects y badges. */
export const TIPO_RAMO_LABELS: Record<number, string> = {
    1: 'Electivo Profesional',
    2: 'Plan Común',
    3: 'Formación Profesional',
};

/** Etiqueta legible de un tipo de ramo; tolera string/null y devuelve '' si no hay tipo. */
export function getTipoRamoLabel(tipoRamo: number | string | null | undefined): string {
    if (!tipoRamo) return '';
    const key = typeof tipoRamo === 'number' ? tipoRamo : parseInt(String(tipoRamo));
    return TIPO_RAMO_LABELS[key] ?? String(tipoRamo);
}
