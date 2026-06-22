<script lang="ts">
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import AsignacionDeleteConfirm from '@/modules/resources/detalle-malla/components/asignacionDeleteConfirm.svelte';
  import AsignaturasCatalogo from '@/modules/resources/detalle-malla/components/asignaturasCatalogo.svelte';
  import MallaGrid from '@/modules/resources/detalle-malla/components/mallaGrid.svelte';
  import EditAsignacionModal from '@/modules/resources/detalle-malla/components/editAsignacionModal.svelte';
  import { deleteAsignacion } from '@/modules/resources/detalle-malla/services/mallaApi';
  import type {
    Plan,
    Asignatura,
    AsignacionPlan,
    MallaData,
  } from '@/modules/resources/detalle-malla/types/mallaCurricular.types';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    plan: Plan;
    malla: MallaData;
    asignaturas: Asignatura[];
    flash?: { error?: string; success?: string };
  }

  let { plan, malla, asignaturas = [], flash }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Planes de Estudio', href: '/admin/planes' },
    { title: plan?.nombre ?? 'Detalle Malla', href: '#' },
  ];

  // ── Stats para el header ─────────────────────────────────────────────────
  const assignedIds = $derived.by(() => {
    const ids = new Set<number>();
    Object.values(malla ?? {}).forEach((list) => list.forEach((a) => ids.add(a.id_asignatura)));
    return ids;
  });

  const totalAssigned = $derived(Object.values(malla).reduce((sum, list) => sum + list.length, 0));
  const totalCredits = $derived(
    Object.values(malla)
      .flat()
      .reduce((sum, a) => sum + (a.asignatura?.creditos_sct ?? 0), 0),
  );

  // ── Edit ─────────────────────────────────────────────────────────────────
  let showEditModal = $state(false);
  let editingAsignacion = $state<AsignacionPlan | null>(null);

  function openEditModal(asignacion: AsignacionPlan) {
    editingAsignacion = asignacion;
    showEditModal = true;
  }

  // ── Delete ───────────────────────────────────────────────────────────────
  let showDeleteDialog = $state(false);
  let deletingAsignacion = $state<AsignacionPlan | null>(null);
  let deleteLoading = $state(false);

  function openDeleteDialog(asignacion: AsignacionPlan) {
    deletingAsignacion = asignacion;
    showDeleteDialog = true;
  }

  function handleDelete() {
    if (!deletingAsignacion) return;
    deleteLoading = true;
    deleteAsignacion(plan.id_plan, deletingAsignacion.id_asignatura, {
      onSuccess: () => {
        showDeleteDialog = false;
        deletingAsignacion = null;
        deleteLoading = false;
      },
      onError: () => {
        deleteLoading = false;
      },
    });
  }
</script>

<AdminLayout {breadcrumbs}>
  <!-- ── Page header ─────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <a
        href="/admin/planes"
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 mb-2 transition-colors"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <polyline points="15 18 9 12 15 6" />
        </svg>
        Volver a Planes
      </a>
      <h1 class="text-2xl font-bold text-gray-900">Editar Malla Curricular</h1>
      <p class="text-sm text-gray-500 mt-0.5">
        {plan.carrera?.nombre ?? ''} · Año {plan.agno_plan} · v{plan.version_plan}
      </p>
    </div>
    <div class="flex items-center gap-3 shrink-0">
      <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 text-center">
        <p class="text-xs text-blue-600 font-medium uppercase tracking-wide">Créditos SCT</p>
        <p class="text-2xl font-bold text-blue-700">{totalCredits}</p>
      </div>
      <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-center">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Asignaturas</p>
        <p class="text-2xl font-bold text-gray-700">{totalAssigned}</p>
      </div>
    </div>
  </div>

  <!-- ── Flash messages ─────────────────────────────────────────────────── -->
  {#if flash?.success}
    <div
      class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm"
    >
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
      >
        <polyline points="20 6 9 17 4 12" />
      </svg>
      {flash.success}
    </div>
  {/if}
  {#if flash?.error}
    <div
      class="mb-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm"
    >
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
      >
        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line
          x1="12"
          y1="16"
          x2="12.01"
          y2="16"
        />
      </svg>
      {flash.error}
    </div>
  {/if}

  <!-- ── Two-column layout ──────────────────────────────────────────────── -->
  <div
    class="grid grid-cols-[5fr_7fr] gap-0 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm"
    style="height: calc(100vh - 14rem);"
  >
    <AsignaturasCatalogo planId={plan.id_plan} {asignaturas} {assignedIds} onAssigned={() => {}} />
    <MallaGrid {malla} onEdit={openEditModal} onDelete={openDeleteDialog} />
  </div>

  <EditAsignacionModal
    bind:isOpen={showEditModal}
    planId={plan.id_plan}
    {editingAsignacion}
    onSuccess={() => {}}
  />

  <AsignacionDeleteConfirm
    bind:isOpen={showDeleteDialog}
    onConfirm={handleDelete}
    onCancel={() => {
      showDeleteDialog = false;
      deletingAsignacion = null;
    }}
    isLoading={deleteLoading}
  />
</AdminLayout>
