/**
 * Tipos canónicos de la estructura JSONB `programa.data_syllabus`.
 *
 * Única fuente de verdad para el contrato de syllabus, usada por todas las
 * vistas (wizard admin/ayudante/docente, alumno, jefe de carrera). Antes de
 * este módulo existían 3 declaraciones duplicadas y desactualizadas
 * (admin.types.ts, programa.types.ts, SyllabusModal.svelte) que tipaban
 * `secciones` como `Record<string, any>`.
 *
 * Contrato verificado end-to-end contra SyllabusModal.svelte::buildSecciones()
 * (escritura real) y las reglas de validación de Admin\ProgramaController.php
 * (getValidationRulesForBasico/Completo/Seccion).
 */

// ─── Metadata ────────────────────────────────────────────────────────────────

export interface SyllabusHorasAsignatura {
  catedra: number;
  taller: number;
  laboratorio: number;
  dirigidas: number;
  autonomas: number;
}

export interface SyllabusHorasSeccionI {
  catedra: number;
  taller: number;
  laboratorio: number;
}

export interface SyllabusAsignatura {
  id_asignatura: number;
  nombre: string;
  codigo: string;
  descripcion: string | null;
  creditos_sct: number;
  horas: SyllabusHorasAsignatura;
}

export interface SyllabusDocentePrincipal {
  id_docente: number;
  nombre: string | null;
  titulo: string | null;
  grado: string | null;
  cargo: string | null;
}

export interface SyllabusCurso {
  id_curso: number;
  codigo: string;
  año_academico: number;
  semestre: number;
  es_plantilla: boolean;
  seccion_count: number;
  docente_principal: SyllabusDocentePrincipal | null;
}

export interface SyllabusCategoria {
  tipo: string;
  descripcion: string;
}

export type SyllabusTipo = 'BASICO' | 'COMPLETO';

export interface SyllabusMetadata {
  asignatura: SyllabusAsignatura;
  curso: SyllabusCurso;
  categoria: SyllabusCategoria;
  tipo_syllabus: SyllabusTipo;
}

// ─── Contenido por sección (I - IX) ─────────────────────────────────────────

export interface SeccionIContenido {
  nombre_asignatura: string;
  codigo: string;
  creditos_sct: number;
  horas: SyllabusHorasSeccionI;
  categoria: string;
}

/** Usado por las secciones II (Presentación) y III (Estándares). */
export interface SeccionTextoContenido {
  texto: string;
}

export interface TituloItem {
  titulo: string;
}

export interface SeccionIVContenido {
  competencias_especificas: TituloItem[];
  competencias_genericas: TituloItem[];
  subcompetencias: TituloItem[];
}

export interface EvaluacionDiagnosticaItem {
  titulo: string;
  descripcion: string | null;
}

export interface SeccionVContenido {
  items: EvaluacionDiagnosticaItem[];
}

export interface ResultadoAprendizajeItem {
  resultado: string;
}

export interface UnidadSyllabus {
  numero: number;
  titulo: string;
  contenidos_items: { item: string }[];
  resultados_aprendizaje: ResultadoAprendizajeItem[];
}

export interface SeccionVIContenido {
  unidades: UnidadSyllabus[];
}

export interface ActividadSyllabus {
  id_actividad: number | null;
  nombre: string;
  tipo: string;
  nombre_unidad: string;
}

/** Sección VII en syllabus BASICO: actividades de aprendizaje. */
export interface SeccionVIIContenidoBasico {
  actividades: ActividadSyllabus[];
}

/** Sección VII en syllabus COMPLETO: planificación de la enseñanza. */
export interface SeccionVIIContenidoCompleto {
  resultados_aprendizaje: { titulo: string; items: ResultadoAprendizajeItem[] };
  metodologia: { titulo: string; tipo_estrategia: string };
  evaluacion: { titulo: string; tipo_evaluacion: string };
}

export interface RecursoSyllabus {
  descripcion: string;
  tipo: string;
  ubicacion: string | null;
}

export interface SeccionVIIIContenido {
  recursos: RecursoSyllabus[];
}

export interface ComponenteEvaluacion {
  componente: string;
  porcentaje: number;
  genera_acta: boolean;
  aprobacion_obligatoria: boolean;
  asistencia_obligatoria: number | null;
}

export interface SeccionIXContenido {
  descripcion: string;
  ponderacion_optativa: { porcentaje: number };
  tabla_componentes: ComponenteEvaluacion[];
}

// ─── Envoltorio de sección + contenedor asociativo (formato real post-wizard) ─

export interface SeccionEnvoltorio<C> {
  contenido: C;
  ultima_modificacion?: string;
}

export interface DataSyllabusSeccionesBasico {
  I: SeccionEnvoltorio<SeccionIContenido>;
  II: SeccionEnvoltorio<SeccionTextoContenido>;
  VI: SeccionEnvoltorio<SeccionVIContenido>;
  VII: SeccionEnvoltorio<SeccionVIIContenidoBasico>;
  VIII: SeccionEnvoltorio<SeccionVIIIContenido>;
}

export interface DataSyllabusSeccionesCompleto {
  I: SeccionEnvoltorio<SeccionIContenido>;
  II: SeccionEnvoltorio<SeccionTextoContenido>;
  III: SeccionEnvoltorio<SeccionTextoContenido>;
  IV: SeccionEnvoltorio<SeccionIVContenido>;
  V: SeccionEnvoltorio<SeccionVContenido>;
  VI: SeccionEnvoltorio<SeccionVIContenido>;
  VII: SeccionEnvoltorio<SeccionVIIContenidoCompleto>;
  VIII: SeccionEnvoltorio<SeccionVIIIContenido>;
  IX: SeccionEnvoltorio<SeccionIXContenido>;
}

export type DataSyllabusSecciones = DataSyllabusSeccionesBasico | DataSyllabusSeccionesCompleto;

// ─── Formato transitorio/legacy (shell de SyllabusStructure::build(), pre-wizard) ─

export interface ContenidoSecuencial {
  texto_contenido: string;
  orden_item: number;
  descripcion?: string | null;
  num_unidad?: number;
}

export interface SeccionSecuencial {
  nombre_seccion: string;
  numeral_romano: string;
  orden: number;
  contenidos: ContenidoSecuencial[];
}

// ─── Contenedor raíz de data_syllabus ───────────────────────────────────────

export interface DataSyllabus {
  metadata: SyllabusMetadata;
  /**
   * Forma asociativa (I..IX con 'contenido') una vez que el wizard guardó
   * contenido real, o forma secuencial legacy justo tras instanciar el
   * programa sin haber pasado aún por el wizard.
   */
  secciones: DataSyllabusSecciones | SeccionSecuencial[];
  timestamp: string;
}

// ─── Tipos "aplanados" (salida de ParsesSyllabus, consumidos por ProgramaDocument) ─

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
  /** Campo real emitido por ParsesSyllabus::parseSecciones(). */
  contenidos: ContenidoPrograma[];
  /** Alias legacy — algunos componentes lo leen defensivamente (`contenidos || contenidos_programa`). */
  contenidos_programa?: ContenidoPrograma[];
  componentes?: ComponenteEvaluacion[];
  ponderacion_optativa?: { porcentaje?: number } | null;
}

// ─── Vista del alumno ────────────────────────────────────────────────────────

/**
 * Prop `datos` que emite `App\Services\Student\StudentSyllabusPresenter`.
 *
 * Es el syllabus ya aplanado para las vistas del alumno: la ficha del curso
 * (`student/Courses/Show`) y el programa completo (`student/Courses/Syllabus`).
 * Todos los campos son opcionales porque un programa BÁSICO no trae las
 * secciones exclusivas del COMPLETO.
 */
export interface DatosSyllabusAlumno {
  categoria?: string;
  descripcion?: string;
  competencias_especificas?: TituloItem[];
  competencias_genericas?: TituloItem[];
  componentes?: ComponenteEvaluacion[];
  normativa?: string;
  recursos?: RecursoSyllabus[];
  resultados_aprendizaje?: ResultadoAprendizajeItem[];
  unidades?: UnidadSyllabus[];
}

/** Docente de la sección del alumno, tal como lo entrega el presenter. */
export interface DocenteAlumno {
  nombre: string;
  email?: string | null;
  es_titular: boolean;
  componente?: string | null;
}
