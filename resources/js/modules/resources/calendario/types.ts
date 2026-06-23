/**
 * Tipos del módulo de calendario del docente.
 *
 * El calendario es una vista de solo lectura que muestra las actividades a
 * vencer de todos los cursos del docente, agrupadas por día, al estilo de un
 * Google Calendar ambientado a UTAMED.
 */

/** Una actividad ubicada en una fecha del calendario. */
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
}

/** Curso del docente con su nombre amplio (asignatura + año + semestre). */
export interface CalendarCurso {
  id_curso: number;
  /** Nombre del curso = nombre asignatura + año + semestre. */
  nombre: string;
  asignatura: string;
  cod_curso: string | null;
  agno_real: number | null;
  semestre_real: number | null;
  total_actividades: number;
}

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
