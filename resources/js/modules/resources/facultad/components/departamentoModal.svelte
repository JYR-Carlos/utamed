<script lang="ts">
  /**
   * departamentoModal — Creación contextual de un departamento desde la
   * fila de una facultad (la facultad llega pre-seleccionada y se muestra
   * como texto de solo lectura).
   *
   * Componente controlado: delega en onSubmit({nombre, id_facultad}).
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';

  interface Props {
    isOpen?: boolean;
    /** Nombre visible de la facultad destino (solo lectura). */
    facultadNombre?: string;
    facultadId?: number;
    /** Enviando; lo controla el padre porque es quien hace la petición. */
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
