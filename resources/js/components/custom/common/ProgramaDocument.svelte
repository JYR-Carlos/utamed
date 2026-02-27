<script lang="ts">
  import type { Snippet } from 'svelte';

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
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('es-ES');
    } catch {
      return dateString;
    }
  }

  const seccionI = $derived(secciones.find((s) => s.numeral_romano === 'I'));
  const seccionesRest = $derived(secciones.filter((s) => s.numeral_romano !== 'I'));
</script>

<!-- Sección I: Identificación (tabla) -->
{#if seccionI}
  <div class="bg-white rounded-lg p-8 shadow-sm border border-gray-200 mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">I. {seccionI.nombre_seccion.toUpperCase()}</h2>
    <div class="overflow-x-auto">
      <table class="w-full">
        <tbody>
          {#each getContenidos(seccionI) as contenido}
            {#each parseContent(contenido.texto_contenido) as line}
              <tr class="border-b border-gray-200">
                <td class="py-3 px-4 font-medium text-gray-700 w-1/3">{line.split(':')[0]}</td>
                <td class="py-3 px-4 text-gray-900">{line.split(':').slice(1).join(':').trim() || line}</td>
              </tr>
            {/each}
          {/each}
        </tbody>
      </table>
    </div>
  </div>
{/if}

<!-- Secciones II+ -->
{#if seccionesRest.length > 0}
  <div class="bg-white rounded-lg p-8 shadow-sm border border-gray-200 mb-8">
    {#each seccionesRest as seccion, i (seccion.numeral_romano)}
      <div class="mb-10">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">
          {seccion.numeral_romano}. {seccion.nombre_seccion.toUpperCase()}
        </h2>

        {#if getContenidos(seccion).length > 0}
          {#each getContenidos(seccion) as contenido}
            {#if contenido.texto_contenido?.trim()}
              <div class="mb-6">
                {#if seccion.numeral_romano === 'II' || seccion.numeral_romano === 'III' || seccion.numeral_romano === 'IV'}
                  <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">{contenido.texto_contenido}</p>
                {:else if seccion.numeral_romano === 'V'}
                  <div class="space-y-3">
                    {#each parseContent(contenido.texto_contenido) as item}
                      <div class="flex gap-3">
                        <span class="text-blue-600 font-semibold">•</span>
                        <span class="text-gray-700">{item}</span>
                      </div>
                    {/each}
                  </div>
                {:else if seccion.numeral_romano === 'VI'}
                  <div class="border-l-4 border-blue-500 pl-4 py-3 mb-4">
                    <div class="whitespace-pre-wrap text-gray-700 leading-relaxed">{contenido.texto_contenido}</div>
                  </div>
                {:else if seccion.numeral_romano === 'VII'}
                  <div class="whitespace-pre-wrap text-gray-700 leading-relaxed">{contenido.texto_contenido}</div>
                {:else if seccion.numeral_romano === 'VIII'}
                  <div class="space-y-2">
                    {#each parseContent(contenido.texto_contenido) as item}
                      {#if item.includes('•')}
                        <div class="flex gap-3 text-gray-700">
                          <span class="text-blue-600">•</span>
                          <span>{item.replace('•', '').trim()}</span>
                        </div>
                      {:else}
                        <p class="text-gray-700">{item}</p>
                      {/if}
                    {/each}
                  </div>
                {:else if seccion.numeral_romano === 'IX'}
                  <div class="space-y-6">
                    {#each parseContent(contenido.texto_contenido) as item}
                      {#if !item.includes('•')}
                        <p class="text-gray-700 leading-relaxed">{item}</p>
                      {/if}
                    {/each}
                    {#if seccion.componentes && seccion.componentes.length > 0}
                      <div class="mt-6">
                        <h4 class="font-semibold text-gray-900 mb-3">Componentes</h4>
                        <div class="overflow-x-auto">
                          <table class="w-full border-collapse text-sm">
                            <thead>
                              <tr class="bg-gray-100 border-b-2 border-gray-300">
                                <th class="border border-gray-300 px-4 py-2 text-left font-semibold text-gray-900">Componente</th>
                                <th class="border border-gray-300 px-4 py-2 text-center font-semibold text-gray-900">Genera Acta</th>
                                <th class="border border-gray-300 px-4 py-2 text-center font-semibold text-gray-900">Porcentaje</th>
                                <th class="border border-gray-300 px-4 py-2 text-center font-semibold text-gray-900">Aprobación Obligatoria</th>
                                <th class="border border-gray-300 px-4 py-2 text-center font-semibold text-gray-900">Asistencia Obligatoria</th>
                              </tr>
                            </thead>
                            <tbody>
                              {#each seccion.componentes as comp}
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                  <td class="border border-gray-300 px-4 py-2 text-gray-900">{comp.componente}</td>
                                  <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">{comp.genera_acta ? 'Sí' : 'No'}</td>
                                  <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">{comp.porcentaje}%</td>
                                  <td class="border border-gray-300 px-4 py-2 text-center text-gray-700"
                                    >{comp.aprobacion_obligatoria ? 'Sí' : 'No'}</td
                                  >
                                  <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">{comp.asistencia_obligatoria}%</td>
                                </tr>
                              {/each}
                            </tbody>
                          </table>
                        </div>
                        {#if seccion.ponderacion_optativa?.porcentaje}
                          <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded">
                            <p class="text-gray-900">
                              <span class="font-semibold">Ponderación Prueba Optativa:</span>
                              {seccion.ponderacion_optativa.porcentaje}%
                            </p>
                          </div>
                        {/if}
                      </div>
                    {/if}
                  </div>
                {:else}
                  <p class="text-gray-700 whitespace-pre-wrap leading-relaxed">{contenido.texto_contenido}</p>
                {/if}
              </div>
            {/if}
          {/each}
        {:else}
          <p class="text-gray-500 italic">(Sin contenido)</p>
        {/if}

        {#if i < seccionesRest.length - 1}
          <hr class="my-10 border-gray-300" />
        {/if}
      </div>
    {/each}
  </div>
{/if}

<!-- Metadatos -->
{#if metadata}
  <div class="bg-gray-50 rounded-lg p-6 mb-6 border border-gray-200">
    <div class="grid grid-cols-3 gap-4 text-sm">
      <div>
        <p class="text-gray-600"><span class="font-medium">Versión:</span> {metadata.version ?? 'N/A'}</p>
      </div>
      <div>
        <p class="text-gray-600"><span class="font-medium">Creado por:</span> {metadata.creado_por ?? 'N/A'}</p>
      </div>
      <div>
        <p class="text-gray-600">
          <span class="font-medium">Fecha de Creación:</span>
          {formatDate(metadata.fecha_creacion)}
        </p>
      </div>
    </div>
  </div>
{/if}

<!-- Acciones (aprobar/rechazar, editar, etc.) — inyectado por el padre -->
{#if actions}
  {@render actions()}
{/if}
