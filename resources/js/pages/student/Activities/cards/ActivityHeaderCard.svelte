<script lang="ts">
  import { ClipboardList, Download, FileText } from 'lucide-svelte';
  import type { RubricaResponse } from '@/types/rubrica';
  import { formatBytes } from '@/utils/formatters';

  interface Props {
    nombre_actividad: string;
    nombre_curso: string;
    descripcion: string;
    es_sumativa: boolean;
    entrega_obligatoria: boolean;
    rubrica?: RubricaResponse | null;
    onVerRubricaClick?: () => void;
    /** Enunciado adjunto por el docente; null si aún no hay. */
    archivo_enunciado?: { nombre_original: string; mime_type: string | null; peso_bytes: number | null } | null;
    onVerEnunciadoClick?: () => void;
    urlDescargaEnunciado?: string | null;
  }

  let {
    nombre_actividad,
    nombre_curso,
    descripcion,
    es_sumativa,
    entrega_obligatoria,
    rubrica = null,
    onVerRubricaClick,
    archivo_enunciado = null,
    onVerEnunciadoClick,
    urlDescargaEnunciado = null,
  }: Props = $props();

  // «PDF · 2,4 MB»: el tipo sale de la extensión del nombre original, que es
  // lo que el estudiante reconoce (el mime puede venir genérico).
  const detalleEnunciado = $derived.by(() => {
    if (!archivo_enunciado) return '';
    const ext = archivo_enunciado.nombre_original.split('.').pop()?.toUpperCase() ?? '';
    const peso = archivo_enunciado.peso_bytes ? formatBytes(archivo_enunciado.peso_bytes) : '';
    return [ext, peso].filter(Boolean).join(' · ');
  });

  const rubricaObj = $derived(rubrica?.rubrica);
  const tieneRubrica = $derived(!!rubricaObj && (rubricaObj.niveles?.length ?? 0) > 0);
  const cantidadCriterios = $derived(rubricaObj?.niveles?.length ?? 0);
  const totalPuntos = $derived(rubricaObj?.detalles_evaluacion?.puntaje_total ?? null);
</script>

<section class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-sm">
  <div class="grid grid-cols-1 md:grid-cols-10 md:items-stretch">
    <!-- Columna izquierda: Información (7 de 10 columnas) -->
    <div class="flex min-w-0 flex-col justify-between gap-3.5 p-5 md:col-span-7 md:p-6">
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

    <!--
      Columna derecha: los dos recursos de la actividad, enunciado y rúbrica (solo sumativas),
      juntos en la cabecera. Antes el enunciado vivía en el panel lateral,
      debajo del plazo, y en el móvil quedaba al final de la página.
    -->
    <div class="flex flex-col border-t border-[#E5E7EB] md:col-span-3 md:border-l md:border-t-0">
      {#if archivo_enunciado}
        <div class="flex flex-1 items-stretch {es_sumativa ? 'border-b border-[#E5E7EB]' : ''}">
          <button
            type="button"
            onclick={onVerEnunciadoClick}
            class="group flex min-h-[64px] flex-1 items-center gap-3 bg-[#E6EDF5] px-4 py-4 text-left text-[#002855] transition-colors hover:bg-[#002855] hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#002855]"
          >
            <FileText class="h-5 w-5 shrink-0" />
            <span class="flex min-w-0 flex-col">
              <span class="whitespace-nowrap text-sm font-semibold tracking-tight">Ver enunciado</span>
              {#if detalleEnunciado}
                <span class="text-[11.5px] font-medium opacity-80">{detalleEnunciado}</span>
              {/if}
            </span>
          </button>
          {#if urlDescargaEnunciado}
            <a
              href={urlDescargaEnunciado}
              aria-label="Descargar enunciado"
              title="Descargar enunciado"
              class="flex w-12 shrink-0 items-center justify-center border-l border-[#C9D6E6] bg-[#E6EDF5] text-[#002855] transition-colors hover:bg-[#002855] hover:text-white"
            >
              <Download class="h-4 w-4" />
            </a>
          {/if}
        </div>
      {:else}
        <div
          class="flex min-h-[64px] flex-1 items-center gap-3 {es_sumativa ? 'border-b border-dashed border-gray-200' : ''} bg-gray-50/90 px-4 py-4 select-none"
        >
          <FileText class="h-5 w-5 shrink-0 text-gray-400" />
          <span class="text-xs font-medium leading-tight text-gray-500">El docente aún no adjunta el enunciado</span>
        </div>
      {/if}

      {#if es_sumativa}
        {#if tieneRubrica}
          <button
            type="button"
            onclick={onVerRubricaClick}
            class="group flex min-h-[64px] flex-1 items-center gap-3 bg-[#E0D1FF] px-4 py-4 text-left text-[#5812c2] transition-colors hover:bg-[#5812c2] hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#5812c2] md:rounded-br-xl"
          >
            <ClipboardList class="h-5 w-5 shrink-0" />
            <span class="flex min-w-0 flex-col">
              <span class="whitespace-nowrap text-sm font-semibold tracking-tight">Ver rúbrica</span>
              {#if cantidadCriterios > 0}
                <span class="text-[11.5px] font-medium opacity-80">
                  {cantidadCriterios} {cantidadCriterios === 1 ? 'criterio' : 'criterios'}{#if totalPuntos !== null}{' '}· {totalPuntos} pts{/if}
                </span>
              {/if}
            </span>
          </button>
        {:else}
          <div class="flex min-h-[64px] flex-1 items-center gap-3 bg-gray-50/90 px-4 py-4 select-none">
            <ClipboardList class="h-5 w-5 shrink-0 text-gray-400" />
            <span class="text-xs font-medium leading-tight text-gray-500">No hay rúbrica para esta actividad</span>
          </div>
        {/if}
      {/if}
    </div>
  </div>
</section>
