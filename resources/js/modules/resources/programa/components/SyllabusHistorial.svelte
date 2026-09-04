<script lang="ts">
  /**
   * Línea de tiempo del documento — 280 px a la derecha de la columna de lectura.
   *
   * Se alimenta de `auditoria.programa_historial`, que es lo que dejan los
   * triggers `tr_programa_creado` / `tr_programa_modificado`: es la única fuente
   * de fechas del programa, porque `curso.programa` no tiene ninguna columna de
   * fecha. Las razones de rechazo se citan aquí íntegras.
   *
   * Bajo 1440px el panel se pliega tras el botón «Historial» de la cabecera y
   * abre el mismo contenido como slide-over (ver `Programa.svelte`).
   */
  import { Clock } from 'lucide-svelte';
  import { formatFechaHora } from '@/utils/formatters';
  import { eventoVisual, type EventoHistorial } from '../utils/syllabusEstado';

  interface Props {
    eventos: EventoHistorial[];
    /** Sin marco propio: se usa dentro del slide-over. */
    plano?: boolean;
  }

  let { eventos, plano = false }: Props = $props();
</script>

<section
  class="flex w-full flex-col gap-3.5 {plano
    ? ''
    : 'rounded-[10px] border border-[#E5E7EB] bg-white p-4 lg:sticky lg:top-6 lg:w-[280px] lg:flex-none'}"
  aria-label="Historial del syllabus"
>
  {#if !plano}
    <div class="flex items-center gap-2">
      <Clock size={15} class="text-[#5A5E6E]" aria-hidden="true" />
      <h2 class="m-0 text-[13px] font-semibold text-[#1A1A24]">Historial</h2>
    </div>
  {/if}

  {#if eventos.length === 0}
    <p class="m-0 text-[12.5px] text-[#5A5E6E]">
      Todavía no hay movimientos registrados para este documento.
    </p>
  {:else}
    <ol class="m-0 flex list-none flex-col gap-3.5 p-0">
      {#each eventos as evento, i}
        {@const visual = eventoVisual(evento)}
        <li class="flex gap-2.5">
          <div class="flex flex-none flex-col items-center">
            <span class="mt-[5px] h-2 w-2 rounded-full {visual.dot}" aria-hidden="true"></span>
            {#if i < eventos.length - 1}
              <span class="my-1 w-px flex-1 bg-[#E5E7EB]" aria-hidden="true"></span>
            {/if}
          </div>
          <div class="flex min-w-0 flex-col gap-[3px]">
            <span class="text-[12.5px] font-semibold text-[#1A1A24]">{visual.titulo}</span>
            <span class="text-[12px] text-[#5A5E6E]">
              {formatFechaHora(evento.fecha_accion)}
              {#if evento.usuario}· {evento.usuario}{/if}
            </span>
            {#if visual.esRechazo && evento.observaciones}
              <p
                class="m-0 rounded-md border border-[#F3DCDC] bg-[#FDF3F3] px-2 py-1.5 text-[12px] leading-[1.45] text-[#5A5E6E]"
              >
                «{evento.observaciones}»
              </p>
            {/if}
          </div>
        </li>
      {/each}
    </ol>
  {/if}
</section>
