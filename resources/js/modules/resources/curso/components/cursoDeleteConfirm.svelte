<script lang="ts">
  /**
   * Componente: Diálogo de Confirmación de Eliminación de Curso
   *
   * Modal de confirmación antes de eliminar un curso.
   *
   * Props:
   * - isOpen: boolean = $bindable() - Estado del modal
   * - curso: Curso | null = $bindable() - Curso a eliminar
   * - isLoading: boolean - Si está procesando la eliminación
   * - onConfirm: () => void - Callback de confirmación
   * - onCancel: () => void - Callback de cancelación
   */
  import { AlertTriangle, X } from 'lucide-svelte';
  import type { Curso } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    curso?: Curso | null;
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    curso = $bindable<Curso | null>(null),
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  let isProcessing = $state(false);

  function handleConfirm() {
    isProcessing = true;
    try {
      onConfirm();
    } finally {
      isProcessing = false;
      isOpen = false;
    }
  }

  function handleCancel() {
    isOpen = false;
    onCancel();
  }
</script>

{#if isOpen && curso}
  <!-- Modal Overlay -->
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    onclick={(e) => e.target === e.currentTarget && handleCancel()}
    onkeydown={(e) => e.key === 'Escape' && handleCancel()}
    role="alertdialog"
    aria-modal="true"
    tabindex="-1"
  >
    <!-- Modal Content -->
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-red-50">
        <div class="flex items-center gap-3">
          <AlertTriangle size={20} class="text-red-600" />
          <h2 class="text-lg font-semibold text-gray-900">Eliminar Curso</h2>
        </div>
        <button
          onclick={handleCancel}
          class="p-1 text-gray-500 hover:text-gray-700 transition"
          title="Cerrar"
        >
          <X size={20} />
        </button>
      </div>

      <!-- Content -->
      <div class="p-6">
        <p class="text-gray-700 mb-2">¿Estás seguro de que deseas eliminar este curso?</p>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-4">
          <p class="text-sm text-gray-600">
            <span class="font-semibold text-gray-900">{curso.cod_curso}</span>
          </p>
          <p class="text-sm text-gray-600">
            {curso.asignatura_nombre}
          </p>
          {#if curso.carrera_nombre}
            <p class="text-sm text-gray-600">
              {curso.carrera_nombre}
            </p>
          {/if}
        </div>
        <p class="text-sm text-red-600 mt-4">Esta acción no se puede deshacer.</p>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
        <button
          type="button"
          onclick={handleCancel}
          disabled={isProcessing}
          class="px-4 py-2 text-gray-700 border border-gray-200 rounded-lg hover:bg-white transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Cancelar
        </button>
        <button
          type="button"
          onclick={handleConfirm}
          disabled={isProcessing}
          class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isProcessing ? 'Eliminando...' : 'Eliminar'}
        </button>
      </div>
    </div>
  </div>
{/if}
