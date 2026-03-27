/**
 * Tipos locales del módulo Programa.
 *
 * Re-exporta Programa e interfaces del módulo compartido,
 * y añade tipos propios usados en SyllabusModal / ProgramaWizardSteps.
 */

export type { Programa, Curso } from '@/types/admin.types';

// ─── Structural types for the syllabus sections ──────────────────────────────

export interface ContenidoPrograma {
    id_contenido_programa?: number;
    texto_contenido: string | null;
    valor_numerico?: number | null;
    orden_item: number;
}

export interface SeccionPrograma {
    id_estructura_programa?: number;
    nombre_seccion: string;
    numeral_romano?: string;
    orden: number;
    es_lista?: boolean;
    es_actual?: boolean;
    contenidos_programa: ContenidoPrograma[];
}

export interface ProgramaFull {
    id_programa: number;
    id_curso: number;
    estado: string;
    version_programa: number;
    fecha_creacion: string;
    es_plantilla?: boolean;
    es_actual?: boolean;
    creado_por: number;
    revisado_por?: number;
    data_syllabus?: {
        metadata?: {
            tipo_syllabus?: string;
            curso?: string;
            asignatura?: string;
            creditos?: number;
        };
        secciones?: Record<string, any>;
    };
    secciones?: SeccionPrograma[];
}

// ─── Wizard state types ───────────────────────────────────────────────────────

export interface WizardUnidad {
    numero: number;
    titulo: string;
    contenidos: string;
    resultados_aprendizaje: { resultado: string }[];
}

export interface WizardActividad {
    id_actividad: number | null;
    nombre: string;
    tipo: string;
    id_unidad?: number | null;
    id_seccion?: number | null;
    nombre_unidad?: string;
}

export interface WizardComponente {
    componente: string;
    porcentaje: number;
    genera_acta: boolean;
    aprobacion_obligatoria: boolean;
    asistencia_obligatoria: number;
}

export type SyllabusType = 'simplified' | 'complete' | 'combined';

// ─── API payload types ────────────────────────────────────────────────────────

export interface GuardarProgramaPayload {
    secciones: {
        nombre_seccion: string;
        numeral_romano: string;
        orden: number;
        contenidos: {
            texto_contenido: string;
            orden_item: number;
        }[];
    }[];
}

export interface GenerarProgramaPayload {
    secciones: Record<string, any>;
    syllabus_type: SyllabusType;
    actividades_to_create?: {
        nombre: string;
        tipo_actividad: number;
        tipo_entrega: string;
        es_grupal: boolean;
        max_integrantes: number;
        nombre_unidad: string;
    }[];
}
