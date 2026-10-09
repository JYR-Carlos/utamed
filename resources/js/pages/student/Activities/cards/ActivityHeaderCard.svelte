<script lang="ts">
  import { ClipboardList } from 'lucide-svelte';
  import type { RubricaResponse } from '@/types/rubrica';

  interface Props {
    nombre_actividad: string;
    nombre_curso: string;
    descripcion: string;
    es_sumativa: boolean;
    entrega_obligatoria: boolean;
    rubrica?: RubricaResponse | null;
    onVerRubricaClick?: () => void;
  }

  let {
    nombre_actividad,
    nombre_curso,
    descripcion,
    es_sumativa,
    entrega_obligatoria,
    rubrica = null,
    onVerRubricaClick,
  }: Props = $props();

  const rubricaObj = $derived(rubrica?.rubrica);
  const tieneRubrica = $derived(!!rubricaObj && (rubricaObj.niveles?.length ?? 0) > 0);
  const cantidadCriterios = $derived(rubricaObj?.niveles?.length ?? 0);
  const totalPuntos = $derived(rubricaObj?.detalles_evaluacion?.puntaje_total ?? null);
</script>

<section class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-sm">
  <div class="grid grid-cols-1 {es_sumativa ? 'md:grid-cols-10' : ''} md:items-stretch">
    <!-- Columna izquierda: Información (7 de 10 columnas en sumativa, ancho completo en formativa) -->
    <div class="flex min-w-0 flex-col justify-between gap-3.5 p-5 {es_sumativa ? 'md:col-span-7' : 'w-full'} md:p-6">
      <div class="flex flex-col gap-1">
        <span class="text-[11.5px] font-medium text-[#5A5E6E]">{nombre_curso}</span>
        <h1 class="text-xl font-semibold leading-tight text-[#1A1A24] md:text-2xl">{nombre_actividad}</h1>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span
          class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold
          {es_sumativa ? 'border-amber-100 bg-amber-50 text-amber-800' : 'border-sky-100 bg-sky-50 text-sky-800'}"
        >
          <span class="h-1.5 w-1.5 rounded-full {es_sumativa ? 'bg-amber-500' : 'bg-sky-500'}"></span>
          {es_sumativa ? 'Sumativa' : 'Formativa'}
        </span>

        {#if entrega_obligatoria}
          <span
            class="inline-flex items-center gap-1.5 rounded-md border border-red-100 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"
          >
            <span class="h-1.5 w-1.5 rounded-full bg-red-600"></span>
            Entrega obligatoria
          </span>
        {/if}
      </div>

      {#if descripcion}
        <p class="text-sm leading-relaxed text-[#5A5E6E]">{descripcion}</p>
      {/if}
    </div>

    <!-- Columna derecha: Cápsula de Rúbrica (Solo actividades sumativas) -->
    {#if es_sumativa}
      <div class="flex md:col-span-3">
        {#if tieneRubrica}
          <button
            type="button"
            onclick={onVerRubricaClick}
            class="group flex h-full w-full min-h-[68px] md:min-h-0 flex-col items-center justify-center gap-1.5 rounded-b-xl md:rounded-l-none md:rounded-r-xl border-t md:border-t-0 md:border-l border-[#EDE4FA] bg-[#E0D1FF] px-4 py-5 text-[#5812c2] shadow-none transition-all duration-200 hover:bg-[#5812c2] hover:text-white active:bg-[#470ea1] active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#5812c2]"
          >
            <div class="flex items-center gap-2">
              <ClipboardList class="h-5 w-5 text-[#5812c2] transition-all group-hover:scale-110 group-hover:text-purple-100" />
              <span class="text-sm font-semibold tracking-tight transition-colors">Ver Rúbrica</span>
            </div>
            {#if cantidadCriterios > 0}
              <span class="text-[11.5px] font-medium text-[#7C5CAE] transition-colors group-hover:text-purple-100/90">
                {cantidadCriterios} {cantidadCriterios === 1 ? 'criterio' : 'criterios'}
                {#if totalPuntos !== null}
                  · {totalPuntos} pts
                {/if}
              </span>
            {/if}
          </button>
        {:else}
          <div
            class="flex h-full w-full min-h-[68px] md:min-h-0 flex-col items-center justify-center gap-1.5 rounded-b-xl md:rounded-l-none md:rounded-r-xl border-t md:border-t-0 md:border-l border-dashed border-gray-200 bg-gray-50/90 px-4 py-5 text-center select-none"
          >
            <ClipboardList class="h-5 w-5 text-gray-400" />
            <span class="text-xs font-medium text-gray-500 leading-tight">
              No hay rúbrica para esta actividad
            </span>
          </div>
        {/if}
      </div>
    {/if}
  </div>
</section>
