/**
 * Tipos del módulo Bibliografía (vista del estudiante).
 */

/** Entrada bibliográfica de una unidad del curso; el shape viene del payload del backend. */
export interface Bib {
    id_bib: number;
    titulo: string;
    autor: string;
    editorial: string;
    año: number;
    url?: string;
    tipo: 'utamed' | 'uta' | 'otro';
    unidad: string;
}
