<script lang="ts">
  /**
   * Cabeza de la tarjeta lateral de la actividad (en el móvil, de la pestaña
   * Entrega): lo primero que el alumno busca, arriba y en grande (OBS-38,
   * OBS-43). La tarjeta la pone quien la usa; debajo va la entrega.
   *
   * - Sumativa: «Tu nota». Con nota, el número grande, si aprueba, el puntaje
   *   y quién y cuándo calificó; sin nota, una línea discreta que dice que
   *   aparecerá aquí.
   * - Formativa: el estado en color de semáforo: «Revisada» (verde),
   *   «Entregada · en revisión» (ámbar), «Sin entrega» (rojo) o «Por
   *   entregar».
   *
   * La retroalimentación va aquí sólo cuando no hay rúbrica evaluada donde
   * leerla.
   */
  import { ChevronRight, CircleAlert, CircleCheck, Clock } from 'lucide-svelte';
  import { btnSuave, etiqueta, pildora, tono } from '@/lib/dm';
  import { formatDiaCorto } from '@/utils/formatters';
  import { titleCase } from '@/lib/utils';

  interface Props {
    esSumativa: boolean;
    esGrupal: boolean;
    /** null: todavía sin nota ni evaluación. */
    nota: number | null;
    evaluada: boolean;
    /** Formativa sin revisar: si ya hay entrega y si el plazo cerró. */
    entregada?: boolean;
    cerrada?: boolean;
    conEntrega?: boolean;
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
    entregada = false,
    cerrada = false,
    conEntrega = true,
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
  const delGrupo = $derived(esGrupal ? ' del grupo' : '');

  // «Nota del grupo 7,0 · ajuste del docente −0,5»: explica por qué la nota
  // propia no coincide con la que da la rúbrica.
  const detalleAjuste = $derived.by(() => {
    if (!ajusteDecimas || notaGrupo == null) return null;
    const signo = ajusteDecimas > 0 ? '+' : '−';
    return `Nota del grupo ${fmt(notaGrupo)} · ajuste del docente ${signo}${fmt(Math.abs(ajusteDecimas))}`;
  });

  const tienePuntaje = $derived(puntaje != null && puntajeTotal > 0);

  // Estado de una formativa, en semáforo.
  const estadoFormativa = $derived.by(() => {
    if (evaluada) return { texto: 'Revisada', color: 'text-dm-ok', icono: CircleCheck, ayuda: null };
    if (entregada) {
      return {
        texto: 'Entregada · en revisión',
        color: 'text-dm-warn',
        icono: Clock,
        ayuda: `Tu docente todavía no revisa la entrega${delGrupo}.`,
      };
    }
    if (!conEntrega) {
      return { texto: 'Aún sin revisión', color: 'text-dm-ink-2', icono: Clock, ayuda: 'Esta actividad no lleva entrega.' };
    }
    if (cerrada) return { texto: 'Sin entrega', color: 'text-dm-error', icono: CircleAlert, ayuda: null };
    return { texto: 'Por entregar', color: 'text-dm-ink', icono: Clock, ayuda: null };
  });
</script>

<div class="flex flex-col gap-3 p-4 lg:p-5">
  <h2 id="t-nota" class="{etiqueta} text-dm-ink-3">{esSumativa ? 'Tu nota' : 'Estado'}</h2>

  {#if esSumativa}
    {#if evaluada && nota != null}
      <span class="flex items-center gap-4">
        <span
          class="font-dm-cond text-[64px] leading-[60px] font-black tabular-nums lg:text-[72px] lg:leading-[68px] {aprobada
            ? 'text-dm-ink'
            : 'text-dm-error'}">{fmt(nota)}</span
        >
        <span class="flex flex-col items-start gap-1.5">
          <span class="{pildora} {aprobada ? tono.ok : tono.error} font-bold">
            {#if aprobada}<CircleCheck size={14} aria-hidden="true" />Aprobada{:else}<CircleAlert
                size={14}
                aria-hidden="true"
              />Reprobada{/if}
          </span>
          {#if tienePuntaje}
            <span class="text-[13px] leading-[18px] text-dm-ink-2">{String(puntaje).replace('.', ',')} de {puntajeTotal} pts</span>
          {/if}
        </span>
      </span>
    {:else}
      <span class="flex items-center gap-3">
        <span aria-hidden="true" class="font-dm-cond text-[32px] leading-8 font-black text-dm-ink-3">—</span>
        <span class="text-sm leading-5 text-dm-ink-2">Tu nota aparecerá aquí cuando tu docente evalúe la entrega{delGrupo}.</span>
      </span>
    {/if}
  {:else}
    {@const Icono = estadoFormativa.icono}
    <span class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
      <span class="inline-flex items-center gap-2 text-xl leading-7 font-bold {estadoFormativa.color}">
        <Icono size={22} aria-hidden="true" class="shrink-0" />{estadoFormativa.texto}
      </span>
      {#if evaluada && resultado}<span class="{pildora} {tono.neutro} font-bold">{resultado}</span>{/if}
    </span>
    {#if estadoFormativa.ayuda}
      <span class="text-sm leading-5 text-dm-ink-2">{estadoFormativa.ayuda}</span>
    {/if}
  {/if}

  {#if evaluada && (fecha || evaluador)}
    <span class="text-sm leading-5 text-dm-ink-2">
      {esSumativa ? 'Calificada' : 'Revisada'}{fecha ? ` el ${formatDiaCorto(new Date(fecha))}` : ''}{#if evaluador}
        {' '}por <strong class="font-semibold text-dm-ink">{titleCase(evaluador)}</strong>{/if}.
    </span>
  {/if}
  {#if evaluada && detalleAjuste}
    <span class="text-sm leading-5 text-dm-ink-2">{detalleAjuste}</span>
  {/if}
  {#if evaluada && retroalimentacion}
    <blockquote class="m-0 rounded-r-[10px] border-l-4 border-dm-amarillo bg-dm-sutil px-3.5 py-3">
      <span class="{etiqueta} mb-1 block text-dm-ink-3">Retroalimentación</span>
      <p class="text-[15px] leading-[22px] break-words whitespace-pre-line text-dm-ink">{retroalimentacion}</p>
    </blockquote>
  {:else if evaluada && !esSumativa && !onVerRubrica}
    <p class="text-sm leading-5 text-dm-ink-2">Tu docente no dejó comentarios escritos.</p>
  {/if}
  {#if evaluada && onVerRubrica}
    <button type="button" class={btnSuave} onclick={onVerRubrica}>
      Ver cómo te evaluaron<ChevronRight size={18} aria-hidden="true" />
    </button>
  {/if}
</div>
