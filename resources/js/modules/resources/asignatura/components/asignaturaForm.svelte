<script lang="ts">
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { useForm } from '@inertiajs/svelte';
  import type { Asignatura } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    editingAsignatura?: Asignatura | null;
    onSuccess?: () => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    editingAsignatura = null,
    onSuccess = () => {},
    onClose = () => {},
  }: Props = $props();

  let formData = useForm({
    cod_asignatura: '',
    nombre: '',
    descripcion: '',
    creditos_sct: 0,
    horas_catedra: 0,
    horas_taller: 0,
    horas_laboratorio: 0,
    horas_dirigidas: 0,
    horas_autonomas: 0,
  });

  $effect.pre(() => {
    if (isOpen && editingAsignatura) {
      $formData.defaults({
        cod_asignatura: editingAsignatura.cod_asignatura,
        nombre: editingAsignatura.nombre,
        descripcion: editingAsignatura.descripcion || '',
        creditos_sct: editingAsignatura.creditos_sct || 0,
        horas_catedra: editingAsignatura.horas_catedra || 0,
        horas_taller: editingAsignatura.horas_taller || 0,
        horas_laboratorio: editingAsignatura.horas_laboratorio || 0,
        horas_dirigidas: editingAsignatura.horas_dirigidas || 0,
        horas_autonomas: editingAsignatura.horas_autonomas || 0,
      });
      $formData.reset();
    } else if (isOpen && !editingAsignatura) {
      $formData.reset();
      $formData.clearErrors();
    }
  });

  function handleClose() {
    $formData.reset();
    $formData.clearErrors();
    onClose();
  }

  function handleSubmit() {
    const url = editingAsignatura
      ? `/admin/asignaturas/${editingAsignatura.id_asignatura}`
      : '/admin/asignaturas';

    const opts = {
      onSuccess: () => {
        isOpen = false;
        onSuccess();
      },
    };

    if (editingAsignatura) {
      $formData.put(url, opts);
    } else {
      $formData.post(url, opts);
    }
  }
</script>

<FormModal
  bind:isOpen
  title={editingAsignatura ? 'Crear Nueva Versión de Asignatura' : 'Nueva Asignatura'}
  onClose={handleClose}
  onSubmit={handleSubmit}
  isLoading={$formData.processing}
>
  {#if editingAsignatura}
    <div class="mb-4 flex gap-3 items-start bg-blue-50 border border-blue-300 rounded-lg px-4 py-3">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="text-blue-500 mt-0.5 shrink-0"
        ><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line
          x1="12"
          y1="8"
          x2="12.01"
          y2="8"
        /></svg
      >
      <div>
        <p class="text-sm font-semibold text-blue-800">Creando nueva versión</p>
        <p class="text-xs text-blue-700 mt-0.5">
          Los cambios crearán una <strong>nueva versión</strong> de la asignatura. La versión anterior
          será marcada como histórica. Esto preserva el historial completo de cambios.
        </p>
      </div>
    </div>
  {/if}

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="cod_asignatura" class="block text-sm font-medium text-gray-700 mb-2">Código</label
      >
      <input
        id="cod_asignatura"
        type="text"
        bind:value={$formData.cod_asignatura}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        class:border-red-500={$formData.errors.cod_asignatura}
        placeholder="Ej: MED101"
        required
      />
      {#if $formData.errors.cod_asignatura}
        <p class="text-red-500 text-sm">{$formData.errors.cod_asignatura}</p>
      {/if}
    </div>

    <div class="mb-4">
      <label for="creditos_sct" class="block text-sm font-medium text-gray-700 mb-2"
        >Créditos SCT</label
      >
      <input
        id="creditos_sct"
        type="number"
        bind:value={$formData.creditos_sct}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        class:border-red-500={$formData.errors.creditos_sct}
        min="0"
      />
      {#if $formData.errors.creditos_sct}
        <p class="text-red-500 text-sm">{$formData.errors.creditos_sct}</p>
      {/if}
    </div>
  </div>

  <div class="mb-4">
    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
    <input
      id="nombre"
      type="text"
      bind:value={$formData.nombre}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      class:border-red-500={$formData.errors.nombre}
      placeholder="Ej: Anatomía Humana"
      required
    />
    {#if $formData.errors.nombre}
      <p class="text-red-500 text-sm">{$formData.errors.nombre}</p>
    {/if}
  </div>

  <div class="mb-4">
    <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label
    >
    <textarea
      id="descripcion"
      bind:value={$formData.descripcion}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      rows="3"
      placeholder="Descripción de la asignatura"
    ></textarea>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="horas_catedra" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Cátedra</label
      >
      <input
        id="horas_catedra"
        type="number"
        bind:value={$formData.horas_catedra}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="0"
      />
    </div>

    <div class="mb-4">
      <label for="horas_taller" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Taller</label
      >
      <input
        id="horas_taller"
        type="number"
        bind:value={$formData.horas_taller}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="0"
      />
    </div>
  </div>

  <div class="grid grid-cols-2 gap-4">
    <div class="mb-4">
      <label for="horas_laboratorio" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Laboratorio</label
      >
      <input
        id="horas_laboratorio"
        type="number"
        bind:value={$formData.horas_laboratorio}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="0"
      />
    </div>

    <div class="mb-4">
      <label for="horas_dirigidas" class="block text-sm font-medium text-gray-700 mb-2"
        >Horas Dirigidas</label
      >
      <input
        id="horas_dirigidas"
        type="number"
        bind:value={$formData.horas_dirigidas}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="0"
      />
    </div>
  </div>

  <div class="mb-4">
    <label for="horas_autonomas" class="block text-sm font-medium text-gray-700 mb-2"
      >Horas Autónomas</label
    >
    <input
      id="horas_autonomas"
      type="number"
      bind:value={$formData.horas_autonomas}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      min="0"
    />
  </div>
</FormModal>
