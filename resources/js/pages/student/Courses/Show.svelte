<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
  import CourseSidebar from '@/components/student/CourseSidebar.svelte';
  import ResourceCard from '@/components/student/ResourceCard.svelte';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import BibsIndex from './Bibs/Index.svelte';
  import Syllabus from './Syllabus.svelte';

  interface Actividad {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    max_integrantes: number;
    fecha_limite: string;
    visible: boolean;
  }

  interface Props {
    curso?: Curso;
    actividades?: Actividad[];
  }

  let { curso, actividades = [] }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');
  let activeView = $state<'principal' | 'bibliografias'>('principal');

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: '' }, 
  ]);

  // Estados de los filtros
  let filterSumativa = $state(false);
  let filterEntrega = $state(false);
  let filterGrupal = $state(false);

  // Actividades de ejemplo
  const actividadesEjemplo: Actividad[] = [
    {
      id_actividad: 1001,
      nombre: 'Taller de diseño inicial',
      es_sumativa: true,
      con_entrega: true,
      es_grupal: false,
      max_integrantes: 1,
      fecha_limite: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      visible: true,
    },
    {
      id_actividad: 1002,
      nombre: 'Proyecto grupal - Prototipo interactivo',
      es_sumativa: true,
      con_entrega: true,
      es_grupal: true,
      max_integrantes: 4,
      fecha_limite: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      visible: true,
    },
  ];

  const actividadesBase = $derived(actividades.length > 0 ? actividades : actividadesEjemplo);


  const actividadesFiltradas = $derived(
    actividadesBase.filter((actividad) => {
      const cumpleSumativa = !filterSumativa || actividad.es_sumativa;
      const cumpleEntrega = !filterEntrega || actividad.con_entrega;
      const cumpleGrupal = !filterGrupal || actividad.es_grupal;

      return cumpleSumativa && cumpleEntrega && cumpleGrupal;
    }),
  );

  function clearFilters() {
    filterSumativa = false;
    filterEntrega = false;
    filterGrupal = false;
  }

  function toggleFilter(type: string) {
    if (type === 'sumativa') {
      filterSumativa = !filterSumativa;
    } else if (type === 'entrega') {
      filterEntrega = !filterEntrega;
    } else if (type === 'grupal') {
      filterGrupal = !filterGrupal;
    }
  }

  let showSyllabus = $state(false);
  let showActividades = $state(true);
</script>

<svelte:head>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=JetBrains+Mono:wght@400;500;700&display=swap"
    rel="stylesheet"
  />
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen bg-white flex flex-col md:flex-row">
    <div class="w-full md:w-80 shrink-0 md:sticky md:self-start">
      <CourseSidebar
        units={[]}
        {activeModuleId}
        onModuleClick={(id: string) => {
          activeView = 'principal';
          activeModuleId = id;
        }}
        onBibliografiaClick={() => (activeView = 'bibliografias')}
        courseName={curso?.nombre ?? 'Diseño de Interfaces Digitales'}
      />
    </div>

    <!-- Content Views -->
    <div class="w-full md:flex-1">
      {#if activeView === 'principal'}
        <div class="space-y-4 px-2">
          <button
            class="px-4 py-2 rounded bg-primary text-secondary hover:bg-secondary hover:text-primary font-semibold focus:outline-none"
            onclick={() => (showSyllabus = !showSyllabus)}
            aria-expanded={showSyllabus}
            aria-controls="syllabus-section"
          >
            {showSyllabus ? '▼' : '►'} Acerca de este curso
          </button>
          {#if showSyllabus}
            <div id="syllabus-section" class="pl-4 border-gray-200 mb-2">
              <Syllabus {curso} />
            </div>
          {/if}

          
          {#if showActividades}
            <div id="actividades-section" class="pl-4 border-gray-200">
              <ActividadesView
                {activeModuleId}
                {id_curso}
                {filterSumativa}
                {filterEntrega}
                {filterGrupal}
                filtered={actividadesFiltradas}
                onToggleFilter={toggleFilter}
                onClearFilters={clearFilters}
              />
            </div>
          {/if}
        </div>
      {:else if activeView === 'bibliografias'}
        <BibsIndex {id_curso} />
      {/if}
    </div>
  </div></StudentLayout
>
