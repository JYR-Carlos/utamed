<script lang="ts">
  /**
   * Lista de actividades del curso para el alumno.
   *
   * Va después de la ficha del curso en student/Courses/Show.svelte, que es
   * quien pone el encabezado de la sección y el conteo; aquí viven los filtros
   * y las tarjetas.
   */
  import { Link } from '@inertiajs/svelte';
  import { ClipboardList, Filter } from 'lucide-svelte';
  import ActividadCard from '@/modules/resources/actividad/components/actividadCard.svelte';

  interface Actividad {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    max_integrantes: number;
    fecha_limite: string;
    visible: boolean;
  }

  interface Props {
    id_curso: number;
    filterSumativa: boolean;
    filterEntrega: boolean;
    filterGrupal: boolean;
    /** True si hay al menos un filtro activo; cambia el estado vacío. */
    hayFiltros: boolean;
    filtered: Actividad[];
    onToggleFilter: (type: string) => void;
    onClearFilters: () => void;
  }

  let {
    id_curso,
    filterSumativa,
    filterEntrega,
    filterGrupal,
    hayFiltros,
    filtered,
    onToggleFilter,
    onClearFilters,
  }: Props = $props();

  const filtros = $derived([
    { tipo: 'sumativa', label: 'Sumativas', activo: filterSumativa },
    { tipo: 'entrega', label: 'Con entrega', activo: filterEntrega },
    { tipo: 'grupal', label: 'Grupales', activo: filterGrupal },
  ]);
</script>

<div class="flex flex-col gap-5">
  <!-- Filtros -->
  <div class="flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 font-medium mr-1">
      <Filter class="w-3.5 h-3.5" />
      Filtrar
    </span>
    {#each filtros as filtro (filtro.tipo)}
      <button
        onclick={() => onToggleFilter(filtro.tipo)}
        aria-pressed={filtro.activo}
        class="px-4 py-2 rounded-xl text-sm font-medium transition-all {filtro.activo
          ? 'bg-uta-blue text-white'
          : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}"
      >
        {filtro.label}
      </button>
    {/each}
    {#if hayFiltros}
      <button
        onclick={onClearFilters}
        class="ml-auto text-sm font-medium text-gray-500 hover:text-uta-blue transition-colors"
      >
        Quitar filtros
      </button>
    {/if}
  </div>

  <!-- Listado -->
  {#if filtered.length === 0}
    <div class="rounded-3xl border border-gray-200 bg-gray-50 p-12 text-center">
      <div class="mb-4 flex justify-center">
        <div class="rounded-full bg-gray-100 p-4">
          <ClipboardList class="w-8 h-8 text-gray-400" />
        </div>
      </div>
      {#if hayFiltros}
        <h3 class="mb-2 text-lg font-bold text-gray-900">Ninguna actividad coincide</h3>
        <p class="text-gray-600 mb-4">Prueba con otra combinación de filtros.</p>
        <button
          onclick={onClearFilters}
          class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-uta-blue text-white hover:bg-uta-blue-hover transition-colors"
        >
          Quitar filtros
        </button>
      {:else}
        <h3 class="mb-2 text-lg font-bold text-gray-900">Todavía no hay actividades</h3>
        <p class="text-gray-600">Tu docente aún no publica actividades para este curso.</p>
      {/if}
    </div>
  {:else}
    <ul class="flex flex-col gap-4">
      {#each filtered as act (act.id_actividad)}
        <li>
          <Link
            href={`/estudiante/cursos/${id_curso}/actividad/${act.id_actividad}`}
            class="block no-underline rounded-3xl"
          >
            <ActividadCard actividad={act} />
          </Link>
        </li>
      {/each}
    </ul>
  {/if}
</div>
