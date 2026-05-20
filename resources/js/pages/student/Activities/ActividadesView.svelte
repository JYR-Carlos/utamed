<script lang="ts">
  import { Link } from '@inertiajs/svelte';
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
    activeModuleId: string;
    id_curso: number;
    filterSumativa: boolean | null;
    filterEntrega: boolean | null;
    filterGrupal: boolean | null;
    filtered: Actividad[];
    onToggleFilter: (type: string) => void;
    onClearFilters: () => void;
  }

  let {
    activeModuleId,
    id_curso,
    filterSumativa,
    filterEntrega,
    filterGrupal,
    filtered,
    onToggleFilter,
    onClearFilters,
  }: Props = $props();
</script>

<!-- Main Content -->
<div class="w-full md:flex-1 p-2">
  <p class="text-2xl font-bold text-gray-900 my-4 mx-6">Actividades</p>
  <p class="text-xl font-semibold visible sm:hidden mb-4 mx-6">Unidad {activeModuleId}</p>
  <div class="flex flex-col gap-4 mx-6">
    <!-- Filter Buttons -->
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
      <div
        class="flex gap-2 items-center overflow-x-auto pb-2 sm:pb-0 w-full sm:w-auto no-scrollbar"
      >
        <button
          class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterSumativa
            ? 'bg-blue-500 text-white border-blue-500'
            : 'bg-white text-gray-900 border-gray-300'}"
          onclick={() => onToggleFilter('sumativa')}
        >
          {filterSumativa ? 'Sumativas' : 'Ver Sumativas'}
        </button>

        <button
          class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterEntrega
            ? 'bg-blue-500 text-white border-blue-500'
            : 'bg-white text-gray-900 border-gray-300'}"
          onclick={() => onToggleFilter('entrega')}
        >
          {filterEntrega ? 'Con Entrega' : 'Ver Con Entrega'}
        </button>

        <button
          class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterGrupal
            ? 'bg-blue-500 text-white border-blue-500'
            : 'bg-white text-gray-900 border-gray-300'}"
          onclick={() => onToggleFilter('grupal')}
        >
          {filterGrupal ? 'Grupal' : 'Ver Grupal'}
        </button>
      </div>

      <button
        class="w-full sm:w-auto px-4 py-2 rounded-lg border border-gray-300 sm:ml-auto hover:bg-gray-100 text-sm"
        onclick={onClearFilters}
      >
        Limpiar filtros
      </button>
    </div>

    <!-- Activities List -->
    <div class="flex flex-col gap-4">
      {#each filtered as act (act.id_actividad)}
        <Link href={`/estudiante/cursos/${id_curso}/actividad/${act.id_actividad}`}>
          <ActividadCard actividad={act} idCurso={id_curso} />
        </Link>
      {/each}
    </div>
  </div>
</div>
