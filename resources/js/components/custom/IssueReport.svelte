<script lang="ts">
  import Toast from '@/components/admin/Toast.svelte';
  import { router } from '@inertiajs/svelte'
  import { Bug, Flag, Loader2, MessageCircleWarning, Trash2, Upload, X } from 'lucide-svelte';
  import { cn } from '@/lib/utils';

  interface Props {
    /** Clases para el botón disparador; pisan los colores por defecto (p. ej. la barra noche del alumno). */
    class?: string;
  }

  let { class: claseBoton = '' }: Props = $props();

  let isOpen = $state(false);
  let showConfirmModal = $state(false);
  let isUploading = $state(false);
  let toast = $state<{ type: 'success' | 'error'; message: string } | null>(null);
  let file = $state<File | null>(null);

  let formData = $state({
    title: '',
    description: '',
    expected: '',
    actual: '',
  });

  const MAX_FILE_SIZE = 10 * 1024 * 1024;

  function closeModal() {
    if (isUploading) return;
    isOpen = false;
    showConfirmModal = false;
  }

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
      if (showConfirmModal) {
        showConfirmModal = false;
      } else if (isOpen) {
        closeModal();
      }
    }
  }

  function handleFile(event: Event) {
    const input = event.currentTarget as HTMLInputElement;
    const selected = input.files?.[0];

    if (!selected) return;
    if (!selected.type.startsWith('image/')) {
      toast = { type: 'error', message: 'Solo se permite subir una imagen (PNG o JPG).' };
      input.value = '';
      return;
    }
    if (selected.size > MAX_FILE_SIZE) {
      toast = { type: 'error', message: 'La imagen debe pesar como máximo 10 MB.' };
      input.value = '';
      return;
    }
    file = selected;
  }

  function removeFile() {
    file = null;
  }

  function resetForm() {
    formData = { title: '', description: '', expected: '', actual: '' };
    file = null;
  }

  function handlePreSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (isUploading) return;

    if (!formData.title.trim() || !formData.description.trim()) {
      toast = { type: 'error', message: 'Completa el título y la descripción del error.' };
      return;
    }

    showConfirmModal = true;
  }

  function handleConfirmSubmit() {
    if (isUploading) return;

    router.post('/reportes', {
      title: formData.title.trim(),
      description: formData.description.trim(),
      expected: formData.expected.trim(),
      actual: formData.actual.trim(),
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
        showConfirmModal = false;
        isOpen = false;
        toast = { type: 'success', message: 'Reporte enviado correctamente.' };
      },
      onError: (errors) => {
        showConfirmModal = false;
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

<svelte:window onkeydown={handleKeydown} />


<!-- Botón disparador -->
<button
  type="button"
  onclick={() => (isOpen = true)}
  class={cn(
    'group flex min-h-11 cursor-pointer items-center justify-center gap-0 sm:gap-2 rounded-lg border border-[#7B8AA0] bg-white px-4 text-sm font-semibold text-uta-blue max-sm:size-11 max-sm:px-0 transition-colors duration-200 ease-out hover:bg-slate-200',
    claseBoton,
  )}
  title="Reportar error"
  aria-label="Reportar error"
>
  <Bug
    size={16}
    class="shrink-0"
  />

  <!-- En móvil sólo el ícono: con el rótulo la ruta de navegación casi desaparece. -->
  <span class="hidden whitespace-nowrap sm:inline">Reportar error</span>
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
      class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-2xl"
    >
      <header class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-purple-100 text-purple-600">
            <Bug size={18} />
          </div>
          <div>
            <h2 id="bug-modal-title" class="text-base font-semibold text-slate-900">Reportar un problema</h2>
            <p class="text-xs text-slate-500">Notifica un error o fallo en la plataforma</p>
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

      <form onsubmit={handlePreSubmit} class="flex min-h-0 flex-1 flex-col">
        <div class="space-y-4 overflow-y-auto px-6 py-4 text-left">
          <div class="bg-amber-100 border-2 text-shadow-amber-800 border-amber-200 p-4 gap-5 flex flex-col rounded-3xl text-justify font-semibold">
            <p class="text-sm">Tu reporte será revisado por el equipo de desarrollo de UTAmed. Por favor, envía únicamente incidencias reales y comprobables.  </p>
            <p class="text-sm">El spam o envío masivo de reportes falsos o inapropiados resultará en el bloqueo de tu cuenta o IP y será notificado.</p>
          </div>
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
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
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
              placeholder="Describe qué ocurrió y los pasos para reproducir el fallo..."
              class="w-full resize-y rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
            ></textarea>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label for="bug-expected" class="mb-1 block text-xs font-semibold text-slate-700">
                Resultado esperado
              </label>
              <textarea
                id="bug-expected"
                bind:value={formData.expected}
                rows="2"
                placeholder="¿Qué debería suceder?"
                class="w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              ></textarea>
            </div>

            <div>
              <label for="bug-actual" class="mb-1 block text-xs font-semibold text-slate-700">
                Resultado actual
              </label>
              <textarea
                id="bug-actual"
                bind:value={formData.actual}
                rows="2"
                placeholder="¿Qué sucede realmente?"
                class="w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              ></textarea>
            </div>
          </div>

          <div>
            <span class="mb-1 block text-xs font-semibold text-slate-700">Captura de pantalla (opcional)</span>
            {#if !file}
              <label
                class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50/50 px-4 py-3 text-xs text-slate-500 transition hover:border-blue-400 hover:bg-blue-50/30"
              >
                <Upload size={16} class="text-slate-400" />
                <span>Haz clic para adjuntar imagen <span class="text-slate-400">(máx. 10 MB)</span></span>
                <input type="file" accept="image/*" onchange={handleFile} class="sr-only" />
              </label>
            {:else}
              <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
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
            Continuar
          </button>
        </footer>
      </form>
    </div>
  </div>
{/if}

<!-- Modal de confirmación -->
{#if showConfirmModal}
  <div class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs">
    <div
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="confirm-title"
      aria-describedby="confirm-desc"
      class="w-full max-w-md rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl"
    >
      <div class="mb-4 flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
        <MessageCircleWarning size={20} />
      </div>

      <h3 id="confirm-title" class="text-base font-bold text-slate-900">
        Confirmar envío de reporte
      </h3>

      <p id="confirm-desc" class="mt-2 text-xs leading-relaxed text-slate-600">
        Recuerda que tu reporte será revisado por los administradores de UTAMED. Cualquier contenido inapropiado o no válido será notificado.
      </p>

      <div class="mt-6 flex justify-end gap-2.5">
        <button
          type="button"
          onclick={() => (showConfirmModal = false)}
          disabled={isUploading}
          class="cursor-pointer rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
        >
          Volver
        </button>

        <button
          type="button"
          onclick={handleConfirmSubmit}
          disabled={isUploading}
          class="cursor-pointer flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-50"
        >
          {#if isUploading}
            <Loader2 size={14} class="animate-spin" />
            <span>Enviando...</span>
          {:else}
            <span>Confirmar y enviar</span>
          {/if}
        </button>
      </div>
    </div>
  </div>
{/if}
