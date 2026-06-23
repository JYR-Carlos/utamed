/**
 * Utilidades de fechas y colores para el calendario del docente.
 *
 * Todo el manejo de fechas es local (sin zonas horarias) usando claves
 * 'YYYY-MM-DD' para evitar desfases por UTC al comparar contra fecha_limite.
 */
import type { CalendarDay, CursoAccent } from '../types';

export const MESES = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre',
];

/** Encabezados de la semana iniciando en Lunes (convención chilena). */
export const DIAS_SEMANA = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

const DIAS_LARGOS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

/** Paleta de acentos por curso — tonos distinguibles entre sí. */
export const ACCENTS: CursoAccent[] = [
  { base: '#4F46E5', soft: '#EEF0FF', text: '#3730A3', border: '#C7CBFF' },
  { base: '#0891B2', soft: '#E0F5FB', text: '#0E6A80', border: '#A5E4F2' },
  { base: '#DB2777', soft: '#FCE7F1', text: '#9D174D', border: '#F7C0DA' },
  { base: '#16A34A', soft: '#E3F8EC', text: '#11713A', border: '#B2E7C7' },
  { base: '#EA580C', soft: '#FDEDE1', text: '#9A3412', border: '#F8CBA8' },
  { base: '#7C3AED', soft: '#F3EEFF', text: '#5B21B6', border: '#D9C7FB' },
  { base: '#CA8A04', soft: '#FBF3D6', text: '#854D0E', border: '#F0DC9A' },
  { base: '#0D9488', soft: '#DEF5F2', text: '#0B6157', border: '#A4E3DB' },
];

/** Devuelve el acento estable para una posición de curso. */
export function accentFor(index: number): CursoAccent {
  return ACCENTS[((index % ACCENTS.length) + ACCENTS.length) % ACCENTS.length];
}

/** Clave local 'YYYY-MM-DD' de un Date (sin conversión a UTC). */
export function toKey(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/** Clave 'YYYY-MM-DD' de hoy. */
export function todayKey(): string {
  return toKey(new Date());
}

/**
 * Construye la matriz del mes (semanas que empiezan en Lunes), incluyendo los
 * días de relleno del mes anterior/siguiente para completar las semanas.
 */
export function buildMonthMatrix(year: number, month: number): CalendarDay[][] {
  const today = todayKey();
  const first = new Date(year, month, 1);
  // getDay(): 0=Dom..6=Sáb -> queremos offset desde Lunes
  const offset = (first.getDay() + 6) % 7;

  const start = new Date(year, month, 1 - offset);
  const weeks: CalendarDay[][] = [];
  const monthYm = year * 12 + month;

  for (let w = 0; w < 6; w++) {
    const weekStart = new Date(start);
    weekStart.setDate(start.getDate() + w * 7);

    // Detener cuando una semana completa queda más allá del mes actual
    // (evita filas de relleno cuando el mes cabe en menos semanas).
    if (w >= 4 && weekStart.getFullYear() * 12 + weekStart.getMonth() > monthYm) break;

    const week: CalendarDay[] = [];
    for (let d = 0; d < 7; d++) {
      const current = new Date(weekStart);
      current.setDate(weekStart.getDate() + d);
      const iso = toKey(current);
      const dow = current.getDay();
      week.push({
        iso,
        dayOfMonth: current.getDate(),
        inMonth: current.getMonth() === month,
        isToday: iso === today,
        isWeekend: dow === 0 || dow === 6,
      });
    }
    weeks.push(week);
  }

  return weeks;
}

/** Formatea 'YYYY-MM-DD' como "Lunes 22 de junio de 2026". */
export function formatLargo(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return `${DIAS_LARGOS[date.getDay()]} ${d} de ${MESES[m - 1].toLowerCase()} de ${y}`;
}

/** Formatea 'YYYY-MM-DD' como "22 jun" (compacto). */
export function formatCorto(iso: string): string {
  const [, m, d] = iso.split('-').map(Number);
  return `${d} ${MESES[m - 1].slice(0, 3).toLowerCase()}`;
}

/** Días enteros desde hoy hasta `iso` (negativo si ya pasó). */
export function diasRestantes(iso: string): number {
  const [y, m, d] = iso.split('-').map(Number);
  const target = new Date(y, m - 1, d);
  const now = new Date();
  const base = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  return Math.round((target.getTime() - base.getTime()) / 86_400_000);
}

/** Etiqueta relativa amigable: "Hoy", "Mañana", "En 3 días", "Hace 2 días". */
export function etiquetaRelativa(iso: string): string {
  const n = diasRestantes(iso);
  if (n === 0) return 'Hoy';
  if (n === 1) return 'Mañana';
  if (n === -1) return 'Ayer';
  if (n > 1) return `En ${n} días`;
  return `Hace ${Math.abs(n)} días`;
}
