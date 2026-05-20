<script lang="ts">
  import CursoCard from './cursoCard.svelte'

  interface Props {
    cursosData?: any[];
    groupBySemestre?: boolean;
    showSyllabusButton?: boolean;
    onCourseClick?: (curso: any) => void;
    onSyllabusClick?: (curso: any) => void;
  }

  let {
    cursosData = [],
    groupBySemestre = false,
    showSyllabusButton = true,
    onCourseClick = () => {},
    onSyllabusClick = () => {},
  }: Props = $props();

  const cursosAgrupados = $derived(
    groupBySemestre
      ? {
          'Primer Semestre': cursosData.filter(
            (c) => (c.semestre_real ?? 1) === 1
          ),
          'Segundo Semestre': cursosData.filter(
            (c) => (c.semestre_real ?? 1) === 2
          ),
        }
      : null
  );
</script>

<div class="space-y-6 px-20">

  {#if cursosAgrupados}

    {#each Object.entries(cursosAgrupados) as [titulo, cursos]}
      {#if cursos.length > 0}

        <section class="space-y-4">
          <p class="text-xl font-semibold">
            {titulo}
          </p>

          <div class="space-y-4">
            {#each cursos as curso}
              <CursoCard
                {curso}
                showSyllabusButton={false}
                {onCourseClick}
                {onSyllabusClick}
              />
            {/each}
          </div>
        </section>

      {/if}
    {/each}

  {:else}

    <div class="space-y-4">
      {#each cursosData as curso}
        <CursoCard
          {curso}
          {showSyllabusButton}
          {onCourseClick}
          {onSyllabusClick}
        />
      {/each}
    </div>

  {/if}
</div>