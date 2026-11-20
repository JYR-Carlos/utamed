/**
 * Color de curso de la guía dm (uno de 8) para la vista del alumno.
 *
 * El color sale de la posición del curso dentro de su semestre en la lista
 * del menú (`auth.estudiante_courses`), así que es el mismo en el menú, el
 * Inicio, la ficha y la actividad, y dos cursos del mismo semestre no se
 * repiten mientras sean ocho o menos. Si el curso no está en la lista se
 * usa su id, para que al menos sea estable.
 */
interface CursoListado {
    id_curso: number;
    agno_real?: number | null;
    semestre_real?: number | null;
}

export const COLORES_CURSO = 8;

/**
 * Los cursos del semestre vigente: el más reciente de la lista. Los de
 * semestres anteriores quedan en «Mis cursos».
 */
export function cursosDelSemestreVigente<T extends CursoListado>(cursos: T[]): T[] {
    const periodo = (c: CursoListado) => (c.agno_real ?? 0) * 10 + (c.semestre_real ?? 0);
    const conPeriodo = cursos.filter((c) => c.agno_real != null && c.semestre_real != null);
    if (conPeriodo.length === 0) return [];
    const vigente = Math.max(...conPeriodo.map(periodo));
    return conPeriodo.filter((c) => periodo(c) === vigente);
}

export function numeroColorCurso(idCurso: number, cursos: CursoListado[] = []): number {
    const curso = cursos.find((c) => c.id_curso === idCurso);
    if (!curso) return (Math.abs(idCurso) % COLORES_CURSO) + 1;

    const delSemestre = cursos.filter(
        (c) => c.agno_real === curso.agno_real && c.semestre_real === curso.semestre_real,
    );
    return (delSemestre.indexOf(curso) % COLORES_CURSO) + 1;
}

/**
 * Variables CSS del color para un `style`: adentro se usan con
 * `bg-(--curso)`, `bg-(--curso-s)`, `text-(--curso-t)` y, sobre noche,
 * `bg-(--curso-m)`.
 */
export function estiloCurso(numero: number): string {
    const n = `--color-dm-c${numero}`;
    return `--curso: var(${n}); --curso-s: var(${n}-s); --curso-t: var(${n}-t); --curso-m: var(${n}-m)`;
}
