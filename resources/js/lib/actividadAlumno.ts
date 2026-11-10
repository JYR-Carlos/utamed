import type { Tono } from '@/lib/dm';
import { claveDia, formatDiaCorto, parseFechaSoloDia } from '@/utils/formatters';

/** Una actividad tal como la ve el alumno (ResumenActividadesEstudiante::paraAlumno). */
export interface ActividadAlumno {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    max_integrantes: number;
    fecha_limite: string;
    visible: boolean;
    /** ACTIVA, CERRADA (pasó el plazo con su holgura), PLANIFICADA o NO VISIBLE. */
    estado?: string;
    ya_entregada?: boolean;
    /** Día del último envío vigente (YYYY-MM-DD); sólo si ya_entregada. */
    fecha_entrega?: string | null;
    ya_evaluada?: boolean;
    componente_tipo?: string | null;
}

export interface EstadoVisible {
    texto: string;
    tono: Tono;
    icono: 'ok' | 'info' | 'reloj' | 'alerta' | null;
}

/** «21 oct». */
function diaMes(fecha: string): string {
    const d = parseFechaSoloDia(fecha);
    return `${d.getDate()} ${d.toLocaleDateString('es-CL', { month: 'short' }).replace('.', '')}`;
}

/**
 * La píldora de estado de una actividad. El orden importa: evaluada gana a
 * entregada, y entregada a cualquier plazo.
 */
export function estadoDe(a: ActividadAlumno, hoy: string = claveDia()): EstadoVisible {
    const limite = a.fecha_limite?.slice(0, 10) ?? '';

    if (a.ya_evaluada) return { texto: a.es_sumativa ? 'Calificada' : 'Revisada', tono: 'ok', icono: 'ok' };
    if (a.ya_entregada) {
        return {
            texto: a.fecha_entrega ? `Entregada el ${diaMes(a.fecha_entrega)}` : 'Entregada',
            tono: 'info',
            icono: 'info',
        };
    }
    if (!a.con_entrega) return { texto: 'Sin entrega', tono: 'off', icono: null };
    if (a.estado === 'CERRADA') return { texto: 'Vencida · sin entrega', tono: 'error', icono: 'alerta' };
    // Pasó la fecha pero la holgura sigue abierta: todavía se recibe.
    if (limite && limite < hoy) return { texto: 'Atrasada · aún se recibe', tono: 'warn', icono: 'reloj' };
    if (limite === hoy) return { texto: 'Por entregar · vence hoy', tono: 'warn', icono: 'reloj' };
    return { texto: 'Por entregar', tono: 'off', icono: 'reloj' };
}

/** «Hoy, 23:59», «Mañana, 23:59» o «Jue 5 nov, 23:59». El cierre es a las 23:59 de Chile. */
export function fechaLimiteTexto(fecha: string, hoy: string = claveDia()): string {
    const dia = fecha.slice(0, 10);
    if (dia === hoy) return 'Hoy, 23:59';

    const manana = parseFechaSoloDia(hoy);
    manana.setDate(manana.getDate() + 1);
    if (dia === claveDia(manana)) return 'Mañana, 23:59';

    const corto = formatDiaCorto(dia);
    return `${corto.charAt(0).toUpperCase()}${corto.slice(1)}, 23:59`;
}

/** Todavía hay que entregarla y se puede. */
export function porEntregar(a: ActividadAlumno): boolean {
    return a.con_entrega && !a.ya_entregada && !a.ya_evaluada && a.estado !== 'CERRADA';
}
