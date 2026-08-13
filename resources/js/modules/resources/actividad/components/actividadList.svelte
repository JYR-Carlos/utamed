<script lang="ts">
  /**
   * actividadList — Grilla de tarjetas de actividades para el docente.
   *
   * Presentacional: el padre maneja los modales de crear/editar/eliminar vía
   * callbacks. Cada tarjeta enlaza a la evaluación de la actividad
   * (/docente/cursos/{id}/actividades/{id}/evaluacion) y a sus mensajes de
   * agenda (/docente/mensajes?actividad_id={id}) — esa mensajería es de nivel
   * actividad, así que sólo se entra desde aquí; la del curso vive en
   * /docente/cursos/{id}/mensajeria. Los flags can* vienen de los permisos del
   * docente sobre el curso.
   *
   * El acento de color de cada tarjeta (rail, chip de tipo, botón "Evaluar")
   * se deriva de tipo_actividad: SUMATIVA (cuenta para la nota) usa índigo,
   * FORMATIVA (práctica) usa celeste — así el tipo se reconoce de un vistazo
   * sin leer texto. El bloque de fecha límite se colorea por urgencia real
   * (vencida / próxima / normal), reusando la paleta de estado que ya usa
   * docente/Cursos.svelte para "por revisar".
   */
  import { Link } from '@inertiajs/svelte';
  import {
    Edit2,
    Trash2,
    ClipboardList,
    MessageSquare,
    Plus,
    Eye,
    EyeOff,
    Clock,
    Users,
    User,
    Monitor,
    MapPin,
    Shuffle,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatFechaTextoLargo, parseFechaSoloDia } from '@/utils/formatters';

  interface Props {
    actividades: Actividad[];
    idCurso: number;
    /** Permisos del docente sobre el curso (titular vs colaborador). */
    canCreate?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
    /** Abre el modal de edición. */
    onEdit?: (actividad: Actividad) => void;
    /** Abre la confirmación de borrado. */
    onDelete?: (actividad: Actividad) => void;
    /** Abre el modal de creación (botón del estado vacío). */
    onCreateClick?: () => void;
    /** Alterna visible/oculta para los estudiantes (ojito). */
    onToggleVisible?: (actividad: Actividad) => void;
  }

  let {
    actividades,
    idCurso,
    canCreate = true,
    canEdit = true,
    canDelete = true,
    onEdit = () => {},
    onDelete = () => {},
    onCreateClick = () => {},
    onToggleVisible = () => {},
  }: Props = $props();

  // ── Acento por tipo de actividad ──────────────────────────────────────────

  const TIPO_ACCENT: Record<string, { base: string; soft: string }> = {
    SUMATIVA: { base: '#4F46E5', soft: '#EEF0FF' },
    FORMATIVA: { base: '#0284C7', soft: '#E6F4FB' },
  };
  const DEFAULT_ACCENT = { base: '#5C6478', soft: '#FAFBFC' };

  function tipoAccentOf(tipo: string) {
    return TIPO_ACCENT[tipo] ?? DEFAULT_ACCENT;
  }

  // ── Icono según tipo de entrega ────────────────────────────────────────────

  function entregaIcon(tipo: string) {
    if (tipo === 'presencial') return MapPin;
    if (tipo === 'hibrido') return Shuffle;
    return Monitor;
  }

  // ── Urgencia de la fecha límite ────────────────────────────────────────────

  function urgenciaFecha(fechaLimite: string): { bg: string; fg: string; label: string | null } {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fecha = parseFechaSoloDia(fechaLimite);
    const diffDias = Math.round((fecha.getTime() - hoy.getTime()) / 86_400_000);

    if (diffDias < 0) return { bg: '#FEF2F2', fg: '#B91C1C', label: 'Vencida' };
    if (diffDias <= 3) return { bg: '#FEF3CC', fg: '#92580B', label: 'Próxima' };
    return { bg: '#FAFBFC', fg: '#2B3142', label: 'Fecha límite' };
  }
</script>

{#if actividades.length === 0}
  <div class="text-center p-16 bg-white rounded-2xl border border-dashed border-[#E8EAF0]">
    <div
      class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#FAFBFC] border border-[#E8EAF0] text-[#8B92A6] mb-4"
    >
      <ClipboardList size={26} aria-hidden="true" />
    </div>
    <h3 class="text-xl text-[#0E1220] font-semibold mb-2">No hay actividades creadas</h3>
    <p class="text-[#5C6478] mb-6">Crea tu primera actividad para este curso</p>
    {#if canCreate}
      <button
        onclick={onCreateClick}
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#0E1220] text-white rounded-lg font-medium hover:bg-[#2B3142] transition-all"
      >
        <Plus size={18} />
        Crear Actividad
      </button>
    {/if}
  </div>
{:else}
  <div class="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-4">
    {#each actividades as actividad (actividad.id_actividad)}
      {@const tipoAccent = tipoAccentOf(actividad.tipo_actividad)}
      {@const urgencia = urgenciaFecha(actividad.fecha_limite)}
      {@const EntregaIcon = entregaIcon(actividad.tipo_entrega)}
      <div
        class="group relative bg-white border border-[#E8EAF0] rounded-2xl p-5 flex flex-col gap-3.5 overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-[0_12px_24px_-8px_rgba(0,0,0,0.10),0_2px_4px_rgba(0,0,0,0.04)]"
        style="--ac:{tipoAccent.base}; --ac-soft:{tipoAccent.soft}"
      >
        <!-- Rail de acento -->
        <div
          class="absolute left-0 top-0 bottom-0 w-[3px] bg-[var(--ac)] opacity-0 transition-opacity group-hover:opacity-100 pointer-events-none"
          aria-hidden="true"
        ></div>

        <!-- Eyebrow: tipo + componente, acciones a la derecha -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex flex-wrap items-center gap-1.5 min-w-0">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-[6px] text-[0.6875rem] font-semibold uppercase tracking-wide"
              style="background:{tipoAccent.soft}; color:{tipoAccent.base}"
              >{actividad.tipo_actividad}</span
            >
            <span class="text-[0.6875rem] text-[#8B92A6] font-mono tracking-[0.02em] truncate"
              >{actividad.componente?.tipo_componente?.tipo ?? '—'}</span
            >
          </div>
          <div class="flex items-center gap-1 shrink-0">
            {#if canEdit}
              <button
                onclick={() => onToggleVisible(actividad)}
                class={`inline-flex items-center justify-center w-7 h-7 rounded-full border transition-all ${
                  actividad.visible
                    ? 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100'
                    : 'bg-red-50 border-red-200 text-red-700 hover:bg-red-100'
                }`}
                title={actividad.visible
                  ? 'Visible para estudiantes — clic para ocultar'
                  : 'Oculta para estudiantes — clic para mostrar'}
              >
                {#if actividad.visible}
                  <Eye size={14} />
                {:else}
                  <EyeOff size={14} />
                {/if}
              </button>
            {:else if actividad.visible}
              <span
                class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-green-50 text-green-700"
                title="Visible para estudiantes"
              >
                <Eye size={14} />
              </span>
            {:else}
              <span
                class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-50 text-red-700"
                title="Oculta para estudiantes"
              >
                <EyeOff size={14} />
              </span>
            {/if}
            {#if canEdit}
              <button
                onclick={() => onEdit(actividad)}
                class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-[#E8EAF0] text-[#5C6478] hover:bg-[#FAFBFC] hover:text-blue-600 hover:border-blue-300 transition-all"
                title="Editar"
              >
                <Edit2 size={14} />
              </button>
            {/if}
            {#if canDelete}
              <button
                onclick={() => onDelete(actividad)}
                class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-[#E8EAF0] text-[#5C6478] hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-all"
                title="Eliminar"
              >
                <Trash2 size={14} />
              </button>
            {/if}
          </div>
        </div>

        <!-- Título -->
        <h3 class="text-[1.05rem] font-semibold text-[#0E1220] tracking-[-0.01em] leading-snug">
          {actividad.nombre}
        </h3>

        <!-- Chips de metadata -->
        <div class="flex flex-wrap gap-1.5">
          <div
            class="inline-flex items-center gap-1 px-2 py-1 rounded-[7px] text-xs bg-[#FAFBFC] text-[#2B3142] border border-[#E8EAF0] capitalize"
          >
            <EntregaIcon size={13} aria-hidden="true" />
            {actividad.tipo_entrega}
          </div>
          <div
            class="inline-flex items-center gap-1 px-2 py-1 rounded-[7px] text-xs bg-[#FAFBFC] text-[#2B3142] border border-[#E8EAF0]"
          >
            {#if actividad.es_grupal}
              <Users size={13} aria-hidden="true" />
              Grupal · máx {actividad.max_integrantes}
            {:else}
              <User size={13} aria-hidden="true" />
              Individual
            {/if}
          </div>
        </div>

        <!-- Fecha límite, coloreada según urgencia real -->
        <div
          class="flex items-center gap-2 px-3 py-2 rounded-[10px] mt-auto"
          style="background:{urgencia.bg}; color:{urgencia.fg}"
        >
          <Clock size={15} aria-hidden="true" />
          <div class="flex flex-col leading-tight">
            <span class="text-[0.625rem] font-semibold uppercase tracking-wide opacity-80"
              >{urgencia.label}</span
            >
            <span class="text-sm font-semibold">{formatFechaTextoLargo(actividad.fecha_limite)}</span>
          </div>
        </div>

        <!-- Acciones: evaluar y mensajes de la actividad -->
        <div class="flex items-stretch gap-2">
          <Link
            href={`/docente/cursos/${idCurso}/actividades/${actividad.id_actividad}/evaluacion`}
            class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-[10px] font-semibold text-sm no-underline border border-[var(--ac)] bg-[var(--ac-soft)] text-[var(--ac)] hover:bg-[var(--ac)] hover:text-white transition-all"
          >
            <ClipboardList size={15} />
            Evaluar
          </Link>
          <!-- Mensajería de agenda: es de esta actividad, por eso se entra desde
               aquí y no desde el menú lateral. -->
          <Link
            href={`/docente/mensajes?actividad_id=${actividad.id_actividad}`}
            class="relative inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-[10px] font-semibold text-sm no-underline border border-[#E8EAF0] bg-white text-[#5C6478] hover:bg-[#FAFBFC] hover:text-[#0E1220] transition-all"
            title="Mensajes de esta actividad"
          >
            <MessageSquare size={15} />
            {#if (actividad.mensajes_pendientes ?? 0) > 0}
              <span
                class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 inline-flex items-center justify-center rounded-full bg-amber-500 text-white text-[0.625rem] font-bold"
                title={`${actividad.mensajes_pendientes} por responder`}
              >
                {actividad.mensajes_pendientes}
              </span>
            {/if}
          </Link>
        </div>
      </div>
    {/each}
  </div>
{/if}
