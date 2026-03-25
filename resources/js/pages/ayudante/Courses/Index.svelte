<script lang="ts">
  /**
   * Página de cursos para ayudantes (refactorizado).
   *
   * Funcionalidades:
   * - Listar cursos donde eres ayudante
   * - Ver información del curso
   * - Acceso a detalle del curso
   * - Ver/crear programa si tienes permisos
   * - Dos vistas: lista y grilla
   *
   * Refactorizado: Usa componente CursoListRoleAware para reutilizar lógica de vista
   */
  import { router } from '@inertiajs/svelte';
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import CursoListRoleAware from '@/modules/resources/curso/components/cursoListRoleAware.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { usePermissions } from '@/lib/composables/usePermissions';

  interface Props {
    cursos: Array<{
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
      total_estudiantes?: number;
    }>;
  }

  let { cursos }: Props = $props();

  const { can } = usePermissions();
  const canManageSyllabus = can('CURSOS_PROGRAMA');

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/ayudante/dashboard' },
    { title: 'Mis Cursos (Ayudante)', href: '/ayudante/cursos' },
  ];

  let viewMode = $state<'grid' | 'list'>('list');

  function handleViewModeChange(mode: 'grid' | 'list') {
    viewMode = mode;
    localStorage.setItem('ayudante-cursos-view', mode);
  }

  function handleSyllabusClick(curso: any) {
    router.visit(`/ayudante/cursos/${curso.id_curso}/programa`);
  }

  function handleCourseClick(curso: any) {
    router.visit(`/ayudante/cursos/${curso.id_curso}`);
  }
</script>

<AyudanteLayout {breadcrumbs}>
  <CursoListRoleAware
    cursosData={cursos}
    bind:viewMode
    showTeamAction={false}
    showSyllabusAction={canManageSyllabus}
    showCalificationProgress={false}
    groupBySemestre={false}
    mode="ayudante"
    onViewModeChange={handleViewModeChange}
    onSyllabus={handleSyllabusClick}
    onCourseClick={handleCourseClick}
  />
</AyudanteLayout>
