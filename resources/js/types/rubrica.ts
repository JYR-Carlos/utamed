export interface Escala {
  id: string;
  puntos: number;
  criterio: string;
}

export interface Nivel {
  id: string;
  nombre: string;
  descripcion: string;
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
  detalles_evaluacion: DetallesEvaluacion;
}

export interface RubricaResponse {
  id_rubrica: number;
  rubrica: Rubrica;
  estado_rubrica: string;
  id_actividad: number;
}