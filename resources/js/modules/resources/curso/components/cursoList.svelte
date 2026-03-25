<script lang="ts">
  /**
   * Componente: Lista de Cursos
   *
   * Tabla expandible con toolbar para filtros, búsqueda, status toggle y paginación.
   * Muestra información enriquecida de cursos con docentes, asignaturas, etc.
   *
   * Props:
   * - cursos: PaginatedResponse<Curso> - Datos paginados del servidor
   * - searchTerm: string - Término de búsqueda actual
   * - status: string - Filtro de estado (active/all)
   * - perPage: number - Registros por página
   * - paginationOptions: number[] - Opciones de per-page
   * - statusOptions: {ACTIVE, ALL} - Opciones de estado
   * - onSearchChange: (term: string) => void - Callback búsqueda
   * - onStatusChange: (status: string) => void - Callback estado
   * - onPerPageChange: (value: number) => void - Callback per-page
   * - onPageChange: (page: number) => void - Callback cambio página
   * - onEdit: (curso: Curso) => void - Callback editar
   * - onDelete: (curso: Curso) => void - Callback eliminar
   * - onTeam: (curso: Curso) => void - Callback gestionar equipo
   * - onSyllabus: (curso: Curso) => void - Callback gestionar programa
   */
  import PaginationControls from '@/components/admin/PaginationControls.svelte';
  import { Edit2, Trash2, Plus, Users, BookOpen } from 'lucide-svelte';
  import type { Curso, PaginatedResponse } from '../types/curso.types';

  interface Props {
    cursos?: PaginatedResponse<Curso>;
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
    onCreateNew?: () => void;
    onEdit?: (curso: Curso) => void;
    onDelete?: (curso: Curso) => void;
    onTeam?: (curso: Curso) => void;
    onSyllabus?: (curso: Curso) => void;
  }

  let {
    cursos = {
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
    onCreateNew = () => {},
    onEdit = () => {},
    onDelete = () => {},
    onTeam = () => {},
    onSyllabus = () => {},
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
        placeholder="Buscar curso..."
        onkeydown={(e) => e.key === 'Enter' && handleSearch()}
        onchange={handleSearchInput}
        class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
      />
      <button
        onclick={handleSearch}
        class="px-3 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
      >
        Buscar
      </button>
    </div>

    <!-- Status Toggle -->
    <div class="flex gap-2">
      <button
        onclick={() => handleStatusToggle(statusOptions.ACTIVE)}
        class={`px-3 py-2 text-sm font-medium rounded-lg transition ${
          status === statusOptions.ACTIVE
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
        }`}
      >
        Vigentes
      </button>
      <button
        onclick={() => handleStatusToggle(statusOptions.ALL)}
        class={`px-3 py-2 text-sm font-medium rounded-lg transition ${
          status === statusOptions.ALL
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
        }`}
      >
        Todos
      </button>
    </div>

    <!-- Per Page Selector -->
    <div class="flex items-center gap-2">
      <select
        value={perPage}
        onchange={handlePerPageChange}
        class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-900 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
      >
        {#each paginationOptions as option}
          <option value={option}>{option} por página</option>
        {/each}
      </select>
    </div>

    <!-- Crear Nuevo -->
    <button
      onclick={onCreateNew}
      class="ml-auto px-3 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
    >
      <Plus size={16} />
      Crear Curso
    </button>
  </div>

  <!-- Tabla -->
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
          <th
            class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
            >Código</th
          >
          <th
            class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
            >Asignatura</th
          >
          <th
            class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
            >Carrera</th
          >
          <th
            class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
            >Docente</th
          >
          <th
            class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
            >Semestre</th
          >
          <th
            class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider"
            >Acciones</th
          >
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        {#if cursos.data.length === 0}
          <tr>
            <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">
              No hay cursos para mostrar.
            </td>
          </tr>
        {:else}
          {#each cursos.data as curso (curso.id_curso)}
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                {curso.cod_curso}
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                {curso.asignatura_nombre || '-'}
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                {curso.carrera_nombre || '-'}
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                {curso.docente_nombre || '-'}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                {curso.numero_semestre || '-'}
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-2 flex-wrap">
                  <button
                    onclick={() => onEdit(curso)}
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sky-600 hover:bg-sky-50 rounded-lg transition font-medium text-xs"
                    title="Editar curso"
                  >
                    <Edit2 size={14} />
                    Editar
                  </button>
                  <button
                    onclick={() => onTeam(curso)}
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition font-medium text-xs"
                    title="Gestionar equipo"
                  >
                    <Users size={14} />
                    Equipo
                  </button>
                  <button
                    onclick={() => onSyllabus(curso)}
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition font-medium text-xs"
                    title="Gestionar programa"
                  >
                    <BookOpen size={14} />
                    Programa
                  </button>
                  <button
                    onclick={() => onDelete(curso)}
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded-lg transition font-medium text-xs"
                    title="Eliminar curso"
                  >
                    <Trash2 size={14} />
                    Eliminar
                  </button>
                </div>
              </td>
            </tr>
          {/each}
        {/if}
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  {#if cursos.last_page > 1}
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50/60">
      <PaginationControls
        currentPage={cursos.current_page}
        lastPage={cursos.last_page}
        from={cursos.from}
        to={cursos.to}
        total={cursos.total}
        {onPageChange}
      />
    </div>
  {/if}
</div>
