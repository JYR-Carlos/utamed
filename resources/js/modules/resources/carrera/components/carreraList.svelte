<script lang="ts">
  /**
   * Componente: Lista de Carreras
   *
   * Tabla expandible con toolbar para filtros, búsqueda, status toggle y paginación.
   * Muestra información enriquecida de carreras con atributos, estado RBAC, etc.
   *
   * Props:
   * - carreras: PaginatedResponse<Carrera> - Datos paginados del servidor
   * - searchTerm: string - Término de búsqueda actual
   * - status: string - Filtro de estado (active/all)
   * - perPage: number - Registros por página
   * - paginationOptions: number[] - Opciones de per-page
   * - statusOptions: {ACTIVE, ALL} - Opciones de estado
   * - onSearchChange: (term: string) => void - Callback búsqueda
   * - onStatusChange: (status: string) => void - Callback estado
   * - onPerPageChange: (value: number) => void - Callback per-page
   * - onPageChange: (page: number) => void - Callback cambio página
   * - onEdit: (carrera: Carrera) => void - Callback editar
   * - onDiscontinue: (carrera: Carrera) => void - Callback discontinuar
   */
  import AttriBadges from '@/components/admin/AttriBadges.svelte';
  import PaginationControls from '@/components/admin/PaginationControls.svelte';
  import type { Carrera, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    carreras?: PaginatedResponse<Carrera>;
    searchTerm?: string;
    status?: string;
    perPage?: number;
    paginationOptions?: readonly number[];
    statusOptions?: { ACTIVE: string; ALL: string };
    onSearchChange?: (term: string) => void;
    onSearch?: () => void;
    onStatusChange?: (status: string) => void;
    onPerPageChange?: (value: number) => void;
    onPageChange?: (page: number) => void;
    onEdit?: (carrera: Carrera) => void;
    onDiscontinue?: (carrera: Carrera) => void;
  }

  let {
    carreras = {
      data: [],
      current_page: 1,
      last_page: 1,
      per_page: 15,
      from: 0,
      to: 0,
      total: 0,
      links: [],
    },
    searchTerm = '',
    status = 'active',
    perPage = 15,
    paginationOptions = [15, 30, 50] as const,
    statusOptions = { ACTIVE: 'active', ALL: 'all' },
    onSearchChange = () => {},
    onSearch = () => {},
    onStatusChange = () => {},
    onPerPageChange = () => {},
    onPageChange = () => {},
    onEdit = () => {},
    onDiscontinue = () => {},
  }: Props = $props();

  function handleSearch() {
    onSearch();
  }

  function handleStatusToggle(newStatus: string) {
    onStatusChange(newStatus);
  }

  function handlePerPageChange(e: Event) {
    const target = e.target as HTMLSelectElement;
    onPerPageChange(Number(target.value));
  }

  function handleSearchInput(e: Event) {
    const target = e.target as HTMLInputElement;
    onSearchChange(target.value);
  }
</script>

<!-- Tabla con toolbar -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
  <!-- Toolbar -->
  <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3 bg-gray-50/60">
    <!-- Search -->
    <div class="flex gap-2 flex-1 min-w-[200px]">
      <input
        type="text"
        bind:value={searchTerm}
        placeholder="Buscar carrera..."
        onkeydown={(e) => e.key === 'Enter' && handleSearch()}
        onchange={handleSearchInput}
        class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
      />
      <button
        onclick={handleSearch}
        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition cursor-pointer"
      >
        Buscar
      </button>
    </div>

    <!-- Status toggle -->
    <div
      class="flex rounded-lg border border-gray-200 overflow-hidden text-[13px] font-medium shadow-sm"
    >
      <button
        onclick={() => handleStatusToggle(statusOptions.ACTIVE)}
        class={`px-3 py-1.5 transition cursor-pointer ${status === statusOptions.ACTIVE ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
      >
        Solo Activos
      </button>
      <button
        onclick={() => handleStatusToggle(statusOptions.ALL)}
        class={`px-3 py-1.5 border-l border-gray-200 transition cursor-pointer ${status === statusOptions.ALL ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
      >
        Todos
      </button>
    </div>

    <!-- Per-page -->
    <select
      value={perPage}
      onchange={handlePerPageChange}
      class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400 transition"
    >
      {#each paginationOptions as opt}
        <option value={opt}>{opt} por página</option>
      {/each}
    </select>
  </div>

  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
      <thead>
        <tr class="bg-gray-50 border-b border-gray-200">
          <th
            class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
            >Nombre</th
          >
          <th
            class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
            >Atributos</th
          >
          <th
            class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap"
            >Departamento</th
          >
          <th
            class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap"
            >Planes Activos</th
          >
          <th
            class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap"
            >Estado RBAC</th
          >
          <th
            class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
            >Estado</th
          >
          <th
            class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400"
            >Acciones</th
          >
        </tr>
      </thead>
      <tbody>
        {#if carreras.data.length === 0}
          <tr>
            <td colspan="7" class="px-4 py-14 text-center text-gray-400 text-sm">
              No se encontraron resultados
            </td>
          </tr>
        {:else}
          {#each carreras.data as carrera (carrera.id_carrera)}
            <tr
              class={`border-b border-gray-100 last:border-0 transition-colors ${carrera.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : 'bg-white hover:bg-blue-50/20'}`}
            >
              <!-- Nombre + ID -->
              <td class="px-4 py-3 align-middle">
                <div
                  class={`font-semibold text-[13px] leading-snug ${carrera.fecha_eliminacion ? 'text-gray-400 line-through' : 'text-gray-900'}`}
                >
                  {carrera.nombre}
                </div>
                <div class="text-[11px] text-gray-400 font-mono mt-0.5">
                  #{carrera.id_carrera}
                </div>
              </td>

              <!-- Atributos: sede | jornada | modalidad badges -->
              <td class="px-4 py-3 align-middle">
                <AttriBadges
                  sede={carrera.sede}
                  jornada={carrera.jornada}
                  modalidad={carrera.modalidad}
                />
              </td>

              <!-- Departamento + Facultad -->
              <td class="px-4 py-3 align-middle">
                <div
                  class={`text-[13px] font-medium leading-snug ${carrera.departamento?.fecha_eliminacion ? 'line-through text-gray-400' : 'text-gray-900'}`}
                >
                  {carrera.departamento?.nombre ?? '—'}
                  {#if carrera.departamento?.fecha_eliminacion}
                    <span
                      class="text-[10px] font-semibold uppercase tracking-wide ml-1 px-1.5 py-0.5 rounded bg-gray-200 text-gray-600"
                    >
                      Eliminado
                    </span>
                  {/if}
                </div>
                {#if carrera.departamento?.facultad?.nombre}
                  <div class="text-[11px] text-gray-400 mt-0.5 leading-snug">
                    {carrera.departamento.facultad.nombre}
                  </div>
                {/if}
              </td>

              <!-- Planes Activos -->
              <td class="px-4 py-3 align-middle text-center">
                <span
                  class="inline-flex items-center justify-center min-w-[28px] px-2 py-1 rounded-full text-[11px] font-bold {carrera.planes_activos_count &&
                  carrera.planes_activos_count > 0
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                    : 'bg-gray-100 text-gray-500'}"
                >
                  {carrera.planes_activos_count ?? 0}
                </span>
              </td>

              <!-- Estado RBAC (has_director) -->
              <td class="px-4 py-3 align-middle">
                {#if carrera.has_director}
                  <span
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-purple-50 text-purple-700 text-[11px] font-semibold border border-purple-100"
                  >
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                    Director Asignado
                  </span>
                {:else}
                  <span class="text-gray-400 text-[11px]">Sin director</span>
                {/if}
              </td>

              <!-- Estado (Activa/Discontinuada) -->
              <td class="px-4 py-3 align-middle">
                {#if carrera.fecha_eliminacion}
                  <span
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-600 text-[11px] font-semibold"
                  >
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Discontinuada
                  </span>
                {:else}
                  <span
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold border border-emerald-100"
                  >
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Activa
                  </span>
                {/if}
              </td>

              <!-- Acciones -->
              <td class="px-4 py-3 align-middle">
                {#if !carrera.fecha_eliminacion}
                  <div class="flex items-center gap-2">
                    <button
                      onclick={() => onEdit(carrera)}
                      class="text-blue-600 hover:text-blue-800 font-medium text-xs border-0 bg-transparent cursor-pointer transition-colors"
                    >
                      Editar
                    </button>
                    <button
                      onclick={() => onDiscontinue(carrera)}
                      class="text-red-600 hover:text-red-800 font-medium text-xs border-0 bg-transparent cursor-pointer transition-colors"
                    >
                      Discontinuar
                    </button>
                  </div>
                {:else}
                  <span class="text-gray-300 text-[11px] italic">No disponible</span>
                {/if}
              </td>
            </tr>
          {/each}
        {/if}
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  {#if carreras.last_page > 1}
    <PaginationControls
      currentPage={carreras.current_page}
      lastPage={carreras.last_page}
      from={carreras.from}
      to={carreras.to}
      total={carreras.total}
      {onPageChange}
    />
  {/if}
</div>
