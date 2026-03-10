<script lang="ts">
  /**
   * Página de administración de asignaturas globales.
   *
   * Permite crear, leer, actualizar y eliminar asignaturas que pueden
   * ser asignadas a múltiples planes de estudio.
   *
   * Características:
   * - Tabla paginada con búsqueda por código y nombre
   * - Formulario modal para crear/editar asignaturas
   * - Validación de campos (horas, créditos, etc.)
   * - Confirmación antes de eliminación
   *
   * Tabla relacionada:
   * - administrativo.asignatura: Almacena información global de asignaturas
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import type { Asignatura, PaginatedResponse, AsignaturaFormData } from '@/types/admin.types';
  import { useForm } from '@inertiajs/svelte';
  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Asignaturas paginadas del servidor */
    asignaturas: PaginatedResponse<Asignatura>;
    /** Filtros aplicados (búsqueda) */
    filters: { search?: string };
  }

  let { asignaturas, filters }: Props = $props();

  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let editingAsignatura = $state<Asignatura | null>(null);
  let deletingAsignatura = $state<Asignatura | null>(null);

  // Uso de useForm para manejar el estado del formulario
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

  const columns = [
    { key: 'cod_asignatura', label: 'Código' },
    { key: 'nombre', label: 'Nombre' },
    { key: 'creditos_sct', label: 'Créditos SCT' },
    { key: 'planes_count', label: 'Uso en Planes' },
  ];

  function openCreateModal() {
    editingAsignatura = null;
    $formData.reset();
    $formData.clearErrors();
    showModal = true;
  }

  function openEditModal(asignatura: Asignatura) {
    editingAsignatura = asignatura;
    $formData.defaults({
      cod_asignatura: asignatura.cod_asignatura,
      nombre: asignatura.nombre,
      descripcion: asignatura.descripcion || '',
      creditos_sct: asignatura.creditos_sct || 0,
      horas_catedra: asignatura.horas_catedra || 0,
      horas_taller: asignatura.horas_taller || 0,
      horas_laboratorio: asignatura.horas_laboratorio || 0,
      horas_dirigidas: asignatura.horas_dirigidas || 0,
      horas_autonomas: asignatura.horas_autonomas || 0,
    });
    $formData.reset();
    showModal = true;
  }

  function handleSubmit() {
    const url = editingAsignatura ? `/admin/asignaturas/${editingAsignatura.id_asignatura}` : '/admin/asignaturas';

    const opts = {
      onSuccess: () => {
        showModal = false;
        editingAsignatura = null;
      },
      onError: () => {
        console.error('Error al guardar la asignatura:', formData.errors);
      },
    };

    if (editingAsignatura) {
      $formData.put(url, opts);
    } else {
      $formData.post(url, opts);
    }
  }

  function openDeleteDialog(asignatura: Asignatura) {
    deletingAsignatura = asignatura;
    showDeleteDialog = true;
  }

  function handleDelete() {
    if (!deletingAsignatura) return;

    $formData.delete(`/admin/asignaturas/${deletingAsignatura.id_asignatura}`, {
      onSuccess: () => {
        showDeleteDialog = false;
        deletingAsignatura = null;
      },
      onError: () => {
        console.error('Error al eliminar la asignatura:', formData.errors.nombre);
      },
    });
  }
</script>

<AdminLayout>
  <div class="p-8 max-w-6xl mx-auto">
    <div class="flex justify-between items-start mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Asignaturas</h1>
        <p class="text-sm text-gray-500">Gestión del catálogo de asignaturas</p>
        <p class="text-xs text-blue-600 mt-2 font-medium">
          💡 Al editar una asignatura se crea una nueva versión. Esto preserva el historial de cambios.
        </p>
      </div>
      <button
        onclick={openCreateModal}
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Nueva Asignatura
      </button>
    </div>

    <DataTable data={asignaturas} {columns} onEdit={openEditModal} onDelete={openDeleteDialog}>
      {#snippet cellSnippet({ item, column })}
        {#if column.key === 'planes_count'}
          {@const count = item.planes_count ?? 0}
          <span
            class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full
					{count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'}"
          >
            {count > 0 ? `Utilizada en ${count} plan${count === 1 ? '' : 'es'}` : 'Sin asignar'}
          </span>
        {:else}
          {item[column.key] ?? '-'}
        {/if}
      {/snippet}
    </DataTable>
  </div>

  <FormModal
    bind:isOpen={showModal}
    title={editingAsignatura ? 'Crear Nueva Versión de Asignatura' : 'Nueva Asignatura'}
    onClose={() => (showModal = false)}
    onSubmit={handleSubmit}
    isLoading={$formData.processing}
  >
    <!-- Advertencia de versionado cuando se está editando -->
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
          ><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></svg
        >
        <div>
          <p class="text-sm font-semibold text-blue-800">Creando nueva versión</p>
          <p class="text-xs text-blue-700 mt-0.5">
            Los cambios crearán una <strong>nueva versión</strong> de la asignatura. La versión anterior será marcada como histórica. Esto preserva el historial
            completo de cambios.
          </p>
        </div>
      </div>
    {/if}
    <div class="grid grid-cols-2 gap-4">
      <div class="mb-4">
        <label for="cod_asignatura" class="block text-sm font-medium text-gray-700 mb-2">Código</label>
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
        <label for="creditos_sct" class="block text-sm font-medium text-gray-700 mb-2">Créditos SCT</label>
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
      <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
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
        <label for="horas_catedra" class="block text-sm font-medium text-gray-700 mb-2">Horas Cátedra</label>
        <input
          id="horas_catedra"
          type="number"
          bind:value={$formData.horas_catedra}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          min="0"
        />
      </div>

      <div class="mb-4">
        <label for="horas_taller" class="block text-sm font-medium text-gray-700 mb-2">Horas Taller</label>
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
        <label for="horas_laboratorio" class="block text-sm font-medium text-gray-700 mb-2">Horas Laboratorio</label>
        <input
          id="horas_laboratorio"
          type="number"
          bind:value={$formData.horas_laboratorio}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          min="0"
        />
      </div>

      <div class="mb-4">
        <label for="horas_dirigidas" class="block text-sm font-medium text-gray-700 mb-2">Horas Dirigidas</label>
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
      <label for="horas_autonomas" class="block text-sm font-medium text-gray-700 mb-2">Horas Autónomas</label>
      <input
        id="horas_autonomas"
        type="number"
        bind:value={$formData.horas_autonomas}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        min="0"
      />
    </div>
  </FormModal>

  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Asignatura?"
    message="Esta acción no se puede deshacer. Si la asignatura está asignada a planes, no podrá ser eliminada."
    onConfirm={handleDelete}
    onCancel={() => (showDeleteDialog = false)}
    isLoading={$formData.processing}
  />
</AdminLayout>
