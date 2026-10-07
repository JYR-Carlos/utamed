/**
 * Cierre de un plazo en hora de Chile.
 *
 * `fecha_limite` es una fecha sin hora ('2026-09-24'). El backend la cierra al
 * final de ese día en la zona de la aplicación (APP_TIMEZONE =
 * America/Santiago: `fecha_limite->endOfDay()`), así que el instante real de
 * cierre es 23:59:59 en Chile, sea cual sea la zona del navegador.
 *
 * Dos errores que esto evita:
 *  - `new Date('2026-09-24')` es medianoche UTC, que en Chile es el 23 a las
 *    21:00: la fecha se mostraba un día antes y el tablero daba por cerrada
 *    la actividad ~27 horas antes de tiempo.
 *  - Calcular «23:59 local» con la zona del navegador: un estudiante fuera de
 *    Chile veía vencer el plazo a otra hora que la que aplica el servidor.
 */

export const ZONA_CHILE = 'America/Santiago';

/** Minutos que la zona `tz` va por delante de UTC en el instante dado. */
function desfaseMinutos(tz: string, instante: Date): number {
    const partes = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).formatToParts(instante);
    const n = (tipo: string) => Number(partes.find((p) => p.type === tipo)?.value ?? 0);
    const comoUtc = Date.UTC(n('year'), n('month') - 1, n('day'), n('hour'), n('minute'), n('second'));
    return Math.round((comoUtc - instante.getTime()) / 60_000);
}

/**
 * Instante de cierre: 23:59:59 en Chile del día `fechaSoloDia` + `diasExtra`
 * (la holgura general y la personal se suman como días de calendario).
 */
export function cierreEnChile(fechaSoloDia: string, diasExtra = 0): Date {
    const [y, m, d] = fechaSoloDia.slice(0, 10).split('-').map(Number);
    // Hora de pared en Chile expresada como si fuera UTC; luego se corrige con
    // el desfase de Chile en ese momento. Dos pasadas por si el cambio de
    // horario cae entre la estimación y el resultado.
    const pared = Date.UTC(y, m - 1, d + diasExtra, 23, 59, 59);
    let instante = pared - desfaseMinutos(ZONA_CHILE, new Date(pared)) * 60_000;
    instante = pared - desfaseMinutos(ZONA_CHILE, new Date(instante)) * 60_000;
    return new Date(instante);
}

/** true si el navegador está en la hora de Chile continental. */
export function navegadorEnHoraChile(): boolean {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone === ZONA_CHILE;
    } catch {
        return true;
    }
}

const FORMATO: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
};

/** «24 sept 2026, 23:59» en hora de Chile. */
export function formatEnHoraChile(instante: Date): string {
    return instante.toLocaleString('es-CL', { ...FORMATO, timeZone: ZONA_CHILE });
}

/** El mismo instante en la hora del navegador: «25 sept 2026, 04:59». */
export function formatEnHoraLocal(instante: Date): string {
    return instante.toLocaleString('es-CL', FORMATO);
}

/**
 * Texto del cierre para mostrar: siempre en hora de Chile y, si el navegador
 * está en otra zona, también su equivalente local.
 */
export function textoCierre(fechaSoloDia: string, diasExtra = 0): { chile: string; local: string | null } {
    const instante = cierreEnChile(fechaSoloDia, diasExtra);
    const chile = formatEnHoraChile(instante);
    if (navegadorEnHoraChile()) return { chile, local: null };
    const local = formatEnHoraLocal(instante);
    return { chile, local: local === chile ? null : local };
}
