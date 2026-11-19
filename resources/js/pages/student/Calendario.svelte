<script lang="ts">
  /**
   * Calendario general del alumno (FEAT-06, OBS-23): las fechas límite de
   * todos sus cursos del semestre vigente, con el color de cada curso y un
   * filtro por curso. Es el mismo calendario de la ficha del curso
   * (CursoCalendario) en su modo general.
   *
   * Los eventos propios del alumno («Mi organización», OBS-29) no están:
   * quedaron para después.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import CursoCalendario, { type ActividadCalendario } from '@/pages/student/Courses/components/CursoCalendario.svelte';
  import { nombreSemestre } from '@/lib/cursoDocente';
  import { etiqueta, tituloCond } from '@/lib/dm';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    /** Las fechas del semestre, con el curso de cada una (calendarioDelPeriodo). */
    actividades: ActividadCalendario[];
    /** null: el alumno no tiene cursos inscritos. */
    periodo: { semestre: number; agno: number } | null;
  }

  let { actividades = [], periodo = null }: Props = $props();

  const total = $derived(actividades.filter((a) => a.visible && a.fecha_limite).length);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Calendario', href: '/estudiante/calendario' },
  ];
</script>

<svelte:head>
  <title>Calendario | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-280 flex-col gap-6 pb-10 lg:gap-8 lg:px-10 lg:pt-8 lg:pb-12">
    <section
      aria-labelledby="t-calendario"
      class="sobre-noche relative overflow-hidden bg-dm-noche lg:rounded-xl lg:border lg:border-dm-noche-borde"
    >
      <Reticula class="left-[45%]" />
      <div class="relative flex flex-col gap-2 px-4 py-6 lg:px-10 lg:py-8">
        {#if periodo}
          <span class="{etiqueta} text-dm-amarillo">{nombreSemestre(periodo.semestre, periodo.agno)}</span>
        {/if}
        <h1 id="t-calendario" class="{tituloCond} text-[40px] leading-10 text-dm-noche-texto lg:text-[56px] lg:leading-[52px]">
          Calendario
        </h1>
        <p class="text-base leading-6 text-dm-noche-texto-2">
          {total === 0
            ? 'Las fechas límite de todos tus cursos, en un solo lugar.'
            : `${total} ${total === 1 ? 'fecha límite' : 'fechas límite'} de todos tus cursos del semestre.`}
        </p>
      </div>
    </section>

    <div class="px-4 lg:px-0">
      <CursoCalendario general semestre={actividades} />
    </div>
  </div>
</StudentLayout>
