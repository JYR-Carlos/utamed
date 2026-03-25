<script lang="ts">
  /**
   * FASE 3: Página de cursos para docentes (refactorizado con guardias de permisos).
   *
   * Funcionalidades:
   * - Ver información de cursos asignados (asignatura, carrera, estudiantes, progreso)
   * - Gestionar equipos de cátedra (con permiso CURSOS_DOCENTE_EQUIPO)
   * - Acceso a programa/syllabus (con permiso CURSOS_PROGRAMA)
   * - Entrar a detalle del curso
   * - Dos modos de vista: grilla y lista
   *
   * Refactorizado: Usa componente CursoListRoleAware para reutilizar lógica de vista
   */
  import { router, Link, usePage } from '@inertiajs/svelte';
  import { toast } from 'svelte-sonner';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import CursoListRoleAware from '@/modules/resources/curso/components/cursoListRoleAware.svelte';
  import { usePermissions } from '@/lib/composables/usePermissions';
  import {
    BookOpen,
    BookOpenCheck,
    Loader2,
    CheckCircle2,
    FilePlus,
    Info,
    Users,
  } from 'lucide-svelte';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    cursosSemestre1: any[];
    cursosSemestre2: any[];
    availableRoles: any[];
    availablePermissions: Record<string, any[]>;
  }

  let {
    cursosSemestre1 = [],
    cursosSemestre2 = [],
    availableRoles = [],
    availablePermissions = {},
  }: Props = $props();

  // Unificar cursos (para la lista completa)
  let cursosAll = $derived([...cursosSemestre1, ...cursosSemestre2]);

  // Permisos requeridos
  const { can } = usePermissions();
  const canManageTeam = can('CURSOS_DOCENTE_EQUIPO');
  const canManageSyllabus = can('CURSOS_PROGRAMA');

  // Toggle persistente (localStorage)
  import { onMount } from 'svelte';
  let viewMode = $state<'grid' | 'list'>('grid');

  onMount(() => {
    const saved = localStorage.getItem('docente-cursos-view');
    if (saved === 'list' || saved === 'grid') {
      viewMode = saved;
    }
  });

  let isTeamModalOpen = $state(false);
  let isSyllabusModalOpen = $state(false);
  let selectedCurso = $state<any>(null);

  function handleViewModeChange(mode: 'grid' | 'list') {
    viewMode = mode;
    localStorage.setItem('docente-cursos-view', mode);
  }

  function openTeamModal(curso: any) {
    if (!canManageTeam) {
      toast.error('No tienes permisos para gestionar equipos de cátedra');
      return;
    }
    selectedCurso = curso;
    isTeamModalOpen = true;
  }

  function openSyllabusModal(curso: any) {
    if (!canManageSyllabus) {
      toast.error('No tienes permisos para gestionar el programa de la asignatura');
      return;
    }
    selectedCurso = curso;
    isSyllabusModalOpen = true;
  }

  function closeSyllabusModal() {
    isSyllabusModalOpen = false;
    selectedCurso = null;
  }

  function handleSyllabusSuccess() {
    closeSyllabusModal();
    toast.success('Programa generado correctamente');
    router.reload({ only: ['cursosSemestre1', 'cursosSemestre2'] });
  }

  function handleCourseClick(curso: any) {
    router.visit(`/docente/cursos/${curso.id_curso}`);
  }
</script>

<DocenteLayout>
  <CursoListRoleAware
    cursosData={cursosAll}
    bind:viewMode
    showTeamAction={canManageTeam}
    showSyllabusAction={canManageSyllabus}
    showCalificationProgress={true}
    groupBySemestre={true}
    mode="docente"
    onViewModeChange={handleViewModeChange}
    onTeam={openTeamModal}
    onSyllabus={openSyllabusModal}
    onCourseClick={handleCourseClick}
  />

  <!-- Modal de Equipo -->
  {#if selectedCurso}
    <CourseTeamModal
      bind:isOpen={isTeamModalOpen}
      onClose={() => {
        isTeamModalOpen = false;
        selectedCurso = null;
      }}
      curso={selectedCurso}
      urlPrefix="docente"
    />
  {/if}

  <!-- Modal de Programa -->
  {#if isSyllabusModalOpen}
    <SyllabusModal
      bind:isOpen={isSyllabusModalOpen}
      curso={selectedCurso}
      onClose={closeSyllabusModal}
      onSuccess={handleSyllabusSuccess}
    />
  {/if}
</DocenteLayout>
