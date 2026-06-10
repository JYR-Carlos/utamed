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

      // Construir mensaje detallado
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
</script>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape' && isOpen) close();
  }}
/>

{#if isOpen}
  <button
    type="button"
    class="fixed inset-0 bg-black/45 z-50 cursor-default"
    onclick={close}
    aria-label="Cerrar"
  ></button>
  <div
    class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[60] bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.18)] w-[min(540px,calc(100vw-2rem))] max-h-[88dvh] flex flex-col overflow-hidden"
    role="dialog"
    aria-modal="true"
    aria-label="Agregar Estudiantes"
  >
    <div class="flex justify-between items-start px-6 pt-5 pb-4 border-b border-slate-100 shrink-0">
      <div>
        <h2 class="text-[1.0625rem] font-bold text-gray-900 m-0">Agregar Estudiantes</h2>
        <p class="text-[0.8125rem] text-gray-500 mt-0.5 mb-0">
          {selectedCurso ? cursoDisplayName(selectedCurso) : ''}
        </p>
      </div>
      <button
        class="p-1.5 border-0 bg-transparent rounded-md text-gray-400 cursor-pointer hover:bg-gray-100 hover:text-gray-900 transition-all"
        onclick={close}
        aria-label="Cerrar"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg
        >
      </button>
    </div>

    <div class="px-6 py-3.5 border-b border-slate-100 shrink-0">
      <div class="relative">
        <svg
          class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
          xmlns="http://www.w3.org/2000/svg"
          width="15"
          height="15"
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
          class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
        />
      </div>
      {#if bulkContextError}
        <div class="mt-2 flex items-center gap-1.5 text-red-600 text-[0.8125rem]">
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
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          {bulkContextError}
        </div>
      {/if}
    </div>

    <div class="overflow-y-auto flex-1 min-h-0">
      {#if loadingDisponibles}
        <div class="flex items-center justify-center gap-3 py-10 text-gray-500">
          <div
            class="w-5 h-5 border-[2.5px] border-gray-200 border-t-blue-500 rounded-full animate-spin"
          ></div>
          <span>Buscando estudiantes disponibles…</span>
        </div>
      {:else if disponibles.length === 0}
        <p class="py-10 text-center text-gray-500 text-sm">
          Todos los estudiantes ya están inscritos en este curso.
        </p>
      {:else if filteredDisponibles.length === 0}
        <p class="py-10 text-center text-gray-500 text-sm">
          Sin resultados para «{studentSearch}».
        </p>
      {:else}
        <label
          class="flex items-center gap-2.5 px-5 py-2.5 border-b border-slate-100 cursor-pointer hover:bg-gray-50"
        >
          <input
            type="checkbox"
            checked={selectedIds.size === filteredDisponibles.length}
            onchange={toggleAll}
          />
          <span class="text-sm font-medium text-gray-700">
            Seleccionar todos <small>({filteredDisponibles.length})</small>
          </span>
        </label>
        <div class="divide-y divide-slate-100">
          {#each filteredDisponibles as e (e.id_estudiante)}
            <label
              class="flex items-center gap-3 px-5 py-2.5 cursor-pointer transition-colors {selectedIds.has(
                e.id_estudiante,
              )
                ? 'bg-blue-50'
                : 'hover:bg-gray-50'}"
            >
              <input
                type="checkbox"
                checked={selectedIds.has(e.id_estudiante)}
                onchange={() => toggleSelect(e.id_estudiante)}
              />
              <div
                class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-[0.625rem] font-bold flex items-center justify-center shrink-0"
              >
                {(
                  (e.usuario?.nombre1?.[0] ?? '') + (e.usuario?.apellido1?.[0] ?? '')
                ).toUpperCase() || '?'}
              </div>
              <div class="flex flex-col min-w-0">
                <span class="text-sm font-medium text-gray-900 truncate">{disponibleName(e)}</span>
                <span class="text-xs text-gray-500">
                  {#if e.usuario?.rut}
                    {e.usuario.rut} &middot; {e.usuario.username}
                  {:else}
                    {e.usuario?.username ?? ''}
                  {/if}
                </span>
              </div>
            </label>
          {/each}
        </div>
      {/if}
    </div>

    <div class="px-6 py-4 border-t border-slate-100 flex justify-between items-center shrink-0">
      <span class="text-sm text-gray-500">
        {selectedIds.size} seleccionado{selectedIds.size !== 1 ? 's' : ''}
      </span>
      <div class="flex gap-2.5">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer hover:bg-gray-50 hover:border-gray-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          onclick={close}
          disabled={submittingBulk}>Cancelar</button
        >
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
          disabled={selectedIds.size === 0 || submittingBulk}
          onclick={submitBulk}
        >
          {#if submittingBulk}
            <span
              class="inline-block w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"
            ></span>Inscribiendo…
          {:else}
            Confirmar Inscripción
          {/if}
        </button>
      </div>
    </div>
  </div>
{/if}
