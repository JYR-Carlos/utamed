<script lang="ts">
  import Toast from '@/components/admin/Toast.svelte';
  import { router } from '@inertiajs/svelte'
  import { Bug, Loader2, MessageCircleWarning, Trash2, Upload, X } from 'lucide-svelte';
  import { cn } from '@/lib/utils';

  interface Props {
    /** Clases para el botón disparador; pisan los colores por defecto (p. ej. la barra noche del alumno). */
    class?: string;
    /** Rótulo del botón: el docente lo ve como «Sugerencias»; los demás, «Reportar error». */
    etiqueta?: string;
  }

  let { class: claseBoton = '', etiqueta = 'Reportar error' }: Props = $props();
  const esSugerencia = $derived(etiqueta === 'Sugerencias');

  let isOpen = $state(false);
  let isUploading = $state(false);
  let arrastrando = $state(false);
  let toast = $state<{ type: 'success' | 'error'; message: string } | null>(null);
  let file = $state<File | null>(null);

  let formData = $state({
    title: '',
    description: '',
  });

  const MAX_FILE_SIZE = 10 * 1024 * 1024;
  const campoBase =
    'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-slate-400/70 dark:bg-slate-100 dark:text-slate-900 dark:placeholder-slate-500 dark:focus:border-blue-400 dark:focus:ring-blue-500/30';

  function closeModal() {
    if (isUploading) return;
    isOpen = false;
  }

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && isOpen) closeModal();
  }

  /** Valida y adjunta la captura, venga del selector, de arrastrarla o de pegarla. */
  function adjuntar(seleccionada: File | null | undefined): boolean {
    if (!seleccionada) return false;
    if (!seleccionada.type.startsWith('image/')) {
      toast = { type: 'error', message: 'Solo se permite subir una imagen (PNG o JPG).' };
      return false;
    }
    if (seleccionada.size > MAX_FILE_SIZE) {
      toast = { type: 'error', message: 'La imagen debe pesar como máximo 10 MB.' };
      return false;
    }
    file = seleccionada;
    return true;
  }

  function handleFile(event: Event) {
    const input = event.currentTarget as HTMLInputElement;
    if (!adjuntar(input.files?.[0])) input.value = '';
  }

  function soltar(event: DragEvent) {
    event.preventDefault();
    arrastrando = false;
    adjuntar(event.dataTransfer?.files?.[0]);
  }

  // Con el modal abierto, una imagen pegada (Impr Pant, Win+Shift+S) queda como
  // captura aunque el cursor esté en un campo de texto; el texto pegado sigue igual.
  function pegar(event: ClipboardEvent) {
    if (!isOpen || isUploading) return;
    const imagen = Array.from(event.clipboardData?.files ?? []).find((f) => f.type.startsWith('image/'));
    if (!imagen) return;
    event.preventDefault();
    adjuntar(imagen);
  }

  function removeFile() {
    file = null;
  }

  function resetForm() {
    formData = { title: '', description: '' };
    file = null;
  }

  function enviar(event: SubmitEvent) {
    event.preventDefault();
    if (isUploading) return;

    if (!formData.title.trim() || !formData.description.trim()) {
      toast = { type: 'error', message: 'Completa el título y la descripción.' };
      return;
    }

    router.post('/reportes', {
      title: formData.title.trim(),
      description: formData.description.trim(),
      image: file,
    }, {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onStart: () => {
        isUploading = true;
      },
      onSuccess: () => {
        resetForm();
        isOpen = false;
        toast = {
          type: 'success',
          message: esSugerencia
            ? 'Recibimos tu sugerencia. Gracias por ayudarnos a mejorar.'
            : 'Recibimos tu reporte. Gracias por avisarnos.',
        };
      },
      onError: (errors) => {
        const firstErrorMessage = Object.values(errors)[0] as string;
        toast = {
          type: 'error',
          message: firstErrorMessage || 'Error al validar el reporte.',
        };
      },
      onFinish: () => {
        isUploading = false;
      },
    });
  }
</script>

<svelte:window onkeydown={handleKeydown} onpaste={pegar} />


<!-- Botón disparador -->
<button
  type="button"
  onclick={() => (isOpen = true)}
  class={cn(
    'group flex min-h-11 cursor-pointer items-center justify-center gap-0 sm:gap-2 rounded-lg border border-[#7B8AA0] bg-white px-4 text-sm font-semibold text-uta-blue max-sm:size-11 max-sm:px-0 transition-colors duration-200 ease-out hover:bg-slate-200',
    claseBoton,
  )}
  title={etiqueta}
  aria-label={etiqueta}
>
  {#if esSugerencia}
    <MessageCircleWarning size={16} class="shrink-0" />
  {:else}
    <Bug size={16} class="shrink-0" />
  {/if}

  <!-- En móvil sólo el ícono: con el rótulo la ruta de navegación casi desaparece. -->
  <span class="hidden whitespace-nowrap sm:inline">{etiqueta}</span>
</button>


{#if toast}
  <Toast
    type={toast.type}
    message={toast.message}
    onClose={() => (toast = null)}
  />
{/if}

<!-- Modal de reporte -->
{#if isOpen}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="bug-modal-title"
      class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white text-slate-900 shadow-2xl"
    >
      <header class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-purple-100 text-purple-600">
            <Bug size={18} />
          </div>
          <div>
            <h2 id="bug-modal-title" class="text-base font-semibold text-slate-900">{esSugerencia ? 'Enviar una sugerencia' : 'Reportar un problema'}</h2>
            <p class="text-xs text-slate-500">{esSugerencia ? 'Cuéntanos qué mejorarías o qué falló en la plataforma' : 'Notifica un error o fallo en la plataforma'}</p>
          </div>
        </div>

        <button
          type="button"
          onclick={closeModal}
          disabled={isUploading}
          aria-label="Cerrar modal"
          class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 disabled:opacity-50"
        >
          <X size={18} />
        </button>
      </header>

      <form onsubmit={enviar} class="flex min-h-0 flex-1 flex-col">
        <div class="space-y-4 overflow-y-auto px-6 py-4 text-left">
          <p class="text-sm leading-relaxed text-slate-700">
            Gracias por ayudarnos a mejorar <span class="font-semibold">UTAmed</span>. El equipo de desarrollo
            lee cada {esSugerencia ? 'mensaje' : 'reporte'} y lo usa para corregir la plataforma.
          </p>
          <div>
            <label for="bug-title" class="mb-1 block text-xs font-semibold text-slate-700">
              Título <span class="text-red-500">*</span>
            </label>
            <input
              id="bug-title"
              type="text"
              bind:value={formData.title}
              maxlength="120"
              required
              placeholder="Ej. Error al guardar asistencia"
              class="{campoBase} text-sm"
            />
          </div>

          <div>
            <label for="bug-description" class="mb-1 block text-xs font-semibold text-slate-700">
              Descripción <span class="text-red-500">*</span>
            </label>
            <textarea
              id="bug-description"
              bind:value={formData.description}
              required
              rows="3"
              maxlength="2000"
              placeholder="¿En qué página estabas, qué hiciste y qué pasó?"
              class="{campoBase} resize-y text-sm"
            ></textarea>
          </div>

          <div>
            <span class="mb-1 block text-xs font-semibold text-slate-700">Captura de pantalla (opcional)</span>
            {#if !file}
              <label
                ondragover={(e) => {
                  e.preventDefault();
                  arrastrando = true;
                }}
                ondragleave={() => (arrastrando = false)}
                ondrop={soltar}
                class={cn(
                  'flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50/50 px-4 py-3 text-xs text-slate-500 transition hover:border-blue-400 hover:bg-blue-50/30 dark:border-slate-400/70 dark:bg-slate-100/70 dark:text-slate-700 dark:hover:border-blue-400 dark:hover:bg-slate-100',
                  arrastrando && 'border-blue-500 bg-blue-50 dark:bg-slate-100',
                )}
              >
                <Upload size={16} class="shrink-0 text-slate-400" />
                <span>
                  Arrástrala aquí, pégala con <kbd class="font-sans font-semibold">Ctrl+V</kbd> o haz clic para elegirla
                  <span class="text-slate-400">(máx. 10 MB)</span>
                </span>
                <input type="file" accept="image/*" onchange={handleFile} class="sr-only" />
              </label>
            {:else}
              <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-400/70 dark:bg-slate-100">
                <span class="truncate font-medium text-slate-700">
                  {file.name}
                  <span class="font-normal text-slate-400">({(file.size / 1024).toFixed(0)} KB)</span>
                </span>
                <button
                  type="button"
                  onclick={removeFile}
                  class="flex items-center gap-1 text-slate-400 transition hover:text-red-600"
                >
                  <Trash2 size={14} />
                  <span>Quitar</span>
                </button>
              </div>
            {/if}
          </div>

          <p class="text-xs leading-relaxed text-slate-500">
            Envía sólo problemas reales: un reporte falso o con contenido inapropiado puede recibir una advertencia.
          </p>
        </div>

        <footer class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50/50 px-6 py-3.5">
          <button
            type="button"
            onclick={closeModal}
            disabled={isUploading}
            class="cursor-pointer rounded-lg px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-200/60 disabled:opacity-50"
          >
            Cancelar
          </button>

          <button
            type="submit"
            disabled={isUploading}
            class="cursor-pointer flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-50"
          >
            {#if isUploading}
              <Loader2 size={14} class="animate-spin" />
              <span>Enviando…</span>
            {:else}
              <span>{esSugerencia ? 'Enviar sugerencia' : 'Enviar reporte'}</span>
            {/if}
          </button>
        </footer>
      </form>
    </div>
  </div>
{/if}
