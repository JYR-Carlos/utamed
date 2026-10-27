<script lang="ts">
  /**
   * departamentoList — Tabla expandible de departamentos con sus carreras.
   * Utiliza DataTable estandarizado con variant="accent" y paginate={false}.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import { router, page } from '@inertiajs/svelte';
  import type { Departamento, Facultad, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    departamentos: PaginatedResponse<Departamento>;
    facultades?: Facultad[];
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
    facultades = [],
    canEdit = false,
    canDelete = false,
    onEdit = () => {},
    onDelete = () => {},
  }: Props = $props();

  let facultadFiltro = $derived(
    new URL($page.url, window.location.origin).searchParams.get('id_facultad') ?? '',
  );

  function handleFacultadChange(e: Event) {
    const val = (e.target as HTMLSelectElement).value;
    const url = new URL($page.url, window.location.origin);
    if (val) {
      url.searchParams.set('id_facultad', val);
    } else {
      url.searchParams.delete('id_facultad');
    }
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
      only: ['departamentos'],
    });
  }

  const columns = [
    { key: 'nombre', label: 'Departamento' },
    { key: 'facultad.nombre', label: 'Facultad' },
    { key: 'carreras_count', label: 'N° Carreras' },
    { key: 'estado', label: 'Estado' },
  ];
</script>

<DataTable
  data={departamentos}
  {columns}
  variant="accent"
  paginate={false}
  only={['departamentos']}
  searchPlaceholder="Buscar departamento..."
  rowClass={(d) => (d.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : '')}
  isExpandable={(d) => !d.fecha_eliminacion && (d.carreras_count ?? 0) > 0}
  hasRowActions={(d) => !d.fecha_eliminacion}
  isDeleteDisabled={(d) =>
    (d.carreras_count ?? 0) > 0
      ? 'Traslada o discontinúa antes las carreras de este departamento.'
      : false}
  onEdit={canEdit ? (d) => onEdit(d) : undefined}
  onDelete={canDelete ? (d) => onDelete(d) : undefined}
  deleteActionLabel="Eliminar"
>
  {#snippet filterSnippet()}
    {#if facultades.length > 0}
      <select
        value={facultadFiltro}
        onchange={handleFacultadChange}
        class="px-2.5 py-1.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400"
      >
        <option value="">Todas las facultades</option>
        {#each facultades as fac (fac.id_facultad)}
          <option value={String(fac.id_facultad)}>{fac.nombre}</option>
        {/each}
      </select>
    {/if}
  {/snippet}
  {#snippet cellSnippet({ item, column })}
    {#if column.key === 'nombre'}
      <span
        class={`font-medium ${item.fecha_eliminacion ? 'line-through text-gray-400' : 'text-gray-900'}`}
      >
        {item.nombre}
      </span>
    {:else if column.key === 'facultad.nombre'}
      {#if item.facultad}
        <span
          class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-medium border border-blue-100"
        >
          {item.facultad.nombre}
        </span>
      {:else}
        <span class="text-gray-400">—</span>
      {/if}
    {:else if column.key === 'carreras_count'}
      {@const carrerasCount = item.carreras_count ?? 0}
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
    {:else if column.key === 'estado'}
      {#if item.fecha_eliminacion}
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
    {/if}
  {/snippet}

  {#snippet expandableSnippet({ item })}
    {#if item.carreras && item.carreras.length > 0}
      <div class="border-l-4 border-indigo-300 ml-4 mr-2 my-1 rounded-md overflow-hidden">
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
          <tbody class="divide-y divide-indigo-100 bg-white">
            {#each item.carreras as carrera (carrera.id_carrera)}
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
    {/if}
  {/snippet}
</DataTable>
