<script lang="ts">
  /**
   * Página de administración de facultades.
   *
   * Orquestador de componentes modulares para la gestión CRUD de facultades.
   * Las facultades contienen departamentos que a su vez contienen carreras.
   *
   * Estructura de componentes:
   * - FacultadList: tabla expandible con departamentos anidados
   * - FacultadForm: modal para crear/editar facultades
   * - DepartamentoModal: modal contextual para crear departamentos
   * - FacultadDeleteConfirm: confirmación de eliminación
   *
   * Tabla relacionada:
   * - administrativo.facultad: Información de facultades
   */
  import { router, page } from '@inertiajs/svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import FacultadList from '@/modules/resources/facultad/components/facultadList.svelte';
  import FacultadForm from '@/modules/resources/facultad/components/facultadForm.svelte';
  import DepartamentoModal from '@/modules/resources/facultad/components/departamentoModal.svelte';
  import FacultadDeleteConfirm from '@/modules/resources/facultad/components/facultadDeleteConfirm.svelte';
  import {
    createFacultad,
    updateFacultad,
    deleteFacultad,
    createDepartamento,
    deleteDepartamento,
  } from '@/modules/resources/facultad/services/facultadApi';
  import type { Facultad, PaginatedResponse, FacultadFormData } from '@/types/admin.types';
  import type { BreadcrumbItem } from '@/types';

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

  let {
    facultades,
    filters,
    canCreate = false,
    canEdit = false,
    canDelete = false,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facultades', href: '/admin/facultades' },
  ];

  const flashSuccess = $derived(($page.props as any).flash?.success as string | undefined);
  const flashError = $derived(($page.props as any).flash?.error as string | undefined);

  // Estado para formularios y diálogos
  let showModal = $state(false);
  let showDepartamentoModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingFacultad = $state<Facultad | null>(null);
  let deletingFacultad = $state<Facultad | null>(null);
  let selectedFacultadForDept = $state<Facultad | null>(null);

  /**
   * Abre el modal para crear una nueva facultad.
   */
  function openCreateModal() {
    editingFacultad = null;
    showModal = true;
  }

  /**
   * Abre el modal para editar una facultad existente.
   */
  function openEditModal(facultad: Facultad) {
    editingFacultad = facultad;
    showModal = true;
  }

  /**
   * Cierra el modal de facultad.
   */
  function closeModal() {
    showModal = false;
    editingFacultad = null;
  }

  /**
   * Abre el modal para crear un departamento dentro de una facultad.
   */
  function openDepartamentoModal(facultad: Facultad) {
    selectedFacultadForDept = facultad;
    showDepartamentoModal = true;
  }

  /**
   * Cierra el modal de departamento.
   */
  function closeDepartamentoModal() {
    showDepartamentoModal = false;
    selectedFacultadForDept = null;
  }

  /**
   * Envía el formulario de facultad (crear o editar).
   */
  function handleSubmit(formData: FacultadFormData) {
    isLoading = true;

    if (editingFacultad) {
      updateFacultad(editingFacultad.id_facultad, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    } else {
      createFacultad(formData, {
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

  /**
   * Envía el formulario para crear un departamento.
   */
  function handleDepartamentoSubmit(formData: { nombre: string; id_facultad: number }) {
    if (!selectedFacultadForDept) return;

    isLoading = true;
    createDepartamento(formData, {
      onSuccess: () => {
        closeDepartamentoModal();
        isLoading = false;
        router.reload({ only: ['facultades'] });
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  /**
   * Abre el diálogo de confirmación para eliminar una facultad.
   */
  function openDeleteDialog(facultad: Facultad) {
    deletingFacultad = facultad;
    showDeleteDialog = true;
  }

  /**
   * Cierra el diálogo de eliminación.
   */
  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingFacultad = null;
  }

  /**
   * Confirma la eliminación de una facultad.
   */
  function handleDelete() {
    if (!deletingFacultad) return;

    isLoading = true;
    deleteFacultad(deletingFacultad.id_facultad, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
        router.reload({ only: ['facultades'] });
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  /**
   * Elimina un departamento.
   */
  function handleDeleteDepartamento(departamentoId: number) {
    isLoading = true;
    deleteDepartamento(departamentoId, {
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

<AdminLayout {breadcrumbs}>
  <div>
    <!-- Header -->
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

    <!-- Flash messages -->
    {#if flashSuccess}
      <div
        class="px-4 py-3 rounded-md text-sm mb-4 bg-green-50 border border-green-200 text-green-800"
        role="alert"
      >
        {flashSuccess}
      </div>
    {/if}
    {#if flashError}
      <div
        class="px-4 py-3 rounded-md text-sm mb-4 bg-red-50 border border-red-200 text-red-800"
        role="alert"
      >
        {flashError}
      </div>
    {/if}

    <!-- Componente: Lista de Facultades -->
    <FacultadList
      {facultades}
      {canEdit}
      {canDelete}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      onAddDepartamento={openDepartamentoModal}
      onDeleteDepartamento={handleDeleteDepartamento}
    />
  </div>

  <!-- Componente: Modal Facultad (Create/Edit) -->
  <FacultadForm
    bind:isOpen={showModal}
    isEditing={!!editingFacultad}
    facultad={editingFacultad}
    {isLoading}
    onSubmit={handleSubmit}
    onClose={closeModal}
  />

  <!-- Componente: Modal Departamento (Crear) -->
  <DepartamentoModal
    bind:isOpen={showDepartamentoModal}
    facultadNombre={selectedFacultadForDept?.nombre || ''}
    facultadId={selectedFacultadForDept?.id_facultad || 0}
    {isLoading}
    onSubmit={handleDepartamentoSubmit}
    onClose={closeDepartamentoModal}
  />

  <!-- Componente: Confirmación de Eliminación -->
  <FacultadDeleteConfirm
    bind:isOpen={showDeleteDialog}
    {isLoading}
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
  />
</AdminLayout>
