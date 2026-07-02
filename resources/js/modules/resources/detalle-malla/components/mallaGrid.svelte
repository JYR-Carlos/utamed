<script lang="ts">
  /**
   * mallaGrid — Malla curricular de un plan, agrupada por año y semestre.
   *
   * Presentacional: reagrupa las asignaciones en cliente (mallaByYear) sin
   * depender de la agrupación del backend, y delega editar/quitar en el
   * padre vía callbacks. Ambas columnas de semestre comparten el snippet
   * columnaSemestre; solo cambia la paleta (azul/índigo).
   */
  import type { AsignacionPlan, MallaData } from '../types/mallaCurricular.types';
  import { getTipoRamoLabel } from '../types/mallaCurricular.types';

  interface Props {
    malla: MallaData;
    /** Abre el modal de edición de la asignación (año/semestre/tipo). */
    onEdit: (asignacion: AsignacionPlan) => void;
    /** Abre la confirmación para quitar la asignatura del plan. */
    onDelete: (asignacion: AsignacionPlan) => void;
  }

  let { malla, onEdit, onDelete }: Props = $props();

  /** Clases por columna; strings literales completos para el JIT de Tailwind. */
  interface EstilosSemestre {
    punto: string;
    codigo: string;
    badge: string;
    hover: string;
  }

  const estilosSemestre1: EstilosSemestre = {
    punto: 'bg-blue-500',
    codigo: 'text-blue-600',
    badge: 'bg-blue-100 text-blue-700',
    hover: 'hover:border-blue-300',
  };

  const estilosSemestre2: EstilosSemestre = {
    punto: 'bg-indigo-500',
    codigo: 'text-indigo-600',
    badge: 'bg-indigo-100 text-indigo-700',
    hover: 'hover:border-indigo-300',
  };

  /** Reagrupa las asignaciones por año y, dentro de cada año, por semestre. */
  const mallaByYear = $derived.by(() => {
    const years: Record<number, { semestre1: AsignacionPlan[]; semestre2: AsignacionPlan[] }> = {};
    Object.values(malla).forEach((list) => {
      list.forEach((asig) => {
        if (!years[asig.agno_planificado]) {
          years[asig.agno_planificado] = { semestre1: [], semestre2: [] };
        }
        if (asig.semestre_planificado === 1) {
          years[asig.agno_planificado].semestre1.push(asig);
        } else {
          years[asig.agno_planificado].semestre2.push(asig);
        }
      });
    });
    return years;
  });

  const sortedYears = $derived(
    Object.entries(mallaByYear).sort(([a], [b]) => Number(a) - Number(b)),
  );
</script>

{#snippet columnaSemestre(titulo: string, asignaciones: AsignacionPlan[], estilos: EstilosSemestre)}
  <div class="p-3">
    <p
      class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5"
    >
      <span class="w-2 h-2 rounded-full {estilos.punto} inline-block"></span>
      {titulo}
    </p>
    <div class="flex flex-col gap-2">
      {#if asignaciones.length === 0}
        <p class="text-xs text-gray-300 italic py-2 text-center">Sin asignaturas</p>
      {:else}
        {#each asignaciones as asignacion}
          <div
            class="bg-white border border-gray-200 rounded-md p-2.5 {estilos.hover} transition-colors"
          >
            <div class="flex items-start justify-between gap-2 mb-1">
              <span class="font-mono text-xs font-bold {estilos.codigo}"
                >{asignacion.asignatura?.cod_asignatura}</span
              >
              <span
                class="text-[10px] {estilos.badge} px-1.5 py-0.5 rounded-full font-semibold shrink-0"
              >
                {asignacion.asignatura?.creditos_sct ?? 0} SCT
              </span>
            </div>
            <p class="text-xs text-gray-800 font-medium leading-snug mb-1.5">
              {asignacion.asignatura?.nombre}
            </p>
            {#if asignacion.tipo_ramo}
              <span
                class="inline-block text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded mb-1.5"
              >
                {getTipoRamoLabel(asignacion.tipo_ramo)}
              </span>
            {/if}
            <div class="flex gap-1.5">
              <button
                onclick={() => onEdit(asignacion)}
                class="px-2 py-1 bg-gray-100 hover:bg-blue-100 text-gray-600 hover:text-blue-700 border-0 rounded text-[11px] font-medium cursor-pointer transition-colors"
                >Editar</button
              >
              <button
                onclick={() => onDelete(asignacion)}
                class="px-2 py-1 bg-gray-100 hover:bg-red-100 text-gray-600 hover:text-red-600 border-0 rounded text-[11px] font-medium cursor-pointer transition-colors"
                >Quitar</button
              >
            </div>
          </div>
        {/each}
      {/if}
    </div>
  </div>
{/snippet}

<div class="flex flex-col overflow-hidden h-full">
  <!-- Header -->
  <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 shrink-0">
    <h2 class="text-sm font-semibold text-gray-700">Malla del Plan</h2>
    <p class="text-xs text-gray-400 mt-0.5">Asignaturas organizadas por año y semestre</p>
  </div>

  <!-- Malla content -->
  <div class="flex-1 overflow-y-auto p-5">
    {#if sortedYears.length === 0}
      <div class="flex flex-col items-center justify-center h-full text-gray-400">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="48"
          height="48"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="mb-3"
        >
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
          <polyline points="14 2 14 8 20 8" />
          <line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" />
        </svg>
        <p class="text-sm font-medium">Sin asignaturas asignadas</p>
        <p class="text-xs mt-1">Usa el catálogo de la izquierda para agregar asignaturas</p>
      </div>
    {:else}
      <div class="flex flex-col gap-5">
        {#each sortedYears as [year, semesters]}
          <div class="border border-gray-200 rounded-lg overflow-hidden">
            <!-- Year header -->
            <div class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold">
              Año {year}
              <span class="ml-2 text-xs font-normal text-gray-400">
                ({semesters.semestre1.length + semesters.semestre2.length} asignaturas)
              </span>
            </div>

            <!-- Semesters grid -->
            <div class="grid grid-cols-2 divide-x divide-gray-200">
              {@render columnaSemestre('Semestre 1', semesters.semestre1, estilosSemestre1)}
              {@render columnaSemestre('Semestre 2', semesters.semestre2, estilosSemestre2)}
            </div>
          </div>
        {/each}
      </div>
    {/if}
  </div>
</div>
