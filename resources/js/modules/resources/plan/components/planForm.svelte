<script lang="ts">
  /**
   * Componente formulario de planes curriculares.
   *
   * Formulario modal para crear y editar planes.
   * Requiere seleccionar carrera y año.
   *
   * Props: Recibe formData del padre (bindable).
   * Parent es responsable de manejar el estado de formData e isOpen.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import type { Plan, Carrera, PlanFormData } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    editingPlan: Plan | null;
    carreras: Carrera[];
    formData: PlanFormData;
    isLoading: boolean;
    onClose: () => void;
    onSubmit: () => void;
  }

  let {
    isOpen,
    editingPlan,
    carreras,
    formData = $bindable(),
    isLoading,
    onClose,
    onSubmit,
  }: Props = $props();
</script>

<FormModal
  {isOpen}
  title={editingPlan ? 'Editar Plan' : 'Nuevo Plan'}
  {onClose}
  {onSubmit}
  {isLoading}
>
  <div class="mb-4">
    <label for="carrera" class="block text-sm font-medium text-gray-700 mb-2">Carrera *</label>
    <select
      id="carrera"
      bind:value={formData.id_carrera}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      required
    >
      <option value={0}>Seleccione una carrera</option>
      {#each carreras as carrera}
        <option value={carrera.id_carrera}>{carrera.nombre}</option>
      {/each}
    </select>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="agno" class="block text-sm font-medium text-gray-700 mb-2">Año *</label>
      <input
        id="agno"
        type="number"
        bind:value={formData.agno}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="1900"
        max="2100"
        placeholder="Ej: 2024"
        required
      />
    </div>

    <div class="mb-4">
      <label for="version_plan" class="block text-sm font-medium text-gray-700 mb-2"
        >Versión *</label
      >
      <input
        id="version_plan"
        type="number"
        bind:value={formData.version_plan}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="1"
        placeholder="Ej: 1"
        required
      />
    </div>
  </div>

  <div class="mb-4">
    <label for="creditos" class="block text-sm font-medium text-gray-700 mb-2"
      >Créditos SCT Totales</label
    >
    <div class="flex items-center gap-3 p-4 bg-gray-50 border border-gray-200 rounded-md">
      <span class="text-2xl font-bold text-blue-500">{editingPlan?.creditos_sct_totales || 0}</span>
      <span class="text-xs text-gray-500 italic">(Calculado automáticamente)</span>
    </div>
  </div>
</FormModal>
