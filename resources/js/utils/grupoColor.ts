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
        tarjeta: 'border-[#BFDBFE] border-l-[#2563EB] bg-[#EFF6FF] hover:border-[#2563EB]',
        chip: 'bg-[#DBEAFE] text-[#1E40AF]',
        enlace: 'border-[#BFDBFE] border-l-[#2563EB] bg-[#EFF6FF] hover:border-[#2563EB]',
        barra: 'border-l-[#2563EB]',
    },
    {
        // B · verde
        tarjeta: 'border-[#A7F3D0] border-l-[#059669] bg-[#ECFDF5] hover:border-[#059669]',
        chip: 'bg-[#D1FAE5] text-[#065F46]',
        enlace: 'border-[#A7F3D0] border-l-[#059669] bg-[#ECFDF5] hover:border-[#059669]',
        barra: 'border-l-[#059669]',
    },
    {
        // C · violeta
        tarjeta: 'border-[#DDD6FE] border-l-[#7C3AED] bg-[#F5F3FF] hover:border-[#7C3AED]',
        chip: 'bg-[#EDE9FE] text-[#5B21B6]',
        enlace: 'border-[#DDD6FE] border-l-[#7C3AED] bg-[#F5F3FF] hover:border-[#7C3AED]',
        barra: 'border-l-[#7C3AED]',
    },
    {
        // D · cian
        tarjeta: 'border-[#A5F3FC] border-l-[#0891B2] bg-[#ECFEFF] hover:border-[#0891B2]',
        chip: 'bg-[#CFFAFE] text-[#155E75]',
        enlace: 'border-[#A5F3FC] border-l-[#0891B2] bg-[#ECFEFF] hover:border-[#0891B2]',
        barra: 'border-l-[#0891B2]',
    },
    {
        // E · fucsia
        tarjeta: 'border-[#F5D0FE] border-l-[#C026D3] bg-[#FDF4FF] hover:border-[#C026D3]',
        chip: 'bg-[#FAE8FF] text-[#86198F]',
        enlace: 'border-[#F5D0FE] border-l-[#C026D3] bg-[#FDF4FF] hover:border-[#C026D3]',
        barra: 'border-l-[#C026D3]',
    },
    {
        // F · naranja
        tarjeta: 'border-[#FED7AA] border-l-[#EA580C] bg-[#FFF7ED] hover:border-[#EA580C]',
        chip: 'bg-[#FFEDD5] text-[#9A3412]',
        enlace: 'border-[#FED7AA] border-l-[#EA580C] bg-[#FFF7ED] hover:border-[#EA580C]',
        barra: 'border-l-[#EA580C]',
    },
];

/** Sin letra de grupo: tono neutro, como antes. */
const NEUTRO: TonoGrupo = {
    tarjeta: 'border-[#E5E7EB] border-l-[#94A3B8] bg-white hover:border-[#002F6C]',
    chip: 'bg-[#EEF2F7] text-[#33384A]',
    enlace: 'border-[#E5E7EB] border-l-[#94A3B8] bg-[#F8FAFC] hover:border-[#002F6C]',
    barra: 'border-l-transparent',
};

/** Tono del grupo según su letra («A» → azul, «B» → verde, …). */
export function tonoGrupo(letra?: string | null): TonoGrupo {
    const l = letra?.trim().toUpperCase();
    if (!l) return NEUTRO;
    const indice = (l.charCodeAt(0) - 65 + PALETA.length * 100) % PALETA.length;
    return PALETA[indice];
}
