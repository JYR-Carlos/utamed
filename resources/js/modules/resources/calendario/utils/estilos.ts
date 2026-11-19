/**
 * Lenguaje visual del calendario del docente: las piezas de la dirección
 * institucional UTA (lib/inst.ts) con los nombres que usa este módulo.
 * La sobrecarga es un aviso (tono warn), no un error: el rojo queda para
 * un syllabus rechazado.
 */
import { avisoTono, btnPrimario, btnSecundario, chico, chip, etiqueta, tono } from '@/lib/inst';

/** Botón secundario de contorno. */
export const BTN_OUTLINE = `${btnSecundario} ${chico}`;

/** Botón de acción principal. */
export const BTN_PRIMARY = `${btnPrimario} ${chico}`;

/** Rótulo de sobrecarga («2 días con sobrecarga»). */
export const PILL_SOBRECARGA = `${chip} ${tono.warn}`;

/** Rótulo de sección en versalitas. */
export const ETIQUETA = etiqueta;

/** Contenedor de un aviso de sobrecarga. */
export const AVISO_SOBRECARGA = `flex items-start gap-2 rounded-md border border-l-4 border-inst-linea px-3 py-2.5 text-[13px] leading-[1.45] text-inst-ink ${avisoTono.warn}`;
