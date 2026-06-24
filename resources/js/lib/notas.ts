/**
 * notas.ts — Cálculo de notas en la escala chilena.
 *
 * Fuente única de verdad para convertir un puntaje obtenido en una nota
 * de la escala chilena (1.0 a 7.0). Antes esta fórmula estaba duplicada y
 * ya divergente en `Activities/MatrizEvaluacion.svelte` (exigencia 0.6
 * hardcodeada) y `Activities/Agenda/AgendaDocente.svelte` (parametrizable).
 * Ver hallazgo D-02 de la auditoría de frontend docente.
 */

/**
 * Convierte un puntaje a la nota chilena (escala 1.0 – 7.0).
 *
 * La escala es lineal por tramos y se ancla en tres puntos:
 * - 0% del puntaje  → 1.0 (nota mínima)
 * - `exigencia`%    → 4.0 (nota de aprobación)
 * - 100% del puntaje → 7.0 (nota máxima)
 *
 * Bajo la exigencia se interpola entre 1.0 y 4.0; sobre la exigencia,
 * entre 4.0 y 7.0. El resultado se redondea a un decimal y se acota a [1, 7].
 *
 * @param puntaje   Puntaje obtenido por el estudiante.
 * @param total     Puntaje máximo posible de la evaluación.
 * @param exigencia Porcentaje de exigencia para la nota 4.0 (por defecto 60).
 * @returns Nota en la escala chilena, redondeada a un decimal, en el rango [1.0, 7.0].
 *
 * @example
 * calcularNotaChilena(0, 100);    // 1.0  (0%   → mínima)
 * calcularNotaChilena(60, 100);   // 4.0  (60%  → aprobación con exigencia por defecto)
 * calcularNotaChilena(100, 100);  // 7.0  (100% → máxima)
 * calcularNotaChilena(30, 100);   // 2.5  (mitad del tramo de reprobación)
 * calcularNotaChilena(5, 0);      // 1.0  (sin puntaje total → mínima)
 */
export function calcularNotaChilena(puntaje: number, total: number, exigencia = 60): number {
    if (total === 0) return 1;
    const min = total * (exigencia / 100);
    const nota = puntaje >= min ? 4 + (3 * (puntaje - min)) / (total - min) : 1 + (3 * puntaje) / min;
    return Math.min(7, Math.max(1, Math.round(nota * 10) / 10));
}

// TODO(D-02): verificar que el backend usa la misma fórmula al persistir notas
