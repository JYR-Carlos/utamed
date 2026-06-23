<script lang="ts">
  /**
   * Lista de "próximas a vencer": las siguientes actividades pendientes a
   * partir de hoy, ordenadas por fecha. Sirve como vista rápida de pendientes
   * complementaria a la grilla mensual.
   */
  import type { CalendarCurso, CalendarEvento, CursoAccent } from '../types';
  import { diasRestantes, etiquetaRelativa, formatCorto } from '../utils/calendar';
  import { CalendarClock } from 'lucide-svelte';

  interface Props {
    eventos: CalendarEvento[];
    cursoPorId: Record<number, CalendarCurso>;
    accentPorCurso: Record<number, CursoAccent>;
    limite?: number;
    onSelectEvento?: (evento: CalendarEvento) => void;
  }

  let { eventos, cursoPorId, accentPorCurso, limite = 6, onSelectEvento }: Props = $props();

  const proximas = $derived(
    [...eventos]
      .filter((e) => diasRestantes(e.fecha) >= 0)
      .sort((a, b) => a.fecha.localeCompare(b.fecha))
      .slice(0, limite),
  );

  function urgencia(iso: string): string {
    const n = diasRestantes(iso);
    if (n <= 1) return 'text-rose-600 bg-rose-50';
    if (n <= 3) return 'text-amber-600 bg-amber-50';
    return 'text-slate-500 bg-slate-100';
  }
</script>

<div class="rounded-2xl border border-slate-200 bg-white p-4">
  <div class="mb-3 flex items-center gap-2">
    <CalendarClock size={15} class="text-indigo-500" />
    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-slate-400">
      Próximas a vencer
    </h3>
  </div>

  {#if proximas.length === 0}
    <p class="py-6 text-center text-sm text-slate-400">No hay actividades pendientes 🎉</p>
  {:else}
    <ul class="flex flex-col gap-1">
      {#each proximas as evento (evento.id_actividad + '-' + evento.id_componente)}
        {@const curso = cursoPorId[evento.id_curso]}
        {@const accent = accentPorCurso[evento.id_curso]}
        <li>
          <button
            type="button"
            onclick={() => onSelectEvento?.(evento)}
            class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition-colors hover:bg-slate-50"
          >
            <span
              class="flex h-9 w-9 shrink-0 flex-col items-center justify-center rounded-lg text-[10px] font-bold leading-none"
              style="background:{accent.soft};color:{accent.text}"
            >
              <span class="text-[13px]">{evento.fecha.split('-')[2]}</span>
              <span class="uppercase">{formatCorto(evento.fecha).split(' ')[1]}</span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-semibold text-slate-800">{evento.titulo}</span
              >
              <span class="block truncate text-xs text-slate-400">{curso?.nombre ?? 'Curso'}</span>
            </span>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {urgencia(evento.fecha)}"
            >
              {etiquetaRelativa(evento.fecha)}
            </span>
          </button>
        </li>
      {/each}
    </ul>
  {/if}
</div>
