<script lang="ts">
  import { Award, ChevronRight, MessageSquareText } from 'lucide-svelte';
  import { formatFechaCorta } from '@/utils/formatters';

  interface Props {
    ultima_nota?: number | null;
    ajuste_decimas?: number;
    nota_grupo?: number | null;
    es_sumativa: boolean;
    ultima_evaluacion?: {
      id_evaluacion?: number;
      fecha_emision?: string | null;
      emisor?: string | null;
      evaluacion_obtenida?: string | null;
      retroalimentacion?: string | null;
      mensaje?: string | null;
      resultado?: Record<string, string> | null;
    } | null;
    fecha_evaluacion?: string | null;
    evaluador?: string | null;
    onVerRubricaClick?: () => void;
  }

  let {
    ultima_nota,
    ajuste_decimas = 0,
    nota_grupo = null,
    es_sumativa,
    ultima_evaluacion = null,
    fecha_evaluacion,
    evaluador,
    onVerRubricaClick,
  }: Props = $props();

  const aprobada = $derived(ultima_nota !== null && ultima_nota !== undefined && ultima_nota >= 4);

  const gradeLabel = $derived.by(() => {
    if (ultima_nota === null || ultima_nota === undefined) return '-,-';
    return ultima_nota.toFixed(1).replace('.', ',');
  });

  // «Nota del grupo 7,0 · ajuste del docente −0,5»: explica por qué la nota
  // propia no coincide con la que da la rúbrica.
  const detalleAjuste = $derived.by(() => {
    if (!ajuste_decimas || nota_grupo === null || nota_grupo === undefined) return null;
    const signo = ajuste_decimas > 0 ? '+' : '−';
    const ajuste = Math.abs(ajuste_decimas).toFixed(1).replace('.', ',');
    return `Nota del grupo ${nota_grupo.toFixed(1).replace('.', ',')} · ajuste del docente ${signo}${ajuste}`;
  });

  const badgeClass = $derived(
    aprobada
      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
      : 'bg-red-50 text-red-700 border-red-200',
  );
  const dotClass = $derived(aprobada ? 'bg-emerald-600' : 'bg-red-600');

  // Evaluación formativa
  const opinionFormativa = $derived(ultima_evaluacion?.evaluacion_obtenida ?? null);
  const mensajeEvaluacion = $derived(ultima_evaluacion?.retroalimentacion ?? ultima_evaluacion?.mensaje ?? null);
  const fechaEfectiva = $derived(fecha_evaluacion ?? ultima_evaluacion?.fecha_emision ?? null);
  const evaluadorEfectivo = $derived(evaluador ?? ultima_evaluacion?.emisor ?? null);

  const opinionBadge = $derived.by(() => {
    switch (opinionFormativa) {
      case 'Bueno':
        return {
          label: 'Bueno',
          badge: 'bg-emerald-50 text-emerald-800 border-emerald-300',
          dot: 'bg-emerald-600',
        };
      case 'Regular':
        return {
          label: 'Regular',
          badge: 'bg-amber-50 text-amber-800 border-amber-300',
          dot: 'bg-amber-500',
        };
      case 'Malo':
        return {
          label: 'Malo',
          badge: 'bg-rose-50 text-rose-800 border-rose-300',
          dot: 'bg-rose-600',
        };
      default:
        return {
          label: opinionFormativa ?? 'Evaluado',
          badge: 'bg-slate-100 text-slate-800 border-slate-300',
          dot: 'bg-slate-500',
        };
    }
  });
</script>

<!--
  Card "Nota / Evaluación Formativa"
  - Sumativa: nota numérica, estado Aprobada/Reprobada y acceso a rúbrica evaluada.
  - Formativa: apreciación cualitativa (Bueno/Regular/Malo) en badge de cabecera y mensaje extenso de evaluación del docente.
-->
<div class="flex w-full flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
  <div class="flex items-center gap-2">
    {#if es_sumativa}
      <Award class="h-[15px] w-[15px] text-[#5A5E6E]" />
      <span class="text-[13px] font-semibold text-[#1A1A24]">Nota</span>
      {#if ultima_nota !== null && ultima_nota !== undefined}
        <span
          class="ml-auto inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {badgeClass}"
        >
          <span class="h-1.5 w-1.5 rounded-full {dotClass}"></span>
          {aprobada ? 'Aprobada' : 'Reprobada'}
        </span>
      {/if}
    {:else}
      <MessageSquareText class="h-[15px] w-[15px] text-[#5A5E6E]" />
      <span class="text-[13px] font-semibold text-[#1A1A24]">Evaluación Formativa</span>
      {#if opinionFormativa}
        <span
          class="ml-auto inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-bold {opinionBadge.badge}"
        >
          <span class="h-1.5 w-1.5 rounded-full {opinionBadge.dot}"></span>
          {opinionBadge.label}
        </span>
      {/if}
    {/if}
  </div>

  {#if es_sumativa}
    <div class="flex flex-col items-center gap-2.5 text-center">
      <span class="text-[80px] font-semibold leading-none tracking-tight text-[#1A1A24]">{gradeLabel}</span>
      <div class="flex flex-col pb-1">
        <span class="text-xs text-[#5A5E6E]">Nota sumativa</span>
        {#if detalleAjuste}
          <span class="mt-1 text-[11.5px] font-medium text-[#3F4352]">{detalleAjuste}</span>
        {/if}
      </div>
    </div>
  {:else}
    <div class="flex flex-col gap-2 text-left">
      {#if mensajeEvaluacion}
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5">
          <p class="text-[11px] font-semibold text-slate-500 mb-1.5">Mensaje de evaluación:</p>
          <p class="text-[13px] leading-relaxed text-slate-800 whitespace-pre-line break-words">
            {mensajeEvaluacion}
          </p>
        </div>
      {:else}
        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-3 text-center">
          <p class="text-xs text-slate-500">Sin observaciones registradas por el docente.</p>
        </div>
      {/if}
    </div>
  {/if}

  {#if fechaEfectiva || (es_sumativa && onVerRubricaClick)}
    <div class="flex items-center gap-2 border-t border-[#E5E7EB] pt-3">
      {#if fechaEfectiva}
        <span class="text-[11.5px] text-[#5A5E6E]">
          Publicada {formatFechaCorta(fechaEfectiva)}{evaluadorEfectivo ? ` · ${evaluadorEfectivo}` : ''}
        </span>
      {/if}
      {#if es_sumativa && onVerRubricaClick}
        <button
          class="ml-auto inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-[#22213F] transition-colors hover:bg-[#F8FAFC]"
          onclick={onVerRubricaClick}
        >
          Ver rúbrica evaluada
          <ChevronRight class="h-3.5 w-3.5" />
        </button>
      {/if}
    </div>
  {/if}
</div>
