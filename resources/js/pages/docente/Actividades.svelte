<script lang="ts">
  /**
   * Página de gestión de actividades/tareas en un curso.
   *
   * Permite a docentes crear, leer, actualizar y eliminar actividades
   * (tareas, evaluaciones, trabajos) en sus cursos.
   *
   * Características:
   * - CRUD de actividades con datos como nombre, tipo, fecha límite
   * - Soporte para actividades individuales o grupales
   * - Asignación a secciones específicas del curso
   * - Organización por unidades temáticas
   * - Configuración de tipo de entrega (online, presencial, etc.)
   * - Validación de acceso (solo docente responsable del curso)
   *
   * Tabla relacionada:
   * - agenda.actividad: Información de actividades/tareas
   */
  import { Link, page } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import {
    ActividadList,
    ActividadForm,
    ActividadDeleteConfirm,
    createActividad,
    updateActividad,
    deleteActividad,
    toggleVisibilidadActividad,
  } from '@/modules/resources/actividad';
  import { Plus, ArrowLeft } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';

  interface Props {
    curso: {
      id_curso: number;
      cod_asignatura: string;
      asignatura_nombre: string;
      es_titular_curso?: boolean;
      userPermissions?: Permission[];
    };
    actividades: Actividad[];
    componentes: any[];
    unidades: any[];
  }

  let { curso, actividades, componentes, unidades }: Props = $props();

  const canCreate = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades:crear'),
  );
  const canEdit = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades:editar'),
  );
  const canDelete = $derived(
    curso.es_titular_curso || hasPermission(curso.userPermissions ?? [], 'actividades:eliminar'),
  );

  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingActividad = $state<Actividad | null>(null);
  let deletingActividad = $state<Actividad | null>(null);
  let formErrors = $state<Record<string, string>>({});

  let flashError = $state<string | undefined>(undefined);
  let flashSuccess = $state<string | undefined>(undefined);

  // Detectar errores de validación desde $page.props
  $effect(() => {
    const pageErrors = ($page.props as any).errors as Record<string, string> | undefined;
    if (pageErrors && Object.keys(pageErrors).length > 0) {
      formErrors = pageErrors;
      isLoading = false;
    }
  });

  $effect(() => {
    const err = ($page.props as any).flash?.error as string | undefined;
    if (err) {
      flashError = err;
      const t = setTimeout(() => {
        flashError = undefined;
      }, 5000);
      return () => clearTimeout(t);
    }
  });

  $effect(() => {
    const ok = ($page.props as any).flash?.success as string | undefined;
    if (ok) {
      flashSuccess = ok;
      const t = setTimeout(() => {
        flashSuccess = undefined;
      }, 4000);
      return () => clearTimeout(t);
    }
  });

  function openCreateModal() {
    editingActividad = null;
    formErrors = {};
    showModal = true;
  }

  function openEditModal(actividad: Actividad) {
    editingActividad = actividad;
    formErrors = {};
    showModal = true;
  }

  function handleSubmit(data: Partial<Actividad>) {
    isLoading = true;
    formErrors = {};

    const done = () => {
      showModal = false;
      editingActividad = null;
      isLoading = false;
      formErrors = {};
    };
    const fail = (errors: Record<string, string>) => {
      formErrors = errors;
      isLoading = false;
    };

    if (editingActividad) {
      updateActividad(curso.id_curso, editingActividad.id_actividad, data, {
        onSuccess: done,
        onError: fail,
      });
    } else {
      createActividad(curso.id_curso, data, { onSuccess: done, onError: fail });
    }
  }

  function handleToggleVisible(actividad: Actividad) {
    toggleVisibilidadActividad(curso.id_curso, actividad.id_actividad);
  }

  function openDeleteDialog(actividad: Actividad) {
    deletingActividad = actividad;
    showDeleteDialog = true;
  }

  function handleDelete() {
    if (!deletingActividad) return;

    isLoading = true;
    deleteActividad(curso.id_curso, deletingActividad.id_actividad, {
      onSuccess: () => {
        showDeleteDialog = false;
        deletingActividad = null;
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }
</script>

<DocenteLayout>
  <div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto">
    <!-- Header with back button -->
    <div class="mb-8">
      <div class="mb-4">
        <Link
          href="/docente/cursos"
          class="inline-flex items-center gap-2 text-blue-500 font-medium hover:text-blue-600 transition-colors py-2"
        >
          <ArrowLeft size={20} />
          Mis Cursos
        </Link>
      </div>
      <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-0">Actividades del Curso</h1>
          <p class="text-gray-600 text-base mt-2">
            {curso.cod_asignatura} - {curso.asignatura_nombre}
          </p>
        </div>
        <div class="flex gap-3">
          {#if canCreate}
            <button
              onclick={openCreateModal}
              class="inline-flex items-center gap-2 px-6 py-3 bg-blue-500 text-white rounded-lg font-semibold hover:bg-blue-600 transition-colors whitespace-nowrap"
            >
              <Plus size={20} />
              Nueva Actividad
            </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Activities List -->
    {#if flashError}
      <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4">
        <p class="text-sm font-medium text-red-800">{flashError}</p>
      </div>
    {/if}
    {#if flashSuccess}
      <div class="mb-6 rounded-md bg-green-50 border border-green-200 p-4">
        <p class="text-sm font-medium text-green-800">{flashSuccess}</p>
      </div>
    {/if}
    <ActividadList
      {actividades}
      idCurso={curso.id_curso}
      {canCreate}
      {canEdit}
      {canDelete}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      onCreateClick={openCreateModal}
      onToggleVisible={handleToggleVisible}
    />
  </div>

  <ActividadForm
    bind:isOpen={showModal}
    {isLoading}
    {editingActividad}
    {componentes}
    {unidades}
    errors={formErrors}
    onClose={() => {
      showModal = false;
      editingActividad = null;
      formErrors = {};
    }}
    onSubmit={handleSubmit}
  />

  <ActividadDeleteConfirm
    bind:isOpen={showDeleteDialog}
    {isLoading}
    onConfirm={handleDelete}
    onCancel={() => {
      showDeleteDialog = false;
      deletingActividad = null;
    }}
  />
</DocenteLayout>
