<script lang="ts">
  import type { Asignatura } from '../types/mallaCurricular.types';
  import { assignAsignatura } from '../services/mallaApi';

  interface Props {
    planId: number;
    asignaturas: Asignatura[];
    assignedIds: Set<number>;
    onAssigned: () => void;
    routePrefix?: string;
  }

  let { planId, asignaturas, assignedIds, onAssigned, routePrefix = '/admin' }: Props = $props();

  let searchTerm = $state('');
  let currentPage = $state(1);
  const PAGE_SIZE = 15;

  let assigningId = $state<number | null>(null);
  let assignForm = $state({ agno_planificado: 1, semestre_planificado: 1 });
  let assignLoading = $state(false);
  let assignError = $state<string | null>(null);

  const filteredAsignaturas = $derived.by(() => {
    const term = searchTerm.toLowerCase().trim();
    if (!term) return asignaturas;
    return asignaturas.filter(
      (a) => a.cod_asignatura.toLowerCase().includes(term) || a.nombre.toLowerCase().includes(term),
    );
  });

  $effect(() => {
    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
    filteredAsignaturas;
    currentPage = 1;
  });

  const totalPages = $derived(Math.max(1, Math.ceil(filteredAsignaturas.length / PAGE_SIZE)));
  const pagedAsignaturas = $derived(
    filteredAsignaturas.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE),
  );

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
    assignAsignatura(
      planId,
      { id_asignatura: assigningId, ...assignForm },
      {
        onSuccess: () => {
          assignLoading = false;
          assigningId = null;
          onAssigned();
        },
        onError: () => {
          assignLoading = false;
          assignError = 'Error al asignar. Inténtalo de nuevo.';
        },
      },
      routePrefix,
    );
  }
</script>

<div class="flex flex-col border-r border-gray-200 overflow-hidden h-full">
  <!-- Header -->
  <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 shrink-0">
    <h2 class="text-sm font-semibold text-gray-700 mb-2">
      Catálogo de Asignaturas
      <span class="ml-1 text-xs font-normal text-gray-400"
        >({filteredAsignaturas.length} resultados)</span
      >
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
        stroke-linejoin="round"
        ><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg
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
          class="mb-3"
        >
          <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
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
                <span class="font-mono text-xs font-bold text-blue-600 shrink-0"
                  >{asignatura.cod_asignatura}</span
                >
                {#if isAssigned}
                  <span
                    class="text-[10px] font-medium bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full shrink-0"
                    >Asignada</span
                  >
                {/if}
              </div>
              <p class="text-sm text-gray-800 font-medium truncate leading-tight">
                {asignatura.nombre}
              </p>
              <p class="text-xs text-gray-400 mt-0.5">
                {asignatura.creditos_sct ?? 0} créditos SCT
              </p>
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
                    stroke-linejoin="round"
                  >
                    <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
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
                    stroke-linejoin="round"
                  >
                    <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
                  </svg>
                  Asignar
                </button>
              {/if}
            {/if}
          </div>

          <!-- Inline assign form -->
          {#if isExpanded}
            <div class="px-4 pb-3 border-t border-blue-200 bg-blue-50">
              {#if assignError}
                <p class="text-xs text-red-600 py-1.5">{assignError}</p>
              {/if}
              <div class="flex items-end gap-2 pt-2.5">
                <div class="flex-1">
                  <label for="assign-agno" class="block text-xs font-medium text-gray-600 mb-1"
                    >Año</label
                  >
                  <select bind:value={assignForm.agno_planificado} id="assign-agno">
                    {#each Array.from({ length: 10 }, (_, i) => i + 1) as y}
                      <option value={y}>{y}</option>
                    {/each}
                  </select>
                </div>
                <div class="flex-1">
                  <label for="assign-semestre" class="block text-xs font-medium text-gray-600 mb-1"
                    >Semestre</label
                  >
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
  <div
    class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 flex items-center justify-between shrink-0"
  >
    <span class="text-xs text-gray-500">Página {currentPage} de {totalPages}</span>
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
