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
    puntaje_minimo: number;
    puntaje_total: number;
    escalas: Escala[];
}

export interface Rubrica {
    niveles: Nivel[];
    detalles_evaluacion: {
        puntaje_total: number;
        escala_evaluacion: Array<{ puntaje_minimo: number; evaluacion: string }>;
    };
};