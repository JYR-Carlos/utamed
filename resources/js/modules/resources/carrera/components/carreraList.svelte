<script lang="ts">
  /**
   * carreraList — Tabla de carreras con toolbar de búsqueda, filtro de estado y paginación.
   * Utiliza DataTable estandarizado con variant="accent".
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import AttriBadges from '@/components/admin/AttriBadges.svelte';
  import { router, page } from '@inertiajs/svelte';
  import type { Carrera, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    carreras?: PaginatedResponse<Carrera>;
    searchTerm?: string;
    /** Filtro de estado actual ('active' | 'all'). */
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
    /** Abre la confirmación de discontinuación (soft delete). */
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
    status = 'active',
    statusOptions = { ACTIVE: 'active', ALL: 'all' },
    onStatusChange,
    onEdit = () => {},
    onDiscontinue = () => {},
  }: Props = $props();

  const columns = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'atributos', label: 'Atributos' },
    { key: 'departamento', label: 'Departamento' },
    { key: 'planes_activos_count', label: 'Planes Activos', class: 'text-center' },
    { key: 'has_director', label: 'Director' },
    { key: 'estado', label: 'Estado' },
  ];

  let activeStatus = $derived(
    new URL($page.url, window.location.origin).searchParams.get('status') ?? status,
  );

  function handleStatusToggle(newStatus: string) {
    if (onStatusChange) {
      onStatusChange(newStatus);
    }
    const url = new URL($page.url, window.location.origin);
    url.searchParams.set('status', newStatus);
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
      only: ['carreras'],
    });
  }
</script>

<DataTable
  data={carreras}
  {columns}
  variant="accent"
  searchPlaceholder="Buscar carrera..."
  rowClass={(c) => (c.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : '')}
  hasRowActions={(c) => !c.fecha_eliminacion}
  deleteActionLabel="Discontinuar"
  only={['carreras']}
  onEdit={(c) => onEdit(c)}
  onDelete={(c) => onDiscontinue(c)}
>
  {#snippet filterSnippet()}
    <div
      class="flex rounded-lg border border-gray-200 overflow-hidden text-[13px] font-medium shadow-sm"
    >
      <button
        onclick={() => handleStatusToggle(statusOptions.ACTIVE)}
        class={`px-3 py-1.5 transition cursor-pointer ${activeStatus === statusOptions.ACTIVE ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
      >
        Solo Activos
      </button>
      <button
        onclick={() => handleStatusToggle(statusOptions.ALL)}
        class={`px-3 py-1.5 border-l border-gray-200 transition cursor-pointer ${activeStatus === statusOptions.ALL ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
      >
        Todos
      </button>
    </div>
  {/snippet}

  {#snippet cellSnippet({ item, column })}
    {#if column.key === 'nombre'}
      <div
        class={`font-semibold text-[13px] leading-snug ${item.fecha_eliminacion ? 'text-gray-400 line-through' : 'text-gray-900'}`}
      >
        {item.nombre}
      </div>
    {:else if column.key === 'atributos'}
      <AttriBadges sede={item.sede} jornada={item.jornada} modalidad={item.modalidad} />
    {:else if column.key === 'departamento'}
      <div
        class={`text-[13px] font-medium leading-snug ${item.departamento?.fecha_eliminacion ? 'line-through text-gray-400' : 'text-gray-900'}`}
      >
        {item.departamento?.nombre ?? '—'}
        {#if item.departamento?.fecha_eliminacion}
          <span
            class="text-[10px] font-semibold uppercase tracking-wide ml-1 px-1.5 py-0.5 rounded bg-gray-200 text-gray-600"
          >
            Eliminado
          </span>
        {/if}
      </div>
      {#if item.departamento?.facultad?.nombre}
        <div class="text-[11px] text-gray-400 mt-0.5 leading-snug">
          {item.departamento.facultad.nombre}
        </div>
      {/if}
    {:else if column.key === 'planes_activos_count'}
      <span
        class="inline-flex items-center justify-center min-w-[28px] px-2 py-1 rounded-full text-[11px] font-bold {item.planes_activos_count &&
        item.planes_activos_count > 0
          ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
          : 'bg-gray-100 text-gray-500'}"
      >
        {item.planes_activos_count ?? 0}
      </span>
    {:else if column.key === 'has_director'}
      {#if item.has_director}
        <span class="badge badge-ok">Asignado</span>
      {:else}
        <span class="badge badge-off">Sin asignar</span>
      {/if}
    {:else if column.key === 'estado'}
      {#if item.fecha_eliminacion}
        <span class="badge badge-off">Discontinuada</span>
      {:else}
        <span class="badge badge-ok">Activa</span>
      {/if}
    {/if}
  {/snippet}
</DataTable>
