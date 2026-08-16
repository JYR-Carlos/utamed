<script lang="ts">
  /**
   * planList — Tabla paginada de planes curriculares.
   *
   * Presentacional: delega editar/eliminar y ver/editar malla en el padre.
   * "Ver Malla" abre el MallaSlideOver (solo lectura); "Editar Malla" navega
   * al editor de malla del plan.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { Plan, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    data: PaginatedResponse<Plan>;
    onEdit: (plan: Plan) => void;
    onDelete: (plan: Plan) => void;
    /** Abre el slide-over de solo lectura de la malla. */
    onViewMalla: (plan: Plan) => void;
    /** Navega al editor de malla del plan. */
    onEditMalla: (plan: Plan) => void;
  }

  let { data, onEdit, onDelete, onViewMalla, onEditMalla }: Props = $props();

  const columns = [
    // Sin columna ID: es el identificador interno, no un dato del plan.
    { key: 'carrera.nombre', label: 'Carrera' },
    { key: 'agno_plan', label: 'Año' },
    { key: 'version_plan', label: 'Versión' },
    { key: 'creditos_sct_totales', label: 'Créditos SCT' },
    { key: 'malla', label: 'Malla' },
  ];

  /**
   * Resuelve claves anidadas ('carrera.nombre'). agno_plan llega como fecha
   * completa desde el backend; la tabla solo muestra el año.
   */
  function getRawValue(item: Plan, key: string) {
    const value = key.split('.').reduce((v: any, k: string) => v?.[k], item);
    if (key === 'agno_plan' && value) {
      return new Date(value).getFullYear();
    }
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

<DataTable {data} {columns} {onEdit} {onDelete} {cellSnippet} />
