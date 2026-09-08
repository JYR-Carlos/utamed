/**
 * Tipos del módulo de calendario del docente.
 *
 * El calendario es una vista de solo lectura que reúne tres familias de marcas
 * sobre el mismo eje temporal, todas coloreadas por CURSO (el color pertenece
 * al curso, no al tipo de evento):
 *
 * - ENTREGA → fecha límite de una actividad (fecha nominal).
 * - SESION  → sesión de asistencia ya tomada, con su contador de presentes.
 * - HITO    → hito del syllabus (fecha límite de entrega o revisión).
 */

/** Una actividad ubicada en su fecha límite nominal. */
export interface CalendarEvento {
  id_actividad: number;
  id_curso: number;
  titulo: string;
  /** Fecha límite en formato 'YYYY-MM-DD'. */
  fecha: string;
  tipo_actividad: 'SUMATIVA' | 'FORMATIVA' | string;
  tipo_entrega: string | null;
  es_grupal: boolean;
  visible: boolean;
  /** Tipo del componente (p. ej. "Teoría", "Laboratorio"). */
  componente: string;
  id_componente: number;
  /** Grupos asignados a la actividad (0 si aún no se han creado). */
  grupos_total: number;
  /** Grupos asignados que siguen sin nota. */
  grupos_sin_nota: number;
}

/**
 * Sesión de asistencia ya tomada. La sesión es implícita en el modelo: la
 * identifica la tupla (componente, día, hora inicio, hora fin) de las filas de
 * `curso.asistencia`. No existen sesiones "planificadas": si no hay filas, no
 * hubo toma de asistencia y el calendario no puede saber que tocaba clase.
 */
export interface CalendarSesion {
  id_curso: number;
  id_componente: number;
  /** Tipo del componente (p. ej. "Cátedra"). */
  componente: string;
  /** Día en formato 'YYYY-MM-DD'. */
  fecha: string;
  /** Hora 'HH:MM'. */
  hora_inicio: string;
  /** Hora 'HH:MM'. */
  hora_fin: string;
  presentes: number;
  total: number;
}

/** Naturaleza de un hito de syllabus. */
export type TipoHito = 'LIMITE_BASICO' | 'LIMITE_SYLLABUS' | 'APROBACION' | 'RECHAZO';

/** Hito del syllabus con fecha propia. */
export interface CalendarHito {
  /** Identificador estable dentro del payload. */
  id: string;
  id_curso: number;
  /** Fecha en formato 'YYYY-MM-DD'. */
  fecha: string;
  tipo: TipoHito;
  titulo: string;
  /** Observaciones del historial: en un rechazo es la razón. */
  detalle: string | null;
}

/** Curso del docente con su nombre amplio (asignatura + año + semestre). */
export interface CalendarCurso {
  id_curso: number;
  /** Nombre del curso = nombre asignatura + año + semestre. */
  nombre: string;
  asignatura: string;
  cod_curso: string | null;
  /** Letra del grupo del curso (curso.letra_grupo), puede faltar. */
  letra_grupo: string | null;
  agno_real: number | null;
  semestre_real: number | null;
  total_actividades: number;
  total_sesiones: number;
  total_hitos: number;
}

/** Familia de marca del calendario. */
export type FamiliaItem = 'ENTREGA' | 'SESION' | 'HITO';

/**
 * Marca unificada que las tres vistas (mes, semana, agenda) saben dibujar.
 * `key` es único dentro del payload y sirve para los bloques `{#each}`.
 */
export type CalendarItem =
  | { familia: 'ENTREGA'; key: string; id_curso: number; fecha: string; entrega: CalendarEvento }
  | { familia: 'SESION'; key: string; id_curso: number; fecha: string; sesion: CalendarSesion }
  | { familia: 'HITO'; key: string; id_curso: number; fecha: string; hito: CalendarHito };

/** Paleta de un curso (color asignado de forma estable según su posición). */
export interface CursoAccent {
  base: string;
  soft: string;
  text: string;
  border: string;
}

/** Día renderizado dentro de la grilla mensual. */
export interface CalendarDay {
  /** Fecha en formato 'YYYY-MM-DD'. */
  iso: string;
  dayOfMonth: number;
  inMonth: boolean;
  isToday: boolean;
  isWeekend: boolean;
}

/**
 * Sobrecarga detectada en un día: un mismo grupo acumula tres o más fechas
 * límite. Se calcula SIEMPRE sobre todas las entregas del docente, incluidas
 * las de cursos ocultos por el filtro: esconder un curso no reduce la carga
 * real del grupo.
 */
export interface Sobrecarga {
  /** Clave del grupo: 'año-semestre-letra'. */
  clave: string;
  /** Letra del grupo, p. ej. "A". */
  letra: string;
  /** Entregas del grupo ese día, contando cursos ocultos. */
  total: number;
  /** Entregas visibles con el filtro actual. */
  visibles: number;
  /** Entregas que el filtro deja fuera. */
  ocultas: number;
  /** Títulos de todas las entregas del grupo ese día. */
  titulos: string[];
}
