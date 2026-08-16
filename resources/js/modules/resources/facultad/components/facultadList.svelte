<script lang="ts">
  /**
   * facultadList — Tabla expandible de facultades con sus departamentos
   * anidados; permite crear/eliminar departamentos desde la propia fila.
   *
   * Presentacional: todas las acciones se delegan al padre vía callbacks.
   * Las facultades con fecha_eliminacion se muestran atenuadas y sin
   * acciones (guardas de doble seguridad en los handlers).
   */
  import { ChevronDown, ChevronRight, Plus, Trash2 } from 'lucide-svelte';
  import type { Facultad, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    facultades: PaginatedResponse<Facultad>;
    /** Permisos del usuario; ocultan los botones de acción. */
    canEdit?: boolean;
    canDelete?: boolean;
    onEdit?: (facultad: Facultad) => void;
    onDelete?: (facultad: Facultad) => void;
    /** Abre el modal contextual de nuevo departamento para la facultad. */
    onAddDepartamento?: (facultad: Facultad) => void;
    /** Abre la confirmación de borrado del departamento. */
    onDeleteDepartamento?: (deptId: number) => void;
  }

  let {
    facultades,
    canEdit = false,
    canDelete = false,
    onEdit = () => {},
    onDelete = () => {},
    onAddDepartamento = () => {},
    onDeleteDepartamento = () => {},
  }: Props = $props();

  let expandedRows: Record<number, boolean> = $state({});

  function toggleRow(id: number) {
    expandedRows[id] = !expandedRows[id];
  }

  function handleEdit(facultad: Facultad) {
    if (facultad.fecha_eliminacion || !canEdit) return;
    onEdit?.(facultad);
  }

  function handleDelete(facultad: Facultad) {
    if (facultad.fecha_eliminacion || !canDelete) return;
    onDelete?.(facultad);
  }

  function handleAddDepartamento(facultad: Facultad) {
    if (facultad.fecha_eliminacion) return;
    onAddDepartamento?.(facultad);
  }

  function handleDeleteDepartamento(deptId: number) {
    onDeleteDepartamento?.(deptId);
  }
</script>

<!-- Tabla expandible de Facultades -->
<div class="overflow-x-auto bg-white rounded-lg shadow">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-200">
      <tr>
        <!-- Sin columna ID: el identificador de la base de datos no le sirve
             a quien gestiona facultades, y ninguna otra tabla lo mostraba. -->
        <th class="px-6 py-3 text-left font-semibold text-gray-700"></th>
        <th class="px-6 py-3 text-left font-semibold text-gray-700">Nombre</th>
        <th class="px-6 py-3 text-left font-semibold text-gray-700">Departamentos</th>
        <th class="px-6 py-3 text-left font-semibold text-gray-700">Acciones</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
      {#each facultades.data as facultad (facultad.id_facultad)}
        <tr
          class={`hover:bg-gray-50 ${facultad.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : ''}`}
        >
          <!-- Expand button -->
          <td class="px-6 py-3">
            <button
              onclick={() => toggleRow(facultad.id_facultad)}
              class="text-gray-400 hover:text-gray-600"
              disabled={!!facultad.fecha_eliminacion}
            >
              {#if expandedRows[facultad.id_facultad]}
                <ChevronDown size={18} />
              {:else}
                <ChevronRight size={18} />
              {/if}
            </button>
          </td>

          <!-- Nombre -->
          <td class="px-6 py-3 text-gray-900 font-medium">
            <div class="flex items-center gap-2">
              <span class={`${facultad.fecha_eliminacion ? 'line-through text-gray-400' : ''}`}>
                {facultad.nombre}
              </span>
              {#if facultad.fecha_eliminacion}
                <span class="badge badge-off">Eliminada</span>
              {/if}
            </div>
          </td>

          <!-- Cuántos departamentos cuelgan de ella: es el dato que la
               columna ID ocupaba sin aportar nada. -->
          <td class="px-6 py-3 text-gray-600">
            {#if facultad.departamentos?.length}
              {facultad.departamentos.length}
            {:else}
              <span class="text-gray-400">Ninguno</span>
            {/if}
          </td>

          <!-- Acciones -->
          <td class="px-6 py-3">
            <div class="flex items-center gap-1.5">
              {#if canEdit && !facultad.fecha_eliminacion}
                <button onclick={() => handleEdit(facultad)} class="btn btn-neutral btn-sm">
                  Editar
                </button>
              {/if}
              {#if canDelete && !facultad.fecha_eliminacion}
                <button onclick={() => handleDelete(facultad)} class="btn btn-neutral btn-sm">
                  Eliminar
                </button>
              {/if}
              {#if facultad.fecha_eliminacion}
                <span class="text-gray-400 text-xs">Eliminada</span>
              {/if}
            </div>
          </td>
        </tr>

        <!-- Expandable sub-row: Departamentos -->
        {#if expandedRows[facultad.id_facultad]}
          <tr class="bg-gray-50">
            <td colspan="4" class="px-6 py-4">
              <!-- 4 columnas: expandir, nombre, departamentos, acciones -->
              <div class="space-y-4">
                <!-- Encabezado de Departamentos -->
                <div class="flex justify-between items-center mb-3">
                  <h3 class="font-semibold text-gray-700">Departamentos</h3>
                  {#if !facultad.fecha_eliminacion}
                    <button
                      onclick={() => handleAddDepartamento(facultad)}
                      class="btn btn-neutral btn-sm"
                    >
                      <Plus size={14} />
                      Agregar departamento
                    </button>
                  {/if}
                </div>

                <!-- Tabla de Departamentos -->
                {#if facultad.departamentos && facultad.departamentos.length > 0}
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm bg-white rounded-lg border border-gray-200">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-4 py-2 text-left font-semibold text-gray-700">
                            Nombre del departamento
                          </th>
                          <th class="px-4 py-2 text-left font-semibold text-gray-700">Acciones</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        {#each facultad.departamentos as dept (dept.id_departamento)}
                          <tr
                            class={`hover:bg-gray-50 ${dept.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : ''}`}
                          >
                            <td class="px-4 py-2">
                              <div class="flex items-center gap-2">
                                <span
                                  class={`text-gray-900 ${dept.fecha_eliminacion ? 'line-through text-gray-400' : ''}`}
                                >
                                  {dept.nombre}
                                </span>
                                {#if dept.fecha_eliminacion}
                                  <span
                                    class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-200 text-gray-600"
                                  >
                                    Eliminado
                                  </span>
                                {/if}
                              </div>
                            </td>
                            <td class="px-4 py-2">
                              {#if dept.fecha_eliminacion}
                                <span class="text-gray-400 text-xs">Eliminado</span>
                              {:else}
                                <button
                                  onclick={() => handleDeleteDepartamento(dept.id_departamento)}
                                  class="btn btn-neutral btn-sm"
                                >
                                  <Trash2 size={12} />
                                  Eliminar
                                </button>
                              {/if}
                            </td>
                          </tr>
                        {/each}
                      </tbody>
                    </table>
                  </div>
                {:else}
                  <div class="text-center py-4 text-gray-500 text-sm">
                    No hay departamentos. Haz clic en "Agregar" para crear uno.
                  </div>
                {/if}
              </div>
            </td>
          </tr>
        {/if}
      {/each}
    </tbody>
  </table>
</div>
