<script lang="ts">
  /**
   * Página de administración de departamentos.
   *
   * Gestión CRUD de departamentos que pertenecen a facultades.
   * Los departamentos contienen carreras que a su vez contienen planes.
   *
   * Características:
   * - Filtrado por facultad
   * - Búsqueda por nombre
   * - Formulario modal para crear/editar departamentos
   * - Confirmación antes de eliminación (soft delete)
   * - Soporte para visualizar departamentos eliminados (read-only)
   *
   * Tablas relacionadas:
   * - administrativo.departamento: Información de departamentos
   * - administrativo.facultad: Facultad padre
   */
  import { router, page } from '@inertiajs/svelte';
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { Departamento, Facultad, PaginatedResponse, DepartamentoFormData } from '@/types/admin.types';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Departamentos paginados */
    departamentos: PaginatedResponse<Departamento>;
    /** Todas las facultades para selector */
    facultades: Facultad[];
    /** Filtros de búsqueda y facultad */
    filters: { search?: string; id_facultad?: number };
    /** Permisos del usuario autenticado */
    canCreate?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
  }

  let { departamentos, facultades, filters, canCreate = false, canEdit = false, canDelete = false }: Props = $props();

  const flashSuccess = $derived(($page.props as any).flash?.success as string | undefined);
  const flashError = $derived(($page.props as any).flash?.error as string | undefined);

  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingDepartamento = $state<Departamento | null>(null);
  let deletingDepartamento = $state<Departamento | null>(null);

  let formData = $state<DepartamentoFormData>({
    nombre: '',
    id_facultad: 0,
  });

  function openCreateModal() {
    editingDepartamento = null;
    formData = { nombre: '', id_facultad: 0 };
    showModal = true;
  }

  function openEditModal(departamento: Departamento) {
    if (departamento.fecha_eliminacion) return; // No permitir editar eliminados
    editingDepartamento = departamento;
    formData = {
      nombre: departamento.nombre,
      id_facultad: departamento.id_facultad,
    };
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingDepartamento = null;
    formData = { nombre: '', id_facultad: 0 };
  }

  function handleSubmit() {
    isLoading = true;

    if (editingDepartamento) {
      router.put(`/admin/departamentos/${editingDepartamento.id_departamento}`, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    } else {
      router.post('/admin/departamentos', formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    }
  }

  function openDeleteDialog(departamento: Departamento) {
    if (departamento.fecha_eliminacion) return; // No permitir eliminar ya eliminados
    deletingDepartamento = departamento;
    showDeleteDialog = true;
  }

  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingDepartamento = null;
  }

  function handleDelete() {
    if (!deletingDepartamento) return;

    isLoading = true;
    router.delete(`/admin/departamentos/${deletingDepartamento.id_departamento}`, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }
</script>

<AdminLayout>
  <div class="p-8 max-w-6xl mx-auto">
    <div class="flex justify-between items-start mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Departamentos</h1>
        <p class="text-sm text-gray-500">Gestión de departamentos por facultad</p>
      </div>
      {#if canCreate}
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
          Nuevo Departamento
        </button>
      {/if}
    </div>

    {#if flashSuccess}
      <div class="px-4 py-3 rounded-md text-sm mb-4 bg-green-50 border border-green-200 text-green-800" role="alert">{flashSuccess}</div>
    {/if}
    {#if flashError}
      <div class="px-4 py-3 rounded-md text-sm mb-4 bg-red-50 border border-red-200 text-red-800" role="alert">{flashError}</div>
    {/if}

    <!-- Tabla personalizada con soporte de soft delete -->
    <div class="overflow-x-auto bg-white rounded-lg shadow">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">ID</th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">Nombre</th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">Facultad</th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">Estado</th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          {#each departamentos.data as departamento (departamento.id_departamento)}
            <tr class={`hover:bg-gray-50 ${departamento.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : ''}`}>
              <td class="px-6 py-3 text-gray-600">{departamento.id_departamento}</td>
              <td class="px-6 py-3">
                <div class="flex items-center gap-2">
                  <span class={`text-gray-900 font-medium ${departamento.fecha_eliminacion ? 'line-through text-gray-400' : ''}`}>
                    {departamento.nombre}
                  </span>
                  {#if departamento.fecha_eliminacion}
                    <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-200 text-gray-600"> Eliminado </span>
                  {/if}
                </div>
              </td>
              <td class="px-6 py-3 text-gray-600">{departamento.facultad?.nombre ?? '—'}</td>
              <td class="px-6 py-3">
                {#if departamento.fecha_eliminacion}
                  <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold"> Soft Deleted </span>
                {:else}
                  <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold"> Activo </span>
                {/if}
              </td>
              <td class="px-6 py-3 flex items-center gap-2">
                {#if canEdit && !departamento.fecha_eliminacion}
                  <button onclick={() => openEditModal(departamento)} class="text-blue-600 hover:text-blue-800 font-medium text-xs">Editar</button>
                {/if}
                {#if canDelete && !departamento.fecha_eliminacion}
                  <button onclick={() => openDeleteDialog(departamento)} class="text-red-600 hover:text-red-800 font-medium text-xs">Eliminar</button>
                {/if}
                {#if departamento.fecha_eliminacion}
                  <span class="text-gray-300 text-[11px] italic">No disponible</span>
                {/if}
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
  </div>

  <FormModal
    bind:isOpen={showModal}
    title={editingDepartamento ? 'Editar Departamento' : 'Nuevo Departamento'}
    onClose={closeModal}
    onSubmit={handleSubmit}
    {isLoading}
  >
    <div class="mb-4">
      <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
      <input
        id="nombre"
        type="text"
        bind:value={formData.nombre}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        placeholder="Ej: Departamento de Ciencias Básicas"
        required
      />
    </div>

    <div class="mb-4">
      <label for="facultad" class="block text-sm font-medium text-gray-700 mb-2">Facultad</label>
      <select
        id="facultad"
        bind:value={formData.id_facultad}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        required
      >
        <option value={0}>Seleccione una facultad</option>
        {#each facultades as facultad}
          <option value={facultad.id_facultad}>{facultad.nombre}</option>
        {/each}
      </select>
    </div>
  </FormModal>

  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Departamento?"
    message="Esta acción no se puede deshacer. Si el departamento tiene carreras asociadas, no podrá ser eliminado."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
  />
</AdminLayout>
