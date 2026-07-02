/**
 * Módulo Calendario — barrel principal.
 *
 * Calendario mensual de eventos académicos (actividades/agenda) del
 * estudiante y docente, con filtro por curso, diálogo por día y lista de
 * próximos eventos.
 */
export {
  CalendarMonth,
  CourseFilter,
  DayEventsDialog,
  EventPill,
  UpcomingList,
} from './components';
export type { CalendarCurso, CalendarDay, CalendarEvento, CursoAccent } from './types';
export * from './utils/calendar';
