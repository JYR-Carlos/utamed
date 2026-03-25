<script lang="ts">
  /**
   * Componente: Formulario de Facultad
   *
   * Modal para crear/editar facultades.
   * Reutilizable en diferentes contextos.
   *
   * Props:
   * - isOpen: boolean controla visibilidad
   * - isEditing: boolean indica si estamos editando
   * - facultad: facultad actual (si estamos editando)
   * - isLoading: boolean para mostrar estado loading
   * - onSubmit: callback cuando se envía el formulario
   * - onClose: callback para cerrar el modal
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import type { Facultad, FacultadFormData } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    isEditing?: boolean;
    facultad?: Facultad | null;
    isLoading?: boolean;
    onSubmit?: (formData: FacultadFormData) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    isEditing = false,
    facultad,
    isLoading = false,
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let nombre = $derived(facultad?.nombre || '');

  function handleSubmit() {
    onSubmit?.({ nombre });
  }

  function handleClose() {
    nombre = '';
    onClose?.();
  }
</script>

<FormModal
  bind:isOpen
  title={isEditing ? 'Editar Facultad' : 'Nueva Facultad'}
  onClose={handleClose}
  onSubmit={handleSubmit}
  {isLoading}
>
  <div class="mb-4">
    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
    <input
      id="nombre"
      type="text"
      bind:value={nombre}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      placeholder="Ej: Facultad de Medicina"
      required
    />
  </div>
</FormModal>
