<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { router } from '@inertiajs/svelte';
  import CursoListAlumno from '@/modules/resources/curso/components/cursoListAlumno.svelte';

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    cod_asignatura: string;
    asignatura_nombre: string;
    carrera_nombre: string;
    imagen_url: string;
    fecha_inicio: string;
    fecha_fin?: string;
    semestre_real?: number;
    agno_real?: number;
    letra_grupo?: string;
    total_estudiantes?: number;
  }

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
    onCourseClick={() => console.log(semestre, agno)}
    onSyllabusClick={handleSyllabusClick}
  />
</StudentLayout>
