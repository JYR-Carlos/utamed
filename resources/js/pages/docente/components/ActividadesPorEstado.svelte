<script lang="ts">
  /**
   * Actividades por estado — tablero de tres columnas.
   *
   * El vocabulario es el de `agenda.estado_actividad_asignada`:
   *
   *  • Planificada — todavía no visible para los alumnos.
   *  • Activa      — visible y aún abierta (sin fecha límite, o con fecha futura).
   *  • Cerrada     — visible y con la fecha límite ya pasada.
   *
   * Cada tarjeta enlaza a la evaluación de la actividad. El avance
   * (`calificados`/`total_grupos`) y el promedio son opcionales: quien no los
   * envía simplemente no muestra la barra de progreso.
   *
   * Props:
   *  - actividades : Actividad[] — Lista de actividades del curso.
   *  - idCurso     : number      — ID del curso para construir rutas.
   */
  import { Link } from '@inertiajs/svelte';
  import {
    Calendar,
    Clock,
    Copy,
    EyeOff,
    Lock,
    AlertTriangle,
    Users,
    User,
    ClipboardList,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { PUNTO_ESTADO, estadoActividad } from '@/utils/actividadEstado';
  import { cierreEnChile } from '@/utils/plazo';
  import { formatFechaCorta, formatNota } from '@/utils/formatters';

  // ── Props ─────────────────────────────────────────────────────────────────

  interface Props {
    actividades: Actividad[];
    idCurso: number;
    canEdit?: boolean;
    onCopiar?: (actividad: Actividad) => void;
  }

  let {
    actividades,
    idCurso,
    canEdit = false,
    onCopiar,
  }: Props = $props();

  // ── Clasificación ─────────────────────────────────────────────────────────

  type EstadoId = 'planificada' | 'activa' | 'cerrada';

  const now = new Date();

  // La regla vive en actividadEstado.ts. Aquí se comparaba con
  // new Date(fecha_limite) (medianoche UTC = 21:00 del día anterior en Chile)
  // y la actividad pasaba a «Cerrada» ~27 horas antes del cierre real.
  function estadoDe(a: Actividad): EstadoId {
    return estadoActividad(a, now);
  }

  const planificadas = $derived(actividades.filter((a) => estadoDe(a) === 'planificada'));
  const activas = $derived(actividades.filter((a) => estadoDe(a) === 'activa'));
  const cerradas = $derived(actividades.filter((a) => estadoDe(a) === 'cerrada'));

  // ── Helpers ───────────────────────────────────────────────────────────────

  /** Días que faltan (positivo) o que pasaron (negativo) hasta el cierre en Chile. */
  function diasRestantes(d: string): number {
    return Math.ceil((cierreEnChile(d).getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
  }

  function cierreLabel(a: Actividad): string {
    if (!a.fecha_limite) return 'Sin fecha de cierre';
    const dias = diasRestantes(a.fecha_limite);
    if (dias <= 0) return 'Cierra hoy';
    if (dias === 1) return 'Cierra mañana';
    return `Cierra en ${dias} días`;
  }

  /** Sigla de 3 letras del componente ("Cátedra" → "CÁT"). */
  function siglaComponente(a: Actividad): string {
    const tipo = a.componente?.tipo_componente?.tipo;
    return tipo ? tipo.slice(0, 3).toUpperCase() : '—';
  }

  function nombreComponente(a: Actividad): string {
    return a.componente?.tipo_componente?.tipo ?? 'Sin componente';
  }

  function ponderacionLabel(a: Actividad): string {
    return a.ponderacion != null ? `${a.ponderacion}%` : '';
  }

  /**
   * Avance de evaluación de la tarjeta. Antes era una barra con
   * «calificadas / grupos»: una formativa no lleva nota y se quedaba en 0 %
   * para siempre. Ahora:
   *  - sumativa:  calificadas (con nota) y por calificar (entregaron, sin nota);
   *  - formativa: con retroalimentación (feedback del docente) y por revisar.
   * Sin entrega de archivo, lo pendiente es todo grupo aún no revisado.
   */
  function avance(a: Actividad) {
    const sumativa = a.tipo_actividad === 'SUMATIVA';
    const conEntrega = a.tipo_entrega !== 'SIN_ENTREGA';
    const total = a.total_grupos ?? 0;
    const revisadas = sumativa ? (a.calificados ?? 0) : (a.con_feedback ?? 0);
    const pendientes = conEntrega
      ? sumativa
        ? (a.por_calificar ?? 0)
        : (a.por_revisar ?? 0)
      : Math.max(0, total - revisadas);
    return {
      total,
      conEntrega,
      entregaron: a.grupos_con_entrega ?? 0,
      revisadas,
      pendientes,
      rotuloRevisadas: sumativa ? 'calificadas' : 'con retroalimentación',
      rotuloPendientes: sumativa ? 'por calificar' : 'por revisar',
      promedio: sumativa ? (a.promedio_nota ?? null) : null,
    };
  }

  const COLUMNAS = $derived([
    { id: 'planificada' as const, label: 'Planificada', items: planificadas, dot: PUNTO_ESTADO.planificada },
    { id: 'activa' as const, label: 'Activa', items: activas, dot: PUNTO_ESTADO.activa },
    { id: 'cerrada' as const, label: 'Cerrada', items: cerradas, dot: PUNTO_ESTADO.cerrada },
  ]);

  const TAG =
    'font-mono text-[10.5px] font-bold tracking-[0.06em] rounded-[5px] px-1.5 py-0.5 bg-[#F5F1EA] border border-[#E5E7EB] text-[#5A5E6E]';
</script>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3.5 items-start">
  {#each COLUMNAS as col (col.id)}
    <section
      class="flex flex-col gap-2.5 rounded-xl border border-[#E5E7EB] bg-[#F5F1EA] p-3"
      aria-label="Actividades en estado {col.label}"
    >
      <!-- Cabecera de columna -->
      <div class="flex items-center gap-2 px-0.5">
        <span class="w-2 h-2 rounded-full {col.dot} shrink-0" aria-hidden="true"></span>
        <h3 class="text-[13px] font-semibold text-[#1A1A24] m-0">{col.label}</h3>
        <span class="ml-auto font-mono text-[11.5px] tabular-nums text-[#5A5E6E]"
          >{col.items.length}</span
        >
      </div>

      {#if col.items.length === 0}
        <div
          class="flex flex-col items-center gap-2 rounded-[10px] border border-dashed border-[#D6D9E0] bg-white/60 py-8 text-center text-[#98A0AE]"
        >
          <ClipboardList size={24} class="opacity-40" />
          <p class="text-[12px] m-0">Sin actividades en este estado</p>
        </div>
      {:else}
        {#each col.items as act (act.id_actividad)}
          {@const av = avance(act)}
          <Link
            href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
            class="flex flex-col gap-2 rounded-[10px] p-3 no-underline transition-colors duration-150 {col.id ===
            'cerrada'
              ? 'bg-[#F8FAFC] border border-[#CBD5E1] hover:bg-white'
              : col.id === 'activa'
                ? 'bg-white border border-[#A7F3D0] border-l-[3px] border-l-[#059669] shadow-[0_1px_2px_rgba(0,0,0,.04)] hover:border-[#6EE7B7]'
                : 'bg-white border border-[#E5E7EB] shadow-[0_1px_2px_rgba(0,0,0,.04)] hover:border-[#D0CBC1]'}"
          >
            <!-- Sigla del componente + estado -->
            <div class="flex items-center gap-1.5">
              <span class={TAG} title={nombreComponente(act)}>{siglaComponente(act)}</span>
              <span
                class="text-[10.5px] font-medium tracking-[0.02em] text-[#5A5E6E] rounded-[5px] border border-[#E5E7EB] px-1.5 py-0.5"
              >
                {act.tipo_actividad === 'SUMATIVA' ? 'Sumativa' : 'Formativa'}
              </span>

              {#if col.id === 'planificada'}
                <span
                  class="ml-auto inline-flex items-center gap-1 rounded-full bg-[#FFFBEB] border border-[#FDE68A] px-2 py-0.5 text-[11px] font-semibold text-[#B45309]"
                >
                  <EyeOff size={12} />
                  Oculta
                </span>
              {:else if col.id === 'activa'}
                <span class="ml-auto text-[11px] font-semibold text-[#047857]"
                  >{cierreLabel(act)}</span
                >
              {:else if av.pendientes > 0}
                <span
                  class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-[#B45309]"
                >
                  <AlertTriangle size={12} />
                  {av.pendientes} {av.rotuloPendientes}
                </span>
              {:else}
                <span
                  class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-[#475569]"
                >
                  <Lock size={12} />
                  Cerrada
                </span>
              {/if}

              {#if onCopiar}
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-[5px] p-1 text-[#5A5E6E] transition-colors hover:bg-[#E5E7EB] hover:text-[#002F6C]"
                  title="Copiar a otro curso"
                  aria-label="Copiar {act.nombre} a otro curso"
                  onclick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onCopiar(act);
                  }}
                >
                  <Copy size={13} aria-hidden="true" />
                </button>
              {/if}
            </div>

            <!-- Nombre -->
            <span
              class="text-[13.5px] font-semibold leading-[1.35] text-pretty {col.id === 'cerrada'
                ? 'text-[#334155]'
                : 'text-[#1A1A24]'}"
            >
              {act.nombre}
            </span>

            <!-- Pie: fecha + ponderación -->
            <div
              class="flex flex-col gap-1.5 border-t pt-2 {col.id === 'cerrada'
                ? 'border-[#CBD5E1]'
                : 'border-[#E5E7EB]'}"
            >
              <div class="flex items-center gap-2.5 text-[11.5px] text-[#5A5E6E]">
                <span class="flex items-center gap-1.5">
                  {#if col.id === 'planificada'}
                    <Calendar size={13} />
                    {act.fecha_limite
                      ? `Cierra ${formatFechaCorta(act.fecha_limite)}`
                      : 'Sin fecha de cierre'}
                  {:else}
                    <Clock size={13} />
                    {act.fecha_limite ? formatFechaCorta(act.fecha_limite) : 'Sin fecha de cierre'}
                  {/if}
                </span>
                <span class="flex items-center gap-1" title={act.es_grupal ? 'Grupal' : 'Individual'}>
                  {#if act.es_grupal}<Users size={12} />{:else}<User size={12} />{/if}
                </span>
                {#if ponderacionLabel(act)}
                  <span class="ml-auto font-mono tabular-nums">{ponderacionLabel(act)}</span>
                {/if}
              </div>

              {#if av.total > 0 && col.id !== 'planificada'}
                <div class="grid grid-cols-3 gap-1.5">
                  {#if av.conEntrega}
                    <div class="flex flex-col rounded-md bg-[#F8FAFC] px-2 py-1.5">
                      <span class="m-0 text-[15px] font-bold tabular-nums text-[#1A1A24]">
                        {av.entregaron}<span class="text-[11px] font-medium text-[#5A5E6E]">/{av.total}</span>
                      </span>
                      <span class="text-[10.5px] text-[#5A5E6E]">entregaron</span>
                    </div>
                  {/if}
                  <div class="flex flex-col rounded-md bg-[#F8FAFC] px-2 py-1.5">
                    <span class="m-0 text-[15px] font-bold tabular-nums text-[#1A1A24]">{av.revisadas}</span>
                    <span class="text-[10.5px] leading-tight text-[#5A5E6E]">{av.rotuloRevisadas}</span>
                  </div>
                  <div
                    class="flex flex-col rounded-md px-2 py-1.5 {av.pendientes > 0
                      ? 'border border-[#FDE68A] bg-[#FFFBEB]'
                      : 'bg-[#F8FAFC]'}"
                  >
                    <span
                      class="m-0 text-[15px] font-bold tabular-nums {av.pendientes > 0
                        ? 'text-[#92400E]'
                        : 'text-[#1A1A24]'}"
                    >
                      {av.pendientes}
                    </span>
                    <span class="text-[10.5px] {av.pendientes > 0 ? 'text-[#92400E]' : 'text-[#5A5E6E]'}">
                      {av.rotuloPendientes}
                    </span>
                  </div>
                </div>
                {#if av.promedio != null}
                  <span class="font-mono text-[11px] tabular-nums text-[#5A5E6E]">
                    Promedio {formatNota(av.promedio)}
                  </span>
                {/if}
              {/if}
            </div>
          </Link>
        {/each}
      {/if}
    </section>
  {/each}
</div>
