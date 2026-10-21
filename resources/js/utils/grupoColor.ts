/**
 * Color distintivo de cada grupo (A, B, C…) del dashboard docente.
 *
 * La letra se mapea de forma estable a una paleta de 6 tonos (A azul, B verde,
 * C violeta, D cian, E fucsia, F naranja) y luego se repite. Se evitan ámbar y
 * rojo porque ya significan «mañana» y «hoy» en los plazos. El color nunca va
 * solo: siempre acompaña al texto «Grupo A».
 *
 * Las clases van literales (no interpoladas) para que Tailwind las extraiga.
 */

export interface TonoGrupo {
    /** Franja izquierda y borde de la tarjeta del grupo. */
    tarjeta: string;
    /** Chip «Grupo A». */
    chip: string;
    /** Botón/enlace del grupo en «Próximas fechas límite». */
    enlace: string;
    /** Sólo la franja izquierda (filas del menú lateral). */
    barra: string;
}

const PALETA: TonoGrupo[] = [
    {
        // A · azul
        tarjeta: 'border-blue-200 border-l-blue-600 bg-blue-50 hover:border-blue-600',
        chip: 'bg-blue-100 text-blue-800',
        enlace: 'border-blue-200 border-l-blue-600 bg-blue-50 hover:border-blue-600',
        barra: 'border-l-blue-600',
    },
    {
        // B · verde
        tarjeta: 'border-emerald-200 border-l-emerald-600 bg-emerald-50 hover:border-emerald-600',
        chip: 'bg-emerald-100 text-emerald-800',
        enlace: 'border-emerald-200 border-l-emerald-600 bg-emerald-50 hover:border-emerald-600',
        barra: 'border-l-emerald-600',
    },
    {
        // C · violeta
        tarjeta: 'border-violet-200 border-l-violet-600 bg-violet-50 hover:border-violet-600',
        chip: 'bg-violet-100 text-violet-800',
        enlace: 'border-violet-200 border-l-violet-600 bg-violet-50 hover:border-violet-600',
        barra: 'border-l-violet-600',
    },
    {
        // D · cian
        tarjeta: 'border-cyan-200 border-l-cyan-600 bg-cyan-50 hover:border-cyan-600',
        chip: 'bg-cyan-100 text-cyan-800',
        enlace: 'border-cyan-200 border-l-cyan-600 bg-cyan-50 hover:border-cyan-600',
        barra: 'border-l-cyan-600',
    },
    {
        // E · fucsia
        tarjeta: 'border-fuchsia-200 border-l-fuchsia-600 bg-fuchsia-50 hover:border-fuchsia-600',
        chip: 'bg-fuchsia-100 text-fuchsia-800',
        enlace: 'border-fuchsia-200 border-l-fuchsia-600 bg-fuchsia-50 hover:border-fuchsia-600',
        barra: 'border-l-fuchsia-600',
    },
    {
        // F · naranja
        tarjeta: 'border-orange-200 border-l-orange-600 bg-orange-50 hover:border-orange-600',
        chip: 'bg-orange-100 text-orange-800',
        enlace: 'border-orange-200 border-l-orange-600 bg-orange-50 hover:border-orange-600',
        barra: 'border-l-orange-600',
    },
];

/** Sin letra de grupo: tono neutro, como antes. */
const NEUTRO: TonoGrupo = {
    tarjeta: 'border-gray-200 border-l-slate-400 bg-white hover:border-navy',
    chip: 'bg-slate-100 text-gray-700',
    enlace: 'border-gray-200 border-l-slate-400 bg-slate-50 hover:border-navy',
    barra: 'border-l-transparent',
};

/** Tono del grupo según su letra («A» → azul, «B» → verde, …). */
export function tonoGrupo(letra?: string | null): TonoGrupo {
    const l = letra?.trim().toUpperCase();
    if (!l) return NEUTRO;
    const indice = (l.charCodeAt(0) - 65 + PALETA.length * 100) % PALETA.length;
    return PALETA[indice];
}
