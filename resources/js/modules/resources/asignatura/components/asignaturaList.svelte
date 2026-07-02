<script lang="ts">
  /**
   * asignaturaList — Tabla del catálogo de asignaturas.
   *
   * Componente presentacional: los datos, la paginación y los modales los
   * maneja la página padre a través de callbacks. La columna "Uso en Planes"
   * viene calculada por el backend (withCount('asignacionPlanes as
   * planes_count')) e indica en cuántos planes de estudio está la asignatura.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { Asignatura, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    /** Página actual del catálogo (paginada por el backend). */
    asignaturas: PaginatedResponse<Asignatura>;
    /** Abre el modal de creación. */
    onCreateNew?: () => void;
    /** Abre el modal de edición (crea nueva versión al guardar). */
    onEdit?: (asignatura: Asignatura) => void;
    /** Abre la confirmación de borrado. */
    onDelete?: (asignatura: Asignatura) => void;
  }

  let {
    asignaturas,
    onCreateNew = () => {},
    onEdit = () => {},
    onDelete = () => {},
  }: Props = $props();

  const columns = [
    { key: 'cod_asignatura', label: 'Código' },
    { key: 'nombre', label: 'Nombre' },
    { key: 'creditos_sct', label: 'Créditos SCT' },
    { key: 'planes_count', label: 'Uso en Planes' },
  ];
</script>

<div>
  <div class="flex justify-between items-start mb-8">
    <div>
      <h1 class="text-3xl font-bold text-gray-900 mb-1">Asignaturas</h1>
      <p class="text-sm text-gray-500">Gestión del catálogo de asignaturas</p>
      <p class="text-xs text-blue-600 mt-2 font-medium">
        💡 Al editar una asignatura se crea una nueva versión. Esto preserva el historial de
        cambios.
      </p>
    </div>
    <button
      onclick={onCreateNew}
      class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      Nueva Asignatura
    </button>
  </div>

  <DataTable data={asignaturas} {columns} {onEdit} {onDelete}>
    {#snippet cellSnippet({ item, column })}
      {#if column.key === 'planes_count'}
        {@const count = item.planes_count ?? 0}
        <span
          class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full
          {count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'}"
        >
          {count > 0 ? `Utilizada en ${count} plan${count === 1 ? '' : 'es'}` : 'Sin asignar'}
        </span>
      {:else}
        {item[column.key] ?? '-'}
      {/if}
    {/snippet}
  </DataTable>
</div>
