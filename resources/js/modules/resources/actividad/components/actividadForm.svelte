<script lang="ts">
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { Checkbox } from '@/components/ui/checkbox';
  import { Label } from '@/components/ui/label';
  import type { Actividad } from '@/types/actividad';

  interface Seccion {
    id_seccion: number;
    numero_seccion: number | string;
  }

  interface Unidad {
    id_unidad: number;
    nombre: string;
  }

  interface Props {
    isOpen?: boolean;
    isLoading?: boolean;
    editingActividad?: Actividad | null;
    secciones?: Seccion[];
    unidades?: Unidad[];
    onClose?: () => void;
    onSubmit?: (data: Partial<Actividad>) => void;
  }

  let {
    isOpen = $bindable(false),
    isLoading = false,
    editingActividad = null,
    secciones = [],
    unidades = [],
    onClose = () => {},
    onSubmit = () => {},
  }: Props = $props();

  let formData = $state<Partial<Actividad>>({
    nombre: '',
    fecha_limite: '',
    tipo_actividad: 1,
    tipo_entrega: 'online',
    es_grupal: false,
    max_integrantes: 1,
    visible: true,
    id_seccion: 0,
    id_unidad: 0,
  });

  $effect.pre(() => {
    if (isOpen) {
      if (editingActividad) {
        formData = { ...editingActividad };
      } else {
        formData = {
          nombre: '',
          fecha_limite: '',
          tipo_actividad: 1,
          tipo_entrega: 'online',
          es_grupal: false,
          max_integrantes: 1,
          visible: true,
          id_seccion: 0,
          id_unidad: 0,
        };
      }
    }
  });

  function handleSubmit() {
    onSubmit(formData);
  }
</script>

<FormModal
  bind:isOpen
  title={editingActividad ? 'Editar Actividad' : 'Nueva Actividad'}
  {onClose}
  onSubmit={handleSubmit}
  {isLoading}
>
  <div class="mb-4">
    <label for="nombre" class="block font-medium text-gray-700 mb-2 text-sm"
      >Nombre de la Actividad *</label
    >
    <input
      id="nombre"
      type="text"
      bind:value={formData.nombre}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
      placeholder="Ej: Tarea 1, Evaluación Parcial"
      required
    />
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="fecha_limite" class="block font-medium text-gray-700 mb-2 text-sm"
        >Fecha Límite *</label
      >
      <input
        id="fecha_limite"
        type="date"
        bind:value={formData.fecha_limite}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
        required
      />
    </div>

    <div class="mb-4">
      <label for="tipo_entrega" class="block font-medium text-gray-700 mb-2 text-sm"
        >Tipo de Entrega *</label
      >
      <select
        id="tipo_entrega"
        bind:value={formData.tipo_entrega}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
        required
      >
        <option value="online">En línea</option>
        <option value="presencial">Presencial</option>
        <option value="hibrido">Híbrido</option>
      </select>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="id_seccion" class="block font-medium text-gray-700 mb-2 text-sm">Sección *</label>
      <select
        id="id_seccion"
        bind:value={formData.id_seccion}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
        required
      >
        <option value={0}>Seleccione una sección</option>
        {#each secciones as seccion}
          <option value={seccion.id_seccion}>
            {seccion.numero_seccion}
          </option>
        {/each}
      </select>
    </div>

    <div class="mb-4">
      <label for="id_unidad" class="block font-medium text-gray-700 mb-2 text-sm">Unidad *</label>
      <select
        id="id_unidad"
        bind:value={formData.id_unidad}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
        required
      >
        <option value={0}>Seleccione una unidad</option>
        {#each unidades as unidad}
          <option value={unidad.id_unidad}>
            {unidad.nombre}
          </option>
        {/each}
      </select>
    </div>
  </div>

  <div class="mb-4 flex items-center gap-2">
    <Checkbox
      id="es_grupal"
      checked={Boolean(formData.es_grupal)}
      onchange={(e) => { formData.es_grupal = (e.target as HTMLInputElement).checked; }}
    />
    <Label for="es_grupal" class="text-sm text-gray-700 cursor-pointer font-normal">
      Es una actividad grupal
    </Label>
  </div>

  {#if formData.es_grupal}
    <div class="mb-4">
      <label for="max_integrantes" class="block font-medium text-gray-700 mb-2 text-sm"
        >Máximo de Integrantes</label
      >
      <input
        id="max_integrantes"
        type="number"
        min="2"
        max="10"
        bind:value={formData.max_integrantes}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100 transition-all"
      />
    </div>
  {/if}

  <div class="mb-4 flex items-center gap-2">
    <Checkbox
      id="visible"
      checked={Boolean(formData.visible)}
      onchange={(e) => { formData.visible = (e.target as HTMLInputElement).checked; }}
    />
    <Label for="visible" class="text-sm text-gray-700 cursor-pointer font-normal">
      Visible para estudiantes
    </Label>
  </div>
</FormModal>
