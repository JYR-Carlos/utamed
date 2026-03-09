<script lang="ts">
  /**
   * Página de administración de facultades.
   *
   * Gestión CRUD de facultades en la jerarquía académica.
   * Las facultades contienen departamentos que a su vez contienen carreras.
   *
   * Características:
   * - Filas expandibles con departamentos anidados
   * - Creación contextual de departamentos desde la fila de facultad
   * - Modal contextual con facultad pre-seleccionada
   * - Tabla con búsqueda por nombre
   * - Formulario modal para crear/editar facultades
   * - Eliminación con confirmación
   *
   * Tabla relacionada:
   * - administrativo.facultad: Información de facultades
   */
  import { router, page } from '@inertiajs/svelte';
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { Facultad, PaginatedResponse, FacultadFormData } from '@/types/admin.types';
  import { ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-svelte';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Facultades paginadas */
    facultades: PaginatedResponse<Facultad>;
    /** Filtros de búsqueda */
    filters: { search?: string };
    /** Permisos del usuario autenticado */
    canCreate?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
  }

  let { facultades, filters, canCreate = false, canEdit = false, canDelete = false }: Props = $props();

  const flashSuccess = $derived(($page.props as any).flash?.success as string | undefined);
  const flashError = $derived(($page.props as any).flash?.error as string | undefined);

  let showModal = $state(false);
  let showDepartamentoModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingFacultad = $state<Facultad | null>(null);
  let deletingFacultad = $state<Facultad | null>(null);
  let selectedFacultadForDept = $state<Facultad | null>(null);

  let formData = $state<FacultadFormData>({
    nombre: '',
  });

  let departamentoFormData = $state({
    nombre: '',
    id_facultad: 0,
  });

  let expandedRows: Record<number, boolean> = $state({});

  function toggleRow(id: number) {
    expandedRows[id] = !expandedRows[id];
  }

  function openCreateModal() {
    editingFacultad = null;
    formData = { nombre: '' };
    showModal = true;
  }

  function openEditModal(facultad: Facultad) {
    if (facultad.fecha_eliminacion) return; // No permitir editar eliminadas
    editingFacultad = facultad;
    formData = { nombre: facultad.nombre };
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingFacultad = null;
    formData = { nombre: '' };
  }

  function openDepartamentoModal(facultad: Facultad) {
    if (facultad.fecha_eliminacion) return; // No permitir agregar depts a facultades eliminadas
    selectedFacultadForDept = facultad;
    departamentoFormData = {
      nombre: '',
      id_facultad: facultad.id_facultad,
    };
    showDepartamentoModal = true;
  }

  function closeDepartamentoModal() {
    showDepartamentoModal = false;
    selectedFacultadForDept = null;
    departamentoFormData = { nombre: '', id_facultad: 0 };
  }

  function handleSubmit() {
    isLoading = true;

    if (editingFacultad) {
      router.put(`/admin/facultades/${editingFacultad.id_facultad}`, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    } else {
      router.post('/admin/facultades', formData, {
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

  function handleDepartamentoSubmit() {
    isLoading = true;
    router.post('/admin/departamentos', departamentoFormData, {
      onSuccess: () => {
        closeDepartamentoModal();
        isLoading = false;
        // Recargar la facultad para mostrar el nuevo departamento
        router.reload({ only: ['facultades'] });
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  function openDeleteDialog(facultad: Facultad) {
    if (facultad.fecha_eliminacion) return; // No permitir eliminar las ya eliminadas
    deletingFacultad = facultad;
    showDeleteDialog = true;
  }

  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingFacultad = null;
  }

  function handleDelete() {
    if (!deletingFacultad) return;

    isLoading = true;
    router.delete(`/admin/facultades/${deletingFacultad.id_facultad}`, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  function deleteDepartamento(departamentoId: number) {
    isLoading = true;
    router.delete(`/admin/departamentos/${departamentoId}`, {
      onSuccess: () => {
        isLoading = false;
        router.reload({ only: ['facultades'] });
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
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Facultades</h1>
        <p class="text-sm text-gray-500">Gestión de facultades y departamentos asociados</p>
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
          Nueva Facultad
        </button>
      {/if}
    </div>

    {#if flashSuccess}
      <div class="px-4 py-3 rounded-md text-sm mb-4 bg-green-50 border border-green-200 text-green-800" role="alert">{flashSuccess}</div>
    {/if}
    {#if flashError}
      <div class="px-4 py-3 rounded-md text-sm mb-4 bg-red-50 border border-red-200 text-red-800" role="alert">{flashError}</div>
    {/if}

    <!-- Tabla expandible de Facultades -->
    <div class="overflow-x-auto bg-white rounded-lg shadow">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-6 py-3 text-left font-semibold text-gray-700"></th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">ID</th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">Nombre</th>
            <th class="px-6 py-3 text-left font-semibold text-gray-700">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          {#each facultades.data as facultad (facultad.id_facultad)}
            <tr class={`hover:bg-gray-50 ${facultad.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : ''}`}>
              <td class="px-6 py-3">
                <button
                  onclick={() => toggleRow(facultad.id_facultad)}
                  class="text-gray-400 hover:text-gray-600"
                  disabled={!!facultad.fecha_eliminacion}
                >
                  {#if expandedRows[facultad.id_facultad]}
                    <ChevronDown size={18} />
                  {:else}
                    <ChevronRight size={18} />
                  {/if}
                </button>
              </td>
              <td class="px-6 py-3 text-gray-600">{facultad.id_facultad}</td>
              <td class="px-6 py-3 text-gray-900 font-medium">
                <div class="flex items-center gap-2">
                  <span class={`${facultad.fecha_eliminacion ? 'line-through text-gray-400' : ''}`}>{facultad.nombre}</span>
                  {#if facultad.fecha_eliminacion}
                    <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-200 text-gray-600"> Eliminada </span>
                  {/if}
                </div>
              </td>
              <td class="px-6 py-3 flex items-center gap-2">
                {#if canEdit && !facultad.fecha_eliminacion}
                  <button onclick={() => openEditModal(facultad)} class="text-blue-600 hover:text-blue-800 font-medium text-xs">Editar</button>
                {/if}
                {#if canDelete && !facultad.fecha_eliminacion}
                  <button onclick={() => openDeleteDialog(facultad)} class="text-red-600 hover:text-red-800 font-medium text-xs">Eliminar</button>
                {/if}
                {#if facultad.fecha_eliminacion}
                  <span class="text-gray-300 text-[11px] italic">No disponible</span>
                {/if}
              </td>
            </tr>

            {#if expandedRows[facultad.id_facultad]}
              <tr class="bg-gray-50">
                <td colspan="4" class="px-6 py-4">
                  <div class="space-y-4">
                    <!-- Encabezado de Departamentos -->
                    <div class="flex justify-between items-center mb-3">
                      <h3 class="font-semibold text-gray-700">Departamentos</h3>
                      <button
                        onclick={() => openDepartamentoModal(facultad)}
                        class="inline-flex items-center gap-1 text-sm px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-md font-medium transition-colors"
                      >
                        <Plus size={14} />
                        Agregar
                      </button>
                    </div>

                    <!-- Tabla de Departamentos -->
                    {#if facultad.departamentos && facultad.departamentos.length > 0}
                      <div class="overflow-x-auto">
                        <table class="w-full text-sm bg-white rounded-lg border border-gray-200">
                          <thead class="bg-gray-50">
                            <tr>
                              <th class="px-4 py-2 text-left font-semibold text-gray-700">ID</th>
                              <th class="px-4 py-2 text-left font-semibold text-gray-700">Nombre Departamento</th>
                              <th class="px-4 py-2 text-left font-semibold text-gray-700">Acciones</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-100">
                            {#each facultad.departamentos as dept (dept.id_departamento)}
                              <tr class={`hover:bg-gray-50 ${dept.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : ''}`}>
                                <td class="px-4 py-2 text-gray-600">{dept.id_departamento}</td>
                                <td class="px-4 py-2">
                                  <div class="flex items-center gap-2">
                                    <span class={`text-gray-900 ${dept.fecha_eliminacion ? 'line-through text-gray-400' : ''}`}>
                                      {dept.nombre}
                                    </span>
                                    {#if dept.fecha_eliminacion}
                                      <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-200 text-gray-600">
                                        Eliminado
                                      </span>
                                    {/if}
                                  </div>
                                </td>
                                <td class="px-4 py-2">
                                  {#if dept.fecha_eliminacion}
                                    <span class="text-gray-300 text-[11px] italic">No disponible</span>
                                  {:else}
                                    <button
                                      onclick={() => deleteDepartamento(dept.id_departamento)}
                                      class="text-red-600 hover:text-red-800 font-medium text-xs inline-flex items-center gap-1"
                                    >
                                      <Trash2 size={12} />
                                      Eliminar
                                    </button>
                                  {/if}
                                </td>
                              </tr>
                            {/each}
                          </tbody>
                        </table>
                      </div>
                    {:else}
                      <div class="text-center py-4 text-gray-500 text-sm">No hay departamentos. Haz clic en "Agregar" para crear uno.</div>
                    {/if}
                  </div>
                </td>
              </tr>
            {/if}
          {/each}
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal: Crear/Editar Facultad -->
  <FormModal
    bind:isOpen={showModal}
    title={editingFacultad ? 'Editar Facultad' : 'Nueva Facultad'}
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
        placeholder="Ej: Facultad de Medicina"
        required
      />
    </div>
  </FormModal>

  <!-- Modal: Crear Departamento (Contextual) -->
  <FormModal
    bind:isOpen={showDepartamentoModal}
    title="Nuevo Departamento"
    onClose={closeDepartamentoModal}
    onSubmit={handleDepartamentoSubmit}
    {isLoading}
  >
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">Facultad</label>
      <div class="px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-600 bg-gray-50">
        {selectedFacultadForDept?.nombre}
      </div>
    </div>

    <div class="mb-4">
      <label for="dept-nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre del Departamento</label>
      <input
        id="dept-nombre"
        type="text"
        bind:value={departamentoFormData.nombre}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        placeholder="Ej: Departamento de Ciencias Básicas"
        required
      />
    </div>
  </FormModal>

  <!-- Dialog: Confirmar Eliminación de Facultad -->
  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Facultad?"
    message="Esta acción no se puede deshacer. Si la facultad tiene departamentos asociados, no podrá ser eliminada."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
  />
</AdminLayout>
