/**
 * Estados unificados de la entrega de un estudiante o grupo (revisión
 * «Mi UTAmed»): la lista de actividades, la actividad desplegada y la agenda
 * usan las mismas tres palabras.
 *
 *   no   → No entregó
 *   pend → Por calificar (sumativa) · Por revisar (formativa)
 *   ok   → Calificado (sumativa)    · Revisado (formativa)
 *
 * Mismo criterio que AvanceEvaluacionService: una sumativa queda calificada
 * con nota; una formativa, revisada cuando el docente la evalúa (escribir en la
 * agenda no cambia el estado). Una actividad
 * presencial no recibe archivos, así que lo que no está evaluado queda
 * pendiente y no «sin entregar».
 */
import type { Tono } from '@/lib/inst';
import type { Actividad } from '@/types/actividad';

export type EstadoEntrega = 'no' | 'pend' | 'ok';

export const ORDEN_ENTREGA: EstadoEntrega[] = ['ok', 'pend', 'no'];

export function textosEntrega(esSumativa: boolean): Record<EstadoEntrega, string> {
    return esSumativa
        ? { no: 'No entregó', pend: 'Por calificar', ok: 'Calificado' }
        : { no: 'No entregó', pend: 'Por revisar', ok: 'Revisado' };
}

export const TONO_ENTREGA: Record<EstadoEntrega, Tono> = { ok: 'ok', pend: 'warn', no: 'off' };

/** Color de la calificación de una formativa. */
export const TONO_CALIFICACION: Record<string, Tono> = { Bueno: 'ok', Regular: 'warn', Malo: 'error' };

/** Color de cada tramo de la barra de avance. */
export const BARRA_ENTREGA: Record<EstadoEntrega, string> = {
    ok: 'bg-inst-ok',
    pend: 'bg-inst-warn',
    no: 'bg-inst-control',
};

/** Estado de un estudiante o grupo dentro de la actividad. */
export function estadoEntrega(
    g: { nota: number | null; entrega: unknown | null; evaluado?: boolean },
    esSumativa: boolean,
    traeArchivo: boolean,
): EstadoEntrega {
    if (esSumativa ? g.nota !== null : !!g.evaluado) return 'ok';
    if (g.entrega || !traeArchivo) return 'pend';
    return 'no';
}

/** Conteo de la columna «Entregas» de la lista, en unidades (estudiantes o grupos). */
export function conteoEntregas(a: Actividad): Record<EstadoEntrega, number> {
    const total = a.total_grupos ?? 0;
    const esSumativa = a.tipo_actividad === 'SUMATIVA';
    const ok = esSumativa ? (a.calificados ?? 0) : (a.revisados ?? 0);
    const pend =
        a.tipo_entrega === 'presencial'
            ? Math.max(0, total - ok)
            : esSumativa
              ? (a.por_calificar ?? 0)
              : (a.por_revisar ?? 0);
    return { ok, pend, no: Math.max(0, total - ok - pend) };
}

/** Iniciales para el avatar: primera letra de las dos primeras palabras. */
export function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]!.toUpperCase())
        .join('');
}
