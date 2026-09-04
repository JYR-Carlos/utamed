export interface Actividad {
    id_actividad: number;
    nombre: string;
    fecha_limite: string;
    tipo_actividad: 'SUMATIVA' | 'FORMATIVA';
    tipo_entrega: string;
    es_grupal: boolean;
    max_integrantes: number;
    visible: boolean;
    ponderacion?: number;
    exigencia?: number;
    id_componente?: number;
    id_unidad?: number;
    componente?: { id_componente: number; tipo_componente?: { tipo: string } | null } | null;
    seccion?: { id_seccion?: number; tipo?: string } | null;
    unidad?: { id_unidad: number; nombre: string; num_unidad?: number } | null;
    ultima_nota?: number | null;
    /**
     * Entregas de archivo realmente recibidas (agenda.agenda con
     * tipo_mensaje = 'Entrega de archivo'). Es lo que se pierde al borrar.
     */
    total_entregas?: number;
    /** Grupos asignados de la actividad (cada uno es una entrega evaluable). */
    total_grupos?: number;
    /** Grupos con nota puesta. */
    calificados?: number;
    /** Promedio de las notas puestas, o null si todavía no hay ninguna. */
    promedio_nota?: number | null;
    /**
     * Mensajes de agenda de esta actividad cuyo último turno es del estudiante.
     * Es la mensajería de nivel actividad; no incluye la mensajería del curso
     * (curso.mensaje), que se lleva aparte en /docente/cursos/{id}/mensajeria.
     */
    mensajes_pendientes?: number;
    archivo_enunciado?: {
        nombre_original: string;
        mime_type: string | null;
        peso_bytes: number | null;
    } | null;
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

export interface ArchivoEntrega {
    nombre_original: string;
    mime_type: string | null;
    tamanio_bytes: number | null;
}

export interface Entrega {
    id_agenda: number;
    fecha_envio: string;
    mensaje: string | null;
    tipo_registro: string;
    archivo: ArchivoEntrega | null;
    evaluada: boolean;
}

export interface ActividadResumen {
    id_actividad: number;
    nombre: string;
    es_grupal: boolean;
    fecha_limite: string;
}

/** Mensaje/feedback de nivel 2 asociado a un grupo de actividad (agenda.agenda) */
export interface MensajeGrupo {
    id_agenda: number;
    fecha_envio: string;
    mensaje: string;
    tipo_registro: 'Mensaje al profesor' | 'Feedback' | string;
    emisor_id_usuario: number;
    emisor_nombre: string;
}

/** Mensaje directo de nivel 1 entre docente y estudiante (agenda.mensaje_directo) */
export interface MensajeEstudiante {
    id_agenda: number;
    fecha_envio: string;
    mensaje: string;
    grupo: number;
    tipo_registro: 'Mensaje al profesor' | 'Feedback' | string;
    emisor_nombre: string;
    emisor_id_usuario: number;
    actividad_nombre: string;
    id_actividad: number;
}
