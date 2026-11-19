/**
 * Cursos de los centros transversales del docente (Calificaciones y
 * Asistencia): cada curso con los componentes que el docente ve. El titular
 * del curso ve todos; el resto, sólo los que imparte.
 */
export interface ComponenteCentro {
    id_componente: number;
    tipo_componente: string;
    imparte: boolean;
    es_titular_componente: boolean;
}

export interface CursoCentro<K extends ComponenteCentro = ComponenteCentro> {
    id_curso: number;
    nombre: string;
    etiqueta: string;
    letra_grupo: string | null;
    cod_asignatura: string | null;
    cod_curso: string;
    agno_real: number | null;
    semestre_real: number | null;
    es_titular_curso: boolean;
    componentes: K[];
}
