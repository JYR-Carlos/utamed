import type { Rubrica } from '@/types/rubrica';

/**
 * La rúbrica como tabla: criterios en filas y niveles en columnas, igual que
 * la ventana «Rúbrica» de UTAmed anterior. La leen el alumno (pestaña Rúbrica)
 * y el docente (ver, evaluar y la vista previa del editor), cada uno con su
 * propio estilo.
 */

export interface ColumnaMatriz {
    nombre: string;
    /**
     * Puntaje de la columna, o null en las rúbricas anteriores a `columnas`:
     * ahí cada celda trae el suyo y puede variar entre criterios.
     */
    puntos: number | null;
    /**
     * Para la barra de color de la cabecera: 1 en la columna de más puntaje y
     * menos hacia la de menos, sin importar el orden en que el docente las
     * puso (hay rúbricas con «Excelente» a la izquierda y otras a la derecha).
     * 0 en la que vale 0 puntos.
     */
    intensidad: number;
}

export interface CeldaMatriz {
    id: string;
    texto: string;
    puntos: number;
    /** El nivel que asignó el docente (o que está eligiendo). */
    marcada: boolean;
}

export interface CriterioMatriz {
    id: string;
    nombre: string;
    descripcion: string;
    /** «40 %»; null si la rúbrica no pondera los criterios. */
    peso: string | null;
    maximo: number;
    /** Una por columna; null donde el criterio no tiene ese nivel. */
    celdas: (CeldaMatriz | null)[];
    /** Puntos del nivel marcado; null si el criterio no tiene marca. */
    obtenido: number | null;
}

export interface Matriz {
    columnas: ColumnaMatriz[];
    criterios: CriterioMatriz[];
    /** El puntaje va una vez en la cabecera de cada columna, no en cada celda. */
    puntajePorColumna: boolean;
    /** Puntaje máximo de la rúbrica. */
    maximo: number;
    /** Suma de los puntos de los niveles marcados. */
    obtenido: number;
    /** Criterios con un nivel marcado. */
    marcados: number;
}

/** «12», «12,5». */
export const fmtPuntos = (n: number) => n.toLocaleString('es-CL', { maximumFractionDigits: 2 });

/** «1 pt», «4 pts». */
export const pts = (n: number) => `${fmtPuntos(n)} ${n === 1 ? 'pt' : 'pts'}`;

/** Puntaje máximo de la rúbrica. */
export function puntajeTotal(rubrica: Rubrica | null | undefined): number {
    return Number(rubrica?.detalles_evaluacion?.puntaje_total ?? rubrica?.niveles?.reduce((n, c) => n + Number(c.puntaje_total), 0) ?? 0);
}

/**
 * `resultado` es criterio → escala marcada, como lo guarda la evaluación. Sin
 * él (o vacío) la tabla se lee «antes» de evaluar.
 */
export function matrizDe(rubrica: Rubrica | null | undefined, resultado?: Record<string, string> | null): Matriz {
    const niveles = rubrica?.niveles ?? [];
    const n = Math.max(0, ...niveles.map((c) => c.escalas.length));
    const puntajePorColumna = !!rubrica?.columnas?.length;

    const criterios: CriterioMatriz[] = niveles.map((c) => {
        const elegida = resultado?.[String(c.id)];
        const celdas = Array.from({ length: n }, (_, i): CeldaMatriz | null => {
            const e = c.escalas[i];
            if (!e) return null;
            return {
                id: String(e.id),
                texto: e.criterio ?? '',
                puntos: Number(e.puntos),
                marcada: elegida != null && String(e.id) === String(elegida),
            };
        });
        const ponderacion = Number(c.ponderacion);
        return {
            id: String(c.id),
            nombre: c.nombre,
            descripcion: c.descripcion ?? '',
            peso: ponderacion > 0 ? `${fmtPuntos(ponderacion)} %` : null,
            maximo: Number(c.puntaje_total) || Math.max(0, ...celdas.map((x) => x?.puntos ?? 0)),
            celdas,
            obtenido: celdas.find((x) => x?.marcada)?.puntos ?? null,
        };
    });

    // En las rúbricas antiguas la columna no tiene puntaje propio: para la
    // barra se toma el más alto de sus celdas.
    const referencia = Array.from({ length: n }, (_, i) =>
        puntajePorColumna
            ? Number(rubrica?.columnas?.[i]?.puntos ?? 0)
            : Math.max(0, ...criterios.map((c) => c.celdas[i]?.puntos ?? 0)),
    );
    const tope = Math.max(0, ...referencia);

    return {
        columnas: referencia.map((p, i) => ({
            nombre: rubrica?.columnas?.[i]?.nombre?.trim() || `Nivel ${i + 1}`,
            puntos: puntajePorColumna ? p : null,
            intensidad: tope > 0 ? p / tope : 0,
        })),
        criterios,
        puntajePorColumna,
        maximo: puntajeTotal(rubrica),
        obtenido: criterios.reduce((s, c) => s + (c.obtenido ?? 0), 0),
        marcados: criterios.filter((c) => c.obtenido !== null).length,
    };
}

/** Opacidad de la barra de una columna: nunca menos de un tercio, para que se vea. */
export const opacidadBarra = (intensidad: number) => (intensidad > 0 ? 0.3 + 0.7 * intensidad : 1);
