<script lang="ts">
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import {
    AsignaturaList,
    AsignaturaForm,
    AsignaturaDeleteConfirm,
  } from '@/modules/resources/asignatura';
  import type { Asignatura, PaginatedResponse } from '@/types/admin.types';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    asignaturas: PaginatedResponse<Asignatura>;
    filters: { search?: string };
  }

  let { asignaturas, filters }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asignaturas', href: '/admin/asignaturas' },
  ];

  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let editingAsignatura = $state<Asignatura | null>(null);
  let deletingAsignatura = $state<Asignatura | null>(null);

  function openCreateModal() {
    editingAsignatura = null;
    showModal = true;
  }

  function openEditModal(asignatura: Asignatura) {
    editingAsignatura = asignatura;
    showModal = true;
  }

  function openDeleteDialog(asignatura: Asignatura) {
    deletingAsignatura = asignatura;
    showDeleteDialog = true;
  }
</script>

<AdminLayout {breadcrumbs}>
  <AsignaturaList
    {asignaturas}
    onCreateNew={openCreateModal}
    onEdit={openEditModal}
    onDelete={openDeleteDialog}
  />

  <AsignaturaForm
    bind:isOpen={showModal}
    {editingAsignatura}
    onClose={() => {
      showModal = false;
      editingAsignatura = null;
    }}
  />

  <AsignaturaDeleteConfirm
    bind:isOpen={showDeleteDialog}
    {deletingAsignatura}
    onSuccess={() => {
      deletingAsignatura = null;
    }}
    onCancel={() => {
      showDeleteDialog = false;
      deletingAsignatura = null;
    }}
  />
</AdminLayout>
