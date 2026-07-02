<script lang="ts">
  /**
   * Página de administración de planes curriculares (Mallas).
   *
   * Gestión CRUD de planes de estudio que pertenecen a carreras.
   * Cada plan es una versión/año específico de una carrera.
   *
   * Características:
   * - Filtrado por carrera
   * - Búsqueda por nombre de plan
   * - Formulario modal para crear/editar planes (requiere año y carrera)
   * - Acceso a editor visual de malla (asignaciones de asignaturas por semestre)
   * - Confirmación antes de eliminación
   *
   * Tablas relacionadas:
   * - administrativo.plan: Información de planes/mallas
   * - administrativo.carrera: Carrera padre
   * - administrativo.asignacion_plan: Asignaturas asignadas al plan
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import {
    PlanList,
    PlanForm,
    PlanDeleteConfirm,
    MallaSlideOver,
    createPlan,
    updatePlan,
    deletePlan,
    visitEditarMalla,
    fetchMalla,
  } from '@/modules/resources/plan';
  import type {
    Plan,
    Carrera,
    PaginatedResponse,
    PlanFormData,
    MallaData,
  } from '@/types/admin.types';
  import type { BreadcrumbItem } from '@/types';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Planes paginados */
    planes: PaginatedResponse<Plan>;
    /** Todas las carreras para selector */
    carreras: Carrera[];
    /** Filtros de búsqueda y carrera */
    filters: { search?: string; id_carrera?: number };
    /**
     * Prefijo de rutas para las acciones CRUD. '/admin' (default) para el panel
     * de administración; '/docente/jefe-carrera' cuando lo renderiza el Jefe de Carrera.
     */
    routePrefix?: string;
  }

  let { planes, carreras, filters, routePrefix = '/admin' }: Props = $props();

  const isJefe = $derived(routePrefix !== '/admin');

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: isJefe ? '/docente/jefe-carrera/dashboard' : '/dashboard' },
    { title: 'Planes de Estudio', href: `${routePrefix}/planes` },
  ]);

  // Si solo se entrega una carrera (caso Jefe de Carrera), se preselecciona en el formulario.
  const defaultCarreraId = $derived(carreras.length === 1 ? carreras[0].id_carrera : 0);

  // Modales
  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let isLoading = $state(false);
  let editingPlan = $state<Plan | null>(null);
  let deletingPlan = $state<Plan | null>(null);

  // Slide-over (Ver Malla)
  let showMallaPanel = $state(false);
  let mallaLoading = $state(false);
  let mallaPlan = $state<Plan | null>(null);
  let mallaData = $state<MallaData | null>(null);

  // Formulario
  let formData = $state<PlanFormData>({
    id_carrera: defaultCarreraId,
    agno_plan: new Date().getFullYear(),
    version_plan: 1,
  });

  function openCreateModal() {
    editingPlan = null;
    formData = {
      id_carrera: defaultCarreraId,
      agno_plan: new Date().getFullYear(),
      version_plan: 1,
    };
    showModal = true;
  }

  function openEditModal(plan: Plan) {
    editingPlan = plan;
    formData = {
      id_carrera: plan.id_carrera,
      agno_plan: new Date(plan.agno_plan).getFullYear(),
      version_plan: plan.version_plan,
    };
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingPlan = null;
  }

  function handleSubmit() {
    isLoading = true;

    if (editingPlan) {
      updatePlan(
        editingPlan.id_plan,
        formData,
        {
          onSuccess: () => {
            closeModal();
            isLoading = false;
          },
          onError: () => {
            isLoading = false;
          },
        },
        routePrefix,
      );
    } else {
      createPlan(
        formData,
        {
          onSuccess: () => {
            closeModal();
            isLoading = false;
          },
          onError: () => {
            isLoading = false;
          },
        },
        routePrefix,
      );
    }
  }

  function openDeleteDialog(plan: Plan) {
    deletingPlan = plan;
    showDeleteDialog = true;
  }

  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingPlan = null;
  }

  function handleDelete() {
    if (!deletingPlan) return;

    isLoading = true;
    deletePlan(
      deletingPlan.id_plan,
      {
        onSuccess: () => {
          closeDeleteDialog();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      },
      routePrefix,
    );
  }

  async function verMalla(plan: Plan) {
    mallaPlan = plan;
    mallaData = null;
    mallaLoading = true;
    showMallaPanel = true;

    try {
      const json = await fetchMalla(plan, routePrefix);
      mallaPlan = json.plan;
      mallaData = json.malla;
    } catch (err) {
      console.error('Error cargando malla:', err);
    } finally {
      mallaLoading = false;
    }
  }

  function closeMallaPanel() {
    showMallaPanel = false;
  }

  function editarMalla(plan: Plan) {
    visitEditarMalla(plan.id_plan, routePrefix);
  }
</script>

{#snippet cellSnippet({ item, column }: { item: Plan; column: { key: string; label: string } })}
  {#if column.key === 'malla'}
    <div class="flex gap-2">
      <button
        onclick={() => verMalla(item)}
        class="px-2.5 py-1 border border-indigo-300 hover:border-indigo-400 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded text-[0.73rem] font-medium cursor-pointer transition-all"
      >
        Ver Malla
      </button>
      <button
        onclick={() => editarMalla(item)}
        class="px-2.5 py-1 border border-green-300 hover:border-green-400 bg-green-50 hover:bg-green-100 text-green-700 rounded text-[0.73rem] font-medium cursor-pointer transition-all"
      >
        Editar Malla
      </button>
    </div>
  {:else}
    {column.key.split('.').reduce((v: any, k: string) => v?.[k], item) ?? '-'}
  {/if}
{/snippet}

<AdminLayout {breadcrumbs}>
  <div>
    <div class="flex justify-between items-start mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Planes de Estudio</h1>
        <p class="text-sm text-gray-500">Gestión de planes de estudio por carrera</p>
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
        Nuevo Plan
      </button>
    </div>

    <PlanList
      data={planes}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      onViewMalla={verMalla}
      onEditMalla={editarMalla}
    />
  </div>

  <PlanForm
    isOpen={showModal}
    {editingPlan}
    {carreras}
    bind:formData
    {isLoading}
    onClose={closeModal}
    onSubmit={handleSubmit}
  />

  <PlanDeleteConfirm
    isOpen={showDeleteDialog}
    {isLoading}
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
  />

  <MallaSlideOver
    isOpen={showMallaPanel}
    plan={mallaPlan}
    malla={mallaData}
    isLoading={mallaLoading}
    onClose={closeMallaPanel}
  />
</AdminLayout>
