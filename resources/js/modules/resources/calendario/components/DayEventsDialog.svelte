<script lang="ts">
  /**
   * Panel de detalle de un día: lista todas las actividades a vencer en esa
   * fecha, indicando a qué curso pertenece cada una. Permite saltar al curso.
   * Vista de solo lectura.
   */
  import { Link } from '@inertiajs/svelte';
  import { X, Users, ArrowUpRight, CalendarDays } from 'lucide-svelte';
  import type { CalendarCurso, CalendarEvento, CursoAccent } from '../types';
  import { formatLargo, etiquetaRelativa } from '../utils/calendar';

  interface Props {
    /** Día seleccionado en 'YYYY-MM-DD' o null si está cerrado. */
    iso: string | null;
    eventos: CalendarEvento[];
    cursoPorId: Record<number, CalendarCurso>;
    accentPorCurso: Record<number, CursoAccent>;
    onClose?: () => void;
  }

  let { iso, eventos, cursoPorId, accentPorCurso, onClose }: Props = $props();

  function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') onClose?.();
  }
</script>

<svelte:window onkeydown={onKeydown} />

{#if iso}
  <div class="fixed inset-0 z-50 flex justify-end">
    <!-- Backdrop -->
    <button
      type="button"
      class="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]"
      aria-label="Cerrar"
      onclick={() => onClose?.()}
    ></button>

    <!-- Panel -->
    <div
      class="relative flex h-full w-full max-w-md flex-col bg-white shadow-2xl"
      role="dialog"
      aria-modal="true"
      aria-label="Actividades del día"
    >
      <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-5">
        <div>
          <div class="flex items-center gap-2 text-indigo-600">
            <CalendarDays size={18} />
            <span class="text-xs font-bold uppercase tracking-widest">{etiquetaRelativa(iso)}</span>
          </div>
          <h2 class="mt-1 text-lg font-bold capitalize text-slate-900">{formatLargo(iso)}</h2>
          <p class="text-sm text-slate-500">
            {eventos.length}
            {eventos.length === 1 ? 'actividad a vencer' : 'actividades a vencer'}
          </p>
        </div>
        <button
          type="button"
          onclick={() => onClose?.()}
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
          aria-label="Cerrar"
        >
          <X size={20} />
        </button>
      </header>

      <div class="flex-1 overflow-y-auto px-6 py-5">
        {#if eventos.length === 0}
          <div class="flex flex-col items-center justify-center py-16 text-center">
            <CalendarDays size={36} class="text-slate-300" />
            <p class="mt-3 text-sm font-medium text-slate-500">Sin actividades este día</p>
          </div>
        {:else}
          <ul class="flex flex-col gap-3">
            {#each eventos as evento (evento.id_actividad + '-' + evento.id_componente)}
              {@const curso = cursoPorId[evento.id_curso]}
              {@const accent = accentPorCurso[evento.id_curso]}
              <li
                class="rounded-xl border border-slate-200 p-4 transition-shadow hover:shadow-sm"
                style="border-left:4px solid {accent.base}"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h3 class="truncate font-semibold text-slate-900">{evento.titulo}</h3>
                    <p class="mt-0.5 truncate text-sm font-medium" style="color:{accent.text}">
                      {curso?.nombre ?? 'Curso'}
                    </p>
                  </div>
                  {#if !evento.visible}
                    <span
                      class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500"
                      >Oculta</span
                    >
                  {/if}
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                  <span
                    class="rounded-md px-2 py-0.5 text-[11px] font-semibold {evento.tipo_actividad ===
                    'SUMATIVA'
                      ? 'bg-amber-100 text-amber-800'
                      : 'bg-sky-100 text-sky-800'}"
                  >
                    {evento.tipo_actividad === 'SUMATIVA' ? 'Sumativa' : 'Formativa'}
                  </span>
                  <span
                    class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
                  >
                    {evento.componente}
                  </span>
                  {#if evento.es_grupal}
                    <span
                      class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
                    >
                      <Users size={11} /> Grupal
                    </span>
                  {/if}
                  {#if evento.tipo_entrega}
                    <span
                      class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
                    >
                      {evento.tipo_entrega}
                    </span>
                  {/if}
                </div>

                <Link
                  href={`/docente/cursos/${evento.id_curso}/actividades`}
                  class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-700"
                >
                  Ver en el curso
                  <ArrowUpRight size={15} />
                </Link>
              </li>
            {/each}
          </ul>
        {/if}
      </div>
    </div>
  </div>
{/if}
