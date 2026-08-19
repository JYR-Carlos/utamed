<script lang="ts">
  /**
   * Página de administración de departamentos.
   *
   * Orquestador modular para la gestión CRUD de departamentos.
   * Los departamentos pertenecen a facultades y contienen carreras.
   *
   * Estructura de componentes:
   * - DepartamentoList: tabla expandible con carreras anidadas
   * - DepartamentoForm: modal para crear/editar departamentos
   * - DepartamentoDeleteConfirm: confirmación de eliminación
   *
   * Tabla relacionada:
   * - administrativo.departamento: Información de departamentos
   */
  import { page } from '@inertiajs/svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import PageHeader from '@/components/admin/PageHeader.svelte';
  import DepartamentoList from '@/modules/resources/departamento/components/departamentoList.svelte';
  import DepartamentoForm from '@/modules/resources/departamento/components/departamentoForm.svelte';
  import DepartamentoDeleteConfirm from '@/modules/resources/departamento/components/departamentoDeleteConfirm.svelte';
  import {
    createDepartamento,
    updateDepartamento,
    deleteDepartamento,
  } from '@/modules/resources/departamento/services/departamentoApi';
  import type {
    Departamento,
    Facultad,
    PaginatedResponse,
    DepartamentoFormData,
  } from '@/types/admin.types';
  import type { BreadcrumbItem } from '@/types';

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

  let {
    departamentos,
    facultades,
    filters,
    canCreate = false,
    canEdit = false,
    canDelete = false,
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departamentos', href: '/admin/departamentos' },
  ];

  const flashSuccess = $derived(($page.props as any).flash?.success as string | undefined);
  const flashError = $derived(($page.props as any).flash?.error as string | undefined);

  // Estado para formularios y diálogos
  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingDepartamento = $state<Departamento | null>(null);
  let deletingDepartamento = $state<Departamento | null>(null);

  /**
   * Abre el modal para crear un nuevo departamento.
   */
  function openCreateModal() {
    editingDepartamento = null;
    showModal = true;
  }

  /**
   * Abre el modal para editar un departamento existente.
   */
  function openEditModal(departamento: Departamento) {
    editingDepartamento = departamento;
    showModal = true;
  }

  /**
   * Cierra el modal de departamento.
   */
  function closeModal() {
    showModal = false;
    editingDepartamento = null;
  }

  /**
   * Envía el formulario de departamento (crear o editar).
   */
  function handleSubmit(formData: DepartamentoFormData) {
    isLoading = true;

    if (editingDepartamento) {
      updateDepartamento(editingDepartamento.id_departamento, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    } else {
      createDepartamento(formData, {
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
   * Abre el diálogo de confirmación para eliminar un departamento.
   */
  function openDeleteDialog(departamento: Departamento) {
    deletingDepartamento = departamento;
    showDeleteDialog = true;
  }

  /**
   * Cierra el diálogo de confirmación.
   */
  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingDepartamento = null;
  }

  /**
   * Confirma la eliminación (soft delete) de un departamento.
   */
  function handleDelete() {
    if (!deletingDepartamento) return;

    isLoading = true;
    deleteDepartamento(deletingDepartamento.id_departamento, {
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

<AdminLayout {breadcrumbs}>
  <div>
    <PageHeader title="Departamentos" subtitle="Gestión de departamentos por facultad">
      {#snippet primaryAction()}
        {#if canCreate}
          <button onclick={openCreateModal} class="btn btn-primary">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Nuevo departamento
          </button>
        {/if}
      {/snippet}
    </PageHeader>

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

    <!-- Componente: Lista de Departamentos (Tabla expandible) -->
    <DepartamentoList
      {departamentos}
      {canEdit}
      {canDelete}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
    />
  </div>

  <!-- Componente: Modal Formulario Departamento -->
  <DepartamentoForm
    bind:isOpen={showModal}
    {editingDepartamento}
    {facultades}
    {isLoading}
    onSubmit={handleSubmit}
    onClose={closeModal}
  />

  <!-- Componente: Diálogo Confirmación Eliminación -->
  <DepartamentoDeleteConfirm
    bind:isOpen={showDeleteDialog}
    departamento={deletingDepartamento}
    {isLoading}
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
  />
</AdminLayout>
