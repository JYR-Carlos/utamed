/**
 * Tipos locales del módulo Programa.
 *
 * Re-exporta Programa e interfaces del módulo compartido,
 * y añade tipos propios usados en SyllabusModal / ProgramaWizardSteps.
 */

export type { Programa, Curso } from '@/types/admin.types';
export type { ContenidoPrograma, SeccionPrograma } from '@/types/syllabus.types';

import type { DataSyllabus, DataSyllabusSecciones, SeccionPrograma } from '@/types/syllabus.types';

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
    data_syllabus?: DataSyllabus;
    secciones?: SeccionPrograma[];
}

// ─── Wizard state types ───────────────────────────────────────────────────────
// Shape del estado local del wizard (no del JSONB); se transforman a
// DataSyllabusSecciones al guardar (ver SyllabusModal.svelte::buildSecciones()).

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
    secciones: DataSyllabusSecciones;
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
