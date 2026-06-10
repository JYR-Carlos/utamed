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
    nome?: string;
    nome_real?: string;
    nombre?: string;
    asignatura_nombre?: string;
    carrera_nombre?: string;
    agno_real?: number;
    semestre_real?: number;
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
    _saving?: boolean;
    _prevEstado?: EstadoInscripcion;
}

export interface EstudianteDisponible {
    id_estudiante: number;
    usuario?: { nombre1: string; apellido1: string; username: string; rut?: string | null };
}

// ── State machine config ────────────────────────────────────────────────────

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

export const ESTADO_CFG: Record<
    EstadoInscripcion,
    { label: string; cls: string; rowCls: string }
> = {
    INSCRITO: { label: 'Inscrito', cls: 'bg-emerald-100 text-emerald-800', rowCls: 'normal' },
    RETIRADO: { label: 'Retirado', cls: 'bg-amber-100 text-amber-800', rowCls: 'dimmed' },
    ANULADO: { label: 'Anulado', cls: 'bg-gray-100 text-gray-500', rowCls: 'voided' },
    SUSPENDIDO: { label: 'Suspendido', cls: 'bg-indigo-100 text-indigo-800', rowCls: 'dimmed' },
    APROBADO: { label: 'Aprobado', cls: 'bg-emerald-100 text-emerald-800', rowCls: 'normal' },
    REPROBADO: { label: 'Reprobado', cls: 'bg-red-100 text-red-700', rowCls: 'normal' },
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
