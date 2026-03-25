<script lang="ts">
  /**
   * Componente: Modal de Formulario de Sección
   *
   * Modal para crear o editar una sección dentro de un curso.
   * Permite asignar tipo de sección y docente responsable.
   *
   * Props:
   * - isOpen: boolean = $bindable() - Estado del modal
   * - cursoId: number - ID del curso al que pertenece la sección
   * - editingSeccion: Seccion | null - Sección siendo editada (null = crear nuevo)
   * - tiposSeccion: TipoSeccion[] - Tipos de sección disponibles
   * - docentes: Docente[] - Docentes disponibles para asignar
   * - onSubmit: (data: SeccionFormState) => void - Callback submit
   */
  import { X } from 'lucide-svelte';
  import type { Seccion, TipoSeccion, Docente } from '../types/curso.types';
  import type { SeccionFormState } from '../types/curso.types';

  interface Props {
    isOpen?: boolean;
    cursoId?: number;
    editingSeccion?: Seccion | null;
    tiposSeccion?: TipoSeccion[];
    docentes?: Docente[];
    onSubmit?: (data: SeccionFormState) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    cursoId = 0,
    editingSeccion = $bindable<Seccion | null>(null),
    tiposSeccion = [],
    docentes = [],
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let formData = $state<SeccionFormState>({
    id_tipo_seccion: 0,
    id_docente: undefined,
  });

  let isSubmitting = $state(false);

  // Sincronizar con editingSeccion
  $effect(() => {
    if (editingSeccion) {
      formData = {
        id_tipo_seccion: editingSeccion.id_tipo_seccion,
        id_docente: editingSeccion.id_docente,
      };
    } else {
      formData = {
        id_tipo_seccion: 0,
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
          {editingSeccion ? 'Editar Sección' : 'Crear Sección'}
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
        <!-- Tipo Sección -->
        <div>
          <label for="id_tipo_seccion" class="block text-sm font-medium text-gray-700 mb-1">
            Tipo de Sección *
          </label>
          <select
            id="id_tipo_seccion"
            bind:value={formData.id_tipo_seccion}
            required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          >
            <option value={0}>Seleccionar tipo</option>
            {#each tiposSeccion as tipo}
              <option value={tipo.id_tipo_seccion}>
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
            {isSubmitting ? 'Guardando...' : editingSeccion ? 'Actualizar' : 'Crear'}
          </button>
        </div>
      </form>
    </div>
  </div>
{/if}
