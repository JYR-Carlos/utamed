<script lang="ts">
  import { router, page } from '@inertiajs/svelte';
  import type { Snippet } from 'svelte';
  import type { PaginatedResponse } from '@/types/admin.types';
  import { sleep } from '@/lib';

  interface Props {
    data: PaginatedResponse<any>;
    columns: { key: string; label: string; sortable?: boolean }[];
    onEdit?: (item: any) => void;
    onDelete?: (item: any) => void;
    onPasswordChange?: (item: any) => void;
    onToggleActive?: (item: any) => void;
    onCustomAction?: (item: any) => void;
    customActionLabel?: string;
    onSyllabus?: (item: any) => void;
    searchPlaceholder?: string;
    /** Optional Svelte 5 snippet for custom cell rendering.
     *  Receives { item, column } and should return cell content.
     *  Falls back to plain text for unhandled columns. */
    cellSnippet?: Snippet<[{ item: any; column: { key: string; label: string } }]>;
  }

  let {
    data,
    columns,
    onEdit,

    onPasswordChange,
    onToggleActive,
    onCustomAction,
    customActionLabel = 'Ver',
    onSyllabus,
    searchPlaceholder = 'Buscar...',
    cellSnippet,
  }: Props = $props();

  let searchTerm = $state('');
  // Usar solo el pathname para evitar duplicación de query params con el objeto params
  let currentPath = $derived(new URL($page.url, window.location.origin).pathname);

  // ── Sort state is derived from the URL (single source of truth) ──────────
  type SortDir = 'asc' | 'desc' | null;

  function urlParams(): URLSearchParams {
    return new URL($page.url, window.location.origin).searchParams;
  }

  let activeSortKey = $derived(urlParams().get('sort_key'));
  let activeSortDir = $derived(urlParams().get('sort_dir') as SortDir);

  // Build a plain params object from the current URL, omitting the given keys
  function currentParamsExcept(...omit: string[]): Record<string, string> {
    const out: Record<string, string> = {};
    urlParams().forEach((v, k) => {
      if (!omit.includes(k)) out[k] = v;
    });
    return out;
  }

  // Three-state cycle: none → asc → desc → none
  // Fires a server request so the full dataset is sorted.
  function cycleSort(key: string) {
    let nextDir: SortDir;
    if (activeSortKey !== key) {
      nextDir = 'asc';
    } else if (activeSortDir === 'asc') {
      nextDir = 'desc';
    } else {
      nextDir = null;
    }

    // Preserve search, tipo, per_page — drop page (reset to 1) and old sort
    const params = currentParamsExcept('sort_key', 'sort_dir', 'page');
    if (nextDir) {
      params.sort_key = key;
      params.sort_dir = nextDir;
    }

    router.get(currentPath, params, { preserveState: true, preserveScroll: true });
  }

  function getValue(item: any, key: string): any {
    return key.split('.').reduce((v: any, k: string) => v?.[k], item) ?? null;
  }

  function getDisplayValue(item: any, key: string): string {
    const v = getValue(item, key);
    return v !== null && v !== undefined ? String(v) : '-';
  }

  // ── Navigation ───────────────────────────────────────────────────────────
  async function handleSearch() {
    // Preserve tipo, sort, per_page — drop page (reset to 1)
    const params = currentParamsExcept('search', 'page');
    if (searchTerm) params.search = searchTerm;
    await sleep(1000)
    router.get(currentPath, params, { preserveState: true, preserveScroll: true });
  }

  function goToPage(p: number) {
    const params = currentParamsExcept('page');
    params.page = String(p);
    router.get(currentPath, params, { preserveState: true, preserveScroll: true });
  }

  // ── Per-page selector ───────────────────────────────────────────────────
  const perPageOptions = [10, 15, 25, 50, 100];

  let currentPerPage = $derived(Number(urlParams().get('per_page') ?? '15'));

  function changePerPage(value: number) {
    const params = currentParamsExcept('per_page', 'page');
    params.per_page = String(value);
    router.get(currentPath, params, { preserveState: true, preserveScroll: true });
  }

  // ── Sliding page buttons ─────────────────────────────────────────────────
  // Always shows 5 contiguous pages centered on current, plus first/last with ellipsis
  type PageButton = { type: 'page'; n: number } | { type: 'ellipsis'; id: string };

  let pageButtons = $derived.by((): PageButton[] => {
    const cur = data.current_page;
    const last = data.last_page;
    if (last <= 1) return [];

    const winStart = Math.max(1, Math.min(cur - 2, last - 4));
    const winEnd = Math.min(last, winStart + 4);
    const btns: PageButton[] = [];

    if (winStart > 1) {
      btns.push({ type: 'page', n: 1 });
      if (winStart > 2) btns.push({ type: 'ellipsis', id: 'left' });
    }

    for (let i = winStart; i <= winEnd; i++) {
      btns.push({ type: 'page', n: i });
    }

    if (winEnd < last) {
      if (winEnd < last - 1) btns.push({ type: 'ellipsis', id: 'right' });
      btns.push({ type: 'page', n: last });
    }

    return btns;
  });
</script>

<div class="bg-white rounded-lg shadow overflow-hidden">
  <!-- Search Bar -->
  <div class="p-4 border-b border-gray-200 flex gap-2">
    <input
      type="text"
      bind:value={searchTerm}
      placeholder={searchPlaceholder}
      onkeydown={ () => handleSearch()}
      class="flex-1 px-4 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-shadow"
    />
    <button
      onclick={handleSearch}
      class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-sm font-medium transition-colors cursor-pointer"
    >
      Buscar
    </button>
  </div>
  <!-- Pagination -->
  <div class="px-4 py-3 flex items-center justify-between border-t border-gray-200 flex-wrap gap-3">
    <!-- Left: record count info -->
    <span class="text-sm text-gray-500 shrink-0">
      {#if data.total === 0}
        Sin resultados
      {:else if data.to - data.from + 1 === data.total}
        Mostrando {data.to} registro{data.to === 1 ? '' : 's'}
      {:else}
        Mostrando los elementos {data.from} a {data.to} de {data.total} registros
      {/if}
    </span>

    <!-- Right: per-page + prev/page buttons/next -->
    {#if data.last_page > 1 || data.total > 0}
      <div class="flex items-center gap-2 ml-auto">
        <!-- Per-page selector -->
        <select
          value={currentPerPage}
          onchange={(e) => changePerPage(Number((e.target as HTMLSelectElement).value))}
          class="px-2 py-1.5 text-sm border border-gray-300 rounded-md bg-white cursor-pointer focus:outline-none focus:border-blue-400 transition-colors"
        >
          {#each perPageOptions as opt}
            <option value={opt}>{opt} por página</option>
          {/each}
        </select>

        {#if data.last_page > 1}
          <!-- Prev -->
          <button
            onclick={() => goToPage(data.current_page - 1)}
            disabled={data.current_page === 1}
            class="px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 rounded-md cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Anterior
          </button>

          <!-- Sliding page buttons -->
          <div class="flex items-center gap-1">
            {#each pageButtons as btn}
              {#if btn.type === 'ellipsis'}
                <span class="px-1 text-sm text-gray-400 select-none">…</span>
              {:else}
                <button
                  onclick={() => goToPage(btn.n)}
                  class="min-w-[2rem] h-8 px-2 text-sm rounded-md border cursor-pointer transition-all
                    {btn.n === data.current_page
                    ? 'bg-blue-500 border-blue-500 text-white font-semibold'
                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400'}"
                >
                  {btn.n}
                </button>
              {/if}
            {/each}
          </div>

          <!-- Next -->
          <button
            onclick={() => goToPage(data.current_page + 1)}
            disabled={data.current_page === data.last_page}
            class="px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 rounded-md cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed"
          >
            Siguiente
          </button>
        {/if}
      </div>
    {/if}
  </div>

  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="w-full border-collapse">
      <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
          {#each columns as column}
            <th
              onclick={() => cycleSort(column.key)}
              class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 tracking-wide cursor-pointer select-none hover:bg-gray-100 transition-colors"
            >
              <span class="inline-flex items-center gap-1">
                {column.label}

                {#if activeSortKey === column.key && activeSortDir === 'asc'}
                  <!-- Ascending: A→Z, arrow down -->
                  <span class="text-blue-500 shrink-0">
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
                      <path d="m3 16 4 4 4-4" />
                      <path d="M7 20V4" />
                      <path d="M20 8h-5" />
                      <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                      <path d="M15 14h5l-5 6h5" />
                    </svg>
                  </span>
                {:else if activeSortKey === column.key && activeSortDir === 'desc'}
                  <!-- Descending: Z→A, arrow up — vertical mirror of ascending -->
                  <span class="text-blue-500 shrink-0">
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
                      <path d="m3 16 4 4 4-4"></path>
                      <path d="M7 4v16"></path>
                      <path d="M15 4h5l-5 6h5"></path>
                      <path d="M15 20v-3.5a2.5 2.5 0 0 1 5 0V20"></path>
                      <path d="M20 18h-5"></path>
                    </svg>
                  </span>
                {:else}
                  <!-- Unsorted: neutral hint icon -->
                  <span class="text-gray-300 shrink-0">
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
                      <path d="m3 16 4 4 4-4" />
                      <path d="M7 20V4" />
                      <path d="M20 8h-5" />
                      <path d="M15 10V6.5a2.5 2.5 0 0 1 5 0V10" />
                      <path d="M15 14h5l-5 6h5" />
                    </svg>
                  </span>
                {/if}
              </span>
            </th>
          {/each}
          {#if onEdit || onCustomAction || onSyllabus || onPasswordChange || onToggleActive}
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 tracking-wide"> Acciones </th>
          {/if}
        </tr>
      </thead>
      <tbody>
        {#if data.data.length === 0}
          <tr>
            <td colspan={columns.length + (onEdit ? 1 : 0)} class="text-center text-gray-400 py-12 px-4">
              No se encontraron resultados
            </td>
          </tr>
        {:else}
          {#each data.data as item}
            <tr class="hover:bg-gray-50 transition-colors">
              {#each columns as column}
                <td class="px-4 py-3 border-b border-gray-100 text-sm text-gray-900 align-middle">
                  {#if cellSnippet}
                    {@render cellSnippet({ item, column })}
                  {:else}
                    {getDisplayValue(item, column.key)}
                  {/if}
                </td>
              {/each}
              {#if onEdit || onPasswordChange || onToggleActive || onCustomAction || onSyllabus}
                <td class="px-4 py-3 border-b border-gray-100 align-middle">
                  <div class="flex items-center gap-1.5 whitespace-nowrap">
                    <!-- P1: Syllabus -->
                    {#if onSyllabus}
                      <button
                        onclick={() => onSyllabus?.(item)}
                        title={item.has_programa ? 'Ver / Regenerar Programa' : 'Generar Programa'}
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-white rounded text-[0.73rem] font-semibold cursor-pointer transition-all shadow-sm relative
												       {item.has_programa ? 'bg-blue-800 hover:bg-blue-900' : 'bg-blue-600 hover:bg-blue-700'}"
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          width="13"
                          height="13"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          ><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line
                            x1="16"
                            y1="13"
                            x2="8"
                            y2="13"
                          /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" /></svg
                        >
                        Programa
                        {#if item.has_programa}
                          <span class="inline-block w-1.5 h-1.5 bg-green-400 rounded-full border border-white/60 shrink-0"></span>
                        {/if}
                      </button>
                    {/if}

                    <!-- P2: Custom action -->
                    {#if onCustomAction}
                      <button
                        onclick={() => onCustomAction?.(item)}
                        class="px-2.5 py-1 border border-indigo-300 hover:border-indigo-400 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded text-[0.73rem] font-medium cursor-pointer transition-all"
                      >
                        {customActionLabel}
                      </button>
                    {/if}

                    <!-- Separator -->
                    {#if (onSyllabus || onCustomAction) && (onEdit)}
                      <div class="w-px h-5 bg-gray-200 mx-0.5 shrink-0"></div>
                    {/if}

                    <!-- P3: Edit -->
                    {#if onEdit}
                      <button
                        onclick={() => onEdit?.(item)}
                        title="Editar"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-transparent hover:bg-gray-100 text-gray-600 hover:text-gray-900 rounded text-[0.73rem] font-medium cursor-pointer transition-all border-0"
                      >
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
                          ><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path
                            d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                          /></svg
                        >
                        Editar
                      </button>
                    {/if}

                   

                    <!-- Utility: password -->
                    {#if onPasswordChange}
                      <button
                        onclick={() => onPasswordChange?.(item)}
                        class="px-2.5 py-1 bg-green-50 hover:bg-green-100 text-green-700 rounded text-[0.73rem] font-medium cursor-pointer transition-all border-0"
                      >
                        Contraseña
                      </button>
                    {/if}

                    <!-- Utility: toggle active -->
                    {#if onToggleActive}
                      <button
                        onclick={() => onToggleActive?.(item)}
                        class="px-2.5 py-1 rounded text-[0.73rem] font-medium cursor-pointer transition-all border-0
												       {item.usuario.esta_activo ? 'bg-blue-100 hover:bg-blue-200 text-blue-700' : 'bg-red-100 hover:bg-red-200 text-red-600'}"
                      >
                        {item.usuario.esta_activo ? 'Activo' : 'Inactivo'}
                      </button>
                    {/if}
                  </div>
                </td>
              {/if}
            </tr>
          {/each}
        {/if}
      </tbody>
    </table>
  </div>
</div>
