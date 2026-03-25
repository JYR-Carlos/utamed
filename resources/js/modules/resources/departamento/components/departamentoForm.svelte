<script lang="ts">
  /**
   * Componente: Modal Formulario Departamento
   *
   * Modal para crear un nuevo departamento o editar uno existente.
   * En modo edición, el campo facultad es read-only (no puede cambiar la facultad padre).
   *
   * Props:
   * - isOpen: boolean para controlar visibilidad
   * - editingDepartamento: departamento siendo editado (null para crear)
   * - facultades: array de facultades para el selector
   * - isLoading: boolean para estado de carga
   * - onSubmit: callback cuando se envía el formulario
   * - onClose: callback para cerrar el modal
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import type { Departamento, Facultad, DepartamentoFormData } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    editingDepartamento?: Departamento | null;
    facultades?: Facultad[];
    isLoading?: boolean;
    onSubmit?: (formData: DepartamentoFormData) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    editingDepartamento = null,
    facultades = [],
    isLoading = false,
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  let nombre = $state('');
  let id_facultad = $state(0);

  function handleClose() {
    nombre = '';
    id_facultad = 0;
    onClose?.();
  }

  function handleSubmit() {
    const formData: DepartamentoFormData = {
      nombre,
      id_facultad,
    };
    onSubmit?.(formData);
  }

  // Cuando se abre el modal en edición, poner los datos del departamento
  $effect.pre(() => {
    if (isOpen && editingDepartamento) {
      nombre = editingDepartamento.nombre;
      id_facultad = editingDepartamento.id_facultad;
    } else if (!isOpen) {
      nombre = '';
      id_facultad = 0;
    }
  });
</script>

<FormModal
  bind:isOpen
  title={editingDepartamento ? 'Editar Departamento' : 'Nuevo Departamento'}
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
      placeholder="Ej: Departamento de Ciencias Básicas"
      required
    />
  </div>

  <div class="mb-4">
    <label for="facultad" class="block text-sm font-medium text-gray-700 mb-2">
      Facultad
      {#if editingDepartamento}
        <span
          class="ml-1 text-[11px] font-normal text-amber-600 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded"
        >
          No modificable
        </span>
      {/if}
    </label>
    <select
      id="facultad"
      bind:value={id_facultad}
      disabled={!!editingDepartamento}
      class={`w-full px-3.5 py-2.5 border rounded-md text-sm text-gray-900 transition-all focus:outline-none ${
        editingDepartamento
          ? 'border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed'
          : 'border-gray-300 bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100'
      }`}
      required
    >
      <option value={0}>Seleccione una facultad</option>
      {#each facultades as facultad}
        <option value={facultad.id_facultad}>{facultad.nombre}</option>
      {/each}
    </select>
    {#if editingDepartamento}
      <p class="mt-1 text-[11px] text-gray-500">
        La facultad de un departamento no puede cambiarse una vez creado.
      </p>
    {/if}
  </div>
</FormModal>
