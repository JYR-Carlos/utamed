<script lang="ts">
  /**
   * Asignaturas — Página de gestión del catálogo de asignaturas.
   *
   * Vista compartida por dos roles; el controlador que la renderiza define el
   * routePrefix con el que operan los modales:
   * - Admin\AsignaturaController → '/admin' (catálogo completo)
   * - Docente\JefeCarrera\AsignaturaController → '/docente/jefe-carrera'
   *   (solo asignaturas en planes de su carrera)
   *
   * Orquesta la lista y los modales de crear/editar y eliminar: el estado de
   * qué asignatura está en edición/borrado vive aquí y baja por props.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import {
    AsignaturaList,
    AsignaturaForm,
    AsignaturaDeleteConfirm,
  } from '@/modules/resources/asignatura';
  import type { Asignatura, PaginatedResponse } from '@/types/admin.types';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    /** Página actual del catálogo (paginada y ordenada por el backend). */
    asignaturas: PaginatedResponse<Asignatura>;
    /**
     * Búsqueda aplicada en el servidor (nombre/código). El backend ya la
     * soporta pero la página aún no renderiza un input de búsqueda.
     */
    filters: { search?: string };
    /** Prefijo base de rutas. '/admin' (default) o '/docente/jefe-carrera'. */
    routePrefix?: string;
  }

  let { asignaturas, filters, routePrefix = '/admin' }: Props = $props();

  const isJefe = $derived(routePrefix !== '/admin');

  const breadcrumbs: BreadcrumbItem[] = $derived(
    isJefe
      ? [
          { title: 'Jefe de Carrera', href: '/docente/jefe-carrera/dashboard' },
          { title: 'Asignaturas', href: `${routePrefix}/asignaturas` },
        ]
      : [
          { title: 'Dashboard', href: '/dashboard' },
          { title: 'Asignaturas', href: '/admin/asignaturas' },
        ],
  );

  // Estado de los modales: null en editingAsignatura = modo creación.
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
    {routePrefix}
    onClose={() => {
      showModal = false;
      editingAsignatura = null;
    }}
  />

  <AsignaturaDeleteConfirm
    bind:isOpen={showDeleteDialog}
    {deletingAsignatura}
    {routePrefix}
    onSuccess={() => {
      deletingAsignatura = null;
    }}
    onCancel={() => {
      showDeleteDialog = false;
      deletingAsignatura = null;
    }}
  />
</AdminLayout>
