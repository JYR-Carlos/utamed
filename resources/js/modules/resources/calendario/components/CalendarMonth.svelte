<script lang="ts">
  /**
   * Grilla mensual estilo Google Calendar (semanas que inician en Lunes).
   *
   * Renderiza las celdas del mes y, en cada día, las actividades a vencer
   * coloreadas por curso. Es de solo lectura: al hacer clic en un día (o en
   * "+N más") se notifica al contenedor para abrir el detalle del día.
   */
  import type { CalendarEvento, CursoAccent } from '../types';
  import { buildMonthMatrix, DIAS_SEMANA } from '../utils/calendar';
  import EventPill from './EventPill.svelte';

  interface Props {
    year: number;
    month: number;
    /** Eventos agrupados por clave 'YYYY-MM-DD'. */
    eventosPorDia: Record<string, CalendarEvento[]>;
    /** Acento por id_curso. */
    accentPorCurso: Record<number, CursoAccent>;
    onSelectDay?: (iso: string) => void;
    onSelectEvento?: (evento: CalendarEvento) => void;
  }

  let { year, month, eventosPorDia, accentPorCurso, onSelectDay, onSelectEvento }: Props = $props();

  const weeks = $derived(buildMonthMatrix(year, month));

  const MAX_VISIBLE = 3;

  function eventosDe(iso: string): CalendarEvento[] {
    return eventosPorDia[iso] ?? [];
  }
</script>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
  <!-- Encabezado de días -->
  <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
    {#each DIAS_SEMANA as dia, i (dia)}
      <div
        class="px-2 py-2.5 text-center text-[11px] font-bold uppercase tracking-wider {i >= 5
          ? 'text-slate-400'
          : 'text-slate-500'}"
      >
        {dia}
      </div>
    {/each}
  </div>

  <!-- Semanas -->
  {#each weeks as week, wi (wi)}
    <div class="grid grid-cols-7 {wi < weeks.length - 1 ? 'border-b border-slate-100' : ''}">
      {#each week as day (day.iso)}
        {@const evs = eventosDe(day.iso)}
        {@const visibles = evs.slice(0, MAX_VISIBLE)}
        {@const restantes = evs.length - visibles.length}
        <button
          type="button"
          onclick={() => onSelectDay?.(day.iso)}
          class="flex min-h-[112px] flex-col gap-1 border-r border-slate-100 p-1.5 text-left align-top transition-colors last:border-r-0 hover:bg-slate-50/70 focus:outline-none focus-visible:bg-indigo-50/50 {day.inMonth
            ? 'bg-white'
            : 'bg-slate-50/40'} {day.isWeekend && day.inMonth ? 'bg-slate-50/30' : ''}"
        >
          <!-- Número del día -->
          <div class="flex items-center justify-between px-0.5">
            <span
              class="flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-[12px] font-semibold {day.isToday
                ? 'bg-indigo-600 text-white'
                : day.inMonth
                  ? 'text-slate-700'
                  : 'text-slate-300'}"
            >
              {day.dayOfMonth}
            </span>
            {#if evs.length > 0}
              <span class="text-[10px] font-semibold text-slate-300">{evs.length}</span>
            {/if}
          </div>

          <!-- Eventos del día -->
          <div class="flex flex-col gap-0.5">
            {#each visibles as evento (evento.id_actividad + '-' + evento.id_componente)}
              <EventPill
                {evento}
                accent={accentPorCurso[evento.id_curso]}
                onClick={onSelectEvento}
              />
            {/each}
            {#if restantes > 0}
              <span
                class="px-1.5 text-[11px] font-semibold text-slate-400 hover:text-indigo-600"
              >
                +{restantes} más
              </span>
            {/if}
          </div>
        </button>
      {/each}
    </div>
  {/each}
</div>
