<script lang="ts">
  /**
   * La nota de la actividad. Sin nota todavía, un «—» con lo que falta para
   * que aparezca. Con nota (sumativa) o con evaluación (formativa), la
   * tarjeta grande con la retroalimentación del docente.
   */
  import { CircleAlert, CircleCheck } from 'lucide-svelte';
  import { etiqueta, pildora, tarjeta, tono } from '@/lib/dm';
  import { formatDiaCorto } from '@/utils/formatters';
  import { titleCase } from '@/lib/utils';

  interface Props {
    esSumativa: boolean;
    esGrupal: boolean;
    /** null: todavía sin nota ni evaluación. */
    nota: number | null;
    evaluada: boolean;
    /** Nota del grupo y décimas del docente, si las hubo. */
    notaGrupo?: number | null;
    ajusteDecimas?: number;
    fecha?: string | null;
    evaluador?: string | null;
    puntaje?: number | null;
    puntajeTotal?: number;
    /** Resultado cualitativo de una formativa («Bueno»). */
    resultado?: string | null;
    retroalimentacion?: string | null;
  }

  let {
    esSumativa,
    esGrupal,
    nota,
    evaluada,
    notaGrupo = null,
    ajusteDecimas = 0,
    fecha = null,
    evaluador = null,
    puntaje = null,
    puntajeTotal = 0,
    resultado = null,
    retroalimentacion = null,
  }: Props = $props();

  const fmt = (n: number) => n.toFixed(1).replace('.', ',');
  const aprobada = $derived(nota != null && nota >= 4);

  // «Nota del grupo 7,0 · ajuste del docente −0,5»: explica por qué la nota
  // propia no coincide con la que da la rúbrica.
  const detalleAjuste = $derived.by(() => {
    if (!ajusteDecimas || notaGrupo == null) return null;
    const signo = ajusteDecimas > 0 ? '+' : '−';
    return `Nota del grupo ${fmt(notaGrupo)} · ajuste del docente ${signo}${fmt(Math.abs(ajusteDecimas))}`;
  });

  const tienePuntaje = $derived(puntaje != null && puntajeTotal > 0);
</script>

{#if !evaluada}
  <section
    aria-labelledby="t-nota"
    class="{tarjeta} flex items-center gap-4 p-4 lg:flex-col lg:items-start lg:gap-2 lg:p-5"
  >
    <span aria-hidden="true" class="w-12 shrink-0 font-dm-cond text-[40px] leading-10 font-black text-dm-ink-3 lg:order-2">—</span>
    <div class="flex flex-col gap-0.5 lg:contents">
      <!-- Móvil: «Aún sin nota» al lado del guion. Escritorio: «Nota» encima. -->
      <h2 id="t-nota" class="text-base leading-[22px] font-semibold lg:order-1 lg:text-lg lg:leading-6">
        <span class="lg:hidden">{esSumativa ? 'Aún sin nota' : 'Aún sin revisión'}</span><span class="max-lg:hidden"
          >{esSumativa ? 'Nota' : 'Revisión'}</span
        >
      </h2>
      <span class="text-[13px] leading-[18px] text-dm-ink-2 lg:order-3 lg:text-sm lg:leading-5">
        <span class="max-lg:hidden">{esSumativa ? 'Aún sin nota.' : 'Aún sin revisión.'}</span>
        Aparece aquí cuando tu docente {esSumativa ? 'evalúe' : 'revise'} la entrega{esGrupal ? ' del grupo' : ''}.
      </span>
    </div>
  </section>
{:else}
  <section
    aria-labelledby="t-nota"
    class="{tarjeta} flex flex-col gap-3 border-t-6 border-t-(--curso) p-4 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-8 lg:gap-y-6 lg:p-6"
  >
    <div class="flex items-end justify-between gap-3 lg:flex-none lg:flex-col lg:items-start lg:gap-1.5">
      <div class="flex flex-col gap-1.5 lg:contents">
        <h2 id="t-nota" class="{etiqueta} text-dm-ink-3">{esSumativa ? 'Tu nota' : 'Revisión de tu docente'}</h2>
        {#if esSumativa && nota != null}
          <span class="{pildora} {aprobada ? tono.ok : tono.error} self-start lg:order-3">
            {#if aprobada}<CircleCheck size={14} aria-hidden="true" />Aprobada{:else}<CircleAlert
                size={14}
                aria-hidden="true"
              />Reprobada{/if}
          </span>
        {:else if resultado}
          <span class="{pildora} {tono.neutro} self-start lg:order-3">{resultado}</span>
        {/if}
      </div>
      {#if esSumativa && nota != null}
        <span
          class="font-dm-cond text-[64px] leading-[60px] font-black tabular-nums lg:order-2 lg:text-[80px] lg:leading-[76px] {aprobada
            ? 'text-dm-ink'
            : 'text-dm-error'}">{fmt(nota)}</span
        >
      {/if}
    </div>

    <div class="flex min-w-0 flex-col gap-3 lg:flex-[1_1_320px]">
      {#if fecha || evaluador || tienePuntaje}
        <span class="text-sm leading-5 text-dm-ink-2">
          {esSumativa ? 'Calificada' : 'Revisada'}{fecha ? ` el ${formatDiaCorto(new Date(fecha))}` : ''}{#if evaluador}
            por <strong class="font-semibold text-dm-ink">{titleCase(evaluador)}</strong>{/if}{#if tienePuntaje}
            · {String(puntaje).replace('.', ',')} de {puntajeTotal} puntos<span class="max-lg:hidden"> en la rúbrica</span>{/if}
        </span>
      {/if}
      {#if detalleAjuste}
        <span class="text-sm leading-5 text-dm-ink-2">{detalleAjuste}</span>
      {/if}
      {#if retroalimentacion}
        <blockquote class="m-0 rounded-r-[10px] border-l-4 border-dm-amarillo bg-dm-sutil px-3.5 py-3 lg:px-4 lg:py-3.5">
          <span class="{etiqueta} mb-1 block text-dm-ink-3">Retroalimentación</span>
          <p class="text-[15px] leading-[22px] break-words whitespace-pre-line text-dm-ink lg:leading-6">{retroalimentacion}</p>
        </blockquote>
      {:else if !esSumativa}
        <p class="text-sm leading-5 text-dm-ink-2">Tu docente no dejó comentarios escritos.</p>
      {/if}
    </div>
  </section>
{/if}
