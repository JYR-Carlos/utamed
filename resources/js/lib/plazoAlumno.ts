import { claveDia, formatDiaCorto, parseFechaSoloDia } from '@/utils/formatters';

/**
 * Actividad sin entregar cuyo plazo termina en los próximos 7 días o que
 * venció hace poco (ResumenActividadesEstudiante::proximasAVencer).
 */
export interface ActividadPorVencer {
    id_actividad: number;
    id_curso: number;
    /** Código del curso (DM301). */
    curso: string;
    nombre: string;
    es_sumativa: boolean;
    fecha_limite: string;
    /** Último día con la holgura sumada; null si no hay holgura. */
    plazo_hasta: string | null;
    /** El plazo, con holgura, ya pasó: ya no se recibe. */
    es_vencida?: boolean;
}

export interface PlazoVisible {
    texto: string;
    /** Vence hoy, está atrasada o ya venció: se marca en ámbar. */
    urgente: boolean;
    /** Todavía se puede entregar. */
    abierta: boolean;
}

/** Cómo se lee el plazo de una entrega pendiente en el Inicio. El cierre es a las 23:59 de Chile. */
export function plazoDe(a: ActividadPorVencer, hoy: string = claveDia()): PlazoVisible {
    const limite = a.fecha_limite.slice(0, 10);
    const hasta = (a.plazo_hasta ?? a.fecha_limite).slice(0, 10);

    if (a.es_vencida) return { texto: `Venció el ${formatDiaCorto(hasta)}`, urgente: true, abierta: false };
    if (limite < hoy) return { texto: `Atrasada · se recibe hasta el ${formatDiaCorto(hasta)}`, urgente: true, abierta: true };
    if (limite === hoy) return { texto: 'Vence hoy, 23:59', urgente: true, abierta: true };

    const manana = parseFechaSoloDia(hoy);
    manana.setDate(manana.getDate() + 1);
    if (limite === claveDia(manana)) return { texto: 'Vence mañana, 23:59', urgente: false, abierta: true };

    return { texto: `Vence el ${formatDiaCorto(limite)}`, urgente: false, abierta: true };
}
