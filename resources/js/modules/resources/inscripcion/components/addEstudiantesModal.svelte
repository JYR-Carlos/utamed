<script lang="ts">
  import type { CursoItem, RosterItem, EstudianteDisponible } from '../types/inscripcion.types';
  import { disponibleName, cursoDisplayName } from '../types/inscripcion.types';
  import { fetchDisponibles, bulkInscribir } from '../services/inscripcionApi';

  interface Props {
    isOpen: boolean;
    activeCursoId: number;
    selectedCurso: CursoItem | null;
    onInscribed: (created: RosterItem[]) => void;
    onToast: (msg: string, type: 'success' | 'error') => void;
  }

  let {
    isOpen = $bindable(),
    activeCursoId,
    selectedCurso,
    onInscribed,
    onToast,
  }: Props = $props();

  let disponibles = $state<EstudianteDisponible[]>([]);
  let loadingDisponibles = $state(false);
  let selectedIds = $state<Set<number>>(new Set());
  let studentSearch = $state('');
  let submittingBulk = $state(false);
  let bulkContextError = $state('');

  const filteredDisponibles = $derived(
    studentSearch.trim().length === 0
      ? disponibles
      : disponibles.filter((e) => {
          const term = studentSearch.toLowerCase();
          return `${e.usuario?.nombre1 ?? ''} ${e.usuario?.apellido1 ?? ''} ${e.usuario?.username ?? ''}`
            .toLowerCase()
            .includes(term);
        }),
  );

  $effect(() => {
    if (!isOpen) return;
    selectedIds = new Set();
    studentSearch = '';
    bulkContextError = '';
    disponibles = [];
    loadAndFetch();
  });

  async function loadAndFetch() {
    loadingDisponibles = true;
    try {
      disponibles = await fetchDisponibles(activeCursoId);
    } catch {
      disponibles = [];
    } finally {
      loadingDisponibles = false;
    }
  }

  function close() {
    isOpen = false;
  }

  function toggleSelect(id: number) {
    if (selectedIds.has(id)) selectedIds.delete(id);
    else selectedIds.add(id);
    selectedIds = new Set(selectedIds);
  }

  function toggleAll() {
    selectedIds =
      selectedIds.size === filteredDisponibles.length && filteredDisponibles.length > 0
        ? new Set()
        : new Set(filteredDisponibles.map((e) => e.id_estudiante));
  }

  async function submitBulk() {
    if (selectedIds.size === 0) return;
    bulkContextError = '';
    submittingBulk = true;
    try {
      const json = await bulkInscribir(activeCursoId, [...selectedIds]);
      onInscribed(json.created ?? []);
      const created = (json.created ?? []).length;
      const skipped = (json.skipped ?? []).length;
      const errors = (json.errors ?? []).length;

      let message = '';
      if (created > 0) {
        message += `${created} estudiante${created !== 1 ? 's' : ''} inscrito${created !== 1 ? 's' : ''}.`;
      }
      if (skipped > 0) {
        if (message) message += ' ';
        message += `${skipped} omitido${skipped !== 1 ? 's' : ''} (${json.skipped?.map((s: any) => s.razon || 'desconocido').join(', ') || 'sin detalles'}).`;
      }
      if (errors > 0 && json.errors?.length > 0) {
        if (message) message += ' ';
        message += `Error${errors !== 1 ? 'es' : ''}: ${json.errors.map((e: any) => e.error_detail || e.razon).join('; ')}.`;
      }

      close();
      onToast(message || 'No hay cambios', 'success');
    } catch (e) {
      const err = (e as any)?.response?.data;
      bulkContextError =
        err?.errors?.id_estudiantes?.[0] ??
        err?.message ??
        (e instanceof Error ? e.message : 'Error de red.');
    } finally {
      submittingBulk = false;
    }
  }

  const allSelected = $derived(
    filteredDisponibles.length > 0 && selectedIds.size === filteredDisponibles.length,
  );
  const someSelected = $derived(selectedIds.size > 0 && !allSelected);
</script>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape' && isOpen) close();
  }}
/>

{#if isOpen}
  <!-- Backdrop -->
  <button
    type="button"
    class="fixed inset-0 bg-black/40 backdrop-blur-[2px] z-50 cursor-default"
    onclick={close}
    aria-label="Cerrar"
  ></button>

  <!-- Dialog -->
  <div
    class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[60] bg-white rounded-2xl shadow-2xl w-[min(540px,calc(100vw-2rem))] max-h-[88dvh] flex flex-col overflow-hidden"
    role="dialog"
    aria-modal="true"
    aria-label="Agregar Estudiantes"
  >
    <!-- Header -->
    <div class="flex justify-between items-start px-6 pt-6 pb-4 border-b border-gray-100 shrink-0">
      <div>
        <h2 class="text-base font-bold text-gray-900">Agregar Estudiantes</h2>
        {#if selectedCurso}
          <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
            <span
              class="inline-flex items-center px-1.5 py-px rounded bg-blue-50 text-blue-600 font-mono text-[0.625rem] font-bold ring-1 ring-inset ring-blue-100"
            >
              {selectedCurso.cod_curso}
            </span>
            {cursoDisplayName(selectedCurso)}
          </p>
        {/if}
      </div>
      <button
        class="p-1.5 border-0 bg-transparent rounded-lg text-gray-400 cursor-pointer hover:bg-gray-100 hover:text-gray-700 transition-all ml-4 shrink-0"
        onclick={close}
        aria-label="Cerrar"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="17"
          height="17"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg
        >
      </button>
    </div>

    <!-- Search -->
    <div class="px-6 py-3.5 border-b border-gray-100 shrink-0">
      <div class="relative">
        <svg
          class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
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
          <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          type="search"
          bind:value={studentSearch}
          placeholder="Buscar por nombre o usuario…"
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50 transition-shadow"
        />
      </div>
      {#if bulkContextError}
        <div class="mt-2.5 flex items-center gap-1.5 text-red-500 text-xs bg-red-50 border border-red-100 rounded-lg px-3 py-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          {bulkContextError}
        </div>
      {/if}
    </div>

    <!-- List -->
    <div class="overflow-y-auto flex-1 min-h-0">
      {#if loadingDisponibles}
        <div class="flex items-center justify-center gap-3 py-12 text-gray-400">
          <div
            class="w-5 h-5 border-2 border-gray-200 border-t-blue-500 rounded-full animate-spin"
          ></div>
          <span class="text-sm">Buscando estudiantes disponibles…</span>
        </div>
      {:else if disponibles.length === 0}
        <p class="py-12 text-center text-gray-400 text-sm">
          Todos los estudiantes ya están inscritos en este curso.
        </p>
      {:else if filteredDisponibles.length === 0}
        <p class="py-12 text-center text-gray-400 text-sm">
          Sin resultados para «{studentSearch}».
        </p>
      {:else}
        <!-- Select all -->
        <label
          class="flex items-center gap-3 px-5 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors"
        >
          <input
            type="checkbox"
            checked={allSelected}
            indeterminate={someSelected}
            onchange={toggleAll}
            class="w-4 h-4 rounded border-gray-300 accent-blue-600 cursor-pointer"
          />
          <span class="text-sm font-semibold text-gray-700">
            Seleccionar todos
          </span>
          <span class="text-xs text-gray-400 ml-auto tabular-nums">
            {filteredDisponibles.length}
          </span>
        </label>

        <!-- Student rows -->
        <div class="divide-y divide-gray-100">
          {#each filteredDisponibles as e (e.id_estudiante)}
            {@const isSelected = selectedIds.has(e.id_estudiante)}
            <label
              class="flex items-center gap-3 px-5 py-3 cursor-pointer transition-colors {isSelected
                ? 'bg-blue-50/70'
                : 'hover:bg-gray-50'}"
            >
              <input
                type="checkbox"
                checked={isSelected}
                onchange={() => toggleSelect(e.id_estudiante)}
                class="w-4 h-4 rounded border-gray-300 accent-blue-600 cursor-pointer shrink-0"
              />
              <div
                class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-[0.625rem] font-bold flex items-center justify-center shrink-0"
              >
                {(
                  (e.usuario?.nombre1?.[0] ?? '') + (e.usuario?.apellido1?.[0] ?? '')
                ).toUpperCase() || '?'}
              </div>
              <div class="flex flex-col min-w-0 flex-1">
                <span class="text-sm font-medium text-gray-900 truncate">{disponibleName(e)}</span>
                <span class="text-xs text-gray-400">
                  {#if e.usuario?.rut}
                    {e.usuario.rut} &middot; {e.usuario.username}
                  {:else}
                    {e.usuario?.username ?? ''}
                  {/if}
                </span>
              </div>
              {#if isSelected}
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
                  class="text-blue-500 shrink-0"
                >
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              {/if}
            </label>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Footer -->
    <div
      class="px-6 py-4 border-t border-gray-100 flex justify-between items-center shrink-0 bg-gray-50/50"
    >
      <div class="flex items-center gap-2">
        {#if selectedIds.size > 0}
          <span
            class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white text-[0.625rem] font-bold tabular-nums"
          >
            {selectedIds.size}
          </span>
          <span class="text-sm text-gray-500">
            seleccionado{selectedIds.size !== 1 ? 's' : ''}
          </span>
        {:else}
          <span class="text-sm text-gray-400">Ninguno seleccionado</span>
        {/if}
      </div>
      <div class="flex gap-2.5">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 border border-gray-200 rounded-xl text-sm font-medium cursor-pointer hover:bg-gray-50 hover:border-gray-300 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          onclick={close}
          disabled={submittingBulk}
        >
          Cancelar
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold cursor-pointer shadow-sm hover:bg-blue-700 active:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          disabled={selectedIds.size === 0 || submittingBulk}
          onclick={submitBulk}
        >
          {#if submittingBulk}
            <span
              class="inline-block w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"
            ></span>
            Inscribiendo…
          {:else}
            Confirmar Inscripción
          {/if}
        </button>
      </div>
    </div>
  </div>
{/if}
