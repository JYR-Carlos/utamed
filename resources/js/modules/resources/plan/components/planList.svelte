<script lang="ts">
  /**
   * Componente lista de planes curriculares.
   *
   * Muestra tabla paginada de planes con opciones para editar, eliminar, ver/editar malla.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { Plan, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    data: PaginatedResponse<Plan>;
    onEdit: (plan: Plan) => void;
    onDelete: (plan: Plan) => void;
    onViewMalla: (plan: Plan) => void;
    onEditMalla: (plan: Plan) => void;
  }

  let { data, onEdit, onDelete, onViewMalla, onEditMalla }: Props = $props();

  const columns = [
    { key: 'id_plan', label: 'ID' },
    { key: 'carrera.nombre', label: 'Carrera' },
    { key: 'agno_plan', label: 'Año' },
    { key: 'version_plan', label: 'Versión' },
    { key: 'creditos_sct_totales', label: 'Créditos SCT' },
    { key: 'malla', label: 'Malla' },
  ];

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
    <div class="flex gap-2">
      <button
        onclick={() => onViewMalla(item)}
        class="px-2.5 py-1 border border-indigo-300 hover:border-indigo-400 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded text-[0.73rem] font-medium cursor-pointer transition-all"
      >
        Ver Malla
      </button>
      <button
        onclick={() => onEditMalla(item)}
        class="px-2.5 py-1 border border-green-300 hover:border-green-400 bg-green-50 hover:bg-green-100 text-green-700 rounded text-[0.73rem] font-medium cursor-pointer transition-all"
      >
        Editar Malla
      </button>
    </div>
  {:else}
    {getRawValue(item, column.key)}
  {/if}
{/snippet}

<DataTable {data} {columns} {onEdit} {onDelete} {cellSnippet} />
