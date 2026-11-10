import type { Rubrica } from '@/types/rubrica';

/** Un nivel de un criterio, en el orden de las columnas de la rúbrica. */
export interface NivelCriterio {
    nombre: string;
    /** El que asignó el docente. */
    marcado: boolean;
}

export interface CriterioAlumno {
    id: string;
    nombre: string;
    /** «30 %» o, en rúbricas sin ponderación, «5 pts». */
    peso: string;
    niveles: NivelCriterio[];
    /**
     * Barra del móvil, de menos a más puntaje (hay rúbricas con «Excelente» en
     * la primera columna): llena hasta el nivel obtenido.
     */
    barra: boolean[];
    /** Nombre del nivel marcado; null si el criterio no tiene resultado. */
    obtenido: string | null;
}

/**
 * Los criterios de una rúbrica tal como los lee el alumno. Las columnas sin
 * nombre (rúbricas anteriores a `columnas`) se numeran, igual que en la vista
 * completa de la rúbrica.
 */
export function criteriosDe(rubrica: Rubrica | null | undefined, resultado?: Record<string, string> | null): CriterioAlumno[] {
    return (rubrica?.niveles ?? []).map((criterio) => {
        const elegida = resultado?.[String(criterio.id)];
        const marcada = criterio.escalas.find((e) => elegida != null && String(e.id) === String(elegida));
        const niveles = criterio.escalas.map((escala, i) => ({
            nombre: rubrica?.columnas?.[i]?.nombre?.trim() || `Nivel ${i + 1}`,
            marcado: escala === marcada,
        }));
        const barra = criterio.escalas
            .map((e) => Number(e.puntos))
            .sort((a, b) => a - b)
            .map((puntos) => marcada != null && puntos <= Number(marcada.puntos));
        const ponderacion = Number(criterio.ponderacion);

        return {
            id: String(criterio.id),
            nombre: criterio.nombre,
            peso: ponderacion > 0 ? `${ponderacion} %` : `${criterio.puntaje_total} pts`,
            niveles,
            barra,
            obtenido: niveles.find((n) => n.marcado)?.nombre ?? null,
        };
    });
}

/** Puntaje máximo de la rúbrica. */
export function puntajeTotal(rubrica: Rubrica | null | undefined): number {
    return rubrica?.detalles_evaluacion?.puntaje_total ?? rubrica?.niveles?.reduce((n, c) => n + c.puntaje_total, 0) ?? 0;
}
