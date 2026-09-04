<script lang="ts">
  /**
   * Grilla mensual (semanas que inician en Lunes).
   *
   * Dibuja las tres familias de marcas del día —fechas límite, sesiones de
   * asistencia e hitos de syllabus— coloreadas por CURSO, marca la banda de HOY
   * y avisa cuando un mismo grupo acumula tres o más entregas ese día.
   *
   * Es de solo lectura: al pulsar un día (o "+N más") se notifica al contenedor
   * para abrir el detalle, desde donde se navega al curso.
   */
  import { TriangleAlert } from 'lucide-svelte';
  import type { CalendarCurso, CalendarItem, CursoAccent, Sobrecarga } from '../types';
  import { buildMonthMatrix, DIAS_SEMANA, MESES } from '../utils/calendar';
  import EventPill from './EventPill.svelte';

  interface Props {
    year: number;
    month: number;
    /** Marcas visibles agrupadas por clave 'YYYY-MM-DD'. */
    itemsPorDia: Record<string, CalendarItem[]>;
    /** Acento por id_curso. */
    accentPorCurso: Record<number, CursoAccent>;
    /** Curso por id, para rotular las sesiones. */
    cursoPorId: Record<number, CalendarCurso>;
    /** Sobrecargas por día, calculadas sobre TODAS las entregas. */
    sobrecargas: Record<string, Sobrecarga[]>;
    onSelectDay?: (iso: string) => void;
    onSelectItem?: (item: CalendarItem) => void;
  }

  let {
    year,
    month,
    itemsPorDia,
    accentPorCurso,
    cursoPorId,
    sobrecargas,
    onSelectDay,
    onSelectItem,
  }: Props = $props();

  const weeks = $derived(buildMonthMatrix(year, month));

  const MAX_VISIBLE = 3;

  const CELDA =
    'relative flex min-h-[112px] min-w-0 flex-1 flex-col gap-1 border-r border-[#E5E7EB] p-1.5 last:border-r-0';

  function etiquetaCurso(id: number): string {
    const curso = cursoPorId[id];
    if (!curso) return '';
    const cod = curso.cod_curso ?? curso.asignatura;
    return curso.letra_grupo ? `${cod} ${curso.letra_grupo}` : String(cod);
  }

  /** Fondo de la celda: hoy manda sobre sobrecarga, y ésta sobre el resto. */
  function fondoCelda(inMonth: boolean, isWeekend: boolean, isToday: boolean, hayCarga: boolean) {
    if (isToday) return 'background:#F7FAFF;box-shadow:inset 0 4px 0 #002F6C';
    if (hayCarga) return 'background:#FFFCFC';
    if (!inMonth || isWeekend) return 'background:#FBFAF8';
    return 'background:#FFFFFF';
  }
</script>

<div
  class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_1px_3px_rgba(0,0,0,.06)]"
>
  <!-- La grilla no se comprime por debajo de 760px: se desplaza dentro de su
       propio contenedor en vez de romper el ancho de la página. -->
  <div class="overflow-x-auto">
    <div class="min-w-[760px]">
      <!-- Encabezado de días -->
      <div class="flex border-b border-[#E5E7EB] bg-[#F8F7F4]">
        {#each DIAS_SEMANA as dia, i (dia)}
          <div
            class="flex-1 border-r border-[#E5E7EB] px-2 py-2 text-[11px] font-semibold uppercase tracking-[0.06em] last:border-r-0 {i >=
            5
              ? 'text-[#8A8E9C]'
              : 'text-[#5A5E6E]'}"
          >
            {dia}
          </div>
        {/each}
      </div>

      <!-- Semanas -->
      {#each weeks as week, wi (wi)}
        <div class="flex {wi < weeks.length - 1 ? 'border-b border-[#E5E7EB]' : ''}">
          {#each week as day (day.iso)}
            {@const items = itemsPorDia[day.iso] ?? []}
            {@const visibles = items.slice(0, MAX_VISIBLE)}
            {@const restantes = items.length - visibles.length}
            {@const cargas = sobrecargas[day.iso] ?? []}
            {@const ocultas = cargas.reduce((n, c) => n + c.ocultas, 0)}
            <div
              class={CELDA}
              style={fondoCelda(day.inMonth, day.isWeekend, day.isToday, cargas.length > 0)}
            >
              <!-- Superficie de apertura del día: va detrás de las píldoras
                   para no anidar botones dentro de un botón. -->
              <button
                type="button"
                onclick={() => onSelectDay?.(day.iso)}
                class="absolute inset-0 z-0 cursor-pointer focus:outline-none focus-visible:bg-[#E8EDF5]/40"
                aria-label="Ver el día {day.dayOfMonth}"
              ></button>

              <!-- Número del día y avisos -->
              <div class="pointer-events-none relative z-10 flex flex-wrap items-center gap-1.5">
                {#if day.isToday}
                  <span
                    class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-[#002F6C] px-1.5 text-[11.5px] font-bold text-white"
                  >
                    {day.dayOfMonth}
                  </span>
                  <span
                    class="text-[10px] font-bold uppercase tracking-[0.08em] text-[#002F6C]">hoy</span
                  >
                {:else}
                  <span
                    class="text-[12.5px] font-semibold {day.inMonth
                      ? 'text-[#1A1A24]'
                      : 'text-[#A1A1AA]'}"
                  >
                    {day.inMonth
                      ? day.dayOfMonth
                      : `${day.dayOfMonth} ${MESES[Number(day.iso.split('-')[1]) - 1]
                          .slice(0, 3)
                          .toLowerCase()}`}
                  </span>
                {/if}

                {#each cargas as carga (carga.clave)}
                  <span
                    class="pointer-events-auto inline-flex items-center gap-1"
                    title="Grupo {carga.letra} tiene {carga.total} entregas este día: {carga.titulos.join(
                      ', ',
                    )}"
                  >
                    <span
                      class="inline-flex h-[19px] w-[19px] items-center justify-center rounded-md border border-[#FECACA] bg-[#FEF2F2]"
                    >
                      <TriangleAlert size={11} color="#B91C1C" />
                    </span>
                    <span
                      class="text-[10px] font-bold uppercase tracking-[0.04em] text-[#B91C1C]"
                      >Grupo {carga.letra} ×{carga.total}</span
                    >
                  </span>
                {/each}
              </div>

              <!-- Marcas del día -->
              <div class="relative z-10 flex flex-col gap-1">
                {#each visibles as item (item.key)}
                  <EventPill
                    {item}
                    accent={accentPorCurso[item.id_curso]}
                    etiquetaCurso={etiquetaCurso(item.id_curso)}
                    onClick={onSelectItem}
                  />
                {/each}

                {#if restantes > 0}
                  <button
                    type="button"
                    onclick={(e) => {
                      e.stopPropagation();
                      onSelectDay?.(day.iso);
                    }}
                    class="px-1 text-left text-[10.5px] font-semibold text-[#002F6C] transition-colors hover:text-[#1B4789]"
                  >
                    +{restantes} más
                  </button>
                {/if}

                {#if ocultas > 0}
                  <span class="px-1 text-[10px] font-semibold leading-[1.3] text-[#B91C1C]">
                    {ocultas}
                    {ocultas === 1 ? 'entrega más' : 'entregas más'} en cursos ocultos
                  </span>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      {/each}
    </div>
  </div>
</div>
