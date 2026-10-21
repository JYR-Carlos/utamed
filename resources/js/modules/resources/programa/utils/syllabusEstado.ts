/**
 * Vocabulario del visor de syllabus: traduce los estados internos de
 * `curso.programa.estado` al lenguaje que usan docente, jefe de carrera y admin,
 * y da nombre a los eventos de `auditoria.programa_historial`.
 *
 * Nota sobre RECHAZADO: **no existe** como estado en la BD. Rechazar devuelve el
 * programa a BORRADOR y deja la razón en el historial, así que "rechazado" es
 * BORRADOR + una razón de rechazo vigente.
 */

export type EstadoPrograma =
  | 'BORRADOR'
  | 'BASICO_COMPLETO'
  | 'COMPLETO'
  | 'ENVIADO'
  | 'APROBADO'
  | 'PUBLICADO'
  | (string & {});

export interface EstadoVisual {
  /** Etiqueta visible. */
  label: string;
  /** Clases de la pastilla completa. */
  pill: string;
  /** Color del punto. */
  dot: string;
}

const BADGE_BORRADOR: EstadoVisual = {
  label: 'BORRADOR',
  pill: 'border-sand-line bg-sand text-stone-600',
  dot: 'bg-stone-400',
};

const BADGE_BASICO: EstadoVisual = {
  label: 'BÁSICO COMPLETO',
  pill: 'border-slate-300 bg-uta-blue-light text-navy',
  dot: 'bg-navy',
};

const BADGE_REVISION: EstadoVisual = {
  label: 'EN REVISIÓN',
  pill: 'border-amber-200 bg-amber-50 text-amber-700',
  dot: 'bg-amber-600',
};

const BADGE_APROBADO: EstadoVisual = {
  label: 'APROBADO',
  pill: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  dot: 'bg-emerald-600',
};

const BADGE_RECHAZADO: EstadoVisual = {
  label: 'RECHAZADO',
  pill: 'border-red-200 bg-red-50 text-red-700',
  dot: 'bg-red-600',
};

const BADGE_SIN_SYLLABUS: EstadoVisual = {
  label: 'Sin syllabus',
  pill: 'border-zinc-200 bg-gray-100 text-ink-2',
  dot: 'bg-gray-400',
};

/**
 * Pastilla de estado del documento.
 *
 * @param estado         Estado interno del programa, o null si no existe programa.
 * @param fueRechazado   true si el BORRADOR arrastra una razón de rechazo vigente.
 */
export function estadoVisual(estado: EstadoPrograma | null | undefined, fueRechazado = false): EstadoVisual {
  if (!estado) return BADGE_SIN_SYLLABUS;

  switch (estado) {
    case 'BORRADOR':
      return fueRechazado ? BADGE_RECHAZADO : BADGE_BORRADOR;
    case 'BASICO_COMPLETO':
      return BADGE_BASICO;
    case 'COMPLETO':
    case 'ENVIADO':
      return BADGE_REVISION;
    case 'APROBADO':
    case 'PUBLICADO':
      return BADGE_APROBADO;
    default:
      return { label: estado, pill: 'border-zinc-200 bg-gray-100 text-ink-2', dot: 'bg-gray-400' };
  }
}

/** El documento está sellado: se lee, no se toca. */
export function estaSellado(estado: EstadoPrograma | null | undefined): boolean {
  return estado === 'APROBADO' || estado === 'PUBLICADO';
}

/** El documento está en manos del revisor; el docente no tiene acciones. */
export function estaEnRevision(estado: EstadoPrograma | null | undefined): boolean {
  return estado === 'COMPLETO' || estado === 'ENVIADO';
}

// ── Historial ───────────────────────────────────────────────────────────────

export interface EventoHistorial {
  accion: string;
  estado_anterior: string | null;
  estado_nuevo: string | null;
  observaciones: string | null;
  fecha_accion: string | null;
  usuario: string | null;
}

export interface EventoVisual {
  titulo: string;
  /** Color del punto en la línea de tiempo. */
  dot: string;
  /** true cuando `observaciones` es la razón de un rechazo y debe citarse. */
  esRechazo: boolean;
}

/**
 * Nombre y color de un evento del historial. Los triggers sólo registran
 * `accion` ('CREATE' | 'RECHAZO' | 'UPDATE') y el par de estados, así que el
 * título se deriva del estado al que se llegó.
 */
export function eventoVisual(evento: EventoHistorial): EventoVisual {
  if (evento.accion === 'CREATE') {
    return { titulo: 'Syllabus creado', dot: 'bg-line', esRechazo: false };
  }

  if (evento.accion === 'RECHAZO') {
    return { titulo: 'Rechazado', dot: 'bg-red-600', esRechazo: true };
  }

  switch (evento.estado_nuevo) {
    case 'COMPLETO':
    case 'ENVIADO':
      return { titulo: 'Enviado a revisión', dot: 'bg-amber-600', esRechazo: false };
    case 'APROBADO':
    case 'PUBLICADO':
      return { titulo: 'Aprobado', dot: 'bg-emerald-600', esRechazo: false };
    case 'BASICO_COMPLETO':
      return { titulo: 'Versión básica completada', dot: 'bg-navy', esRechazo: false };
    case 'BORRADOR':
      return { titulo: 'Devuelto a borrador', dot: 'bg-stone-400', esRechazo: false };
    default:
      return { titulo: 'Documento modificado', dot: 'bg-line', esRechazo: false };
  }
}
