<script lang="ts">
  /**
   * Componente: Modal de Formulario de Componente
   *
   * Modal para crear o editar un componente dentro de un curso.
   * Permite asignar tipo de componente y docente responsable.
   *
   * Props:
   * - isOpen: boolean = $bindable() - Estado del modal
   * - cursoId: number - ID del curso al que pertenece el componente
   * - editingComponente: Componente | null - Componente siendo editado (null = crear nuevo)
   * - tiposComponente: TipoComponente[] - Tipos de componente disponibles
   * - docentes: Docente[] - Docentes disponibles para asignar
   * - onSubmit: (data: ComponenteFormState) => void - Callback submit
   */
  import { X } from 'lucide-svelte';
  import type { Componente, TipoComponente, Docente } from '../types/curso.types';
  import type { ComponenteFormState } from '../types/curso.types';

  interface Props {
    isOpen?: boolean;
    cursoId?: number;
    editingComponente?: Componente | null;
    tiposComponente?: TipoComponente[];
    docentes?: Docente[];
    onSubmit?: (data: ComponenteFormState) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    cursoId = 0,
    editingComponente = $bindable<Componente | null>(null),
    tiposComponente = [],
    docentes = [],
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let formData = $state<ComponenteFormState>({
    id_tipo_componente: 0,
    id_docente: undefined,
  });

  let isSubmitting = $state(false);

  // Sincronizar con editingComponente
  $effect(() => {
    if (editingComponente) {
      formData = {
        id_tipo_componente: editingComponente.id_tipo_componente,
        // Tomar el primer docente asignado si existe (relación ahora es M-M via pivot)
        id_docente: editingComponente.docentes?.[0]?.id_docente,
      };
    } else {
      formData = {
        id_tipo_componente: 0,
        id_docente: undefined,
      };
    }
  });

  function handleSubmit(e: Event) {
    e.preventDefault();
    isSubmitting = true;
    try {
      onSubmit(formData);
      isOpen = false;
    } finally {
      isSubmitting = false;
    }
  }

  function handleClose() {
    isOpen = false;
    onClose();
  }
</script>

{#if isOpen}
  <!-- Modal Overlay -->
  <div
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    onclick={(e) => e.target === e.currentTarget && handleClose()}
    onkeydown={(e) => e.key === 'Escape' && handleClose()}
    role="dialog"
    aria-modal="true"
    tabindex="-1"
  >
    <!-- Modal Content -->
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">
          {editingComponente ? 'Editar Componente' : 'Crear Componente'}
        </h2>
        <button
          onclick={handleClose}
          class="p-1 text-gray-500 hover:text-gray-700 transition"
          title="Cerrar"
        >
          <X size={20} />
        </button>
      </div>

      <!-- Form -->
      <form onsubmit={handleSubmit} class="p-6 space-y-4">
        <!-- Tipo Componente -->
        <div>
          <label for="id_tipo_componente" class="block text-sm font-medium text-gray-700 mb-1">
            Tipo de Componente *
          </label>
          <select
            id="id_tipo_componente"
            bind:value={formData.id_tipo_componente}
            required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={0}>Seleccionar tipo</option>
            {#each tiposComponente as tipo}
              <option value={tipo.id_tipo_componente}>
                {tipo.tipo}
              </option>
            {/each}
          </select>
        </div>

        <!-- Docente -->
        <div>
          <label for="id_docente" class="block text-sm font-medium text-gray-700 mb-1">
            Docente Responsable
          </label>
          <select
            id="id_docente"
            bind:value={formData.id_docente}
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={undefined}>Sin asignar</option>
            {#each docentes as doc}
              <option value={doc.id_docente}>
                {doc.nombre_completo || `${doc.nombre1} ${doc.apellido1}`}
              </option>
            {/each}
          </select>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3 pt-4">
          <button
            type="button"
            onclick={handleClose}
            class="px-4 py-2 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition font-medium"
          >
            Cancelar
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? 'Guardando...' : editingComponente ? 'Actualizar' : 'Crear'}
          </button>
        </div>
      </form>
    </div>
  </div>
{/if}
