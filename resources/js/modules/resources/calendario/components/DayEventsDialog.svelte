<script lang="ts">
  /**
   * Detalle de un día: todo lo que ocurre esa fecha, separado en lo que tiene
   * hora (sesiones de asistencia) y lo que no (fechas límite e hitos), con el
   * aviso de sobrecarga arriba del todo.
   *
   * Vista de solo lectura: desde cada ficha se sale al curso.
   */
  import { CalendarDays, TriangleAlert, X } from 'lucide-svelte';
  import type { CalendarCurso, CalendarItem, CursoAccent, Sobrecarga } from '../types';
  import { etiquetaRelativa, formatLargo } from '../utils/calendar';
  import { AVISO_SOBRECARGA, ETIQUETA } from '../utils/estilos';
  import ItemCard from './ItemCard.svelte';

  interface Props {
    /** Día seleccionado en 'YYYY-MM-DD' o null si está cerrado. */
    iso: string | null;
    items: CalendarItem[];
    cursoPorId: Record<number, CalendarCurso>;
    accentPorCurso: Record<number, CursoAccent>;
    /** Sobrecargas del día, calculadas sobre todas las entregas. */
    cargas: Sobrecarga[];
    onClose?: () => void;
  }

  let { iso, items, cursoPorId, accentPorCurso, cargas, onClose }: Props = $props();

  const conHora = $derived(items.filter((i) => i.familia === 'SESION'));
  const sinHora = $derived(items.filter((i) => i.familia !== 'SESION'));
  const entregas = $derived(items.filter((i) => i.familia === 'ENTREGA').length);

  function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') onClose?.();
  }
</script>

<svelte:window onkeydown={onKeydown} />

{#if iso}
  <div class="fixed inset-0 z-50 flex justify-end">
    <!-- Fondo -->
    <button
      type="button"
      class="absolute inset-0 bg-[#1A1A24]/25 backdrop-blur-[2px]"
      aria-label="Cerrar"
      onclick={() => onClose?.()}
    ></button>

    <!-- Panel -->
    <div
      class="relative flex h-full w-full max-w-md flex-col border-l border-[#D6D9E0] bg-white shadow-[0_16px_40px_rgba(0,0,0,.18)]"
      role="dialog"
      aria-modal="true"
      aria-label="Detalle del día"
    >
      <header class="flex items-start gap-3 border-b border-[#E5E7EB] px-5 py-4">
        <div class="flex min-w-0 flex-col gap-0.5">
          <span class="text-[11px] font-bold uppercase tracking-[0.08em] text-[#002F6C]">
            {etiquetaRelativa(iso)}
          </span>
          <h2 class="m-0 text-[15px] font-semibold text-[#1A1A24]">{formatLargo(iso)}</h2>
          <p class="m-0 text-[11.5px] text-[#5A5E6E]">
            {items.length}
            {items.length === 1 ? 'marca' : 'marcas'} · {entregas}
            {entregas === 1 ? 'fecha límite' : 'fechas límite'}
          </p>
        </div>
        <button
          type="button"
          onclick={() => onClose?.()}
          class="ml-auto flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors hover:bg-[#F5F1EA]"
          aria-label="Cerrar"
        >
          <X size={16} color="#5A5E6E" />
        </button>
      </header>

      <div class="flex-1 overflow-y-auto px-4 py-4">
        {#each cargas as carga (carga.clave)}
          <div class="{AVISO_SOBRECARGA} mb-3">
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

        {#if items.length === 0}
          <div class="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <CalendarDays size={32} color="#C9CDD6" />
            <p class="m-0 text-[13px] font-medium text-[#5A5E6E]">Sin marcas este día</p>
          </div>
        {:else}
          <div class="flex flex-col gap-2.5">
            {#if conHora.length > 0}
              <span class={ETIQUETA}>Con hora</span>
              {#each conHora as item (item.key)}
                <ItemCard
                  {item}
                  curso={cursoPorId[item.id_curso]}
                  accent={accentPorCurso[item.id_curso]}
                />
              {/each}
            {/if}

            {#if sinHora.length > 0}
              <span class="{ETIQUETA} {conHora.length > 0 ? 'mt-1.5' : ''}">
                Sin hora · fechas límite e hitos
              </span>
              {#each sinHora as item (item.key)}
                <ItemCard
                  {item}
                  curso={cursoPorId[item.id_curso]}
                  accent={accentPorCurso[item.id_curso]}
                />
              {/each}
            {/if}
          </div>
        {/if}
      </div>

      <footer class="border-t border-[#E5E7EB] bg-[#FCFCFB] px-5 py-3">
        <p class="m-0 text-[11px] leading-[1.45] text-[#5A5E6E]">
          Fecha <span class="font-semibold text-[#1A1A24]">nominal</span>. La fecha efectiva de cada
          alumno suma su holgura y se ve en el detalle de la actividad.
        </p>
      </footer>
    </div>
  </div>
{/if}
