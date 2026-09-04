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
export const DIAS_SEMANA = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];

const DIAS_LARGOS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

/**
 * Paleta de acentos por curso.
 *
 * El color pertenece al CURSO, no al tipo de evento: con varios cursos abiertos
 * la primera pregunta del docente es "de qué curso es esta fecha". Los cinco
 * primeros tonos son los del lenguaje visual institucional; los tres restantes
 * continúan el pool para docentes con más de cinco cursos, corriendo el turno
 * cuando dos cursos colisionarían.
 */
export const ACCENTS: CursoAccent[] = [
  { base: '#0F5B9B', soft: '#EAF2FA', text: '#0F5B9B', border: '#C4DAF0' },
  { base: '#8A2B4E', soft: '#FBEDF1', text: '#8A2B4E', border: '#EBC9D5' },
  { base: '#1F6F45', soft: '#EAF5EF', text: '#1F6F45', border: '#C3E2D0' },
  { base: '#6B4BA8', soft: '#F1ECFA', text: '#6B4BA8', border: '#D8CCEF' },
  { base: '#A2551A', soft: '#FBF0E6', text: '#A2551A', border: '#EDD5BC' },
  { base: '#0E6A80', soft: '#E5F1F5', text: '#0B5464', border: '#BCDDE7' },
  { base: '#31456B', soft: '#ECEEF4', text: '#31456B', border: '#C9D0DF' },
  { base: '#8A6D14', soft: '#F7F1DE', text: '#6F5710', border: '#E5D8A9' },
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

/** Convierte una clave 'YYYY-MM-DD' en un Date local. */
export function fromKey(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}

/** Describe un día suelto (sin depender de la matriz mensual). */
export function describirDia(iso: string, mesReferencia?: number): CalendarDay {
  const date = fromKey(iso);
  const dow = date.getDay();
  return {
    iso,
    dayOfMonth: date.getDate(),
    inMonth: mesReferencia === undefined ? true : date.getMonth() === mesReferencia,
    isToday: iso === todayKey(),
    isWeekend: dow === 0 || dow === 6,
  };
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

/** Lunes de la semana que contiene `iso`. */
export function inicioSemana(iso: string): string {
  const d = fromKey(iso);
  const offset = (d.getDay() + 6) % 7;
  d.setDate(d.getDate() - offset);
  return toKey(d);
}

/** Los siete días (lunes → domingo) de la semana que contiene `iso`. */
export function buildWeekDays(iso: string): CalendarDay[] {
  const today = todayKey();
  const start = fromKey(inicioSemana(iso));
  const dias: CalendarDay[] = [];

  for (let i = 0; i < 7; i++) {
    const current = new Date(start);
    current.setDate(start.getDate() + i);
    const key = toKey(current);
    const dow = current.getDay();
    dias.push({
      iso: key,
      dayOfMonth: current.getDate(),
      inMonth: true,
      isToday: key === today,
      isWeekend: dow === 0 || dow === 6,
    });
  }

  return dias;
}

/** Desplaza una clave 'YYYY-MM-DD' en N días. */
export function sumarDias(iso: string, dias: number): string {
  const d = fromKey(iso);
  d.setDate(d.getDate() + dias);
  return toKey(d);
}

/** Minutos desde medianoche de una hora 'HH:MM' (0 si no es válida). */
export function minutosDeHora(hora: string): number {
  const [h, m] = (hora ?? '').split(':').map(Number);
  if (Number.isNaN(h)) return 0;
  return h * 60 + (Number.isNaN(m) ? 0 : m);
}

/** Minutos transcurridos hoy, para la línea de "ahora" de la vista semana. */
export function minutosAhora(): number {
  const now = new Date();
  return now.getHours() * 60 + now.getMinutes();
}

/** Formatea 'YYYY-MM-DD' como "lunes 22 de junio de 2026". */
export function formatLargo(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return `${DIAS_LARGOS[date.getDay()]} ${d} de ${MESES[m - 1].toLowerCase()} de ${y}`;
}

/** Formatea 'YYYY-MM-DD' como "lunes 22 jun" (cabeceras de la agenda). */
export function formatMedio(iso: string): string {
  const [, m, d] = iso.split('-').map(Number);
  const date = fromKey(iso);
  return `${DIAS_LARGOS[date.getDay()]} ${d} ${MESES[m - 1].slice(0, 3).toLowerCase()}`;
}

/** Formatea 'YYYY-MM-DD' como "22 jun" (compacto). */
export function formatCorto(iso: string): string {
  const [, m, d] = iso.split('-').map(Number);
  return `${d} ${MESES[m - 1].slice(0, 3).toLowerCase()}`;
}

/** Rango de una semana: "14 – 20 sep 2026". */
export function formatRangoSemana(dias: CalendarDay[]): string {
  if (dias.length === 0) return '';
  const primero = dias[0];
  const ultimo = dias[dias.length - 1];
  const [ay, am, ad] = primero.iso.split('-').map(Number);
  const [by, bm, bd] = ultimo.iso.split('-').map(Number);

  if (am === bm) return `${ad} – ${bd} ${MESES[am - 1].slice(0, 3).toLowerCase()} ${by}`;
  if (ay === by) {
    return `${ad} ${MESES[am - 1].slice(0, 3).toLowerCase()} – ${bd} ${MESES[bm - 1]
      .slice(0, 3)
      .toLowerCase()} ${by}`;
  }
  return `${formatCorto(primero.iso)} ${ay} – ${formatCorto(ultimo.iso)} ${by}`;
}

/** Días enteros desde hoy hasta `iso` (negativo si ya pasó). */
export function diasRestantes(iso: string): number {
  const target = fromKey(iso);
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
