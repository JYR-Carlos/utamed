<script lang="ts">
  /**
   * EnunciadoModal — subir o reemplazar el archivo de enunciado de una actividad.
   *
   * Rescatado de `modules/resources/actividad/components/actividadList.svelte`
   * al rediseñar la vista de actividades: la lista pasó a ser una tabla densa
   * (lámina «a») y el modal necesitaba vivir por su cuenta. La validación de
   * extensión y tamaño se hace en cliente contra las reglas que envía el
   * servidor (`config/filetypes.php`), y el POST real lo hace
   * `subirEnunciadoActividad`.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { FileText, Upload, X } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { formatBytes } from '@/utils/formatters';
  // El barrel del módulo no reexporta esta función; se importa del service.
  import { subirEnunciadoActividad } from '@/modules/resources/actividad/services/actividadApi';

  interface ReglasEnunciado {
    extensiones_pdf: string[];
    extensiones_img: string[];
    max_mb_pdf: number;
    max_mb_imagen: number;
  }

  interface Props {
    isOpen?: boolean;
    idCurso: number;
    actividad?: Actividad | null;
    reglas: ReglasEnunciado;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    idCurso,
    actividad = null,
    reglas,
    onClose = () => {},
  }: Props = $props();

  let archivo = $state<File | null>(null);
  let error = $state('');
  let subiendo = $state(false);
  let inputEl: HTMLInputElement | undefined = $state();

  /** Al cerrarse se limpia para que reabrir no herede la selección anterior. */
  $effect(() => {
    if (!isOpen) {
      archivo = null;
      error = '';
      subiendo = false;
    }
  });

  function esValido(file: File): boolean {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    const esPdf = reglas.extensiones_pdf.includes(extension);
    const esImagen = reglas.extensiones_img.includes(extension);

    if (!esPdf && !esImagen) {
      error = `Formato no permitido. Se aceptan PDF (${reglas.extensiones_pdf.join(', ')}) e imágenes (${reglas.extensiones_img.join(', ')}).`;
      return false;
    }

    const maxMb = esPdf ? reglas.max_mb_pdf : reglas.max_mb_imagen;
    if (file.size > maxMb * 1024 * 1024) {
      error = `El archivo supera el límite de ${maxMb} MB.`;
      return false;
    }

    if (file.size === 0) {
      error = 'El archivo está vacío.';
      return false;
    }

    error = '';
    return true;
  }

  function seleccionar(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    archivo = esValido(file) ? file : null;
  }

  function quitar() {
    archivo = null;
    if (inputEl) inputEl.value = '';
  }

  function enviar() {
    if (!actividad || !archivo) return;

    subiendo = true;
    error = '';

    subirEnunciadoActividad(idCurso, actividad.id_actividad, archivo, {
      onSuccess: () => {
        subiendo = false;
        onClose();
      },
      onError: (errores) => {
        error = errores.archivo ?? 'No se pudo subir el enunciado.';
        subiendo = false;
      },
    });
  }
</script>

{#if isOpen && actividad}
  <FormModal
    bind:isOpen
    title={actividad.archivo_enunciado ? 'Reemplazar enunciado' : 'Adjuntar enunciado'}
    submitLabel={subiendo ? 'Subiendo…' : 'Guardar'}
    isLoading={subiendo}
    submitDisabled={!archivo || subiendo}
    {onClose}
    onSubmit={enviar}
  >
    <div class="flex flex-col gap-4">
      <p class="m-0 text-[13px] text-ink-2">
        Archivo descriptivo de <strong class="font-semibold text-ink">{actividad.nombre}</strong
        >. Los alumnos lo descargan desde la actividad.
      </p>

      {#if actividad.archivo_enunciado}
        <div
          class="flex items-center gap-2.5 rounded-[10px] border border-gray-200 bg-sand px-3 py-2.5 text-[12.5px] text-ink-2"
        >
          <FileText size={15} class="shrink-0" aria-hidden="true" />
          <span class="min-w-0 truncate">
            Actual: {actividad.archivo_enunciado.nombre_original}
          </span>
          {#if actividad.archivo_enunciado.peso_bytes}
            <span class="ml-auto shrink-0 font-mono"
              >{formatBytes(actividad.archivo_enunciado.peso_bytes)}</span
            >
          {/if}
        </div>
      {/if}

      {#if !archivo}
        <label
          class="flex cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-uta-blue-light px-4 py-8 transition-colors hover:bg-slate-200"
        >
          <div class="flex flex-col items-center gap-2 text-center">
            <Upload size={28} class="text-navy" aria-hidden="true" />
            <span class="text-[13.5px] font-semibold text-ink">
              Arrastra el archivo o haz clic para seleccionarlo
            </span>
            <span class="text-[12px] text-ink-2">
              PDF hasta {reglas.max_mb_pdf} MB · imágenes hasta {reglas.max_mb_imagen} MB
            </span>
          </div>
          <input
            type="file"
            class="hidden"
            accept=".pdf,image/*"
            onchange={seleccionar}
            bind:this={inputEl}
            disabled={subiendo}
          />
        </label>
      {:else}
        <div
          class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5"
        >
          <div class="flex min-w-0 items-center gap-2.5">
            <FileText size={18} class="shrink-0 text-emerald-700" aria-hidden="true" />
            <div class="min-w-0">
              <p class="m-0 truncate text-[13px] font-semibold text-ink">{archivo.name}</p>
              <p class="m-0 font-mono text-[12px] text-ink-2">{formatBytes(archivo.size)}</p>
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg p-2 text-red-700 transition-colors hover:bg-red-50"
            onclick={quitar}
            disabled={subiendo}
            aria-label="Quitar el archivo seleccionado"
          >
            <X size={16} aria-hidden="true" />
          </button>
        </div>
      {/if}

      {#if error}
        <p
          class="m-0 rounded-[10px] border border-red-200 bg-red-50 px-3 py-2.5 text-[13px] text-red-700"
        >
          {error}
        </p>
      {/if}
    </div>
  </FormModal>
{/if}
