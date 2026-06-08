export interface Escala {
    id: string;
    puntos: number;
    criterio: string;
}

export interface Nivel {
    id: string;
    nombre: string;
    descripcion: string;
    nroEscalas: number;
    puntajeTotal: number;
    puntajeMinimo: number;
    escalas: Escala[];
}

export interface EscalaEvaluacion {
    evaluacion: string;
    puntajeMinimo: number;
}

export interface DetallesEvaluacion {
    puntajeTotal: number;
    escalaEvaluacion: EscalaEvaluacion[];
}

export interface Rubrica {
    niveles: Nivel[];
    detallesEvaluacion: DetallesEvaluacion;
}