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
  import { router } from '@inertiajs/svelte';
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import MallaSlideOver from '@/components/custom/admin/MallaSlideOver.svelte';
  import type {
    Plan,
    Carrera,
    PaginatedResponse,
    PlanFormData,
    MallaData,
  } from '@/types/admin.types';

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
  }

  let { planes, carreras, filters }: Props = $props();

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

  let formData = $state<PlanFormData>({
    id_carrera: 0,
    agno: new Date().getFullYear(),
    version_plan: 1,
  });

  const columns = [
    { key: 'id_plan', label: 'ID' },
    { key: 'carrera.nombre', label: 'Carrera' },
    { key: 'agno', label: 'Año' },
    { key: 'version_plan', label: 'Versión' },
    { key: 'creditos_sct_totales', label: 'Créditos SCT' },
    { key: 'malla', label: 'Malla' },
  ];

  function openCreateModal() {
    editingPlan = null;
    formData = {
      id_carrera: 0,
      agno: new Date().getFullYear(),
      version_plan: 1,
    };
    showModal = true;
  }

  function openEditModal(plan: Plan) {
    editingPlan = plan;
    formData = {
      id_carrera: plan.id_carrera,
      agno: plan.agno,
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
      router.put(`/admin/planes/${editingPlan.id_plan}`, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: () => {
          isLoading = false;
        },
      });
    } else {
      router.post('/admin/planes', formData, {
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
    router.delete(`/admin/planes/${deletingPlan.id_plan}`, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  async function verMalla(plan: Plan) {
    mallaPlan = plan;
    mallaData = null;
    mallaLoading = true;
    showMallaPanel = true;

    try {
      const res = await fetch(`/admin/planes/${plan.id_plan}/asignaturas/json`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
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
    router.visit(`/admin/planes/${plan.id_plan}/asignaturas`);
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

<AdminLayout>
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

    <DataTable
      data={planes}
      {columns}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      {cellSnippet}
    />
  </div>

  <FormModal
    bind:isOpen={showModal}
    title={editingPlan ? 'Editar Plan' : 'Nuevo Plan'}
    onClose={closeModal}
    onSubmit={handleSubmit}
    {isLoading}
  >
    <div class="mb-4">
      <label for="carrera" class="block text-sm font-medium text-gray-700 mb-2">Carrera *</label>
      <select
        id="carrera"
        bind:value={formData.id_carrera}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        required
      >
        <option value={0}>Seleccione una carrera</option>
        {#each carreras as carrera}
          <option value={carrera.id_carrera}>{carrera.nombre}</option>
        {/each}
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="mb-4">
        <label for="agno" class="block text-sm font-medium text-gray-700 mb-2">Año *</label>
        <input
          id="agno"
          type="number"
          bind:value={formData.agno}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          min="1900"
          max="2100"
          placeholder="Ej: 2024"
          required
        />
      </div>

      <div class="mb-4">
        <label for="version_plan" class="block text-sm font-medium text-gray-700 mb-2"
          >Versión *</label
        >
        <input
          id="version_plan"
          type="number"
          bind:value={formData.version_plan}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          min="1"
          placeholder="Ej: 1"
          required
        />
      </div>
    </div>

    <div class="mb-4">
      <label for="creditos" class="block text-sm font-medium text-gray-700 mb-2"
        >Créditos SCT Totales</label
      >
      <div class="flex items-center gap-3 p-4 bg-gray-50 border border-gray-200 rounded-md">
        <span class="text-2xl font-bold text-blue-500"
          >{editingPlan?.creditos_sct_totales || 0}</span
        >
        <span class="text-xs text-gray-500 italic">(Calculado automáticamente)</span>
      </div>
    </div>
  </FormModal>

  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Plan?"
    message="Esta acción no se puede deshacer. Si el plan tiene asignaturas asignadas, no podrá ser eliminado."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
  />

  <MallaSlideOver
    isOpen={showMallaPanel}
    plan={mallaPlan}
    malla={mallaData}
    isLoading={mallaLoading}
    onClose={closeMallaPanel}
  />
</AdminLayout>
