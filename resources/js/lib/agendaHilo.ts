import type { InteraccionItem } from '@/types/agenda';
import { formatFechaTextoLargo } from '@/utils/formatters';

/**
 * Lo que necesita saber quien dibuja el hilo de una agenda, además de lo que
 * manda el backend. Lo comparten la agenda del docente (AgendaHilo) y la del
 * alumno (AgendaHiloAlumno); cada una lo dibuja a su manera.
 */
export interface InteraccionDecorada extends InteraccionItem {
    /** Mismo emisor y mismo día que el mensaje anterior: se omite la cabecera. */
    esConsecutivo?: boolean;
    /** Hora (o fecha y hora) a mostrar antes del mensaje tras 30 min de pausa. */
    marcaTiempoSeparador?: string | null;
}

export const ENTREGA = 'Entrega de archivo';
export const CANCELACION = 'Cancelación de entrega';

/** «14:30 hrs». */
export function formatHora(val: string | null | undefined): string {
    if (!val) return '';
    const d = new Date(val);
    if (!isNaN(d.getTime())) {
        const h = String(d.getHours()).padStart(2, '0');
        const m = String(d.getMinutes()).padStart(2, '0');
        return `${h}:${m} hrs`;
    }
    const match = String(val).match(/(\d{1,2}):(\d{2})/);
    return match ? `${match[1].padStart(2, '0')}:${match[2]} hrs` : '';
}

/**
 * El hilo listo para dibujar:
 * - las cancelaciones marcan su entrega (cancelada, cuándo y por quién) y
 *   desaparecen como mensaje;
 * - de las entregas no canceladas sólo la última vale: las anteriores quedan
 *   reemplazadas; de las evaluaciones, igual (reevaluadas);
 * - pausas de 30 min o más llevan marca de hora, y los mensajes de texto
 *   seguidos del mismo emisor se agrupan.
 */
export function procesarHilo(listado: InteraccionItem[]): InteraccionDecorada[] {
    const lista: InteraccionDecorada[] = listado.map((item) => ({ ...item }));

    for (let i = 0; i < lista.length; i++) {
        const item = lista[i];
        if (item.tipo_interaccion !== CANCELACION) continue;

        let entregaIndex = -1;
        if (item.uuid_archivo) {
            entregaIndex = lista
                .slice(0, i)
                .findLastIndex((prev) => prev.tipo_interaccion === ENTREGA && prev.uuid_archivo === item.uuid_archivo);
        }
        if (entregaIndex === -1) {
            entregaIndex = lista.slice(0, i).findLastIndex((prev) => prev.tipo_interaccion === ENTREGA && !prev.fue_cancelada);
        }
        if (entregaIndex !== -1) {
            lista[entregaIndex].fue_cancelada = true;
            lista[entregaIndex].fecha_cancelacion = item.fecha_emision;
            lista[entregaIndex].cancelado_por = item.emisor;
        }
    }

    let hayEntregaVigente = false;
    let hayEvaluacionVigente = false;
    for (let i = lista.length - 1; i >= 0; i--) {
        const item = lista[i];
        if (item.tipo_interaccion === ENTREGA && !item.fue_cancelada) {
            if (hayEntregaVigente) {
                item.fue_reemplazada = true;
                item.es_no_valido = true;
            }
            hayEntregaVigente = true;
        }
        if (item.tipo_interaccion === 'Evaluación') {
            if (hayEvaluacionVigente) {
                item.fue_reevaluada = true;
                item.es_no_valido = true;
            }
            hayEvaluacionVigente = true;
        }
    }

    const visibles = lista.filter((item) => item.tipo_interaccion !== CANCELACION);
    const tiposTexto = ['Mensaje al profesor', 'Feedback', 'Consulta'];

    for (let i = 1; i < visibles.length; i++) {
        const actual = visibles[i];
        const anterior = visibles[i - 1];
        const diffMinutos = Math.abs(new Date(actual.fecha_emision).getTime() - new Date(anterior.fecha_emision).getTime()) / 60000;
        const diaActual = actual.fecha_emision.slice(0, 10);
        const mismoDia = diaActual === anterior.fecha_emision.slice(0, 10);

        if (diffMinutos >= 30) {
            actual.marcaTiempoSeparador = mismoDia
                ? formatHora(actual.fecha_emision)
                : `${formatFechaTextoLargo(diaActual)} · ${formatHora(actual.fecha_emision)}`;
        }

        const esMensajeTexto = tiposTexto.includes(actual.tipo_interaccion) && tiposTexto.includes(anterior.tipo_interaccion);
        const mismoEmisor = (Boolean(actual.es_propio) && Boolean(anterior.es_propio)) || actual.emisor === anterior.emisor;
        if (!actual.marcaTiempoSeparador && mismoDia && mismoEmisor && actual.es_de_docente === anterior.es_de_docente && esMensajeTexto) {
            actual.esConsecutivo = true;
        }
    }

    return visibles;
}

/** El hilo partido por día ('YYYY-MM-DD'), en orden. */
export function agruparPorDia(items: InteraccionDecorada[]): Array<{ dia: string; items: InteraccionDecorada[] }> {
    const grupos: Array<{ dia: string; items: InteraccionDecorada[] }> = [];
    for (const item of items) {
        const dia = item.fecha_emision.slice(0, 10);
        const ultimo = grupos[grupos.length - 1];
        if (ultimo && ultimo.dia === dia) ultimo.items.push(item);
        else grupos.push({ dia, items: [item] });
    }
    return grupos;
}
