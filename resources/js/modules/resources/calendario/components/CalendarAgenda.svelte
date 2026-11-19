<script lang="ts">
  /**
   * Vista agenda: la semana en vertical, para escanear en vez de navegar.
   * Es la vista por defecto en pantallas estrechas.
   *
   * Cada día lleva su cabecera (HOY / MAÑANA / nombre del día), el aviso de
   * sobrecarga del grupo si lo hay, y una ficha por marca.
   */
  import { ArrowRight, CalendarDays, TriangleAlert } from 'lucide-svelte';
  import type { CalendarCurso, CalendarDay, CalendarItem, CursoAccent, Sobrecarga } from '../types';
  import { diasRestantes, formatCorto, formatMedio } from '../utils/calendar';
  import { AVISO_SOBRECARGA } from '../utils/estilos';
  import ItemCard from './ItemCard.svelte';

  interface Props {
    /** Días del período mostrado, en orden. */
    dias: CalendarDay[];
    itemsPorDia: Record<string, CalendarItem[]>;
    accentPorCurso: Record<number, CursoAccent>;
    cursoPorId: Record<number, CalendarCurso>;
    sobrecargas: Record<string, Sobrecarga[]>;
    /**
     * Primera marca visible después del período. Con la semana vacía, el
     * docente salta a ella en vez de avanzar semanas a ciegas.
     */
    proxima?: CalendarItem | null;
    onIrA?: (iso: string) => void;
  }

  let {
    dias,
    itemsPorDia,
    accentPorCurso,
    cursoPorId,
    sobrecargas,
    proxima = null,
    onIrA,
  }: Props = $props();

  function tituloMarca(item: CalendarItem): string {
    if (item.familia === 'ENTREGA') return item.entrega.titulo;
    if (item.familia === 'SESION') return `Clase de ${item.sesion.componente}`;
    return item.hito.titulo;
  }

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
    class="flex flex-col items-center justify-center gap-3 rounded-xl border border-inst-linea bg-inst-sutil px-8 py-14 text-center shadow-[0_1px_3px_rgba(0,0,0,.06)]"
  >
    <span
      class="flex h-11 w-11 items-center justify-center rounded-xl border border-inst-linea bg-inst-sutil"
    >
      <CalendarDays size={20} class="text-inst-ink-2" />
    </span>
    <p class="m-0 text-[15px] font-semibold text-inst-ink">Nada programado en este período</p>
    <p class="m-0 max-w-[420px] text-[13px] leading-[1.5] text-inst-ink-2">
      Ninguno de los cursos visibles tiene fechas límite, sesiones de asistencia registradas ni
      hitos de syllabus en estos días.
    </p>
    {#if proxima}
      {@const destino = proxima.fecha}
      <div
        class="mt-1 flex max-w-full flex-col items-center gap-2 rounded-lg border border-inst-linea bg-inst-tarjeta px-4 py-3"
      >
        <p class="m-0 max-w-[420px] text-[13px] leading-[1.45] text-inst-ink">
          <span class="font-semibold">Próxima marca: {formatCorto(proxima.fecha)}</span>
          · <span class="break-words">{tituloMarca(proxima)}</span>
        </p>
        {#if onIrA}
          <button
            type="button"
            onclick={() => onIrA?.(destino)}
            class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-inst-accion px-4 text-[13px] font-semibold text-inst-sobre-accion transition-colors hover:bg-inst-accion-h sm:min-h-9"
          >
            Ir a esa semana
            <ArrowRight size={14} aria-hidden="true" />
          </button>
        {/if}
      </div>
    {/if}
  </div>
{:else}
  <div class="flex flex-col gap-3.5">
    {#each diasConMarcas as grupo (grupo.dia.iso)}
      {@const etiqueta = rotulo(grupo.dia.iso)}
      <section class="flex flex-col gap-2">
        <header
          class="flex flex-wrap items-center gap-2 border-l-4 pl-2"
          style="border-color:{grupo.dia.isToday ? 'var(--color-inst-accion)' : 'var(--color-inst-linea)'}"
        >
          {#if etiqueta}
            <span
              class="text-[12.5px] font-bold uppercase tracking-[0.06em]"
              style="color:{grupo.dia.isToday ? 'var(--color-inst-accion)' : 'var(--color-inst-ink-2)'}"
            >
              {etiqueta}
            </span>
          {/if}
          <span
            class={etiqueta
              ? 'text-[12px] text-inst-ink-2'
              : 'text-[12.5px] font-bold text-inst-ink-2'}
          >
            {formatMedio(grupo.dia.iso)}
          </span>
          {#if grupo.items.length > 0}
            <span class="ml-auto font-mono text-[11px] tabular-nums text-inst-ink-2">
              {grupo.items.length}
              {grupo.items.length === 1 ? 'marca' : 'marcas'}
            </span>
          {/if}
        </header>

        {#each grupo.cargas as carga (carga.clave)}
          <div class={AVISO_SOBRECARGA}>
            <TriangleAlert size={14} class="text-inst-warn mt-0.5 shrink-0" />
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
