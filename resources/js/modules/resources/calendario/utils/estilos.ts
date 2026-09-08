/**
 * Lenguaje visual compartido del calendario del docente.
 *
 * Mismas constantes de clase que el resto del panel docente (ver
 * `pages/docente/CursoDetalle.svelte`): fondo de página blanco, beige #F5F1EA
 * sólo como superficie interna, azul institucional #002F6C para la acción.
 */

/** Botón secundario de contorno. */
export const BTN_OUTLINE =
  'inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-[12.5px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA]';

/** Botón de acción principal. */
export const BTN_PRIMARY =
  'inline-flex items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#002F6C] px-3 py-2 text-[12px] font-semibold text-white transition-colors hover:bg-[#1B4789]';

/** Pastilla de alerta (sobrecarga, rechazo). */
export const PILL_ROJA =
  'inline-flex items-center gap-1.5 rounded-full border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#B91C1C]';

/** Rótulo de sección en versalitas. */
export const ETIQUETA =
  'text-[11px] font-semibold uppercase tracking-[0.08em] text-[#5A5E6E]';

/** Contenedor de un aviso de sobrecarga. */
export const AVISO_SOBRECARGA =
  'flex items-start gap-2 rounded-[9px] border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-2.5 text-[11.5px] leading-[1.45] text-[#7F1D1D]';
