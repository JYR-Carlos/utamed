<script lang="ts">
  import type { CursoResumen } from '../types/curso.types';
  /**
   * cursoListAlumno — Lista de tarjetas de cursos del estudiante agrupadas
   * por periodo (actual y anteriores). Presentacional: los clics se delegan
   * al padre vía callbacks.
   */
  import CursoCard from './cursoCard.svelte';

  interface Props {
    cursosData?: CursoResumen[];
    /** Encabezado del grupo (p.ej. "Semestre Otoño 2026"). */
    tituloSemestre?: string;
    showSyllabusButton?: boolean;
    semestreActual: number | string;
    agnoActual: number | string;
    onCourseClick?: (curso: any) => void;
    onSyllabusClick?: (curso: any) => void;
  }

  let {
    cursosData = [],
    tituloSemestre = "",
    showSyllabusButton = true,
    semestreActual,
    agnoActual,
    onCourseClick = () => {},
    onSyllabusClick = () => {},
  }: Props = $props();

  const esActual = (c: CursoResumen) =>
    (c.semestre) == semestreActual &&
    (c.agno) == agnoActual;

  let cursosActuales = $derived(cursosData.filter(esActual));
  let cursosAnteriores = $derived(cursosData.filter((c) => !esActual(c)));
</script>

<div class="space-y-8 px-4 sm:px-8">
  {#if cursosData.length === 0}
    <p class="mx-auto text-center my-12 text-lg font-medium text-slate-500">
      No tienes cursos registrados.
    </p>
  {:else}
    <!-- Cursos Semestre Actual (Preeminente) -->
    <section class="space-y-4">
      <div class="flex items-center justify-between gap-3">
        <span class="text-lg font-semibold text-black">
          Periodo actual
        </span>
        <h2 class="text-lg font-semibold text-slate-600">
          {tituloSemestre || 'Semestre Actual'}
        </h2>
        
      </div>

      {#if cursosActuales.length > 0}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {#each cursosActuales as curso (curso.id_curso ?? curso.cod_curso)}
            <CursoCard
              {curso}
              {showSyllabusButton}
              {onCourseClick}
              {onSyllabusClick}
              isPeriodoActual={true}
            />
          {/each}
        </div>
      {:else}
        <p class="text-sm text-slate-500 italic py-2">
          No tienes cursos inscritos en este periodo.
        </p>
      {/if}
    </section>

    <!-- Cursos Periodos Anteriores -->
    {#if cursosAnteriores.length > 0}
      <section class="space-y-4 pt-6 border-t border-slate-200">
        <h3 class="text-lg font-semibold text-slate-600">
          Periodos Anteriores
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 opacity-80 hover:opacity-100 transition-opacity">
          {#each cursosAnteriores as curso (curso.id_curso ?? curso.cod_curso)}
            <CursoCard
              {curso}
              {showSyllabusButton}
              {onCourseClick}
              {onSyllabusClick}
              isPeriodoActual={false}
            />
          {/each}
        </div>
      </section>
    {/if}
  {/if}
</div>