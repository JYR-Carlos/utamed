<script lang="ts">
  import { Upload, X } from 'lucide-svelte';

  interface Props {
    onCerrar: () => void;
    onEntregaEnviada: (data: { tipo: string; mensaje: string; archivo?: File }) => void;
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    entrega_obligatoria: boolean;
  }

  let {
    onCerrar,
    onEntregaEnviada,
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    entrega_obligatoria,
  }: Props = $props();

  let descripcion = $state('');
  let archivo: File | undefined = $state();
  let inputFile: HTMLInputElement | undefined = $state();
  let enviando = $state(false);
  let error = $state('');

  function manejarSeleccionArchivo(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      archivo = input.files[0];
      error = '';
    }
  }

  function removerArchivo() {
    archivo = undefined;
    if (inputFile) {
      inputFile.value = '';
    }
  }

  function formatearTamano(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const tamaños = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + tamaños[i];
  }

  function manejarEnvio() {
    error = '';

    if (entrega_obligatoria && !archivo) {
      error = 'Debes seleccionar un archivo para entregar.';
      return;
    }

    if (!archivo) {
      error = 'Selecciona un archivo para continuar.';
      return;
    }

    if (descripcion.trim().length === 0) {
      error = 'Agrega una descripción de tu entrega.';
      return;
    }

    enviando = true;

    // Simular envío
    setTimeout(() => {
      const tipo = "Entrega de Avance" // CAMBIAR LUEGO A LOS ENUMERABLES SEGÚN EL TIPO DE ARCHIVO SUBIDO
      onEntregaEnviada({
        archivo,
        tipo,
        mensaje: descripcion.trim(),
      });

      descripcion = '';
      
      if (inputFile) {
        inputFile.value = '';
      }
      enviando = false;
    }, 500);
  }
</script>

<div class="w-full sm:w-[90%] max-w-2xl flex rounded-4xl bg-white shadow-2xl overflow-hidden flex-col">
  <!-- Header -->
  <div class="flex justify-between items-center px-6 sm:px-8 py-6 border-b border-gray-100 shrink-0">
    <div>
      <p class="text-xl sm:text-2xl font-bold text-primary">Agregar Entrega</p>
      <p class="text-xs sm:text-sm text-gray-500 mt-1">
        {cod_curso} - {nombre_actividad}
      </p>
    </div>

    <button
      class="p-2 hover:bg-gray-100 rounded-full transition-colors shrink-0"
      onclick={onCerrar}
      aria-label="cerrar"
      disabled={enviando}
    >
      <X class="w-5 h-5 sm:w-6 sm:h-6" />
    </button>
  </div>

  <!-- Contenido -->
  <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
    <!-- Selector de Archivo -->
    <div class="space-y-3">
      <p class="block text-sm font-semibold text-gray-700">
        Selecciona tu archivo
        {#if entrega_obligatoria}
          <span class="text-red-500">*</span>
        {/if}
      </p>

      {#if !archivo}
        <label
          class="flex items-center justify-center w-full px-4 py-8 border-2 border-dashed border-primary/30 rounded-2xl bg-primary/5 hover:bg-primary/10 cursor-pointer transition-colors group"
        >
          <div class="flex flex-col items-center gap-2 text-center">
            <Upload class="w-8 h-8 text-primary/60 group-hover:text-primary transition-colors" />
            <p class="text-sm font-semibold text-gray-700">
              Arrastra tu archivo aquí o haz clic para seleccionar
            </p>
            <p class="text-xs text-gray-500">
              Formatos permitidos: PDF, DOC, DOCX, TXT (máx. 25MB)
            </p>
          </div>
          <input
            type="file"
            class="hidden"
            accept=".pdf,.doc,.docx,.txt"
            onchange={manejarSeleccionArchivo}
            bind:this={inputFile}
            disabled={enviando}
          />
        </label>
      {:else}
        <div class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-2xl">
          <div class="flex items-center gap-3 min-w-0">
            <div class="flex-shrink-0">
              <svg
                class="w-5 h-5 text-green-600"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"
                />
                <path
                  fill-rule="evenodd"
                  d="M3 4a2 2 0 00-2 2v4a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2H3zm0 2h10v4H3V6z"
                />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-900 truncate">{archivo.name}</p>
              <p class="text-xs text-gray-500">{formatearTamano(archivo.size)}</p>
            </div>
          </div>
          <button
            class="flex-shrink-0 p-2 hover:bg-red-100 rounded-lg transition-colors text-red-600"
            onclick={removerArchivo}
            disabled={enviando}
            aria-label="remover archivo"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
      {/if}
    </div>

    <!-- Descripción -->
    <div class="space-y-3">
      <p class="block text-sm font-semibold text-gray-700">
        Descripción de tu entrega
        <span class="text-red-500">*</span>
      </p>
      <!-- svelte-ignore element_invalid_self_closing_tag -->
      <textarea
        class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary/50 resize-none"
        placeholder="Describe qué incluye tu entrega, cambios realizados, comentarios importantes, etc."
        rows="4"
        bind:value={descripcion}
        disabled={enviando}
      />
      <p class="text-xs text-gray-500">
        {descripcion.length} / 500 caracteres
      </p>
    </div>

    <!-- Mensaje de Error -->
    {#if error}
      <div class="p-3 bg-red-50 border border-red-200 rounded-xl flex items-start gap-2">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"
          />
        </svg>
        <p class="text-sm text-red-700">{error}</p>
      </div>
    {/if}
  </div>

  <!-- Footer -->
  <div class="flex justify-end gap-3 px-6 sm:px-8 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
    <button
      class="px-6 py-2 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 transition-colors font-semibold text-sm disabled:opacity-50"
      onclick={onCerrar}
      disabled={enviando}
    >
      Cancelar
    </button>
    <button
      class="px-6 py-2 rounded-lg bg-primary text-secondary hover:bg-primary/90 transition-colors font-semibold text-sm disabled:opacity-50"
      onclick={manejarEnvio}
      disabled={enviando || !archivo || descripcion.trim().length === 0}
    >
      {enviando ? 'Enviando...' : 'Enviar Entrega'}
    </button>
  </div>
</div>
