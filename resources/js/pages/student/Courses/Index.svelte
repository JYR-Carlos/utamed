<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import { router } from '@inertiajs/svelte';
  import CursoListAlumno from '@/modules/resources/curso/components/cursoListAlumno.svelte';

  interface Props {
    cursos: Curso[];
    semestre?: number;
    agno?: number;
  }

  let { cursos, semestre = 1, agno = 2026 }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
  ];

  function handleSyllabusClick(curso: Curso) {
    router.visit(`/estudiante/cursos/${curso.id_curso}/programa`);
  }

  function handleCourseClick(curso: Curso) {
    router.visit(`/estudiante/cursos/${curso.id_curso}`);
  }

  function getTituloSemestre(index: string): string {
    if (!index) {
      return "Mostrando Cursos"
    }
    return index === "1" ? "1er Semestre" : "2do Semestre"
  }
</script>

<StudentLayout {breadcrumbs}>
  <CursoListAlumno
    cursosData={cursos}
    tituloSemestre={getTituloSemestre(semestre+"")}
    showSyllabusButton={true}
    onCourseClick={handleCourseClick}
    onSyllabusClick={handleSyllabusClick}
  />
</StudentLayout>
