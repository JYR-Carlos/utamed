<script lang="ts">
  /**
   * cursoListAlumno — Lista de tarjetas de cursos del estudiante agrupadas
   * bajo un título de semestre. Presentacional: los clics se delegan al
   * padre vía callbacks.
   */
  import CursoCard from './cursoCard.svelte';

  interface Props {
    cursosData?: any[];
    /** Encabezado del grupo (p.ej. "Semestre Otoño 2026"). */
    tituloSemestre?: string;
    showSyllabusButton?: boolean;
    onCourseClick?: (curso: any) => void;
    onSyllabusClick?: (curso: any) => void;
  }

  let {
    cursosData = [],
    tituloSemestre = "",
    showSyllabusButton = true,
    onCourseClick = () => {},
    onSyllabusClick = () => {},
  }: Props = $props();

</script>

<div class="space-y-6 px-20">

  {#if cursosData.length > 0}
    <p class="text-xl font-semibold">
      {tituloSemestre}
    </p>
    {#each cursosData as curso}
      <section class="space-y-4">
          <CursoCard
            {curso}
            {showSyllabusButton}
            {onCourseClick}
            {onSyllabusClick}
          />
        </section>
    {/each}

  {:else}

    <p class="mx-auto text-center my-auto text-xl font-bold">
      No tienes cursos en este semestre.
    </p>

  {/if}
</div>