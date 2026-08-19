/**
 * Tipos, máquina de estados y helpers del módulo Inscripción.
 *
 * Una inscripción vincula estudiante↔curso y evoluciona por estados; las
 * transiciones manuales permitidas están en TRANSITIONS (APROBADO/REPROBADO
 * no aparecen como origen porque los fija el cierre académico, no el admin).
 */
export type EstadoInscripcion =
    | 'INSCRITO'
    | 'RETIRADO'
    | 'ANULADO'
    | 'SUSPENDIDO'
    | 'APROBADO'
    | 'REPROBADO';

export interface CursoItem {
    id_curso: number;
    cod_curso: string;
    nombre?: string;
    asignatura_nombre?: string;
    carrera_nombre?: string;
    agno_real?: number;
    semestre_real?: number;
    /**
     * Estudiantes con inscripción vigente (estado INSCRITO). Lo calcula
     * InscripcionCursoController::index() con withCount; es el dato por el
     * que se elige un curso en el selector.
     */
    inscritos_count?: number;
}

export interface RosterItem {
    id_inscripcion_curso: number;
    id_curso: number;
    id_estudiante: number;
    cod_inscripcion_uta: string | null;
    fecha_inscripcion: string;
    estado_inscripcion: EstadoInscripcion;
    num_intento: number;
    promedio_parcial: number | null;
    curso?: CursoItem;
    estudiante?: {
        id_estudiante: number;
        usuario?: { nombre1: string; apellido1: string; username: string };
    };
    /** Solo UI: fila con cambio de estado en curso (spinner + rollback). */
    _saving?: boolean;
    /** Solo UI: estado previo para revertir si el PATCH falla. */
    _prevEstado?: EstadoInscripcion;
}

export interface EstudianteDisponible {
    id_estudiante: number;
    usuario?: { nombre1: string; apellido1: string; username: string; rut?: string | null };
}

// ── State machine config ────────────────────────────────────────────────────

/** Transiciones manuales permitidas por estado (menú de acciones del roster). */
export const TRANSITIONS: Partial<
    Record<EstadoInscripcion, { label: string; next: EstadoInscripcion; icon: string }[]>
> = {
    INSCRITO: [
        { label: 'Marcar Retirado', next: 'RETIRADO', icon: '⏸' },
        { label: 'Anular inscripción', next: 'ANULADO', icon: '✕' },
    ],
    RETIRADO: [
        { label: 'Reinscribir', next: 'INSCRITO', icon: '↩' },
        { label: 'Anular inscripción', next: 'ANULADO', icon: '✕' },
    ],
    ANULADO: [{ label: 'Reinscribir', next: 'INSCRITO', icon: '↩' }],
    SUSPENDIDO: [
        { label: 'Reinscribir', next: 'INSCRITO', icon: '↩' },
        { label: 'Retirar', next: 'RETIRADO', icon: '⏸' },
    ],
};

/** Presentación por estado: etiqueta, clases del badge y estilo de fila. */
export const ESTADO_CFG: Record<
    EstadoInscripcion,
    { label: string; cls: string; rowCls: string }
> = {
    INSCRITO:   { label: 'Inscrito',   cls: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',  rowCls: 'normal' },
    RETIRADO:   { label: 'Retirado',   cls: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',        rowCls: 'dimmed' },
    ANULADO:    { label: 'Anulado',    cls: 'bg-gray-100 text-gray-400 ring-1 ring-gray-200',          rowCls: 'voided' },
    SUSPENDIDO: { label: 'Suspendido', cls: 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',     rowCls: 'dimmed' },
    APROBADO:   { label: 'Aprobado',   cls: 'bg-teal-50 text-teal-700 ring-1 ring-teal-200',           rowCls: 'normal' },
    REPROBADO:  { label: 'Reprobado',  cls: 'bg-red-50 text-red-600 ring-1 ring-red-200',              rowCls: 'normal' },
};

// ── Helpers ─────────────────────────────────────────────────────────────────

export function studentName(item: RosterItem): string {
    const u = item.estudiante?.usuario;
    return u ? `${u.nombre1} ${u.apellido1}` : `Estudiante #${item.id_estudiante}`;
}

export function studentUsername(item: RosterItem): string {
    return item.estudiante?.usuario?.username ?? '';
}

export function initials(item: RosterItem): string {
    const u = item.estudiante?.usuario;
    return ((u?.nombre1?.[0] ?? '') + (u?.apellido1?.[0] ?? '')).toUpperCase() || '?';
}

export function disponibleName(e: EstudianteDisponible): string {
    const u = e.usuario;
    return u ? `${u.nombre1} ${u.apellido1}` : `#${e.id_estudiante}`;
}

export function cursoDisplayName(c: CursoItem): string {
    return c.nombre || c.asignatura_nombre || c.cod_curso;
}
