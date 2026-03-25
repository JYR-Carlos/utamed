<script lang="ts">
  /**
   * Componente: Modal Crear Departamento (Contextual)
   *
   * Modal para crear un departamento dentro de una facultad.
   * Facultad viene pre-seleccionada.
   *
   * Props:
   * - isOpen: boolean controla visibilidad
   * - facultadNombre: nombre de la facultad (read-only)
   * - facultadId: id de la facultad
   * - isLoading: boolean para mostrar estado loading
   * - onSubmit: callback cuando se envía el formulario
   * - onClose: callback para cerrar el modal
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';

  interface Props {
    isOpen?: boolean;
    facultadNombre?: string;
    facultadId?: number;
    isLoading?: boolean;
    onSubmit?: (formData: { nombre: string; id_facultad: number }) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    facultadNombre = '',
    facultadId = 0,
    isLoading = false,
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let nombre = $state('');

  function handleSubmit() {
    onSubmit?.({ nombre, id_facultad: facultadId });
  }

  function handleClose() {
    nombre = '';
    onClose?.();
  }
</script>

<FormModal
  bind:isOpen
  title="Nuevo Departamento"
  onClose={handleClose}
  onSubmit={handleSubmit}
  {isLoading}
>
  <div class="mb-4">
    <div class="text-sm font-medium text-gray-700 mb-2">Facultad</div>
    <div class="px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-600 bg-gray-50">
      {facultadNombre}
    </div>
  </div>

  <div class="mb-4">
    <label for="dept-nombre" class="block text-sm font-medium text-gray-700 mb-2">
      Nombre del Departamento
    </label>
    <input
      id="dept-nombre"
      type="text"
      bind:value={nombre}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      placeholder="Ej: Departamento de Ciencias Básicas"
      required
    />
  </div>
</FormModal>
