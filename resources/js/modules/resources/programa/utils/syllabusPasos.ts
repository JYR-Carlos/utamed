/**
 * Pasos del asistente de syllabus.
 *
 * Un paso = una sección del documento, más el paso «Resumen» que cierra la
 * variante BÁSICA con la vista previa antes de guardar.
 *
 * Qué secciones lleva cada tipo lo manda el backend, no la lámina:
 * `ProgramaService::getRequiredSecciones` y `SyllabusRules::basico()` coinciden
 * en que BÁSICO son **cinco** secciones (I, II, VI, VII y VIII), no las tres que
 * dibuja el diseño. En BÁSICO la sección VII son actividades de aprendizaje; en
 * COMPLETO es la planificación de la enseñanza.
 */
import {
  BarChart2,
  BookMarked,
  BookOpen,
  Calendar,
  CheckCircle2,
  ClipboardList,
  FileText,
  Library,
  Settings,
  Target,
} from 'lucide-svelte';

export type TipoSyllabus = 'BASICO' | 'COMPLETO';

/** Numeral romano de una sección, o el paso final de revisión. */
export type IdPaso = 'I' | 'II' | 'III' | 'IV' | 'V' | 'VI' | 'VII' | 'VIII' | 'IX' | 'RESUMEN';

export interface PasoSyllabus {
  id: IdPaso;
  /** null en el paso «Resumen»: no es una sección del documento. */
  numeral: string | null;
  /** Nombre completo de la sección, como lo escribe `ParsesSyllabus`. */
  titulo: string;
  /** Etiqueta corta para la barra de pasos. */
  corto: string;
  icono: typeof BookOpen;
}

const PASOS: Record<Exclude<IdPaso, 'RESUMEN'>, Omit<PasoSyllabus, 'id'>> = {
  I: { numeral: 'I', titulo: 'Identificación', corto: 'Identificación', icono: BookOpen },
  II: { numeral: 'II', titulo: 'Presentación', corto: 'Presentación', icono: FileText },
  III: { numeral: 'III', titulo: 'Estándares', corto: 'Estándares', icono: ClipboardList },
  IV: { numeral: 'IV', titulo: 'Competencias', corto: 'Competencias', icono: Target },
  V: {
    numeral: 'V',
    titulo: 'Evaluación Diagnóstica',
    corto: 'Ev. Diagnóstica',
    icono: BarChart2,
  },
  VI: { numeral: 'VI', titulo: 'Unidades', corto: 'Unidades', icono: BookMarked },
  VII: { numeral: 'VII', titulo: 'Planificación', corto: 'Planificación', icono: Calendar },
  VIII: { numeral: 'VIII', titulo: 'Recursos', corto: 'Recursos', icono: Library },
  IX: {
    numeral: 'IX',
    titulo: 'Aspectos Administrativos',
    corto: 'Administrativos',
    icono: Settings,
  },
};

const RESUMEN: PasoSyllabus = {
  id: 'RESUMEN',
  numeral: null,
  titulo: 'Resumen',
  corto: 'Resumen',
  icono: CheckCircle2,
};

/** En BÁSICO la sección VII deja de ser planificación y pasa a ser actividades. */
const VII_BASICO: Omit<PasoSyllabus, 'id'> = {
  ...PASOS.VII,
  titulo: 'Actividades de Aprendizaje',
  corto: 'Actividades',
};

/** Secciones del documento según el tipo, en orden. */
export function seccionesDeTipo(tipo: TipoSyllabus): string[] {
  return tipo === 'BASICO'
    ? ['I', 'II', 'VI', 'VII', 'VIII']
    : ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
}

/** Pasos del asistente según el tipo. */
export function pasosDeTipo(tipo: TipoSyllabus): PasoSyllabus[] {
  const secciones = seccionesDeTipo(tipo).map((numeral) => {
    const base =
      tipo === 'BASICO' && numeral === 'VII'
        ? VII_BASICO
        : PASOS[numeral as Exclude<IdPaso, 'RESUMEN'>];

    return { id: numeral as IdPaso, ...base };
  });

  return tipo === 'BASICO' ? [...secciones, RESUMEN] : secciones;
}
