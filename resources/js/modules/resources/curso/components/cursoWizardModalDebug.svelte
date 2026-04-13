<script lang="ts">
  /**
   * Debug version of CursoWizardModal
   * Simplified to test basic functionality
   */
  import type { Carrera, CursoFormData, TipoComponente } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    carreras: Carrera[];
    tiposComponente: TipoComponente[];
    isLoading: boolean;
    onClose: () => void;
    onSubmit: (data: CursoFormData & { id_docente_sugerido?: number }) => void;
  }

  let { isOpen, carreras, tiposComponente, isLoading, onClose, onSubmit }: Props = $props();

  let currentStep = $state(1);
  let selectedCarrera = $state<Carrera | null>(null);

  const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') onClose();
  };
</script>

<svelte:window onkeydown={handleKeydown} />

{#if isOpen}
  <!-- Backdrop -->
  <div
    class="fixed inset-0 bg-black/30 z-40 backdrop-blur-sm"
    onclick={onClose}
    role="presentation"
  ></div>

  <!-- Dialog -->
  <div
    class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50 bg-white rounded-lg shadow-2xl w-[95vw] max-w-2xl max-h-[95vh] overflow-hidden flex flex-col"
    role="dialog"
    aria-modal="true"
    aria-label="Nuevo Curso"
  >
    <!-- Header -->
    <div class="flex justify-between items-center p-6 border-b border-slate-200 flex-shrink-0">
      <div>
        <h2 class="text-xl font-bold text-slate-900">Nuevo Curso Ofertado</h2>
        <p class="text-sm text-slate-500 mt-1">
          Step {currentStep}/4: {currentStep === 1
            ? 'Carrera'
            : currentStep === 2
              ? 'Plan'
              : currentStep === 3
                ? 'Asignatura'
                : 'Docente'}
        </p>
      </div>
      <button
        onclick={onClose}
        class="p-2 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-slate-700"
        aria-label="Cerrar"
      >
        ✕
      </button>
    </div>

    <!-- Body -->
    <div class="flex-1 overflow-y-auto p-6">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <p class="text-sm text-blue-900">
          <strong>Debug Info:</strong><br />
          isOpen: {isOpen}<br />
          carreras.length: {carreras.length}<br />
          currentStep: {currentStep}
        </p>
      </div>

      {#if currentStep === 1}
        <div>
          <h3 class="font-semibold text-lg mb-4">Selecciona una Carrera</h3>
          {#if carreras.length === 0}
            <div class="p-4 bg-red-50 border border-red-200 rounded text-red-700">
              ⚠️ No hay carreras disponibles
            </div>
          {:else}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              {#each carreras as carrera (carrera.id_carrera)}
                <button
                  onclick={() => {
                    selectedCarrera = carrera;
                    currentStep = 2;
                  }}
                  class="p-4 border-2 border-slate-200 rounded-lg hover:border-blue-400 hover:bg-blue-50 text-left transition-all"
                >
                  <div class="font-semibold text-slate-900">{carrera.nombre}</div>
                  <div class="text-sm text-slate-500 mt-1">{carrera.sede} · {carrera.jornada}</div>
                </button>
              {/each}
            </div>
          {/if}
        </div>
      {/if}

      {#if currentStep === 2}
        <div>
          <button
            onclick={() => (currentStep = 1)}
            class="text-blue-600 hover:underline text-sm mb-4">← Volver</button
          >
          <h3 class="font-semibold text-lg mb-4">Plan seleccionado: {selectedCarrera?.nombre}</h3>
          <p class="text-slate-600">Paso 2 (próximamente: seleccionar plan)</p>
        </div>
      {/if}
    </div>

    <!-- Footer -->
    <div class="flex justify-end gap-3 p-6 border-t border-slate-200 flex-shrink-0 bg-slate-50">
      <button
        onclick={onClose}
        class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-100 transition-colors"
      >
        Cancelar
      </button>
      <button
        disabled={isLoading}
        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
      >
        {isLoading ? 'Guardando...' : 'Continuar'}
      </button>
    </div>
  </div>
{/if}

<style>
  :global(body) {
    overflow: hidden;
  }
</style>
