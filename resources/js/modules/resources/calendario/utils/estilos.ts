/**
 * Lenguaje visual compartido del calendario del docente.
 *
 * Mismas constantes de clase que el resto del panel docente (ver
 * `pages/docente/CursoDetalle.svelte`): fondo de página blanco, beige #F5F1EA
 * sólo como superficie interna, azul institucional #002F6C para la acción.
 */

/** Botón secundario de contorno. */
export const BTN_OUTLINE =
  'inline-flex items-center gap-[7px] rounded-lg border border-line bg-white px-3 py-2 text-[12.5px] font-medium text-ink transition-colors hover:bg-sand';

/** Botón de acción principal. */
export const BTN_PRIMARY =
  'inline-flex items-center gap-[7px] rounded-lg border border-navy bg-navy px-3 py-2 text-[12px] font-semibold text-white transition-colors hover:bg-navy-2';

/** Pastilla de alerta (sobrecarga, rechazo). */
export const PILL_ROJA =
  'inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11.5px] font-semibold text-red-700';

/** Rótulo de sección en versalitas. */
export const ETIQUETA =
  'text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-2';

/** Contenedor de un aviso de sobrecarga. */
export const AVISO_SOBRECARGA =
  'flex items-start gap-2 rounded-[9px] border border-red-200 bg-red-50 px-2.5 py-2.5 text-[11.5px] leading-[1.45] text-red-900';
