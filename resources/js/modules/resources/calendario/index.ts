/**
 * Módulo Calendario — barrel principal.
 *
 * Calendario académico del docente en tres vistas (mes, semana y agenda) sobre
 * las mismas marcas: fechas límite de actividades, sesiones de asistencia ya
 * tomadas e hitos de syllabus, todas coloreadas por CURSO, con filtro por curso
 * y por familia, detalle del día y detección de sobrecarga por grupo.
 */
export {
  CalendarAgenda,
  CalendarMonth,
  CalendarWeek,
  CalendarioVacio,
  CourseFilter,
  DayEventsDialog,
  EventPill,
  ItemCard,
  ResumenPeriodo,
  TipoEventoFilter,
} from './components';
export type {
  CalendarCurso,
  CalendarDay,
  CalendarEvento,
  CalendarHito,
  CalendarItem,
  CalendarSesion,
  CursoAccent,
  FamiliaItem,
  Sobrecarga,
  TipoHito,
} from './types';
export * from './utils/calendar';
export * from './utils/estilos';
export * from './utils/sobrecarga';
