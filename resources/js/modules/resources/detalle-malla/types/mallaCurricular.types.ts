export type { Plan, Asignatura, AsignacionPlan, MallaData } from '@/types/admin.types';

export const TIPO_RAMO_LABELS: Record<number, string> = {
  1: 'Electivo Profesional',
  2: 'Plan Común',
  3: 'Formación Profesional',
};

export function getTipoRamoLabel(tipoRamo: number | string | null | undefined): string {
  if (!tipoRamo) return '';
  const key = typeof tipoRamo === 'number' ? tipoRamo : parseInt(String(tipoRamo));
  return TIPO_RAMO_LABELS[key] ?? String(tipoRamo);
}
