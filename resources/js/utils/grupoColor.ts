/**
 * Color distintivo de cada grupo (A, B, C…) del dashboard docente y vistas admin.
 *
 * Mapeo predefinido de 4 colores:
 * A: azul, B: verde (emerald), C: violeta, D: cian.
 * Cualquier grupo posterior (E, F, etc.) o neutro se muestra en gris.
 *
 * Las clases van literales (no interpoladas) para que Tailwind las extraiga.
 */

export interface TonoGrupo {
  /** Franja izquierda y borde de la tarjeta del grupo. */
  tarjeta: string;
  /** Chip / Badge «Grupo A». */
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
    chip: 'bg-blue-100 text-blue-800 border border-blue-200',
    enlace: 'border-blue-200 border-l-blue-600 bg-blue-50 hover:border-blue-600',
    barra: 'border-l-blue-600',
  },
  {
    // B · verde
    tarjeta: 'border-emerald-200 border-l-emerald-600 bg-emerald-50 hover:border-emerald-600',
    chip: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
    enlace: 'border-emerald-200 border-l-emerald-600 bg-emerald-50 hover:border-emerald-600',
    barra: 'border-l-emerald-600',
  },
  {
    // C · violeta
    tarjeta: 'border-violet-200 border-l-violet-600 bg-violet-50 hover:border-violet-600',
    chip: 'bg-violet-100 text-violet-800 border border-violet-200',
    enlace: 'border-violet-200 border-l-violet-600 bg-violet-50 hover:border-violet-600',
    barra: 'border-l-violet-600',
  },
  {
    // D · cian
    tarjeta: 'border-cyan-200 border-l-cyan-600 bg-cyan-50 hover:border-cyan-600',
    chip: 'bg-cyan-100 text-cyan-800 border border-cyan-200',
    enlace: 'border-cyan-200 border-l-cyan-600 bg-cyan-50 hover:border-cyan-600',
    barra: 'border-l-cyan-600',
  },
];

/** Sin letra de grupo o grupo >= E (E, F...): tono gris neutro. */
const NEUTRO: TonoGrupo = {
  tarjeta: 'border-gray-200 border-l-slate-400 bg-white hover:border-navy',
  chip: 'bg-slate-100 text-slate-700 border border-slate-200',
  enlace: 'border-gray-200 border-l-slate-400 bg-slate-50 hover:border-navy',
  barra: 'border-l-transparent',
};

/** Tono del grupo según su letra («A» → azul, «B» → verde, «C» → violeta, «D» → cian, «E», «F»... → gris). */
export function tonoGrupo(letra?: string | null): TonoGrupo {
  const l = letra?.trim().toUpperCase();
  if (!l) return NEUTRO;

  const charCode = l.charCodeAt(0);
  let indice = -1;
  if (charCode >= 65 && charCode <= 90) {
    indice = charCode - 65;
  } else {
    const num = parseInt(l, 10);
    if (!isNaN(num) && num >= 1) {
      indice = num - 1;
    }
  }

  if (indice >= 0 && indice < PALETA.length) {
    return PALETA[indice];
  }
  return NEUTRO;
}

/**
 * Limpia sufijos redundantes de grupo ("Grupo A", " - Grupo B", etc.) del nombre de un curso.
 */
export function limpiarNombreGrupo(nombre?: string | null, letraGrupo?: string | null): string {
  if (!nombre) return '';
  let cleaned = nombre;
  if (letraGrupo) {
    cleaned = cleaned.replace(new RegExp(`\\s*[-–—]?\\s*Grupo\\s+${letraGrupo}\\s*$`, 'i'), '');
  }
  cleaned = cleaned.replace(/\s*[-–—]?\s*Grupo\s+[A-Z0-9]+\s*$/i, '');
  return cleaned.trim();
}
