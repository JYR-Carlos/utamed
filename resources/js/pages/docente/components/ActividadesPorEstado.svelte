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
    EyeOff,
    Lock,
    AlertTriangle,
    Users,
    User,
    ClipboardList,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatFechaCorta, formatNota } from '@/utils/formatters';

  // ── Props ─────────────────────────────────────────────────────────────────

  interface Props {
    actividades: Actividad[];
    idCurso: number;
  }

  let { actividades, idCurso }: Props = $props();

  // ── Clasificación ─────────────────────────────────────────────────────────

  type EstadoId = 'planificada' | 'activa' | 'cerrada';

  const now = new Date();

  function estadoDe(a: Actividad): EstadoId {
    if (!a.visible) return 'planificada';
    // Sin fecha límite la actividad sigue abierta: nunca vence sola.
    if (!a.fecha_limite) return 'activa';
    return new Date(a.fecha_limite) > now ? 'activa' : 'cerrada';
  }

  const planificadas = $derived(actividades.filter((a) => estadoDe(a) === 'planificada'));
  const activas = $derived(actividades.filter((a) => estadoDe(a) === 'activa'));
  const cerradas = $derived(actividades.filter((a) => estadoDe(a) === 'cerrada'));

  // ── Helpers ───────────────────────────────────────────────────────────────

  /** Días que faltan (positivo) o que pasaron (negativo) respecto de hoy. */
  function diasRestantes(d: string): number {
    return Math.ceil((new Date(d).getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
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

  function sinCalificar(a: Actividad): number {
    return Math.max(0, (a.total_grupos ?? 0) - (a.calificados ?? 0));
  }

  function pctCalificado(a: Actividad): number {
    const total = a.total_grupos ?? 0;
    return total === 0 ? 0 : Math.round(((a.calificados ?? 0) / total) * 100);
  }

  const COLUMNAS = $derived([
    { id: 'planificada' as const, label: 'Planificada', items: planificadas, dot: 'bg-[#64748B]' },
    { id: 'activa' as const, label: 'Activa', items: activas, dot: 'bg-[#059669]' },
    { id: 'cerrada' as const, label: 'Cerrada', items: cerradas, dot: 'bg-[#475569]' },
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
          {@const pendientes = sinCalificar(act)}
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
              {:else if pendientes > 0}
                <span
                  class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-[#B45309]"
                >
                  <AlertTriangle size={12} />
                  {pendientes} sin calificar
                </span>
              {:else}
                <span
                  class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-[#475569]"
                >
                  <Lock size={12} />
                  Cerrada
                </span>
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

              {#if (act.total_grupos ?? 0) > 0}
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-1.5 rounded-full bg-[#EEF1F5] overflow-hidden">
                    <div
                      class="h-1.5 rounded-full bg-[#002F6C]"
                      style="width:{pctCalificado(act)}%"
                    ></div>
                  </div>
                  <span class="font-mono text-[11px] tabular-nums text-[#5A5E6E] shrink-0">
                    {act.calificados ?? 0}/{act.total_grupos} calificadas
                  </span>
                  {#if act.promedio_nota != null}
                    <span class="font-mono text-[11px] tabular-nums text-[#5A5E6E] shrink-0">
                      · prom. {formatNota(act.promedio_nota)}
                    </span>
                  {/if}
                </div>
              {/if}
            </div>
          </Link>
        {/each}
      {/if}
    </section>
  {/each}
</div>
