<script>
  import { router } from '@inertiajs/svelte';
  import Button from '@/components/ui/button/button.svelte';
  import Alert from '@/components/ui/alert/alert.svelte';
  import { AlertCircle, CheckCircle, XCircle, ArrowLeft } from 'lucide-svelte';

  export let programa;
  export let curso;

  let isApproving = false;
  let isRejecting = false;
  let showRejectionReason = false;
  let rejectionReason = '';

  const handleApprove = async () => {
    isApproving = true;
    try {
      router.put(`/admin/cursos/${curso.id_curso}/programa/aprobar`, {});
    } catch (error) {
      console.error('Error al aprobar:', error);
    } finally {
      isApproving = false;
    }
  };

  const handleReject = async () => {
    if (!rejectionReason.trim() && showRejectionReason) {
      alert('Por favor ingresa un motivo de rechazo');
      return;
    }

    isRejecting = true;
    try {
      router.put(`/admin/cursos/${curso.id_curso}/programa/rechazar`, {
        razon: rejectionReason,
      });
    } catch (error) {
      console.error('Error al rechazar:', error);
    } finally {
      isRejecting = false;
    }
  };

  const goBack = () => {
    router.get('/admin/cursos');
  };

  const getStateColor = (estado) => {
    const colors = {
      BORRADOR: 'bg-gray-100 text-gray-800',
      PENDIENTE: 'bg-yellow-100 text-yellow-800',
      APROBADO: 'bg-green-100 text-green-800',
      RECHAZADO: 'bg-red-100 text-red-800',
    };
    return colors[estado] || 'bg-gray-100 text-gray-800';
  };

  // Parse structured content from text
  function parseContent(text) {
    if (!text) return [];
    const lines = text
      .split('\n')
      .map((l) => l.trim())
      .filter((l) => l);
    return lines;
  }
</script>

<div class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-4">
        <Button onclick={goBack} variant="outline" class="inline-flex items-center gap-2">
          <ArrowLeft class="w-4 h-4" />
          Volver
        </Button>
        <div>
          <h1 class="text-4xl font-bold text-gray-900">PROGRAMA DE ASIGNATURA</h1>
          <p class="text-gray-600 mt-2 text-lg">{curso.asignatura_nombre}</p>
        </div>
      </div>
    </div>

    <!-- Información del Curso -->
    <div class="bg-white rounded-lg p-8 shadow-sm border border-gray-200 mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">I. IDENTIFICACIÓN DE LA ASIGNATURA</h2>
      <div class="overflow-x-auto">
        <table class="w-full">
          <tbody>
            {#each programa.data_syllabus?.secciones || [] as seccion}
              {#if seccion.numeral_romano === 'I' && seccion.contenidos}
                {#each seccion.contenidos as contenido}
                  {#each parseContent(contenido.texto_contenido) as line}
                    <tr class="border-b border-gray-200">
                      <td class="py-3 px-4 font-medium text-gray-700 w-1/3">{line.split(':')[0]}</td>
                      <td class="py-3 px-4 text-gray-900">{line.split(':')[1]?.trim() || line}</td>
                    </tr>
                  {/each}
                {/each}
              {/if}
            {/each}
          </tbody>
        </table>
      </div>
    </div>

    <!-- Contenido Principal -->
    <div class="bg-white rounded-lg p-8 shadow-sm border border-gray-200 mb-8">
      {#if programa.data_syllabus?.secciones}
        {#each programa.data_syllabus.secciones as seccion (seccion.numeral_romano)}
          {#if seccion.numeral_romano !== 'I'}
            <div class="mb-10">
              <h2 class="text-2xl font-bold text-gray-900 mb-4">
                {seccion.numeral_romano}. {seccion.nombre_seccion.toUpperCase()}
              </h2>

              {#if seccion.contenidos && seccion.contenidos.length > 0}
                {#each seccion.contenidos as contenido, idx}
                  {#if contenido.texto_contenido && contenido.texto_contenido.trim()}
                    <div class="mb-6">
                      {#if seccion.numeral_romano === 'II' || seccion.numeral_romano === 'III' || seccion.numeral_romano === 'IV'}
                        <!-- Paragraphs for II, III, IV -->
                        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">
                          {contenido.texto_contenido}
                        </p>
                      {:else if seccion.numeral_romano === 'V'}
                        <!-- List items for V -->
                        <div class="space-y-3">
                          {#each parseContent(contenido.texto_contenido) as item}
                            <div class="flex gap-3">
                              <span class="text-blue-600 font-semibold">•</span>
                              <span class="text-gray-700">{item}</span>
                            </div>
                          {/each}
                        </div>
                      {:else if seccion.numeral_romano === 'VI'}
                        <!-- Unidades for VI -->
                        <div class="border-l-4 border-blue-500 pl-4 py-3 mb-4">
                          <div class="whitespace-pre-wrap text-gray-700 leading-relaxed">
                            {contenido.texto_contenido}
                          </div>
                        </div>
                      {:else if seccion.numeral_romano === 'VII'}
                        <!-- Results and methodology for VII -->
                        <div class="space-y-4 whitespace-pre-wrap text-gray-700 leading-relaxed">
                          {contenido.texto_contenido}
                        </div>
                      {:else if seccion.numeral_romano === 'VIII'}
                        <!-- Resources list for VIII -->
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
                        <!-- Administrative aspects with components table -->
                        <div class="space-y-6">
                          <!-- Normativa text -->
                          {#each parseContent(contenido.texto_contenido) as item}
                            {#if item.startsWith('Normativa:') || item.startsWith('Ponderación') || !item.includes('•')}
                              <p class="text-gray-700 leading-relaxed">
                                {item}
                              </p>
                            {/if}
                          {/each}

                          <!-- Components table -->
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
                                        <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">
                                          {comp.genera_acta ? 'Sí' : 'No'}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">{comp.porcentaje}%</td>
                                        <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">
                                          {comp.aprobacion_obligatoria ? 'Sí' : 'No'}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-center text-gray-700">{comp.asistencia_obligatoria}%</td>
                                      </tr>
                                    {/each}
                                  </tbody>
                                </table>
                              </div>

                              <!-- Ponderación Optativa -->
                              {#if seccion.ponderacion_optativa && seccion.ponderacion_optativa.porcentaje}
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
                        <!-- Default -->
                        <p class="text-gray-700 whitespace-pre-wrap leading-relaxed">
                          {contenido.texto_contenido}
                        </p>
                      {/if}
                    </div>
                  {/if}
                {/each}
              {:else}
                <p class="text-gray-500 italic">(Sin contenido)</p>
              {/if}

              {#if seccion.numeral_romano !== programa.data_syllabus?.secciones[programa.data_syllabus?.secciones.length - 1]?.numeral_romano}
                <hr class="my-10 border-gray-300" />
              {/if}
            </div>
          {/if}
        {/each}
      {:else}
        <p class="text-gray-500">No hay contenido en el programa</p>
      {/if}
    </div>

    <!-- Metadatos -->
    <div class="bg-gray-50 rounded-lg p-6 mb-6 border border-gray-200">
      <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
          <p class="text-gray-600"><span class="font-medium">Versión:</span> {programa.version_programa}</p>
        </div>
        <div>
          <p class="text-gray-600"><span class="font-medium">Creado por:</span> {programa.creado_por}</p>
        </div>
        <div>
          <p class="text-gray-600">
            <span class="font-medium">Fecha de Creación:</span>
            {new Date(programa.fecha_creacion).toLocaleDateString('es-ES')}
          </p>
        </div>
      </div>
    </div>

    <!-- Panel de Acciones -->
    {#if programa.estado === 'PENDIENTE'}
      <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Acciones</h2>

        <div class="space-y-4">
          <Alert variant="default">
            <AlertCircle class="h-4 w-4" />
            <div>
              <p class="font-medium">Este programa está en revisión</p>
              <p class="text-sm">
                Puedes aprobarlo para que esté disponible para estudiantes, o rechazarlo para que el docente lo revise nuevamente.
              </p>
            </div>
          </Alert>

          {#if showRejectionReason}
            <div>
              <label for="reason" class="block text-sm font-medium text-gray-900 mb-2"> Motivo de rechazo (opcional) </label>
              <textarea
                id="reason"
                bind:value={rejectionReason}
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Proporciona retroalimentación al docente sobre qué necesita mejorarse..."
              />
            </div>
          {/if}

          <div class="flex gap-3 pt-4">
            <Button onclick={handleApprove} disabled={isApproving} class="flex-1 bg-green-600 hover:bg-green-700 text-white">
              {#if isApproving}
                Aprobando...
              {:else}
                Aprobar Programa
              {/if}
            </Button>
            <Button
              onclick={() => {
                showRejectionReason = !showRejectionReason;
                if (!showRejectionReason) rejectionReason = '';
              }}
              variant="outline"
              class="px-6"
            >
              {showRejectionReason ? 'Cancelar Rechazo' : 'Rechazar'}
            </Button>
            {#if showRejectionReason}
              <Button onclick={handleReject} disabled={isRejecting} class="flex-1 bg-red-600 hover:bg-red-700 text-white">
                {#if isRejecting}
                  Rechazando...
                {:else}
                  Confirmar Rechazo
                {/if}
              </Button>
            {/if}
          </div>
        </div>
      </div>
    {:else if programa.estado === 'RECHAZADO'}
      <Alert variant="destructive">
        <AlertCircle class="h-4 w-4" />
        <div>
          <p class="font-medium">Este programa ha sido rechazado</p>
          <p class="text-sm">El docente puede editarlo nuevamente y enviarlo para revisión.</p>
        </div>
      </Alert>
    {:else if programa.estado === 'APROBADO'}
      <Alert variant="default">
        <CheckCircle class="h-4 w-4" />
        <div>
          <p class="font-medium">Este programa ha sido aprobado</p>
          <p class="text-sm">Está disponible para que los estudiantes lo visualicen.</p>
        </div>
      </Alert>
    {/if}
  </div>
</div>

<style>
  :global(button) {
    transition: all 0.2s ease;
  }

  table {
    border-collapse: collapse;
  }

  tbody tr:last-child {
    border-bottom: none;
  }
</style>
