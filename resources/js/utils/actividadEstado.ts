/**
 * Estado de una actividad tal como lo lee el docente.
 *
 * El vocabulario es el de `agenda.estado_actividad_asignada` y la regla es la
 * misma que aplica el kanban `pages/docente/components/ActividadesPorEstado`:
 *
 *  • Planificada — no visible todavía para los alumnos.
 *  • Activa      — visible y aún abierta (sin fecha límite, o con fecha futura).
 *  • Cerrada     — visible y con la fecha límite ya pasada, contando los días
 *                  de holgura general de la actividad.
 *
 * Importante: el **estado lo pone el sistema** (se deriva); la **visibilidad la
 * pone el docente**. Por eso no hay una columna `estado` en `agenda.actividad`
 * que se pueda editar: cambiar `visible` o `fecha_limite` es lo que mueve la
 * actividad de columna.
 */
import type { Actividad } from '@/types/actividad';
import { cierreEnChile } from '@/utils/plazo';

export type EstadoActividad = 'planificada' | 'activa' | 'cerrada';

/**
 * Fase del guardado optimista del interruptor de visibilidad de UNA actividad.
 * `optimista` es el valor que el docente acaba de pedir y que manda en pantalla
 * mientras `fase` es 'guardando'; si el servidor falla se descarta y el
 * interruptor vuelve al valor real que traen las props.
 */
export interface ToggleVisibilidad {
    fase: 'guardando' | 'ok' | 'error';
    optimista: boolean;
}

/** Estado derivado de una actividad. `ahora` se inyecta para poder testear. */
export function estadoActividad(a: Actividad, ahora: Date = new Date()): EstadoActividad {
    if (!a.visible) return 'planificada';
    // Sin fecha límite la actividad sigue abierta: nunca vence sola.
    if (!a.fecha_limite) return 'activa';
    // fecha_limite es fecha-solo-día: cierra a las 23:59 de ese día en Chile,
    // igual que el backend, aunque el navegador esté en otra zona. La holgura
    // general mantiene abierta la actividad esos días más (Actividad::
    // calcularEstadoBase); la personal es por grupo y no entra aquí.
    const holgura = a.nro_dias_adicionales_para_bloqueo ?? 0;
    return cierreEnChile(a.fecha_limite, holgura) >= ahora ? 'activa' : 'cerrada';
}

/** Rótulo humano del estado. */
export const ETIQUETA_ESTADO: Record<EstadoActividad, string> = {
    planificada: 'Planificada',
    activa: 'Activa',
    cerrada: 'Cerrada',
};

/** Clases de la pastilla de estado, en la paleta institucional. */
export const PILL_ESTADO: Record<EstadoActividad, string> = {
    planificada:
        'inline-flex items-center gap-1.5 rounded-full border border-[#E5E7EB] bg-[#F5F1EA] px-2.5 py-[3px] text-[11.5px] font-medium text-[#5A5E6E]',
    activa:
        'inline-flex items-center gap-1.5 rounded-full border border-[#A7F3D0] bg-[#ECFDF5] px-2.5 py-[3px] text-[11.5px] font-semibold text-[#047857]',
    cerrada:
        'inline-flex items-center gap-1.5 rounded-full border border-[#CBD5E1] bg-[#F1F5F9] px-2.5 py-[3px] text-[11.5px] font-medium text-[#475569]',
};

/**
 * Sólo el color (borde, fondo y texto) de cada estado, para pastillas que ya
 * traen su propio tamaño y forma. Es la misma paleta de PILL_ESTADO: verde
 * para lo abierto y pizarra para lo cerrado, en todas las pantallas.
 */
export const COLOR_ESTADO: Record<EstadoActividad, string> = {
    planificada: 'border-[#E5E7EB] bg-[#F5F1EA] text-[#5A5E6E]',
    activa: 'border-[#A7F3D0] bg-[#ECFDF5] text-[#047857]',
    cerrada: 'border-[#CBD5E1] bg-[#F1F5F9] text-[#475569]',
};

/**
 * Color de la pastilla del estado guardado de un grupo
 * (`agenda.estado_actividad_asignada`: 'PLANIFICADA' | 'ACTIVA' | 'CERRADA').
 *
 * El estado del grupo cuenta la holgura general y la personal, así que un grupo
 * puede seguir «Activa» cuando la actividad ya cerró: es lo que el docente
 * necesita ver, y por eso no tiene un color propio.
 */
export function colorEstadoGrupo(estado: string | null | undefined): string {
    const clave = (estado ?? '').toLowerCase() as EstadoActividad;
    return COLOR_ESTADO[clave] ?? 'border-[#E5E7EB] bg-white text-[#5A5E6E]';
}

/** Color del punto que precede al rótulo dentro de la pastilla. */
export const PUNTO_ESTADO: Record<EstadoActividad, string> = {
    planificada: 'bg-[#A8A093]',
    activa: 'bg-[#059669]',
    cerrada: 'bg-[#64748B]',
};

/** Sigla de 3 letras del componente de la actividad ("Cátedra" → "CÁT"). */
export function siglaComponente(a: Actividad): string {
    const tipo = a.componente?.tipo_componente?.tipo;
    return tipo ? tipo.slice(0, 3).toUpperCase() : '—';
}

/** Nombre completo del componente, para el `title` de la sigla. */
export function nombreComponente(a: Actividad): string {
    return a.componente?.tipo_componente?.tipo ?? 'Sin componente';
}

/** «4 · Prototipado» — número y nombre de la unidad, o null si no tiene. */
export function etiquetaUnidad(a: Actividad): string | null {
    if (!a.unidad) return null;
    const num = a.unidad.num_unidad;
    return num != null ? `${num} · ${a.unidad.nombre}` : a.unidad.nombre;
}
