/**
 * Clases de Tailwind de la dirección institucional UTA que se repiten en la
 * vista del docente (lienzo, hoja «Lote 2 · Docente · institucional UTA»).
 *
 * Son cadenas completas (no armadas por partes) para que Tailwind las
 * encuentre al compilar. Los colores salen de resources/css/institucional.css.
 */

/** Títulos en Century Gothic (Didact Gothic de respaldo). */
export const titulo = 'font-inst-titulo font-bold';

/** Rótulo en mayúsculas: 12 px, el único tamaño bajo 13 px que se usa. */
export const etiqueta = 'text-xs leading-4 font-extrabold tracking-[0.06em] uppercase text-inst-ink-3';

/** Código del curso («DM301 · Grupo A»). */
export const codigo = 'text-xs leading-4 font-extrabold tracking-[0.06em] uppercase text-inst-enlace';

export const tarjeta = 'min-w-0 rounded-lg border border-inst-linea bg-inst-tarjeta';

/** Cabecera y pie de una tarjeta. */
export const tarjetaCabecera = 'flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-inst-linea px-4.5 py-3.5';
export const tarjetaPie =
    'flex flex-wrap items-center gap-2 rounded-b-lg border-t border-inst-linea bg-inst-sutil px-4.5 py-3';

/** Título de sección con la raya gruesa debajo («Cursos que dirijo»). */
export const seccionTitulo = 'flex flex-wrap items-center gap-2.5 border-b-2 border-inst-ink pb-2.5';

const boton =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border px-4 text-sm leading-[18px] font-bold whitespace-nowrap no-underline transition-colors disabled:cursor-not-allowed disabled:opacity-55 aria-disabled:cursor-not-allowed aria-disabled:opacity-55 lg:min-h-10';

/** La única acción primaria de cada vista. */
export const btnPrimario = `${boton} border-inst-accion bg-inst-accion text-inst-sobre-accion hover:border-inst-accion-h hover:bg-inst-accion-h hover:text-inst-sobre-accion`;

export const btnSecundario = `${boton} border-inst-control bg-inst-tarjeta text-inst-ink hover:bg-inst-sutil hover:text-inst-ink`;

/** Acción discreta, sin borde. */
export const btnFantasma = `${boton} border-transparent bg-transparent text-inst-enlace hover:bg-inst-accion-s hover:text-inst-enlace`;

/** Botón chico: 44 px en el teléfono, 36 px con puntero de escritorio. */
export const chico = 'px-3 text-[13px] lg:min-h-9';

export const chip = 'inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs leading-4 font-extrabold whitespace-nowrap';

/** Tonos del chip. */
export const tono = {
    ok: 'bg-inst-ok-s text-inst-ok',
    warn: 'bg-inst-warn-s text-inst-warn',
    error: 'bg-inst-error-s text-inst-error',
    info: 'bg-inst-info-s text-inst-info',
    off: 'bg-inst-off-s text-inst-ink-2',
    accion: 'bg-inst-accion-s text-inst-accion-t',
    oro: 'bg-inst-sel text-inst-ink',
    solido: 'bg-inst-accion text-inst-sobre-accion',
} as const;

export type Tono = keyof typeof tono;

/** Aviso con borde izquierdo grueso. */
export const aviso =
    'flex flex-wrap items-start gap-x-3 gap-y-2 rounded-md border border-l-4 border-inst-linea px-4 py-3';

export const avisoTono = {
    ok: 'border-l-inst-ok bg-inst-ok-s',
    warn: 'border-l-inst-warn bg-inst-warn-s',
    error: 'border-l-inst-error bg-inst-error-s',
    info: 'border-l-inst-info bg-inst-info-s',
} as const;

/** Campo de formulario. */
export const campo =
    'block min-h-11 w-full rounded-md border border-inst-control bg-inst-tarjeta px-3 text-sm text-inst-ink placeholder:text-inst-ink-3 lg:min-h-10';

/** Contenedor del cuerpo de cada página, del mismo ancho que su cabecera. */
export const cuerpo = 'mx-auto flex w-full max-w-[1200px] flex-col gap-6 px-4 pt-4 pb-8 lg:px-10 lg:pt-7 lg:pb-12';
