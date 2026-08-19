<script lang="ts">
  /**
   * carreraForm — Modal de creación/edición de carreras.
   *
   * Al crear, los selects facultad → departamento van en cascada: elegir
   * facultad dispara onFacultadChange y el padre recarga departamentos.
   * Al editar, facultad y departamento son inmutables (se muestran como
   * texto) para preservar la jerarquía en la que se apoya el RBAC.
   *
   * El estado del formulario vive en el padre (useForm de Inertia) y llega
   * bindeable; este componente solo lo renderiza.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import type { Carrera, Facultad, Departamento } from '@/types/admin.types';
  import { JORNADA_OPTIONS, SEDE_OPTIONS, MODALIDAD_OPTIONS } from '@/constants/admin';

  const selectClass =
    'w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white transition focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100';

  interface Props {
    isOpen: boolean;
    /** Carrera a editar; null para modo creación. */
    editingCarrera?: Carrera | null;
    /** Departamentos de la facultad seleccionada (los recarga el padre). */
    departamentos?: Departamento[];
    facultades?: Facultad[];
    /** Snapshot del useForm de Inertia del padre (por eso any). */
    formData?: any;
    isLoading?: boolean;
    onSubmit?: () => void;
    onClose?: () => void;
    /** Notifica el cambio de facultad para recargar sus departamentos. */
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

  <!-- Jornada + Sede — dominios cerrados, no texto libre: escritos a mano
       entraban como «Diurna», «diurna» y «Jornada Diurna», o no entraban. -->
  <div class="grid grid-cols-2 gap-3 mb-4">
    <div>
      <label for="jornada" class="block text-sm font-medium text-gray-700 mb-1.5">Jornada</label>
      <select id="jornada" bind:value={formData.jornada} class={selectClass}>
        <option value="">Sin definir</option>
        {#each JORNADA_OPTIONS as opcion}
          <option value={opcion}>{opcion}</option>
        {/each}
      </select>
    </div>
    <div>
      <label for="sede" class="block text-sm font-medium text-gray-700 mb-1.5">Sede</label>
      <select id="sede" bind:value={formData.sede} class={selectClass}>
        <option value="">Sin definir</option>
        {#each SEDE_OPTIONS as opcion}
          <option value={opcion}>{opcion}</option>
        {/each}
      </select>
    </div>
  </div>

  <!-- Modalidad -->
  <div class="mb-4">
    <label for="modalidad" class="block text-sm font-medium text-gray-700 mb-1.5">Modalidad</label>
    <select id="modalidad" bind:value={formData.modalidad} class={selectClass}>
      <option value="">Sin definir</option>
      {#each MODALIDAD_OPTIONS as opcion}
        <option value={opcion}>{opcion}</option>
      {/each}
    </select>
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
        La facultad y el departamento no se pueden cambiar después de crear la carrera: de esa
        jerarquía dependen los permisos ya concedidos sobre ella.
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
