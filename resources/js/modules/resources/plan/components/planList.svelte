<script lang="ts">
  /**
   * planList — Tabla paginada de planes curriculares.
   *
   * Presentacional: delega editar/eliminar y ver/editar malla en el padre.
   * "Ver Malla" abre el MallaSlideOver (solo lectura); "Editar Malla" navega
   * al editor de malla del plan.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import { router, page } from '@inertiajs/svelte';
  import type { Plan, Carrera, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    data: PaginatedResponse<Plan>;
    carreras?: Carrera[];
    onEdit: (plan: Plan) => void;
    onDelete: (plan: Plan) => void;
    /** Abre el slide-over de solo lectura de la malla. */
    onViewMalla: (plan: Plan) => void;
    /** Navega al editor de malla del plan. */
    onEditMalla: (plan: Plan) => void;
  }

  let { data, carreras = [], onEdit, onDelete, onViewMalla, onEditMalla }: Props = $props();

  let carreraFiltro = $derived(
    new URL($page.url, window.location.origin).searchParams.get('id_carrera') ?? '',
  );

  function handleCarreraChange(e: Event) {
    const val = (e.target as HTMLSelectElement).value;
    const url = new URL($page.url, window.location.origin);
    if (val) {
      url.searchParams.set('id_carrera', val);
    } else {
      url.searchParams.delete('id_carrera');
    }
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
      only: ['planes'],
    });
  }

  const columns = [
    // Sin columna ID: es el identificador interno, no un dato del plan.
    { key: 'carrera.nombre', label: 'Carrera' },
    { key: 'agno_plan', label: 'Año' },
    { key: 'version_plan', label: 'Versión' },
    { key: 'creditos_sct_totales', label: 'Créditos SCT' },
    { key: 'malla', label: 'Malla', sortable: false },
  ];

  /**
   * Resuelve claves anidadas ('carrera.nombre').
   */
  function getRawValue(item: Plan, key: string) {
    const value = key.split('.').reduce((v: any, k: string) => v?.[k], item);
    return value ?? '-';
  }
</script>

{#snippet cellSnippet({ item, column }: { item: Plan; column: { key: string; label: string } })}
  {#if column.key === 'malla'}
    <!-- Dos botones iguales: antes «Ver» era azul y «Editar» verde, sin que
         el color dijera nada sobre la diferencia entre ambos. -->
    <div class="flex gap-1.5">
      <button onclick={() => onViewMalla(item)} class="btn btn-neutral btn-sm"> Ver malla </button>
      <button onclick={() => onEditMalla(item)} class="btn btn-neutral btn-sm">
        Editar malla
      </button>
    </div>
  {:else}
    {getRawValue(item, column.key)}
  {/if}
{/snippet}

<DataTable {data} {columns} {onEdit} {onDelete} {cellSnippet} only={['planes']}>
  {#snippet filterSnippet()}
    {#if carreras.length > 0}
      <select
        value={carreraFiltro}
        onchange={handleCarreraChange}
        class="px-2.5 py-1.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400"
      >
        <option value="">Todas las carreras</option>
        {#each carreras as car (car.id_carrera)}
          <option value={String(car.id_carrera)}>{car.nombre}</option>
        {/each}
      </select>
    {/if}
  {/snippet}
</DataTable>
