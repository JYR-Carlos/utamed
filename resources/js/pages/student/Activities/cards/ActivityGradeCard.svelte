<script lang="ts">
  import { Award, ChevronRight } from 'lucide-svelte';
  import { formatFechaCorta } from '@/utils/formatters';

  interface Props {
    ultima_nota?: number | null;
    es_sumativa: boolean;
    fecha_evaluacion?: string | null;
    evaluador?: string | null;
    onVerRubricaClick?: () => void;
  }

  let { ultima_nota, es_sumativa, fecha_evaluacion, evaluador, onVerRubricaClick }: Props = $props();

  const aprobada = $derived(ultima_nota !== null && ultima_nota !== undefined && ultima_nota >= 4);

  const gradeLabel = $derived.by(() => {
    if (ultima_nota === null || ultima_nota === undefined) return '-,-';
    return ultima_nota.toFixed(1).replace('.', ',');
  });

  const badgeClass = $derived(
    aprobada
      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
      : 'bg-red-50 text-red-700 border-red-200',
  );
  const dotClass = $derived(aprobada ? 'bg-emerald-600' : 'bg-red-600');
</script>

<!--
  Card "Nota" — a diferencia de la variante evaluada del mockup de diseño
  (templates/actividad-estudiante/ActividadEstudiante.dc.html), esta card
  muestra sólo la nota: sin barra de avance. La ponderación y el detalle de
  criterios viven en la rúbrica, no aquí; no se fabrican datos que el backend
  no envía (ponderación %, quién corrigió cada criterio).
-->
<div class="flex w-full flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
  <div class="flex items-center gap-2">
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
  </div>

  <div class="flex items-end gap-2.5">
    <span class="text-[40px] font-semibold leading-none tracking-tight text-[#1A1A24]">{gradeLabel}</span>
    <div class="flex flex-col pb-1">
      <span class="text-xs text-[#5A5E6E]">escala 1,0 – 7,0</span>
      <span class="text-xs text-[#5A5E6E]">{es_sumativa ? 'Nota sumativa' : 'Nota formativa'}</span>
    </div>
  </div>

  {#if fecha_evaluacion || onVerRubricaClick}
    <div class="flex items-center gap-2 border-t border-[#E5E7EB] pt-3">
      {#if fecha_evaluacion}
        <span class="text-[11.5px] text-[#5A5E6E]">
          Publicada {formatFechaCorta(fecha_evaluacion)}{evaluador ? ` · ${evaluador}` : ''}
        </span>
      {/if}
      {#if onVerRubricaClick}
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
