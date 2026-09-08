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
  pill: 'border-[#E3D9C6] bg-[#F5F1EA] text-[#6B5B3E]',
  dot: 'bg-[#A8956F]',
};

const BADGE_BASICO: EstadoVisual = {
  label: 'BÁSICO COMPLETO',
  pill: 'border-[#C9D6E6] bg-[#E8EDF5] text-[#002F6C]',
  dot: 'bg-[#002F6C]',
};

const BADGE_REVISION: EstadoVisual = {
  label: 'EN REVISIÓN',
  pill: 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
  dot: 'bg-[#D97706]',
};

const BADGE_APROBADO: EstadoVisual = {
  label: 'APROBADO',
  pill: 'border-[#A7F3D0] bg-[#ECFDF5] text-[#047857]',
  dot: 'bg-[#059669]',
};

const BADGE_RECHAZADO: EstadoVisual = {
  label: 'RECHAZADO',
  pill: 'border-[#F6C9C9] bg-[#FEF2F2] text-[#B91C1C]',
  dot: 'bg-[#DC2626]',
};

const BADGE_SIN_SYLLABUS: EstadoVisual = {
  label: 'Sin syllabus',
  pill: 'border-[#E1E4EA] bg-[#F1F3F6] text-[#5A5E6E]',
  dot: 'bg-[#9AA0AE]',
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
      return { label: estado, pill: 'border-[#E1E4EA] bg-[#F1F3F6] text-[#5A5E6E]', dot: 'bg-[#9AA0AE]' };
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
    return { titulo: 'Syllabus creado', dot: 'bg-[#D6D9E0]', esRechazo: false };
  }

  if (evento.accion === 'RECHAZO') {
    return { titulo: 'Rechazado', dot: 'bg-[#DC2626]', esRechazo: true };
  }

  switch (evento.estado_nuevo) {
    case 'COMPLETO':
    case 'ENVIADO':
      return { titulo: 'Enviado a revisión', dot: 'bg-[#D97706]', esRechazo: false };
    case 'APROBADO':
    case 'PUBLICADO':
      return { titulo: 'Aprobado', dot: 'bg-[#059669]', esRechazo: false };
    case 'BASICO_COMPLETO':
      return { titulo: 'Versión básica completada', dot: 'bg-[#002F6C]', esRechazo: false };
    case 'BORRADOR':
      return { titulo: 'Devuelto a borrador', dot: 'bg-[#A8956F]', esRechazo: false };
    default:
      return { titulo: 'Documento modificado', dot: 'bg-[#D6D9E0]', esRechazo: false };
  }
}
