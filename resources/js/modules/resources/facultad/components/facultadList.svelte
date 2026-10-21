<script lang="ts">
  /**
   * facultadList — Tabla expandible de facultades con sus departamentos anidados.
   * Utiliza DataTable estandarizado con variant="accent" y paginate={false}.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import { Plus, Trash2 } from 'lucide-svelte';
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

  const columns = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'departamentos_count', label: 'Departamentos' },
  ];
</script>

<DataTable
  data={facultades}
  {columns}
  variant="accent"
  paginate={false}
  onEdit={canEdit ? (f) => (!f.fecha_eliminacion ? onEdit(f) : null) : undefined}
  onDelete={canDelete ? (f) => (!f.fecha_eliminacion ? onDelete(f) : null) : undefined}
  rowClass={(f) => (f.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : '')}
  searchPlaceholder="Buscar facultad..."
>
  {#snippet cellSnippet({ item, column })}
    {#if column.key === 'nombre'}
      <div class="flex items-center gap-2">
        <span class={item.fecha_eliminacion ? 'line-through text-gray-400' : 'text-gray-900 font-medium'}>
          {item.nombre}
        </span>
        {#if item.fecha_eliminacion}
          <span class="badge badge-off">Eliminada</span>
        {/if}
      </div>
    {:else if column.key === 'departamentos_count'}
      <span class="text-gray-600">
        {#if item.departamentos?.length}
          {item.departamentos.length}
        {:else}
          <span class="text-gray-400">Ninguno</span>
        {/if}
      </span>
    {/if}
  {/snippet}

  {#snippet expandableSnippet({ item })}
    <div class="space-y-4">
      <div class="flex justify-between items-center mb-3">
        <h3 class="font-semibold text-gray-700">Departamentos</h3>
        {#if !item.fecha_eliminacion}
          <button
            onclick={() => onAddDepartamento(item)}
            class="btn btn-neutral btn-sm"
          >
            <Plus size={14} />
            Agregar departamento
          </button>
        {/if}
      </div>

      {#if item.departamentos && item.departamentos.length > 0}
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
              {#each item.departamentos as dept (dept.id_departamento)}
                <tr class={`hover:bg-gray-50 ${dept.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : ''}`}>
                  <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                      <span class={dept.fecha_eliminacion ? 'line-through text-gray-400' : 'text-gray-900'}>
                        {dept.nombre}
                      </span>
                      {#if dept.fecha_eliminacion}
                        <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-200 text-gray-600">
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
                        onclick={() => onDeleteDepartamento(dept.id_departamento)}
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
  {/snippet}
</DataTable>
