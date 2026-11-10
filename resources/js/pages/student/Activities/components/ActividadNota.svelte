<script lang="ts">
  /**
   * La nota de la actividad, en la columna lateral (en el móvil, en la
   * pestaña Entrega). Sin nota todavía, un «—» con lo que falta para que
   * aparezca. Con nota (sumativa) o con evaluación (formativa), la nota, quién
   * y cuándo, y el acceso a la rúbrica evaluada. La retroalimentación va aquí
   * sólo cuando no hay rúbrica evaluada donde leerla.
   */
  import { ChevronRight, CircleAlert, CircleCheck } from 'lucide-svelte';
  import { btnSuave, etiqueta, pildora, tarjeta, tono } from '@/lib/dm';
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
    /** «Ver cómo te evaluaron»: lleva a la pestaña Rúbrica. */
    onVerRubrica?: () => void;
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
    onVerRubrica,
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
  <section aria-labelledby="t-nota" class="{tarjeta} flex items-center gap-4 p-4 lg:p-5">
    <span aria-hidden="true" class="w-10 shrink-0 font-dm-cond text-[40px] leading-10 font-black text-dm-ink-3">—</span>
    <span class="flex flex-col gap-0.5">
      <h2 id="t-nota" class="text-base leading-[22px] font-bold">{esSumativa ? 'Aún sin nota' : 'Aún sin revisión'}</h2>
      <span class="text-[13px] leading-[18px] text-dm-ink-2">
        Aparece aquí cuando tu docente {esSumativa ? 'evalúe' : 'revise'} la entrega{esGrupal ? ' del grupo' : ''}.
      </span>
    </span>
  </section>
{:else}
  <section aria-labelledby="t-nota" class="flex flex-col gap-3 rounded-xl border border-dm-linea bg-dm-sutil p-4 lg:p-5">
    <h2 id="t-nota" class="{etiqueta} text-dm-ink-3">{esSumativa ? 'Tu nota' : 'Revisión de tu docente'}</h2>
    {#if (esSumativa && nota != null) || resultado || tienePuntaje}
      <span class="flex items-center gap-4">
        {#if esSumativa && nota != null}
          <span
            class="font-dm-cond text-[64px] leading-[60px] font-black tabular-nums lg:text-[72px] lg:leading-[68px] {aprobada
              ? 'text-dm-ink'
              : 'text-dm-error'}">{fmt(nota)}</span
          >
        {/if}
        <span class="flex flex-col items-start gap-1.5">
          {#if esSumativa && nota != null}
            <span class="{pildora} {aprobada ? tono.ok : tono.error} font-bold">
              {#if aprobada}<CircleCheck size={14} aria-hidden="true" />Aprobada{:else}<CircleAlert
                  size={14}
                  aria-hidden="true"
                />Reprobada{/if}
            </span>
          {:else if resultado}
            <span class="{pildora} {tono.neutro}">{resultado}</span>
          {/if}
          {#if tienePuntaje}
            <span class="text-[13px] leading-[18px] text-dm-ink-2">{String(puntaje).replace('.', ',')} de {puntajeTotal} pts</span>
          {/if}
        </span>
      </span>
    {/if}
    {#if fecha || evaluador}
      <span class="text-sm leading-5 text-dm-ink-2">
        {esSumativa ? 'Calificada' : 'Revisada'}{fecha ? ` el ${formatDiaCorto(new Date(fecha))}` : ''}{#if evaluador}
          {' '}por <strong class="font-semibold text-dm-ink">{titleCase(evaluador)}</strong>{/if}.
      </span>
    {/if}
    {#if detalleAjuste}
      <span class="text-sm leading-5 text-dm-ink-2">{detalleAjuste}</span>
    {/if}
    {#if retroalimentacion}
      <blockquote class="m-0 rounded-r-[10px] border-l-4 border-dm-amarillo bg-dm-tarjeta px-3.5 py-3">
        <span class="{etiqueta} mb-1 block text-dm-ink-3">Retroalimentación</span>
        <p class="text-[15px] leading-[22px] break-words whitespace-pre-line text-dm-ink">{retroalimentacion}</p>
      </blockquote>
    {:else if !esSumativa && !onVerRubrica}
      <p class="text-sm leading-5 text-dm-ink-2">Tu docente no dejó comentarios escritos.</p>
    {/if}
    {#if onVerRubrica}
      <button type="button" class={btnSuave} onclick={onVerRubrica}>
        Ver cómo te evaluaron<ChevronRight size={18} aria-hidden="true" />
      </button>
    {/if}
  </section>
{/if}
