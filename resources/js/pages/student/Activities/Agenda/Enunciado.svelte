<script lang="ts">
  interface Props {
    onCerrar: () => void;
    url_archivo?: string;
    nombre_archivo?: string;
    tipo_archivo?: 'pdf' | 'word' | 'excel' | 'otro';
  }

  let {
    onCerrar,
    url_archivo = '',
    nombre_archivo = 'Enunciado de la Actividad',
    tipo_archivo = 'pdf',
  }: Props = $props();

  // Helper para saber si el navegador lo puede renderizar nativamente (ej. PDFs)
  let esPrevisualizable = $derived(tipo_archivo === 'pdf');
</script>

<div class="flex flex-col gap-4 w-full max-w-5xl bg-white rounded-4xl shadow-xl overflow-hidden">
  <div class="flex justify-between items-center p-6 border-b border-gray-100">
    <h2 class="text-base sm:text-lg font-semibold text-primary">
      {nombre_archivo}
    </h2>
    <button
      class="p-2 hover:bg-gray-200 rounded-full transition-colors flex items-center justify-center gap-2 group"
      onclick={onCerrar}
      aria-label="cerrar"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        class="w-6 h-6 text-gray-600 group-hover:text-primary transition-colors"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
      </svg>
    </button>
  </div>

  <div class="p-6 bg-gray-50 min-h-[60vh] flex flex-col items-center justify-center rounded-b-4xl">
    {#if !url_archivo}
      <div class="text-center text-gray-500 flex flex-col items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p>El enlace del archivo no está disponible.</p>
      </div>
    {:else if esPrevisualizable}
      <iframe
        src={url_archivo}
        title={nombre_archivo}
        class="w-full h-[65vh] rounded-2xl border-2 border-gray-200 shadow-sm"
      ></iframe>
    {:else}
      <div class="flex flex-col items-center gap-6 text-center max-w-md">
        <div class="w-24 h-24 bg-secondary rounded-full border-2 border-primary/10 flex items-center justify-center text-primary shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
        </div>
        
        <div>
          <h3 class="text-xl font-semibold text-primary mb-2">
            Documento de tipo {tipo_archivo.toUpperCase()}
          </h3>
          <p class="text-sm text-gray-600 mb-8">
            Este tipo de archivo no puede ser previsualizado directamente en el navegador. Por favor, haz clic en el botón inferior para descargarlo y verlo en tu equipo.
          </p>
          
          <a
            href={url_archivo}
            download
            target="_blank"
            rel="noopener noreferrer"
            class="px-6 sm:px-8 py-3 sm:py-4 rounded-lg border transition-all bg-primary text-secondary hover:bg-secondary hover:text-primary flex items-center justify-center gap-3 text-sm sm:text-base font-semibold w-max mx-auto shadow-md"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Descargar Archivo
          </a>
        </div>
      </div>
    {/if}
  </div>
</div>