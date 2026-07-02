<script lang="ts">
  /**
   * departamentoList — Tabla expandible de departamentos con sus carreras.
   *
   * Cada fila puede expandirse para listar las carreras anidadas (con enlace
   * a /admin/carreras). Reglas visibles en la tabla:
   * - fecha_eliminacion marca el departamento como "Discontinuado": fila
   *   atenuada y sin acciones.
   * - "Discontinuar" (soft delete) se bloquea con tooltip si el departamento
   *   aún tiene carreras asociadas (carreras_count > 0).
   */
  import type { Departamento, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    departamentos: PaginatedResponse<Departamento>;
    /** Permisos del usuario; ocultan los botones de acción. */
    canEdit?: boolean;
    canDelete?: boolean;
    /** Abre el modal de edición. */
    onEdit?: (departamento: Departamento) => void;
    /** Abre la confirmación de discontinuación. */
    onDelete?: (departamento: Departamento) => void;
  }

  let {
    departamentos,
    canEdit = false,
    canDelete = false,
    onEdit = () => {},
    onDelete = () => {},
  }: Props = $props();

  /** Ids de departamentos con la sub-tabla de carreras desplegada. */
  let expandedRows = $state<Set<number>>(new Set());

  function toggleExpand(id: number) {
    const next = new Set(expandedRows);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    expandedRows = next;
  }

  // Guardas de doble seguridad: el template ya oculta los botones, pero se
  // revalida por si un departamento discontinuado llega a dispararlos.
  function handleEdit(departamento: Departamento) {
    if (departamento.fecha_eliminacion || !canEdit) return;
    onEdit?.(departamento);
  }

  function handleDelete(departamento: Departamento) {
    if (departamento.fecha_eliminacion || !canDelete) return;
    onDelete?.(departamento);
  }
</script>

<!-- Data Grid con Row Expansion -->
<div class="overflow-x-auto bg-white rounded-lg shadow">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-200">
      <tr>
        <th class="w-10"></th>
        <th class="px-4 py-3 text-left font-semibold text-gray-700">Departamento</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-700">Facultad</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-700">N° Carreras</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-700">Estado</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-700">Acciones</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
      {#each departamentos.data as departamento (departamento.id_departamento)}
        {@const isExpanded = expandedRows.has(departamento.id_departamento)}
        {@const carrerasCount = departamento.carreras_count ?? 0}
        {@const isDiscontinuado = !!departamento.fecha_eliminacion}
        <!-- Main row -->
        <tr
          class={`transition-colors ${isDiscontinuado ? 'opacity-60 bg-gray-50/40' : 'hover:bg-gray-50'}`}
        >
          <!-- Expand toggle -->
          <td class="pl-3 pr-1 py-3 text-center">
            {#if !isDiscontinuado && carrerasCount > 0}
              <button
                onclick={() => toggleExpand(departamento.id_departamento)}
                class="w-6 h-6 flex items-center justify-center rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 border-0 cursor-pointer transition-all"
                aria-label={isExpanded ? 'Colapsar' : 'Expandir'}
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
                  class={`transition-transform duration-200 ${isExpanded ? 'rotate-90' : ''}`}
                >
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </button>
            {:else}
              <span class="w-6 h-6 inline-block"></span>
            {/if}
          </td>
          <!-- Nombre -->
          <td class="px-4 py-3">
            <span
              class={`font-medium ${isDiscontinuado ? 'line-through text-gray-400' : 'text-gray-900'}`}
            >
              {departamento.nombre}
            </span>
          </td>
          <!-- Facultad -->
          <td class="px-4 py-3">
            {#if departamento.facultad}
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-medium border border-blue-100"
              >
                {departamento.facultad.nombre}
              </span>
            {:else}
              <span class="text-gray-400">—</span>
            {/if}
          </td>
          <!-- N° Carreras -->
          <td class="px-4 py-3">
            <span
              class={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${
                carrerasCount > 0
                  ? 'bg-indigo-50 text-indigo-700 border border-indigo-100'
                  : 'bg-gray-100 text-gray-500'
              }`}
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="11"
                height="11"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
              >
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              {carrerasCount}
            </span>
          </td>
          <!-- Estado -->
          <td class="px-4 py-3">
            {#if isDiscontinuado}
              <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>
                Discontinuado
              </span>
            {:else}
              <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-50 text-green-700 text-xs font-semibold border border-green-100"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                Activo
              </span>
            {/if}
          </td>
          <!-- Acciones -->
          <td class="px-4 py-3">
            {#if !isDiscontinuado}
              <div class="flex items-center gap-2">
                {#if canEdit}
                  <button
                    onclick={() => handleEdit(departamento)}
                    class="text-blue-600 hover:text-blue-800 font-medium text-xs border-0 bg-transparent cursor-pointer"
                  >
                    Editar
                  </button>
                {/if}
                {#if canDelete}
                  {#if carrerasCount > 0}
                    <!-- Disabled with tooltip -->
                    <span
                      class="relative group inline-flex"
                      title="Debe reasignar o discontinuar las carreras asociadas antes de cerrar este departamento."
                    >
                      <button
                        disabled
                        class="text-gray-300 font-medium text-xs border-0 bg-transparent cursor-not-allowed select-none"
                      >
                        Discontinuar
                      </button>
                      <!-- Tooltip -->
                      <span
                        class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 rounded-md bg-gray-800 px-2.5 py-1.5 text-[11px] leading-snug text-white opacity-0 group-hover:opacity-100 transition-opacity z-50 text-center shadow-lg"
                      >
                        Debe reasignar o discontinuar las carreras asociadas antes de cerrar este
                        departamento.
                        <span
                          class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"
                        ></span>
                      </span>
                    </span>
                  {:else}
                    <button
                      onclick={() => handleDelete(departamento)}
                      class="text-red-500 hover:text-red-700 font-medium text-xs border-0 bg-transparent cursor-pointer"
                    >
                      Discontinuar
                    </button>
                  {/if}
                {/if}
              </div>
            {:else}
              <span class="text-gray-300 text-[11px] italic">No disponible</span>
            {/if}
          </td>
        </tr>
        <!-- Expandable sub-row: nested Carreras -->
        {#if isExpanded && !isDiscontinuado}
          <tr class="bg-indigo-50/40">
            <td colspan="6" class="px-0 py-0">
              <div class="border-l-4 border-indigo-300 ml-9 mr-4 my-2 rounded-md overflow-hidden">
                <table class="w-full text-xs">
                  <thead class="bg-indigo-100/60">
                    <tr>
                      <th class="px-4 py-2 text-left font-semibold text-indigo-700">Carrera</th>
                      <th class="px-4 py-2 text-left font-semibold text-indigo-700">Jornada</th>
                      <th class="px-4 py-2 text-left font-semibold text-indigo-700">Sede</th>
                      <th class="px-4 py-2 text-left font-semibold text-indigo-700">Modalidad</th>
                      <th class="px-4 py-2 text-left font-semibold text-indigo-700"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-indigo-100">
                    {#each departamento.carreras ?? [] as carrera (carrera.id_carrera)}
                      <tr class="hover:bg-indigo-50 transition-colors">
                        <td class="px-4 py-2 font-medium text-gray-800">{carrera.nombre}</td>
                        <td class="px-4 py-2 text-gray-600">{carrera.jornada ?? '—'}</td>
                        <td class="px-4 py-2 text-gray-600">{carrera.sede ?? '—'}</td>
                        <td class="px-4 py-2 text-gray-600">{carrera.modalidad ?? '—'}</td>
                        <td class="px-4 py-2 text-right">
                          <a
                            href={`/admin/carreras?id_carrera=${carrera.id_carrera}`}
                            class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium"
                          >
                            Ir a Carrera
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              width="10"
                              height="10"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              stroke-width="2.5"
                            >
                              <line x1="5" y1="12" x2="19" y2="12"></line>
                              <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                          </a>
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </td>
          </tr>
        {/if}
      {/each}
    </tbody>
  </table>
</div>
