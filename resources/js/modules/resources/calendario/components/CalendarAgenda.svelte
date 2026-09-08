<script lang="ts">
  /**
   * Vista agenda: la semana en vertical, para escanear en vez de navegar.
   * Es la vista por defecto en pantallas estrechas.
   *
   * Cada día lleva su cabecera (HOY / MAÑANA / nombre del día), el aviso de
   * sobrecarga del grupo si lo hay, y una ficha por marca.
   */
  import { CalendarDays, TriangleAlert } from 'lucide-svelte';
  import type { CalendarCurso, CalendarDay, CalendarItem, CursoAccent, Sobrecarga } from '../types';
  import { diasRestantes, formatMedio } from '../utils/calendar';
  import { AVISO_SOBRECARGA } from '../utils/estilos';
  import ItemCard from './ItemCard.svelte';

  interface Props {
    /** Días del período mostrado, en orden. */
    dias: CalendarDay[];
    itemsPorDia: Record<string, CalendarItem[]>;
    accentPorCurso: Record<number, CursoAccent>;
    cursoPorId: Record<number, CalendarCurso>;
    sobrecargas: Record<string, Sobrecarga[]>;
  }

  let { dias, itemsPorDia, accentPorCurso, cursoPorId, sobrecargas }: Props = $props();

  /** Sólo los días que tienen algo que mostrar. */
  const diasConMarcas = $derived(
    dias
      .map((dia) => ({
        dia,
        items: itemsPorDia[dia.iso] ?? [],
        cargas: sobrecargas[dia.iso] ?? [],
      }))
      .filter((d) => d.items.length > 0 || d.cargas.length > 0),
  );

  /** "HOY" / "MAÑANA" / nada: el nombre del día ya va al lado. */
  function rotulo(iso: string): string | null {
    const n = diasRestantes(iso);
    if (n === 0) return 'Hoy';
    if (n === 1) return 'Mañana';
    return null;
  }
</script>

{#if diasConMarcas.length === 0}
  <div
    class="flex flex-col items-center justify-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#FCFCFB] px-8 py-14 text-center shadow-[0_1px_3px_rgba(0,0,0,.06)]"
  >
    <span
      class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-[#F5F1EA]"
    >
      <CalendarDays size={20} color="#5A5E6E" />
    </span>
    <p class="m-0 text-[15px] font-semibold text-[#1A1A24]">Nada programado en este período</p>
    <p class="m-0 max-w-[420px] text-[13px] leading-[1.5] text-[#5A5E6E]">
      Ninguno de los cursos visibles tiene fechas límite, sesiones de asistencia registradas ni
      hitos de syllabus en estos días.
    </p>
  </div>
{:else}
  <div class="flex flex-col gap-3.5">
    {#each diasConMarcas as grupo (grupo.dia.iso)}
      {@const etiqueta = rotulo(grupo.dia.iso)}
      <section class="flex flex-col gap-2">
        <header
          class="flex flex-wrap items-center gap-2 border-l-4 pl-2"
          style="border-color:{grupo.dia.isToday ? '#002F6C' : '#D6D9E0'}"
        >
          {#if etiqueta}
            <span
              class="text-[12.5px] font-bold uppercase tracking-[0.06em]"
              style="color:{grupo.dia.isToday ? '#002F6C' : '#4A4E5C'}"
            >
              {etiqueta}
            </span>
          {/if}
          <span
            class={etiqueta
              ? 'text-[12px] text-[#5A5E6E]'
              : 'text-[12.5px] font-bold text-[#4A4E5C]'}
          >
            {formatMedio(grupo.dia.iso)}
          </span>
          {#if grupo.items.length > 0}
            <span class="ml-auto font-mono text-[11px] tabular-nums text-[#5A5E6E]">
              {grupo.items.length}
              {grupo.items.length === 1 ? 'marca' : 'marcas'}
            </span>
          {/if}
        </header>

        {#each grupo.cargas as carga (carga.clave)}
          <div class={AVISO_SOBRECARGA}>
            <TriangleAlert size={14} class="mt-0.5 shrink-0" color="#B91C1C" />
            <span>
              <span class="font-bold">
                Grupo {carga.letra} tiene {carga.total} entregas este día:
              </span>
              {carga.titulos.join(', ')}.
              {#if carga.ocultas > 0}
                <span class="font-semibold">
                  {carga.ocultas}
                  {carga.ocultas === 1 ? 'queda oculta' : 'quedan ocultas'} por el filtro de cursos.
                </span>
              {/if}
            </span>
          </div>
        {/each}

        {#each grupo.items as item (item.key)}
          <ItemCard
            {item}
            curso={cursoPorId[item.id_curso]}
            accent={accentPorCurso[item.id_curso]}
          />
        {/each}
      </section>
    {/each}
  </div>
{/if}
