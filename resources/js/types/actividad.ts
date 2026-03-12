export interface Actividad {
    id_actividad: number;
    nombre: string;
    fecha_limite: string;
    tipo_actividad: number;
    tipo_entrega: string;
    es_grupal: boolean;
    max_integrantes: number;
    visible: boolean;
    id_seccion?: number;
    id_unidad?: number;
    seccion?: { id_seccion: number; tipo: string } | null;
    unidad?: { id_unidad: number; nombre: string } | null;
}

export interface Integrante {
    id_asignado_actividad: number;
    id_estudiante: number;
    nombre_completo: string;
    nota_individual: number | null;
    diferencia_decimas: number | null;
}

export interface Grupo {
    grupo: number;
    nota: number | null;
    id_estado: number | null;
    estado: { id_estado: number; titulo: string } | null;
    integrantes: Integrante[];
}

export interface Estado {
    id_estado: number;
    titulo: string;
    descripcion: string | null;
}

export interface EstudianteDisponible {
    id_estudiante: number;
    nombre_completo: string;
    email: string;
}
