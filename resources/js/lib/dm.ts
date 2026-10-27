/**
 * Clases de Tailwind de la guía dm que se repiten en la vista del alumno.
 *
 * Son cadenas completas (no armadas por partes) para que Tailwind las
 * encuentre al compilar. Los colores salen de guia-dm.css.
 */

/** Rótulo en mayúsculas: 12 px, el único tamaño bajo 13 px que admite la guía. */
export const etiqueta = 'text-xs leading-4 font-bold tracking-[0.06em] uppercase';

/** Título condensado de cabecera (Sofia Sans Condensed 900). */
export const tituloCond = 'font-dm-cond font-black uppercase';

export const tarjeta = 'rounded-xl border border-dm-linea bg-dm-tarjeta';

const boton =
    'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-[10px] px-4 py-2 text-sm font-semibold no-underline transition-colors disabled:cursor-not-allowed disabled:opacity-50';

/** La única acción primaria de cada vista. */
export const btnPrimario = `${boton} bg-dm-accion text-dm-sobre-accion hover:bg-dm-accion-hover hover:text-dm-sobre-accion`;

export const btnSecundario = `${boton} border border-dm-control bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil hover:text-dm-ink`;

/** Botón con borde sobre fondo noche (cabeceras). */
export const btnNoche = `${boton} border border-dm-noche-texto-2 text-dm-noche-texto hover:bg-dm-noche-activo hover:text-dm-noche-texto`;

/** Enlace de acción discreta («Ver todos ›»). */
export const enlace =
    'inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-dm-enlace no-underline hover:text-dm-enlace-hover hover:underline lg:min-h-9';

export const pildora = 'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[13px] leading-[18px] font-semibold';

/** Tonos de la píldora. `curso` usa las variables de estiloCurso(). */
export const tono = {
    ok: 'bg-dm-ok-s text-dm-ok',
    warn: 'bg-dm-warn-s text-dm-warn',
    info: 'bg-dm-info-s text-dm-info',
    off: 'bg-dm-off-s text-dm-off',
    error: 'bg-dm-error-s text-dm-error',
    accion: 'bg-dm-accion-suave text-dm-accion-texto',
    activo: 'bg-dm-activo-suave text-dm-activo-texto',
    neutro: 'bg-dm-sutil text-dm-ink-2',
    curso: 'bg-(--curso-s) text-(--curso-t)',
} as const;

export type Tono = keyof typeof tono;

/** Píldora sobre fondo noche (tipo, modalidad, componente). */
export const pildoraNoche =
    'inline-flex items-center rounded-full border border-dm-noche-chip px-2.5 py-0.5 text-[13px] leading-[18px] font-semibold text-dm-noche-texto';

/** Campo de formulario. */
export const campo =
    'block min-h-11 w-full rounded-[10px] border border-dm-control bg-dm-tarjeta px-3 py-2 font-dm text-[15px] text-dm-ink placeholder:text-dm-ink-3 focus:border-dm-enlace focus:outline-2 focus:outline-offset-0 focus:outline-dm-enlace aria-invalid:border-dm-error disabled:bg-dm-sutil disabled:text-dm-ink-2';
