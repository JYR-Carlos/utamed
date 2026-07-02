<script lang="ts">
  import type { Snippet } from 'svelte';
  import {
    FileText,
    Hash,
    BookOpen,
    Target,
    Layers,
    FlaskConical,
    BookMarked,
    Library,
    ClipboardList,
  } from 'lucide-svelte';
  import { formatFechaTextoLargo } from '@/utils/formatters';

  interface Seccion {
    numeral_romano?: string;
    nombre_seccion: string;
    contenidos?: Array<{ texto_contenido: string | null }>;
    contenidos_programa?: Array<{ texto_contenido: string | null }>;
    componentes?: any[];
    ponderacion_optativa?: { porcentaje?: number } | null;
  }

  interface Metadata {
    version?: number | string;
    creado_por?: string;
    fecha_creacion?: string;
  }

  interface Props {
    secciones: Seccion[];
    metadata?: Metadata;
    /** Snippet Svelte 5 para renderizar acciones (aprobar/rechazar, editar, etc.) */
    actions?: Snippet;
  }

  let { secciones, metadata, actions }: Props = $props();

  function parseContent(text: string | null | undefined): string[] {
    if (!text) return [];
    return text
      .split('\n')
      .map((l) => l.trim())
      .filter((l) => l);
  }

  function getContenidos(seccion: Seccion) {
    return seccion.contenidos || seccion.contenidos_programa || [];
  }

  function formatDate(dateString?: string): string {
    return dateString ? formatFechaTextoLargo(dateString) : 'N/A';
  }

  // Icono y color de acento por sección
  const SECTION_META: Record<
    string,
    { icon: typeof FileText; accent: string; bg: string; badge: string }
  > = {
    I: {
      icon: FileText,
      accent: 'text-slate-700',
      bg: 'bg-slate-50',
      badge: 'bg-slate-100 text-slate-700 ring-slate-300',
    },
    II: {
      icon: BookOpen,
      accent: 'text-indigo-700',
      bg: 'bg-indigo-50',
      badge: 'bg-indigo-100 text-indigo-700 ring-indigo-300',
    },
    III: {
      icon: Target,
      accent: 'text-violet-700',
      bg: 'bg-violet-50',
      badge: 'bg-violet-100 text-violet-700 ring-violet-300',
    },
    IV: {
      icon: Layers,
      accent: 'text-blue-700',
      bg: 'bg-blue-50',
      badge: 'bg-blue-100 text-blue-700 ring-blue-300',
    },
    V: {
      icon: Hash,
      accent: 'text-cyan-700',
      bg: 'bg-cyan-50',
      badge: 'bg-cyan-100 text-cyan-700 ring-cyan-300',
    },
    VI: {
      icon: BookMarked,
      accent: 'text-teal-700',
      bg: 'bg-teal-50',
      badge: 'bg-teal-100 text-teal-700 ring-teal-300',
    },
    VII: {
      icon: FlaskConical,
      accent: 'text-emerald-700',
      bg: 'bg-emerald-50',
      badge: 'bg-emerald-100 text-emerald-700 ring-emerald-300',
    },
    VIII: {
      icon: Library,
      accent: 'text-amber-700',
      bg: 'bg-amber-50',
      badge: 'bg-amber-100 text-amber-700 ring-amber-300',
    },
    IX: {
      icon: ClipboardList,
      accent: 'text-rose-700',
      bg: 'bg-rose-50',
      badge: 'bg-rose-100 text-rose-700 ring-rose-300',
    },
  };

  function getSectionMeta(numeral?: string) {
    return SECTION_META[numeral ?? ''] ?? SECTION_META['II'];
  }

  const seccionI = $derived(secciones.find((s) => s.numeral_romano === 'I'));
  const seccionesRest = $derived(
    secciones.filter((s) => {
      if (s.numeral_romano === 'I') return false;
      const items = s.contenidos || s.contenidos_programa || [];
      return items.some((c) => c.texto_contenido?.trim());
    }),
  );
</script>

<!-- ═══════════════════════════════════════════════════════════════
     Sección I: Identificación — tabla de metadatos estilizada
     ═══════════════════════════════════════════════════════════════ -->
{#if seccionI}
  {@const meta = getSectionMeta('I')}
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <!-- Section header bar -->
    <div
      class="flex items-center gap-3 px-6 py-4 bg-gradient-to-r from-slate-50 to-white border-b border-slate-200"
    >
      <span
        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm font-bold ring-1 {meta.badge}"
        >I</span
      >
      <h2 class="text-base font-semibold tracking-wide text-slate-800 uppercase">
        {seccionI.nombre_seccion}
      </h2>
    </div>

    <!-- Identification grid -->
    <div class="divide-y divide-gray-100">
      {#each getContenidos(seccionI) as contenido}
        {#each parseContent(contenido.texto_contenido) as line, li}
          {@const parts = line.split(':')}
          {@const label = parts[0]?.trim()}
          {@const value = parts.slice(1).join(':').trim() || line}
          <div class="grid grid-cols-5 gap-0">
            <div class="col-span-2 px-6 py-3 bg-slate-50 flex items-center">
              <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"
                >{label}</span
              >
            </div>
            <div class="col-span-3 px-6 py-3 flex items-center">
              <span class="text-sm font-medium text-slate-900">{value}</span>
            </div>
          </div>
        {/each}
      {/each}
    </div>
  </div>
{/if}

<!-- ═══════════════════════════════════════════════════════════════
     Secciones II+
     ═══════════════════════════════════════════════════════════════ -->
{#if seccionesRest.length > 0}
  <div class="space-y-6 mb-6">
    {#each seccionesRest as seccion (seccion.numeral_romano)}
      {@const meta = getSectionMeta(seccion.numeral_romano)}
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Section header bar -->
        <div
          class="flex items-center gap-3 px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200"
        >
          <span
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm font-bold ring-1 {meta.badge}"
          >
            {seccion.numeral_romano}
          </span>
          <h2 class="text-base font-semibold tracking-wide text-gray-800 uppercase">
            {seccion.nombre_seccion}
          </h2>
        </div>

        <!-- Section body -->
        <div class="px-8 py-6">
          {#if getContenidos(seccion).length > 0}
            {#each getContenidos(seccion) as contenido}
              {#if contenido.texto_contenido?.trim()}
                <!-- II / III / IV: Texto libre con buen espaciado -->
                {#if seccion.numeral_romano === 'II' || seccion.numeral_romano === 'III' || seccion.numeral_romano === 'IV'}
                  <p class="text-gray-700 leading-relaxed whitespace-pre-wrap text-[0.9375rem]">
                    {contenido.texto_contenido}
                  </p>

                  <!-- V: Lista con bullets numerados estilizados -->
                {:else if seccion.numeral_romano === 'V'}
                  <ul class="space-y-3">
                    {#each parseContent(contenido.texto_contenido) as item, idx}
                      <li class="flex gap-3 items-start">
                        <span
                          class="flex-shrink-0 w-6 h-6 rounded-full bg-cyan-100 text-cyan-700 text-xs font-bold flex items-center justify-center mt-0.5"
                        >
                          {idx + 1}
                        </span>
                        <span class="text-gray-700 leading-relaxed text-[0.9375rem]">{item}</span>
                      </li>
                    {/each}
                  </ul>

                  <!-- VI: Unidades con barra lateral de color -->
                {:else if seccion.numeral_romano === 'VI'}
                  <div class="space-y-4">
                    {#each parseContent(contenido.texto_contenido) as item}
                      <div class="border-l-4 border-teal-500 pl-4 py-1">
                        <p
                          class="whitespace-pre-wrap text-gray-700 leading-relaxed text-[0.9375rem]"
                        >
                          {item}
                        </p>
                      </div>
                    {/each}
                  </div>

                  <!-- VII: Metodología — texto con fondo sutil -->
                {:else if seccion.numeral_romano === 'VII'}
                  <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-6 py-5">
                    <p class="whitespace-pre-wrap text-gray-700 leading-relaxed text-[0.9375rem]">
                      {contenido.texto_contenido}
                    </p>
                  </div>

                  <!-- VIII: Bibliografía — lista estilizada -->
                {:else if seccion.numeral_romano === 'VIII'}
                  <ul class="space-y-2">
                    {#each parseContent(contenido.texto_contenido) as item}
                      {#if item.includes('•')}
                        <li class="flex gap-3 items-start text-[0.9375rem]">
                          <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-amber-500 mt-2"
                          ></span>
                          <span class="text-gray-700">{item.replace('•', '').trim()}</span>
                        </li>
                      {:else}
                        <li class="flex gap-3 items-start text-[0.9375rem]">
                          <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full bg-amber-500 mt-2"
                          ></span>
                          <span class="text-gray-700">{item}</span>
                        </li>
                      {/if}
                    {/each}
                  </ul>

                  <!-- IX: Evaluación — tabla completa con diseño moderno -->
                {:else if seccion.numeral_romano === 'IX'}
                  <div class="space-y-4">
                    {#each parseContent(contenido.texto_contenido) as item}
                      {#if !item.includes('•')}
                        <p class="text-gray-700 leading-relaxed text-[0.9375rem]">{item}</p>
                      {/if}
                    {/each}

                    {#if seccion.componentes && seccion.componentes.length > 0}
                      <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                          <thead>
                            <tr class="bg-rose-50 border-b border-rose-200">
                              <th
                                class="px-4 py-3 text-left text-xs font-semibold text-rose-700 uppercase tracking-wide"
                                >Componente</th
                              >
                              <th
                                class="px-4 py-3 text-center text-xs font-semibold text-rose-700 uppercase tracking-wide"
                                >Genera Acta</th
                              >
                              <th
                                class="px-4 py-3 text-center text-xs font-semibold text-rose-700 uppercase tracking-wide"
                                >%</th
                              >
                              <th
                                class="px-4 py-3 text-center text-xs font-semibold text-rose-700 uppercase tracking-wide"
                                >Aprobación Oblig.</th
                              >
                              <th
                                class="px-4 py-3 text-center text-xs font-semibold text-rose-700 uppercase tracking-wide"
                                >Asistencia Oblig.</th
                              >
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-100">
                            {#each seccion.componentes as comp, ci}
                              <tr
                                class="{ci % 2 === 0
                                  ? 'bg-white'
                                  : 'bg-gray-50'} hover:bg-rose-50 transition-colors"
                              >
                                <td class="px-4 py-3 font-medium text-gray-900"
                                  >{comp.componente}</td
                                >
                                <td class="px-4 py-3 text-center">
                                  {#if comp.genera_acta}
                                    <span
                                      class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full"
                                      >Sí</span
                                    >
                                  {:else}
                                    <span
                                      class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"
                                      >No</span
                                    >
                                  {/if}
                                </td>
                                <td class="px-4 py-3 text-center">
                                  <span
                                    class="inline-flex items-center text-sm font-bold text-rose-700"
                                    >{comp.porcentaje}%</span
                                  >
                                </td>
                                <td class="px-4 py-3 text-center text-gray-700">
                                  {#if comp.aprobacion_obligatoria}
                                    <span
                                      class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full"
                                      >Sí</span
                                    >
                                  {:else}
                                    <span class="text-xs text-gray-400">No</span>
                                  {/if}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-700">
                                  <span class="text-sm font-medium"
                                    >{comp.asistencia_obligatoria}%</span
                                  >
                                </td>
                              </tr>
                            {/each}
                          </tbody>
                        </table>
                      </div>

                      {#if seccion.ponderacion_optativa?.porcentaje}
                        <div
                          class="mt-3 flex items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 px-5 py-3"
                        >
                          <span class="text-sm font-semibold text-rose-800"
                            >Ponderación Prueba Optativa:</span
                          >
                          <span class="text-lg font-bold text-rose-700"
                            >{seccion.ponderacion_optativa.porcentaje}%</span
                          >
                        </div>
                      {/if}
                    {/if}
                  </div>

                  <!-- Resto: texto libre -->
                {:else}
                  <p class="text-gray-700 whitespace-pre-wrap leading-relaxed text-[0.9375rem]">
                    {contenido.texto_contenido}
                  </p>
                {/if}
              {/if}
            {/each}
          {:else}
            <p class="text-gray-400 italic text-sm">(Sin contenido)</p>
          {/if}
        </div>
      </div>
    {/each}
  </div>
{/if}

<!-- ═══════════════════════════════════════════════════════════════
     Pie de documento — metadatos y versión
     ═══════════════════════════════════════════════════════════════ -->
{#if metadata}
  <div
    class="rounded-2xl border border-gray-200 bg-gradient-to-r from-slate-50 to-white px-6 py-4 mb-6"
  >
    <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-gray-500">
      <div class="flex items-center gap-6">
        {#if metadata.creado_por}
          <span>
            <span class="font-medium text-gray-700">Elaborado por:</span>
            {metadata.creado_por}
          </span>
        {/if}
        {#if metadata.fecha_creacion}
          <span>
            <span class="font-medium text-gray-700">Fecha:</span>
            {formatDate(metadata.fecha_creacion)}
          </span>
        {/if}
      </div>
      {#if metadata.version}
        <span
          class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-300"
        >
          <FileText class="w-3 h-3" />
          v{metadata.version}
        </span>
      {/if}
    </div>
  </div>
{/if}

<!-- Acciones (aprobar/rechazar, editar, etc.) — inyectado por el padre -->
{#if actions}
  {@render actions()}
{/if}
