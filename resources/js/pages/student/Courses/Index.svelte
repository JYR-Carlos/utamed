<script lang="ts">
  /**
   * FASE 4: Página de cursos para estudiantes (read-only refactorizado).
   *
   * Funcionalidades:
   * - Listar cursos inscritos
   * - Ver información básica: asignatura, carrera
   * - Acceso a detalle del curso
   * - Ver programa/syllabus si está disponible (solo lectura)
   * - Agrupación automática por semestre
   *
   * Refactorizado: Usa componente CursoListAlumno (ultra-simplificado)
   */
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
</script>

<StudentLayout {breadcrumbs}>
  <CursoListAlumno
    cursosData={cursos}
    groupBySemestre={true}
    showSyllabusButton={true}
    onCourseClick={handleCourseClick}
    onSyllabusClick={handleSyllabusClick}
  />
</StudentLayout>
