export interface Escala {
  id: string;
  /**
   * Puntaje de la celda. Desde que el puntaje se define por columna, todas las
   * celdas de una misma columna llevan el mismo número: se sigue estampando en
   * cada celda para que quien ya leía la rúbrica —vista del alumno, matriz de
   * evaluación, cálculo del puntaje obtenido— lo encuentre donde siempre
   * estuvo. La columna es la fuente; esto es la copia.
   */
  puntos: number;
  criterio: string;
}

/**
 * Una columna de la rúbrica: un nivel de desempeño con nombre propio.
 *
 * El nombre lo escribe el docente (hay plantillas de 3, 4 y 5 niveles como
 * punto de partida) y el puntaje vale para toda la columna, no celda por celda.
 */
export interface ColumnaNivel {
  id: string;
  nombre: string;
  puntos: number;
}

export interface Nivel {
  id: string;
  nombre: string;
  descripcion: string;
  /**
   * Peso del criterio dentro de la rúbrica, en porcentaje. Los de una misma
   * rúbrica suman 100.
   *
   * Opcional por lo mismo que {@link Rubrica.columnas}: las rúbricas guardadas
   * antes de esta versión no la traen. Al abrirlas, el editor reparte el 100 %
   * en partes iguales.
   */
  ponderacion?: number;
  nro_escalas: number;
  puntaje_total: number;
  puntaje_minimo: number;
  escalas: Escala[];
}

export interface EscalaEvaluacion {
  evaluacion: string;
  puntaje_minimo: number;
}

export interface DetallesEvaluacion {
  puntaje_total: number;
  escala_evaluacion: EscalaEvaluacion[];
}

export interface Rubrica {
  niveles: Nivel[];
  /**
   * Columnas con nombre y puntaje propios.
   *
   * Opcional porque las rúbricas guardadas antes de esta versión no la traen:
   * ahí las columnas no tenían nombre («Nivel 1», «Nivel 2»…) y el puntaje
   * vivía suelto en cada celda. Quien la lea debe tolerar su ausencia; quien la
   * escriba, incluirla siempre.
   */
  columnas?: ColumnaNivel[];
  detalles_evaluacion: DetallesEvaluacion;
}

export interface RubricaResponse {
  id_rubrica: number;
  rubrica: Rubrica;
  estado_rubrica: string;
  id_actividad: number;
}