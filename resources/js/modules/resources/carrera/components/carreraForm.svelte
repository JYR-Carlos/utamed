<script lang="ts">
  /**
   * Componente: Modal Crear/Editar Carrera
   *
   * Maneja creación y edición de carreras con:
   * - Cascada facultad → departamento
   * - Validación de errores del formulario
   * - Campos read-only en modo edición (id_facultad, id_departamento)
   * - Carga dinámica de departamentos
   *
   * Props:
   * - isOpen: boolean - Control de visibilidad del modal
   * - editingCarrera: Carrera | null - Carrera siendo editada
   * - departamentos: Departamento[] - Lista de departamentos cargados
   * - facultades: Facultad[] - Todas las facultades
   * - formData: useForm object de Inertia
   * - isLoading: boolean - Estado de carga
   * - onSubmit: () => void - Callback al enviar
   * - onClose: () => void - Callback al cerrar
   * - onFacultadChange: (id: number) => void - Callback al cambiar facultad (para cargar depts)
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import type { Carrera, Facultad, Departamento } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    editingCarrera?: Carrera | null;
    departamentos?: Departamento[];
    facultades?: Facultad[];
    formData?: any; // useForm object - bindable
    isLoading?: boolean;
    onSubmit?: () => void;
    onClose?: () => void;
    onFacultadChange?: (id: number) => void;
  }

  let {
    isOpen = $bindable(),
    editingCarrera = null,
    departamentos = [],
    facultades = [],
    formData = $bindable(),
    isLoading = false,
    onSubmit = () => {},
    onClose = () => {},
    onFacultadChange = () => {},
  }: Props = $props();

  function handleFacultadChange(e: Event) {
    const target = e.target as HTMLSelectElement;
    const id = Number(target.value);
    if (formData) formData.id_facultad = id;
    onFacultadChange(id);
  }
</script>

<FormModal
  bind:isOpen
  title={editingCarrera ? 'Editar Carrera' : 'Nueva Carrera'}
  {onClose}
  {onSubmit}
  {isLoading}
>
  <!-- Nombre -->
  <div class="mb-4">
    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1.5">
      Nombre <span class="text-red-500">*</span>
    </label>
    <input
      id="nombre"
      type="text"
      bind:value={formData.nombre}
      class={`w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-100 transition ${formData?.errors?.nombre ? 'border-red-400 focus:border-red-400' : 'border-gray-300 focus:border-blue-400'}`}
      placeholder="Ej: Medicina"
      required
    />
    {#if formData?.errors?.nombre}
      <p class="mt-1 text-xs text-red-500">{formData?.errors?.nombre}</p>
    {/if}
  </div>

  <!-- Jornada + Sede -->
  <div class="grid grid-cols-2 gap-3 mb-4">
    <div>
      <label for="jornada" class="block text-sm font-medium text-gray-700 mb-1.5">Jornada</label>
      <input
        id="jornada"
        type="text"
        bind:value={formData.jornada}
        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
        placeholder="Ej: Diurna"
      />
    </div>
    <div>
      <label for="sede" class="block text-sm font-medium text-gray-700 mb-1.5">Sede</label>
      <input
        id="sede"
        type="text"
        bind:value={formData.sede}
        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
        placeholder="Ej: Arica"
      />
    </div>
  </div>

  <!-- Modalidad -->
  <div class="mb-4">
    <label for="modalidad" class="block text-sm font-medium text-gray-700 mb-1.5">Modalidad</label>
    <input
      id="modalidad"
      type="text"
      bind:value={formData.modalidad}
      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
      placeholder="Ej: Presencial"
    />
  </div>

  <!-- Facultad — read-only when editing -->
  <div class="mb-4">
    <label for="facultad" class="block text-sm font-medium text-gray-700 mb-1.5">
      Facultad <span class="text-red-500">*</span>
    </label>
    {#if editingCarrera}
      <div
        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-between"
      >
        <span>{editingCarrera.departamento?.facultad?.nombre ?? '—'}</span>
        <span
          class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded"
          >Inmutable</span
        >
      </div>
    {:else}
      <select
        id="facultad"
        bind:value={formData.id_facultad}
        onchange={handleFacultadChange}
        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition bg-white"
        required
      >
        <option value={0}>Seleccione una facultad</option>
        {#each facultades as facultad}
          <option value={facultad.id_facultad}>{facultad.nombre}</option>
        {/each}
      </select>
    {/if}
  </div>

  <!-- Departamento — read-only when editing -->
  <div class="mb-2">
    <label for="departamento" class="block text-sm font-medium text-gray-700 mb-1.5">
      Departamento <span class="text-red-500">*</span>
    </label>
    {#if editingCarrera}
      <div
        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-between"
      >
        <span>{editingCarrera.departamento?.nombre ?? '—'}</span>
        <span
          class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded"
          >Inmutable</span
        >
      </div>
      <p class="mt-1 text-[11px] text-gray-400">
        La jerarquía estructural no puede modificarse después de la creación para preservar la
        integridad RBAC.
      </p>
    {:else}
      <select
        id="departamento"
        bind:value={formData.id_departamento}
        class={`w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition bg-white ${!formData.id_facultad ? 'opacity-50 cursor-not-allowed' : ''}`}
        disabled={!formData.id_facultad}
        required
      >
        <option value={0}>Seleccione un departamento</option>
        {#each departamentos as dept}
          <option
            value={dept.id_departamento}
            disabled={!!dept.fecha_eliminacion && !editingCarrera}
          >
            {dept.nombre}{dept.fecha_eliminacion ? ' (Eliminado)' : ''}
          </option>
        {/each}
      </select>
    {/if}
  </div>
</FormModal>
