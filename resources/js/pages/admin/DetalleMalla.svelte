<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import type { Plan, Asignatura, AsignacionPlan, MallaData } from '@/types/admin.types';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';

  interface Props {
    plan: Plan;
    malla: MallaData;
    asignaturas: Asignatura[];
    flash?: { error?: string; success?: string };
  }

  let { plan, malla, asignaturas = [], flash }: Props = $props();

  // ── Left column: catalog ─────────────────────────────────────────────────
  let searchTerm = $state('');
  let currentPage = $state(1);
  const PAGE_SIZE = 15;

  const assignedIds = $derived.by(() => {
    const ids = new Set<number>();
    Object.values(malla ?? {}).forEach((list) => list.forEach((a) => ids.add(a.id_asignatura)));
    return ids;
  });

  const filteredAsignaturas = $derived.by(() => {
    const term = searchTerm.toLowerCase().trim();
    if (!term) return asignaturas;
    return asignaturas.filter((a) => a.cod_asignatura.toLowerCase().includes(term) || a.nombre.toLowerCase().includes(term));
  });

  $effect(() => {
    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
    filteredAsignaturas;
    currentPage = 1;
  });

  const totalPages = $derived(Math.max(1, Math.ceil(filteredAsignaturas.length / PAGE_SIZE)));

  const pagedAsignaturas = $derived(filteredAsignaturas.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE));

  // ── Inline assign form ───────────────────────────────────────────────────
  let assigningId = $state<number | null>(null);
  let assignForm = $state({ agno_planificado: 1, semestre_planificado: 1 });
  let assignLoading = $state(false);
  let assignError = $state<string | null>(null);

  function startAssign(asignatura: Asignatura) {
    assigningId = asignatura.id_asignatura;
    assignForm = { agno_planificado: 1, semestre_planificado: 1 };
    assignError = null;
  }

  function cancelAssign() {
    assigningId = null;
    assignError = null;
  }

  function confirmAssign() {
    if (!assigningId) return;
    assignLoading = true;
    assignError = null;
    router.post(
      `/admin/planes/${plan.id_plan}/asignaturas`,
      { id_asignatura: assigningId, ...assignForm },
      {
        onSuccess: () => {
          assignLoading = false;
          assigningId = null;
        },
        onError: () => {
          assignLoading = false;
          assignError = 'Error al asignar. Inténtalo de nuevo.';
        },
      },
    );
  }

  // ── Right column: malla ──────────────────────────────────────────────────
  const mallaByYear = $derived.by(() => {
    const years: Record<number, { semestre1: AsignacionPlan[]; semestre2: AsignacionPlan[] }> = {};
    Object.values(malla).forEach((list) => {
      list.forEach((asig) => {
        if (!years[asig.agno_planificado]) {
          years[asig.agno_planificado] = { semestre1: [], semestre2: [] };
        }
        if (asig.semestre_planificado === 1) {
          years[asig.agno_planificado].semestre1.push(asig);
        } else {
          years[asig.agno_planificado].semestre2.push(asig);
        }
      });
    });
    return years;
  });

  const sortedYears = $derived(Object.entries(mallaByYear).sort(([a], [b]) => Number(a) - Number(b)));

  const totalAssigned = $derived(Object.values(malla).reduce((sum, list) => sum + list.length, 0));

  const totalCredits = $derived(
    Object.values(malla)
      .flat()
      .reduce((sum, a) => sum + (a.asignatura?.creditos_sct ?? 0), 0),
  );

  // ── Edit modal ───────────────────────────────────────────────────────────
  let showEditModal = $state(false);
  let editingAsignacion = $state<AsignacionPlan | null>(null);
  let editForm = $state({ agno_planificado: 1, semestre_planificado: 1, tipo_ramo: '' });
  let editLoading = $state(false);
  let editError = $state<string | null>(null);

  function openEditModal(asignacion: AsignacionPlan) {
    editingAsignacion = asignacion;
    editForm = {
      agno_planificado: asignacion.agno_planificado,
      semestre_planificado: asignacion.semestre_planificado,
      tipo_ramo: asignacion.tipo_ramo ?? '',
    };
    editError = null;
    showEditModal = true;
  }

  function closeEditModal() {
    showEditModal = false;
    editingAsignacion = null;
    editError = null;
  }

  function handleEdit() {
    if (!editingAsignacion) return;
    editLoading = true;
    router.put(`/admin/planes/${plan.id_plan}/asignaturas/${editingAsignacion.id_asignatura}`, editForm, {
      onSuccess: () => {
        closeEditModal();
        editLoading = false;
      },
      onError: () => {
        editLoading = false;
        editError = 'Error al actualizar la asignación.';
      },
    });
  }

  // ── Delete ───────────────────────────────────────────────────────────────
  let showDeleteDialog = $state(false);
  let deletingAsignacion = $state<AsignacionPlan | null>(null);
  let deleteLoading = $state(false);

  function openDeleteDialog(asignacion: AsignacionPlan) {
    deletingAsignacion = asignacion;
    showDeleteDialog = true;
  }

  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingAsignacion = null;
  }

  function handleDelete() {
    if (!deletingAsignacion) return;
    deleteLoading = true;
    router.delete(`/admin/planes/${plan.id_plan}/asignaturas/${deletingAsignacion.id_asignatura}`, {
      onSuccess: () => {
        closeDeleteDialog();
        deleteLoading = false;
      },
      onError: () => {
        deleteLoading = false;
      },
    });
  }
</script>

<AdminLayout>
  <!-- ── Page header ─────────────────────────────────────────────────────── -->
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <a href="/admin/planes" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 mb-2 transition-colors">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"><polyline points="15 18 9 12 15 6" /></svg
        >
        Volver a Planes
      </a>
      <h1 class="text-2xl font-bold text-gray-900">Editar Malla Curricular</h1>
      <p class="text-sm text-gray-500 mt-0.5">
        {plan.carrera?.nombre ?? ''} · Año {plan.agno} · v{plan.version_plan}
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
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
      >
      {flash.success}
    </div>
  {/if}
  {#if flash?.error}
    <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
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
        ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg
      >
      {flash.error}
    </div>
  {/if}

  <!-- ── Two-column layout ──────────────────────────────────────────────── -->
  <div
    class="grid grid-cols-[5fr_7fr] gap-0 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm"
    style="height: calc(100vh - 14rem);"
  >
    <!-- ═══ LEFT: Catálogo de asignaturas ════════════════════════════════ -->
    <div class="flex flex-col border-r border-gray-200 overflow-hidden">
      <!-- Header -->
      <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 shrink-0">
        <h2 class="text-sm font-semibold text-gray-700 mb-2">
          Catálogo de Asignaturas
          <span class="ml-1 text-xs font-normal text-gray-400">({filteredAsignaturas.length} resultados)</span>
        </h2>
        <div class="relative">
          <svg
            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg
          >
          <input
            type="text"
            placeholder="Buscar por código o nombre..."
            bind:value={searchTerm}
            class="w-full pl-8 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 bg-white"
          />
        </div>
      </div>

      <!-- Subject list -->
      <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
        {#if pagedAsignaturas.length === 0}
          <div class="flex flex-col items-center justify-center py-16 text-gray-400">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="36"
              height="36"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="mb-3"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg
            >
            <p class="text-sm">Sin resultados</p>
          </div>
        {:else}
          {#each pagedAsignaturas as asignatura (asignatura.id_asignatura)}
            {@const isAssigned = assignedIds.has(asignatura.id_asignatura)}
            {@const isExpanded = assigningId === asignatura.id_asignatura}
            <div class="transition-colors {isExpanded ? 'bg-blue-50' : 'hover:bg-gray-50'}">
              <!-- Subject row -->
              <div class="flex items-center gap-2 px-4 py-2.5">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-0.5">
                    <span class="font-mono text-xs font-bold text-blue-600 shrink-0">{asignatura.cod_asignatura}</span>
                    {#if isAssigned}
                      <span class="text-[10px] font-medium bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full shrink-0">Asignada</span>
                    {/if}
                  </div>
                  <p class="text-sm text-gray-800 font-medium truncate leading-tight">{asignatura.nombre}</p>
                  <p class="text-xs text-gray-400 mt-0.5">{asignatura.creditos_sct ?? 0} créditos SCT</p>
                </div>
                {#if !isAssigned}
                  {#if isExpanded}
                    <button
                      onclick={cancelAssign}
                      class="shrink-0 p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-colors border-0 cursor-pointer bg-transparent"
                      title="Cancelar"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
                      >
                    </button>
                  {:else}
                    <button
                      onclick={() => startAssign(asignatura)}
                      class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs font-medium border-0 cursor-pointer transition-colors"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg
                      >
                      Asignar
                    </button>
                  {/if}
                {/if}
              </div>

              <!-- Inline assign form (expands when clicked) -->
              {#if isExpanded}
                <div class="px-4 pb-3 border-t border-blue-200 bg-blue-50">
                  {#if assignError}
                    <p class="text-xs text-red-600 py-1.5">{assignError}</p>
                  {/if}
                  <div class="flex items-end gap-2 pt-2.5">
                    <div class="flex-1">
                      <label for="assign-agno" class="block text-xs font-medium text-gray-600 mb-1">Año</label>
                      <select bind:value={assignForm.agno_planificado} id="assign-agno">
                        {#each Array.from({ length: 10 }, (_, i) => i + 1) as y}
                          <option value={y}>{y}</option>
                        {/each}
                      </select>
                    </div>
                    <div class="flex-1">
                      <label for="assign-semestre" class="block text-xs font-medium text-gray-600 mb-1">Semestre</label>
                      <select bind:value={assignForm.semestre_planificado} id="assign-semestre">
                        <option value={1}>1</option>
                        <option value={2}>2</option>
                      </select>
                    </div>
                    <button
                      onclick={confirmAssign}
                      disabled={assignLoading}
                      class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white rounded-md text-xs font-semibold border-0 cursor-pointer transition-colors whitespace-nowrap"
                    >
                      {assignLoading ? 'Asignando...' : '✓ Confirmar'}
                    </button>
                  </div>
                </div>
              {/if}
            </div>
          {/each}
        {/if}
      </div>

      <!-- Pagination -->
      <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex items-center justify-between shrink-0">
        <span class="text-xs text-gray-500">
          Página {currentPage} de {totalPages}
        </span>
        <div class="flex gap-1">
          <button
            onclick={() => (currentPage = Math.max(1, currentPage - 1))}
            disabled={currentPage === 1}
            class="px-2.5 py-1 text-xs border border-gray-300 rounded bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >Anterior</button
          >
          <button
            onclick={() => (currentPage = Math.min(totalPages, currentPage + 1))}
            disabled={currentPage === totalPages}
            class="px-2.5 py-1 text-xs border border-gray-300 rounded bg-white text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >Siguiente</button
          >
        </div>
      </div>
    </div>

    <!-- ═══ RIGHT: Malla del plan ════════════════════════════════════════ -->
    <div class="flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 shrink-0">
        <h2 class="text-sm font-semibold text-gray-700">Malla del Plan</h2>
        <p class="text-xs text-gray-400 mt-0.5">Asignaturas organizadas por año y semestre</p>
      </div>

      <!-- Malla content -->
      <div class="flex-1 overflow-y-auto p-5">
        {#if sortedYears.length === 0}
          <div class="flex flex-col items-center justify-center h-full text-gray-400">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="48"
              height="48"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="mb-3"
              ><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line
                x1="16"
                y1="13"
                x2="8"
                y2="13"
              /><line x1="16" y1="17" x2="8" y2="17" /></svg
            >
            <p class="text-sm font-medium">Sin asignaturas asignadas</p>
            <p class="text-xs mt-1">Usa el catálogo de la izquierda para agregar asignaturas</p>
          </div>
        {:else}
          <div class="flex flex-col gap-5">
            {#each sortedYears as [year, semesters]}
              <div class="border border-gray-200 rounded-lg overflow-hidden">
                <!-- Year header -->
                <div class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold">
                  Año {year}
                  <span class="ml-2 text-xs font-normal text-gray-400">
                    ({semesters.semestre1.length + semesters.semestre2.length} asignaturas)
                  </span>
                </div>

                <!-- Semesters grid -->
                <div class="grid grid-cols-2 divide-x divide-gray-200">
                  <!-- Semestre 1 -->
                  <div class="p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                      <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
                      Semestre 1
                    </p>
                    <div class="flex flex-col gap-2">
                      {#if semesters.semestre1.length === 0}
                        <p class="text-xs text-gray-300 italic py-2 text-center">Sin asignaturas</p>
                      {:else}
                        {#each semesters.semestre1 as asignacion}
                          <div class="bg-white border border-gray-200 rounded-md p-2.5 hover:border-blue-300 transition-colors">
                            <div class="flex items-start justify-between gap-2 mb-1">
                              <span class="font-mono text-xs font-bold text-blue-600">{asignacion.asignatura?.cod_asignatura}</span>
                              <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold shrink-0">
                                {asignacion.asignatura?.creditos_sct ?? 0} SCT
                              </span>
                            </div>
                            <p class="text-xs text-gray-800 font-medium leading-snug mb-1.5">{asignacion.asignatura?.nombre}</p>
                            {#if asignacion.tipo_ramo}
                              <span class="inline-block text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded mb-1.5"
                                >{asignacion.tipo_ramo}</span
                              >
                            {/if}
                            <div class="flex gap-1.5">
                              <button
                                onclick={() => openEditModal(asignacion)}
                                class="px-2 py-1 bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-700 border-0 rounded text-[11px] font-medium cursor-pointer transition-colors"
                                >Editar</button
                              >
                              <button
                                onclick={() => openDeleteDialog(asignacion)}
                                class="px-2 py-1 bg-gray-100 hover:bg-red-100 text-gray-600 hover:text-red-600 border-0 rounded text-[11px] font-medium cursor-pointer transition-colors"
                                >Quitar</button
                              >
                            </div>
                          </div>
                        {/each}
                      {/if}
                    </div>
                  </div>

                  <!-- Semestre 2 -->
                  <div class="p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                      <span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>
                      Semestre 2
                    </p>
                    <div class="flex flex-col gap-2">
                      {#if semesters.semestre2.length === 0}
                        <p class="text-xs text-gray-300 italic py-2 text-center">Sin asignaturas</p>
                      {:else}
                        {#each semesters.semestre2 as asignacion}
                          <div class="bg-white border border-gray-200 rounded-md p-2.5 hover:border-indigo-300 transition-colors">
                            <div class="flex items-start justify-between gap-2 mb-1">
                              <span class="font-mono text-xs font-bold text-indigo-600">{asignacion.asignatura?.cod_asignatura}</span>
                              <span class="text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-semibold shrink-0">
                                {asignacion.asignatura?.creditos_sct ?? 0} SCT
                              </span>
                            </div>
                            <p class="text-xs text-gray-800 font-medium leading-snug mb-1.5">{asignacion.asignatura?.nombre}</p>
                            {#if asignacion.tipo_ramo}
                              <span class="inline-block text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded mb-1.5"
                                >{asignacion.tipo_ramo}</span
                              >
                            {/if}
                            <div class="flex gap-1.5">
                              <button
                                onclick={() => openEditModal(asignacion)}
                                class="px-2 py-1 bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-700 border-0 rounded text-[11px] font-medium cursor-pointer transition-colors"
                                >Editar</button
                              >
                              <button
                                onclick={() => openDeleteDialog(asignacion)}
                                class="px-2 py-1 bg-gray-100 hover:bg-red-100 text-gray-600 hover:text-red-600 border-0 rounded text-[11px] font-medium cursor-pointer transition-colors"
                                >Quitar</button
                              >
                            </div>
                          </div>
                        {/each}
                      {/if}
                    </div>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </div>
  </div>

  <!-- ── Edit modal ─────────────────────────────────────────────────────── -->
  <FormModal bind:isOpen={showEditModal} title="Editar Asignación" onClose={closeEditModal} onSubmit={handleEdit} isLoading={editLoading}>
    {#if editError}
      <div class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">{editError}</div>
    {/if}
    {#if editingAsignacion}
      <p class="mb-4 text-sm text-gray-600">
        <span class="font-mono font-bold text-blue-600">{editingAsignacion.asignatura?.cod_asignatura}</span>
        — {editingAsignacion.asignatura?.nombre}
      </p>
    {/if}
    <div class="grid grid-cols-2 gap-4 mb-4">
      <div>
        <label for="edit-agno" class="block text-sm font-medium text-gray-700 mb-1.5">Año *</label>
        <select
          bind:value={editForm.agno_planificado}
          id="edit-agno"
          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 bg-white"
          required
        >
          {#each Array.from({ length: 10 }, (_, i) => i + 1) as y}
            <option value={y}>{y}</option>
          {/each}
        </select>
      </div>
      <div>
        <label for="edit-semestre" class="block text-sm font-medium text-gray-700 mb-1.5">Semestre *</label>
        <select
          bind:value={editForm.semestre_planificado}
          id="edit-semestre"
          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 bg-white"
          required
        >
          <option value={1}>1</option>
          <option value={2}>2</option>
        </select>
      </div>
    </div>
    <div>
      <label for="edit-tipo-ramo" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Ramo</label>
      <select
        bind:value={editForm.tipo_ramo}
        id="edit-tipo-ramo"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 bg-white"
      >
        <option value="">Sin tipo (opcional)</option>
        <option value="Electivo Profesional">Electivo Profesional</option>
        <option value="Plan Común">Plan Común</option>
        <option value="Formación Profesional">Formación Profesional</option>
      </select>
    </div>
  </FormModal>

  <!-- ── Delete confirmation ────────────────────────────────────────────── -->
  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Quitar Asignatura del Plan?"
    message="La asignatura será removida de esta malla. Esta acción no se puede deshacer."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    isLoading={deleteLoading}
  />
</AdminLayout>
