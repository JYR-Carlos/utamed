<script lang="ts">
    /**
     * Componente de visualización de syllabus en modo solo lectura.
     * Muestra el programa en formato documento.
     */
    export let sections: any[] = [];
    export let asignatura: any;
    export let curso: any;
</script>

<div class="prose prose-slate max-w-none">
    <!-- Identificación de la Asignatura -->
    <div class="not-prose mb-8">
        <h2 class="text-2xl font-bold mb-4 text-slate-900">I. IDENTIFICACIÓN DE LA ASIGNATURA</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 bg-slate-50 rounded-lg border border-slate-200">
            <div>
                <span class="text-sm font-semibold text-slate-600">Asignatura:</span>
                <p class="text-base font-medium text-slate-900">{asignatura.nombre}</p>
            </div>
            <div>
                <span class="text-sm font-semibold text-slate-600">Código:</span>
                <p class="text-base font-medium text-slate-900">{asignatura.cod_asignatura}</p>
            </div>
            <div>
                <span class="text-sm font-semibold text-slate-600">Créditos SCT:</span>
                <p class="text-base font-medium text-slate-900">{asignatura.creditos_sct}</p>
            </div>
            <div>
                <span class="text-sm font-semibold text-slate-600">Horas Cátedra:</span>
                <p class="text-base font-medium text-slate-900">{asignatura.horas_catedra}</p>
            </div>
        </div>
    </div>

    <!-- Secciones dinámicas del programa -->
    {#each sections as section, index}
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-4 text-slate-900">
                {#if section.numeral_romano}
                    {section.numeral_romano}.
                {/if}
                {section.nombre_seccion}
            </h2>
            
            {#if section.contenidos && section.contenidos.length > 0}
                {#if section.es_lista}
                    <ul class="list-disc pl-6 space-y-2">
                        {#each section.contenidos as content}
                            <li class="text-slate-700 leading-relaxed">{content.texto_contenido}</li>
                        {/each}
                    </ul>
                {:else}
                    <div class="space-y-3">
                        {#each section.contenidos as content}
                            <p class="text-slate-700 leading-relaxed text-justify">{content.texto_contenido}</p>
                        {/each}
                    </div>
                {/if}
            {:else}
                <p class="text-slate-400 italic">Sin contenido</p>
            {/if}
        </div>
    {/each}

    {#if sections.length === 0}
        <div class="text-center py-12">
            <p class="text-slate-500">No hay secciones en este programa</p>
        </div>
    {/if}
</div>

<style>
    :global(.prose) {
        max-width: none;
    }
</style>
