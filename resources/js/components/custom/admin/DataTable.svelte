<script lang="ts">
  import { router, page } from '@inertiajs/svelte';
  import type { Snippet } from 'svelte';
  import type { PaginatedResponse } from '@/types/admin.types';
  import { scale } from 'svelte/transition';
  import { stackTable } from '@/lib/actions/stackTable';

  interface Props {
    data: PaginatedResponse<any> | any[] | { data: any[]; meta?: any; [key: string]: any };
    columns: { key: string; label: string; sortable?: boolean; class?: string }[];
    /** Variante de estilo: 'default' (gris estándar) | 'accent' (borde institucional azul y cabecera contrastada) */
    variant?: 'default' | 'accent';
    /** Clases CSS adicionales para el contenedor principal */
    class?: string;
    /** Si es false, oculta la barra de paginación. Por defecto true. */
    paginate?: boolean;
    onEdit?: (item: any) => void;
    onDelete?: (item: any) => void;
    deleteActionLabel?: string;
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
    /** Optional Svelte 5 snippet for filter controls in the toolbar. */
    filterSnippet?: Snippet;
    /** Optional click handler for table rows. */
    onRowClick?: (item: any) => void;
    /** Optional Svelte 5 snippet for expanded row content (e.g. sub-tables, hierarchy). */
    expandableSnippet?: Snippet<[{ item: any }]>;
    /** Permite inyectar clases dinámicas por fila (ej: atenuar registros eliminados) */
    rowClass?: (item: any) => string;
    /** Identificador único por fila para el estado expandido */
    getRowId?: (item: any) => string | number;
    /** Determina si una fila específica puede expandirse. Si no se pasa, todas son expandibles. */
    isExpandable?: (item: any) => boolean;
    /** Determina si una fila tiene menú de acciones disponible. Si devuelve false, muestra texto neutro. */
    hasRowActions?: (item: any) => boolean;
    /** Condición o mensaje para deshabilitar la acción de eliminar. Si devuelve string, se muestra como tooltip. */
    isDeleteDisabled?: (item: any) => boolean | string;
    /** Opcional: callback para interceptar la búsqueda (útil para filtrado en memoria/cliente) */
    onSearch?: (term: string) => void;
    /** Opcional: claves de props en Inertia para recarga parcial ultra rápida (e.g. ['cursos']) */
    only?: string[];
  }

  let {
    data,
    columns,
    variant = 'default',
    class: className = '',
    paginate = true,
    onEdit,
    onDelete,
    deleteActionLabel = 'Eliminar',
    onPasswordChange,
    onToggleActive,
    onCustomAction,
    customActionLabel = 'Ver',
    onSyllabus,
    searchPlaceholder = 'Buscar...',
    cellSnippet,
    filterSnippet,
    onRowClick,
    expandableSnippet,
    rowClass,
    isExpandable,
    hasRowActions,
    isDeleteDisabled,
    onSearch,
    only,
    getRowId = (item: any) =>
      item?.id_facultad ??
      item?.id_departamento ??
      item?.id_carrera ??
      item?.id_curso ??
      item?.id_usuario ??
      item?.id ??
      JSON.stringify(item),
  }: Props = $props();

  const isClientData = $derived(Array.isArray(data));
  let clientCurrentPage = $state(1);
  let clientPerPage = $state(15);

  /** Set reactivo con las filas actualmente expandidas */
  let expandedRows = $state<Set<string | number>>(new Set());

  function toggleRow(id: string | number, e?: MouseEvent) {
    e?.stopPropagation();
    const next = new Set(expandedRows);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expandedRows = next;
  }

  // Hay columna de "Acciones" si se pasó al menos un handler de acción de fila.
  const hasActions = $derived(
    !!(onEdit || onDelete || onPasswordChange || onToggleActive || onCustomAction || onSyllabus),
  );

  // Usar solo el pathname para evitar duplicación de query params con el objeto params
  let currentPath = $derived(new URL($page.url, window.location.origin).pathname);

  // ── Sort state is derived from the URL (single source of truth) ──────────
  type SortDir = 'asc' | 'desc' | null;

  function urlParams(): URLSearchParams {
    return new URL($page.url, window.location.origin).searchParams;
  }

  // El buscador arranca con lo que ya venga en la URL: si no, tras recargar la
  // página la caja aparece vacía mientras la tabla sigue filtrada.
  let searchTerm = $state(urlParams().get('search') ?? '');
  let buscando = $state(false);
  /** Término efectivamente aplicado en el servidor (el de la URL). */
  let searchAplicado = $derived(urlParams().get('search') ?? '');

  let activeSortKey = $derived(urlParams().get('sort_key'));
  let activeSortDir = $derived(urlParams().get('sort_dir') as SortDir);

  let menuState = $state<{
    item: any;
    index: number;
    right: number;
    top?: number;
    bottom?: number;
    openUpwards: boolean;
  } | null>(null);

  function toggleDropdown(item: any, index: number, event: MouseEvent) {
    event.stopPropagation();

    if (menuState?.index === index) {
      menuState = null;
      return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const menuEstimatedHeight = 200; // Altura máxima esperada del menú en píxeles
    const spaceBelow = window.innerHeight - rect.bottom;

    // Si hay menos espacio abajo que la altura del menú, lo abrimos hacia arriba
    const openUpwards = spaceBelow < menuEstimatedHeight;

    menuState = {
      item,
      index,
      right: window.innerWidth - rect.right,
      openUpwards,
      // Si abre hacia arriba, anulamos el top y usamos bottom relativo a la ventana
      top: openUpwards ? undefined : rect.bottom + 4,
      bottom: openUpwards ? window.innerHeight - rect.top + 4 : undefined,
    };
  }

  function closeDropdown() {
    menuState = null;
  }

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

    router.get(currentPath, params, {
      preserveState: true,
      preserveScroll: true,
      only: only?.length ? only : undefined,
    });
  }

  /**
   * Orden desde el selector móvil. En móvil las cabeceras no se ven (la tabla
   * se apila en tarjetas), así que el orden se elige aquí; `desc` al repetir
   * la misma columna igual que el ciclo de las cabeceras.
   */
  function setSortFromSelect(key: string) {
    if (!key) {
      const params = currentParamsExcept('sort_key', 'sort_dir', 'page');
      router.get(currentPath, params, { preserveState: true, preserveScroll: true });
      return;
    }
    const params = currentParamsExcept('sort_key', 'sort_dir', 'page');
    params.sort_key = key;
    params.sort_dir = activeSortKey === key && activeSortDir === 'asc' ? 'desc' : 'asc';
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

  /** Espera tras la última tecla antes de consultar al servidor. */
  const SEARCH_DEBOUNCE_MS = 300;

  let searchDebounce: ReturnType<typeof setTimeout> | undefined;

  /**
   * Lanza la búsqueda contra el servidor.
   *
   * Se salta la petición si el término ya es el aplicado (teclas que no cambian
   * el texto, Enter repetido): cada visita recarga el listado completo.
   */
  function runSearch() {
    clearTimeout(searchDebounce);

    const term = searchTerm.trim();
    if (onSearch) {
      clientCurrentPage = 1;
      onSearch(term);
      return;
    }
    if (term === searchAplicado) return;

    // Preserve tipo, sort, per_page — drop page (reset to 1)
    const params = currentParamsExcept('search', 'page');
    if (term) params.search = term;

    router.get(currentPath, params, {
      preserveState: true,
      preserveScroll: true,
      replace: true, // no llenar el historial con una entrada por búsqueda
      only: only?.length ? only : undefined,
      onStart: () => (buscando = true),
      onFinish: () => (buscando = false),
    });
  }

  /**
   * Rebota la búsqueda mientras se escribe.
   *
   * Se dispara en `input` —no en `keydown`— porque en `keydown` el valor ligado
   * aún no incluye la tecla recién pulsada y se buscaba siempre un carácter por
   * detrás de lo escrito.
   */
  function handleSearchInput() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(runSearch, SEARCH_DEBOUNCE_MS);
  }

  function handleSearchKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
      event.preventDefault();
      runSearch();
    }
  }

  function clearSearch() {
    searchTerm = '';
    if (onSearch) {
      onSearch('');
      return;
    }
    runSearch();
  }

  $effect(() => () => clearTimeout(searchDebounce));

  function goToPage(p: number) {
    if (isClientData) {
      clientCurrentPage = Math.max(1, Math.min(p, pageLast));
      return;
    }
    const params = currentParamsExcept('page');
    params.page = String(p);
    router.get(currentPath, params, {
      preserveState: true,
      preserveScroll: true,
      only: only?.length ? only : undefined,
    });
  }

  // ── Per-page selector ───────────────────────────────────────────────────
  const perPageOptions = [10, 15, 25, 50, 100];

  let currentPerPage = $derived(
    isClientData ? clientPerPage : Number(urlParams().get('per_page') ?? '15'),
  );

  function changePerPage(value: number) {
    if (isClientData) {
      clientPerPage = value;
      clientCurrentPage = 1;
      return;
    }
    const params = currentParamsExcept('per_page', 'page');
    params.per_page = String(value);
    router.get(currentPath, params, {
      preserveState: true,
      preserveScroll: true,
      only: only?.length ? only : undefined,
    });
  }

  // Support both direct LengthAwarePaginator (data.total), ResourceCollection (data.meta.total), and plain array
  let paginationMeta = $derived((data as any)?.meta ?? data ?? {});
  let totalCount = $derived(
    isClientData ? (data as any[]).length : Number(paginationMeta.total ?? 0),
  );
  let pageCurrent = $derived(
    isClientData ? clientCurrentPage : Number(paginationMeta.current_page ?? 1),
  );
  let pageLast = $derived(
    isClientData
      ? Math.max(1, Math.ceil(totalCount / currentPerPage))
      : Number(paginationMeta.last_page ?? 1),
  );
  let fromIndex = $derived(
    totalCount === 0
      ? 0
      : isClientData
        ? (pageCurrent - 1) * currentPerPage + 1
        : (paginationMeta.from ?? (totalCount > 0 ? 1 : 0)),
  );
  let toIndex = $derived(
    isClientData
      ? Math.min(totalCount, pageCurrent * currentPerPage)
      : (paginationMeta.to ?? (totalCount > 0 ? (data?.data?.length ?? 0) : 0)),
  );

  /** Lista normalizada de elementos a renderizar (soporta array directo o respuesta paginada) */
  let items = $derived<any[]>(
    isClientData
      ? paginate
        ? (data as any[]).slice((pageCurrent - 1) * currentPerPage, pageCurrent * currentPerPage)
        : (data as any[])
      : ((data as any)?.data ?? []),
  );

  $effect(() => {
    if (isClientData && clientCurrentPage > pageLast) {
      clientCurrentPage = 1;
    }
  });

  // ── Sliding page buttons ─────────────────────────────────────────────────
  // Always shows 5 contiguous pages centered on current, plus first/last with ellipsis
  type PageButton = { type: 'page'; n: number } | { type: 'ellipsis'; id: string };

  let pageButtons = $derived.by((): PageButton[] => {
    const cur = pageCurrent;
    const last = pageLast;
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

<svelte:window onclick={closeDropdown} onscrollcapture={closeDropdown} />

<div
  class="bg-white rounded-lg shadow overflow-hidden {variant === 'accent'
    ? 'border-t-4 border-t-blue-600 shadow-md rounded-xl'
    : ''} {className}"
>
  <!-- Search Bar & Filters -->
  <div class="p-3 sm:p-4 border-b border-gray-200 flex flex-wrap items-center gap-3">
    <div class="relative flex-1 min-w-[220px]">
      <input
        type="text"
        bind:value={searchTerm}
        placeholder={searchPlaceholder}
        oninput={handleSearchInput}
        onkeydown={handleSearchKeydown}
        class="w-full px-3 sm:px-4 py-2 pr-24 sm:pr-28 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-200 transition-shadow"
      />
      <div
        class="absolute inset-y-0 right-2 flex items-center gap-2 text-xs text-gray-400 pointer-events-none"
      >
        {#if buscando}
          <span>Buscando…</span>
        {/if}
        {#if searchTerm}
          <button
            onclick={clearSearch}
            title="Limpiar búsqueda"
            aria-label="Limpiar búsqueda"
            class="pointer-events-auto p-1 text-gray-400 hover:text-gray-700 rounded-full hover:bg-gray-100 transition-colors cursor-pointer"
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
              ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
            >
          </button>
        {/if}
      </div>
    </div>
    {#if filterSnippet}
      {@render filterSnippet()}
    {/if}
  </div>
  <!-- Pagination -->
  {#if paginate}
    <div class="px-3 sm:px-4 py-3 flex items-center justify-between border-t border-gray-200 flex-wrap gap-3">
      <!-- Left: record count info -->
      <span class="text-sm text-gray-500 sm:shrink-0">
        {#if totalCount === 0}
          Sin resultados
        {:else if toIndex - fromIndex + 1 === totalCount}
          Mostrando {toIndex} registro{toIndex === 1 ? '' : 's'}
        {:else}
          Mostrando los elementos {fromIndex} a {toIndex} de {totalCount} registros
        {/if}
      </span>

      <!-- Right: per-page + prev/page buttons/next -->
      {#if pageLast > 1 || totalCount > 0}
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:ml-auto">
          <!-- Orden: sólo móvil, donde las cabeceras de la tabla no se ven -->
          <select
            value={activeSortKey ?? ''}
            onchange={(e) => setSortFromSelect((e.target as HTMLSelectElement).value)}
            aria-label="Ordenar por"
            class="md:hidden px-2 py-1.5 text-sm border border-gray-300 rounded-md bg-white cursor-pointer focus:outline-none focus:border-blue-400 transition-colors"
          >
            <option value="">Orden por defecto</option>
            {#each columns as column (column.key)}
              <option value={column.key}>
                {column.label}{activeSortKey === column.key ? (activeSortDir === 'desc' ? ' ↓' : ' ↑') : ''}
              </option>
            {/each}
          </select>

          <!-- Per-page selector -->
          <select
            value={currentPerPage}
            onchange={(e) => changePerPage(Number((e.target as HTMLSelectElement).value))}
            class="px-2 py-1.5 text-sm border border-gray-300 rounded-md bg-white cursor-pointer focus:outline-none focus:border-blue-400 transition-colors"
          >
            {#each perPageOptions as opt (opt)}
              <option value={opt}>{opt} por página</option>
            {/each}
          </select>

          {#if pageLast > 1}
            <!-- Prev -->
            <button
              onclick={() => goToPage(pageCurrent - 1)}
              disabled={pageCurrent === 1}
              class="px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 rounded-md cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Anterior
            </button>

            <!-- Móvil: «Página X de Y» en lugar de la ventana de números -->
            <span class="sm:hidden text-sm text-gray-500 tabular-nums">
              {pageCurrent} / {pageLast}
            </span>

            <!-- Sliding page buttons -->
            <div class="hidden sm:flex items-center gap-1">
              {#each pageButtons as btn, rowIndex (rowIndex)}
                {#if btn.type === 'ellipsis'}
                  <span class="px-1 text-sm text-gray-400 select-none">…</span>
                {:else}
                  <button
                    onclick={() => goToPage(btn.n)}
                    class="min-w-8 h-8 px-2 text-sm rounded-md border cursor-pointer transition-all
                      {btn.n === pageCurrent
                      ? 'page-current'
                      : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400'}"
                    aria-current={btn.n === pageCurrent ? 'page' : undefined}
                  >
                    {btn.n}
                  </button>
                {/if}
              {/each}
            </div>

            <!-- Next -->
            <button
              onclick={() => goToPage(pageCurrent + 1)}
              disabled={pageCurrent === pageLast}
              class="px-3 py-1.5 text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 rounded-md cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Siguiente
            </button>
          {/if}
        </div>
      {/if}
    </div>
  {/if}

  <!-- Table -->
  <div class="table-stack-wrap overflow-auto max-h-[72vh]" onscroll={closeDropdown}>
    <table class="table-stack w-full border-collapse relative" use:stackTable>
      <thead
        class="{variant === 'accent'
          ? 'bg-slate-100/90 text-slate-700'
          : 'bg-gray-50 text-gray-500'} border-b border-gray-200 sticky top-0 z-20 shadow-xs"
      >
        <tr>
          {#if expandableSnippet}
            <th class="w-10 px-3 py-3"></th>
          {/if}
          {#each columns as column (column.key)}
            <th
              onclick={() => cycleSort(column.key)}
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide cursor-pointer select-none hover:bg-gray-100 transition-colors {variant === 'accent' ? 'text-slate-700' : 'text-gray-500'}"
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
          {#if hasActions}
            <th
              class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 tracking-wide w-1 whitespace-nowrap"
            >
              Acciones
            </th>
          {/if}
        </tr>
      </thead>
      <tbody>
        {#if items.length === 0}
          <tr>
            <td
              colspan={columns.length + (hasActions ? 1 : 0) + (expandableSnippet ? 1 : 0)}
              class="text-center text-gray-400 py-12 px-4"
            >
              No se encontraron resultados
            </td>
          </tr>
        {:else}
          {#each items as item, rowIndex (rowIndex)}
            {@const itemId = getRowId(item)}
            {@const isExpanded = expandedRows.has(itemId)}
            {@const customClass = rowClass ? rowClass(item) : ''}
            <tr
              onclick={() => onRowClick?.(item)}
              class="transition-colors {onRowClick ? 'cursor-pointer' : ''} {item.usuario?.esta_activo === false
                ? 'bg-red-50/60 hover:bg-red-100/60'
                : 'hover:bg-gray-50'} {customClass}"
            >
              {#if expandableSnippet}
                {@const canExpand = isExpandable ? isExpandable(item) : true}
                <td class="w-10 px-3 py-3 text-center align-middle" onclick={(e) => e.stopPropagation()}>
                  {#if canExpand}
                    <button
                      onclick={(e) => toggleRow(itemId, e)}
                      class="p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors cursor-pointer"
                      aria-label={isExpanded ? 'Colapsar' : 'Expandir'}
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
                        class="transition-transform duration-200 {isExpanded ? 'rotate-90' : ''}"
                      >
                        <polyline points="9 18 15 12 9 6" />
                      </svg>
                    </button>
                  {:else}
                    <span class="w-6 h-6 inline-block"></span>
                  {/if}
                </td>
              {/if}
              {#each columns as column (rowIndex + '-' + column.key)}
                <td
                  class="px-4 py-3 border-b border-gray-100 text-sm text-gray-900 align-middle {column.class ||
                    ''}"
                >
                  {#if cellSnippet}
                    {@render cellSnippet({ item, column })}
                  {:else}
                    {getDisplayValue(item, column.key)}
                  {/if}
                </td>
              {/each}
              {#if hasActions}
                {@const canAct = hasRowActions ? hasRowActions(item) : true}
                <td
                  class="px-4 py-3 border-b border-gray-100 align-middle text-center relative w-1 whitespace-nowrap"
                >
                  {#if canAct}
                    <button
                      onclick={(e) => toggleDropdown(item, rowIndex, e)}
                      class="p-2.5 md:p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors cursor-pointer"
                      title="Opciones"
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
                        stroke-linejoin="round"
                        ><circle cx="12" cy="12" r="1" /><circle cx="12" cy="5" r="1" /><circle
                          cx="12"
                          cy="19"
                          r="1"
                        /></svg
                      >
                    </button>
                  {:else}
                    <span class="text-gray-400 text-xs select-none">Discontinuado</span>
                  {/if}
                </td>
              {/if}
            </tr>
            {#if expandableSnippet && isExpanded}
              <tr class="bg-slate-50/70 border-b border-gray-100">
                <td colspan={columns.length + (hasActions ? 1 : 0) + 1} class="px-6 py-4">
                  {@render expandableSnippet({ item })}
                </td>
              </tr>
            {/if}
          {/each}
        {/if}
      </tbody>
    </table>
  </div>

  {#if menuState}
    <div
      role="menu"
      tabindex="-1"
      transition:scale={{ duration: 150, start: 0.95 }}
      class="fixed w-48 bg-white border border-gray-100 rounded-md shadow-lg z-100 py-1.5 text-left {menuState?.openUpwards
        ? 'origin-bottom-right'
        : 'origin-top-right'}"
      style="
        right: {menuState?.right}px; 
        {menuState?.openUpwards ? `bottom: ${menuState?.bottom}px;` : `top: ${menuState?.top}px;`}
      "
      onclick={(e) => e.stopPropagation()}
      onkeydown={(e) => e.stopPropagation()}
    >
      {#if onSyllabus}
        <button
          onclick={() => {
            onSyllabus?.(menuState?.item);
            closeDropdown();
          }}
          class="menu-item"
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
            ><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline
              points="14 2 14 8 20 8"
            /><line x1="16" y1="13" x2="8" y2="13" /><line
              x1="16"
              y1="17"
              x2="8"
              y2="17"
            /><polyline points="10 9 9 9 8 9" /></svg
          >
          {menuState?.item?.has_programa ? 'Ver Programa' : 'Generar Programa'}
          {#if menuState?.item?.has_programa}
            <span class="inline-block w-1.5 h-1.5 bg-green-500 rounded-full shrink-0 ml-auto"
            ></span>
          {/if}
        </button>
      {/if}

      {#if onCustomAction}
        <button
          onclick={() => {
            onCustomAction?.(menuState?.item);
            closeDropdown();
          }}
          class="menu-item"
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
            ><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><path d="m9 12 2 2 4-4" /></svg
          >
          {customActionLabel}
        </button>
      {/if}

      {#if (onSyllabus || onCustomAction) && (onEdit || onPasswordChange || onToggleActive)}
        <div class="h-px bg-gray-100 my-1"></div>
      {/if}

      {#if onEdit}
        <button
          onclick={() => {
            onEdit?.(menuState?.item);
            closeDropdown();
          }}
          class="menu-item"
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
            ><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path
              d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
            /></svg
          >
          Editar
        </button>
      {/if}

      {#if onPasswordChange}
        <button
          onclick={() => {
            onPasswordChange?.(menuState?.item);
            closeDropdown();
          }}
          class="menu-item"
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
            ><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path
              d="M7 11V7a5 5 0 0 1 10 0v4"
            /></svg
          >
          Contraseña
        </button>
      {/if}

      {#if onToggleActive}
        <button
          onclick={() => {
            onToggleActive?.(menuState?.item);
            closeDropdown();
          }}
          class="menu-item"
        >
          {#if menuState?.item?.usuario?.esta_activo}
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
              ><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline
                points="22 4 12 14.01 9 11.01"
              /></svg
            >
          {:else}
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
              ><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line
                x1="9"
                y1="9"
                x2="15"
                y2="15"
              /></svg
            >
          {/if}
          {menuState?.item?.usuario?.esta_activo ? 'Desactivar' : 'Activar'}
        </button>
      {/if}

      {#if onDelete}
        {#if onEdit || onPasswordChange || onToggleActive || onCustomAction || onSyllabus}
          <div class="h-px bg-gray-100 my-1"></div>
        {/if}
        {@const delDisabled = isDeleteDisabled ? isDeleteDisabled(menuState?.item) : false}
        <button
          onclick={() => {
            if (delDisabled) return;
            onDelete?.(menuState?.item);
            closeDropdown();
          }}
          disabled={!!delDisabled}
          title={typeof delDisabled === 'string' ? delDisabled : undefined}
          class="menu-item menu-item-danger {delDisabled ? 'opacity-40 cursor-not-allowed' : ''}"
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
            ><polyline points="3 6 5 6 21 6" /><path
              d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
            /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" /></svg
          >
          {deleteActionLabel}
        </button>
      {/if}
    </div>
  {/if}
</div>

<style>
  /*
   * Menú de acciones de fila.
   *
   * Antes cada entrada llevaba su propio color —violeta para permisos, gris
   * para editar, verde para contraseña, azul para desactivar, rojo para
   * eliminar— sin que la agrupación significara nada. Ahora todas comparten
   * un tratamiento neutro y el rojo queda reservado a lo que destruye datos,
   * que es la única distinción que el usuario necesita ver de un vistazo.
   */
  .menu-item {
    width: 100%;
    text-align: left;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--action-neutral-fg);
    background: transparent;
    border: 0;
    cursor: pointer;
    transition: background-color 0.15s;
  }

  .menu-item:hover {
    background: var(--action-neutral-hover);
  }

  .menu-item-danger {
    color: var(--action-danger);
  }

  .menu-item-danger:hover {
    background: var(--action-danger-soft);
  }
</style>
