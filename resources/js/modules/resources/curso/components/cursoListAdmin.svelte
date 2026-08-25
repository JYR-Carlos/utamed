<script lang="ts">
  /**
   * Componente: Lista de Cursos (Admin) — Rediseño Enterprise
   *
   * Data grid de alta densidad con:
   * - Búsqueda en tiempo real (debounced)
   * - Segmented control de estado (Activos / Históricos / Todos)
   * - Badge de secciones y badge de estado de programa
   * - Botón "Gestionar" que abre el slide-over
   * - Menú kebab (⋮) para acciones secundarias (Editar, Eliminar)
   */
  import PaginationControls from '@/components/admin/PaginationControls.svelte';
  import { Plus, Search, MoreVertical, Edit2, Trash2 } from 'lucide-svelte';
  import { untrack } from 'svelte';
  import type { Curso, Componente, PaginatedResponse } from '../types/curso.types';

  interface Props {
    cursos?: PaginatedResponse<Curso>;
    searchTerm?: string;
    status?: string;
    perPage?: number;
    paginationOptions?: readonly number[];
    onSearchChange?: (term: string) => void;
    onStatusChange?: (status: string) => void;
    onPerPageChange?: (value: number) => void;
    onPageChange?: (page: number) => void;
    onCreateNew?: () => void;
    /** Abre el slide-over de gestión */
    onManage?: (curso: Curso) => void;
    /** Desde menú kebab */
    onEdit?: (curso: Curso) => void;
    /** Desde menú kebab */
    onDelete?: (curso: Curso) => void;
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
    onSearchChange = () => {},
    onStatusChange = () => {},
    onPerPageChange = () => {},
    onPageChange = () => {},
    onCreateNew = () => {},
    onManage = () => {},
    onEdit = () => {},
    onDelete = () => {},
  }: Props = $props();

  // ── Búsqueda con debounce ──────────────────────────────────────────────────
  // untrack() previene la advertencia de Svelte 5 "state_referenced_locally"
  // ya que capturar el valor inicial del prop es el comportamiento deseado.
  let localSearch = $state(untrack(() => searchTerm));
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;

  function handleSearchInput(value: string) {
    localSearch = value;
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => onSearchChange(value), 350);
  }

  // ── Kebab menu ─────────────────────────────────────────────────────────────
  let openKebabId = $state<number | null>(null);

  function toggleKebab(id: number, e: MouseEvent) {
    e.stopPropagation();
    openKebabId = openKebabId === id ? null : id;
  }

  function closeKebab() {
    openKebabId = null;
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  function handlePerPageChange(e: Event) {
    const target = e.target as HTMLSelectElement;
    onPerPageChange(Number(target.value));
  }

  function getSeccionesBadge(curso: Curso): { count: number; label: string } {
    const count = curso.componentes?.length ?? 0;
    return { count, label: count === 1 ? '1 Sección' : `${count} Secciones` };
  }

  interface ProgramaBadge {
    label: string;
    cls: string;
  }

  function getProgramaBadge(curso: Curso): ProgramaBadge | null {
    const estado = curso.programa_estado;
    if (!estado || estado === 'SIN_PROGRAMA') return null;
    const map: Record<string, ProgramaBadge> = {
      BORRADOR: { label: 'Borrador', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
      BASICO_COMPLETO: { label: 'Básico', cls: 'bg-blue-50 text-blue-700 border-blue-200' },
      COMPLETO: { label: 'Completo', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
      APROBADO: { label: 'Aprobado', cls: 'bg-green-50 text-green-700 border-green-200' },
      ENVIADO: { label: 'Enviado', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
    };
    return map[estado] ?? { label: estado, cls: 'bg-gray-100 text-gray-600 border-gray-200' };
  }

  const STATUS_SEGMENTS = [
    { label: 'Activos', value: 'active' },
    { label: 'Históricos', value: 'inactive' },
    { label: 'Todos', value: 'all' },
  ] as const;
</script>

<svelte:window onclick={closeKebab} />

<div class="flex flex-col bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
  <!-- ── Toolbar ──────────────────────────────────────────────────────────── -->
  <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
    <!-- Búsqueda en tiempo real -->
    <div class="relative flex-1 min-w-[220px] max-w-sm">
      <Search
        size={14}
        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
      />
      <input
        type="text"
        value={localSearch}
        oninput={(e) => handleSearchInput((e.target as HTMLInputElement).value)}
        placeholder="Buscar por nombre o código..."
        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
      />
    </div>

    <!-- Segmented control de estado -->
    <div class="flex items-center bg-gray-100 rounded-lg p-0.5 gap-px">
      {#each STATUS_SEGMENTS as seg}
        <button
          onclick={() => onStatusChange(seg.value)}
          class="px-3.5 py-1.5 text-sm font-medium rounded-md transition-all {status === seg.value
            ? 'bg-white text-gray-900 shadow-sm'
            : 'text-gray-500 hover:text-gray-700'}"
        >
          {seg.label}
        </button>
      {/each}
    </div>

    <!-- Per page -->
    <select
      value={perPage}
      onchange={handlePerPageChange}
      class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-600 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
    >
      {#each paginationOptions as opt}
        <option value={opt}>{opt} / página</option>
      {/each}
    </select>

    <!-- La acción de crear vive en la cabecera de la página, como en el
         resto de módulos; aquí quedaba enterrada entre los filtros. -->
  </div>

  <!-- ── Tabla ────────────────────────────────────────────────────────────── -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-100 bg-gray-50/70">
          <th
            class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
            >Asignatura</th
          >
          <th
            class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
            >Carrera</th
          >
          <th
            class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
            >Período</th
          >
          <th
            class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
            >Secciones</th
          >
          <th
            class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider"
            >Programa</th
          >
          <th
            class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider"
            >Acciones</th
          >
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        {#if cursos.data.length === 0}
          <tr>
            <td colspan="6" class="px-5 py-14 text-center">
              <div class="flex flex-col items-center gap-2 text-gray-400">
                <Search size={30} class="text-gray-300" />
                <p class="text-sm">No se encontraron cursos.</p>
              </div>
            </td>
          </tr>
        {:else}
          {#each cursos.data as curso (curso.id_curso)}
            {@const sec = getSeccionesBadge(curso)}
            {@const prog = getProgramaBadge(curso)}
            <tr
              class="hover:bg-gray-50/80 transition-colors group cursor-pointer"
              onclick={() => onManage(curso)}
            >
              <!-- Asignatura + código -->
              <td class="px-5 py-3.5">
                <div>
                  <p class="font-semibold text-gray-900 leading-snug">
                    {curso.nombre || '(sin nombre) ' + curso.asignatura_nombre }
                  </p>
                  <p class="text-xs text-gray-500 font-mono mt-0.5">{curso.cod_curso}</p>
                </div>
              </td>

              <!-- Carrera -->
              <td class="px-5 py-3.5 text-gray-600 max-w-[200px]">
                <span class="line-clamp-2 text-sm">{curso.carrera_nombre || '—'}</span>
              </td>

              <!-- Período -->
              <td class="px-5 py-3.5 whitespace-nowrap">
                {#if curso.asignacionPlan?.semestre_planificado && curso.asignacionPlan?.agno_planificado}
                  <div class="flex flex-col gap-0.5">
                    <span class="text-sm font-medium text-gray-800">
                      {curso.asignacionPlan.agno_planificado}
                    </span>
                    <span class="text-xs text-gray-500">
                      Semestre {curso.asignacionPlan.semestre_planificado}
                    </span>
                  </div>
                {:else if curso.numero_semestre}
                  <span class="text-sm text-gray-500">Sem. {curso.numero_semestre}</span>
                {:else}
                  <span class="text-gray-400">—</span>
                {/if}
              </td>

              <!-- Secciones badge -->
              <td class="px-5 py-3.5">
                {#if sec.count > 0}
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200"
                  >
                    {sec.label}
                  </span>
                {:else}
                  <span class="text-xs text-gray-500">Sin secciones</span>
                {/if}
              </td>

              <!-- Programa badge -->
              <td class="px-5 py-3.5">
                {#if prog}
                  <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {prog.cls}"
                  >
                    {prog.label}
                  </span>
                {:else}
                  <span class="text-xs text-gray-400">—</span>
                {/if}
              </td>

              <!-- Acciones -->
              <td class="px-5 py-3.5 text-right" onclick={(e) => e.stopPropagation()} role="cell">
                <div class="flex items-center justify-end gap-1.5">
                  <!-- Gestionar (visible on hover) -->
                  <button
                    onclick={() => onManage(curso)}
                    class="px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-100 transition opacity-0 group-hover:opacity-100 focus:opacity-100"
                  >
                    Gestionar
                  </button>

                  <!-- Kebab ⋮ -->
                  <div class="relative">
                    <button
                      onclick={(e) => toggleKebab(curso.id_curso, e)}
                      class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
                      aria-label="Más opciones"
                      aria-haspopup="true"
                      aria-expanded={openKebabId === curso.id_curso}
                    >
                      <MoreVertical size={15} />
                    </button>

                    {#if openKebabId === curso.id_curso}
                      <div
                        class="absolute right-0 top-full mt-1 z-20 w-44 bg-white border border-gray-200 rounded-xl shadow-lg py-1 divide-y divide-gray-100"
                        role="menu"
                        tabindex="-1"
                        onclick={(e) => e.stopPropagation()}
                        onkeydown={(e) => e.key === 'Escape' && closeKebab()}
                      >
                        <div class="py-1">
                          <button
                            onclick={() => {
                              onEdit(curso);
                              closeKebab();
                            }}
                            class="flex items-center gap-2.5 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition"
                            role="menuitem"
                          >
                            <Edit2 size={13} class="text-gray-400" />
                            Editar curso
                          </button>
                        </div>
                        <div class="py-1">
                          <button
                            onclick={() => {
                              onDelete(curso);
                              closeKebab();
                            }}
                            class="flex items-center gap-2.5 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition"
                            role="menuitem"
                          >
                            <Trash2 size={13} />
                            Eliminar
                          </button>
                        </div>
                      </div>
                    {/if}
                  </div>
                </div>
              </td>
            </tr>
          {/each}
        {/if}
      </tbody>
    </table>
  </div>

  <!-- ── Paginación ───────────────────────────────────────────────────────── -->
  {#if cursos.last_page > 1}
    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60">
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
