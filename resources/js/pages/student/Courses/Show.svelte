<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
  import CourseSidebar from '@/components/student/CourseSidebar.svelte';
  import ResourceCard from '@/components/student/ResourceCard.svelte';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import BibsIndex from './Bibs/Index.svelte';

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
    curso?: {
      id_curso?: number;
      nombre?: string;
    };
    actividades?: Actividad[];
  }

  let { curso, actividades = [] }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');
  let activeView = $state<'actividades' | 'bibliografias'>('actividades');

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: 'curso.nombre', href: '' },
  ]);

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

  // Mostrar actividades reales o ejemplos si no hay
  const actividadesAMostrar = $derived(actividades.length > 0 ? actividades : actividadesEjemplo);

  function clearFilters() {
    filterSumativa = false;
    filterEntrega = false;
    filterGrupal = false;
  }

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
    
    <div class="w-full md:w-80 shrink-0 md:sticky  md:self-start">
      <CourseSidebar
        units={[]}
        {activeModuleId}
        onModuleClick={(id: string) => {
          activeView="actividades";
          (activeModuleId = id)
        }}
        onBibliografiaClick={() => activeView="bibliografias"}
        courseName={curso?.nombre ?? 'Diseño de Interfaces Digitales'}
      />
    </div>
     
    <!-- Content Views -->
    <div class="w-full md:flex-1">
    
      {#if activeView === 'actividades'}
        <ActividadesView
          {activeModuleId}
          {id_curso}
          {filterSumativa}
          {filterEntrega}
          {filterGrupal}
          filtered={actividadesAMostrar}
          onToggleFilter={() => {}}
          onClearFilters={clearFilters}
        />
      {:else if activeView === 'bibliografias'}
        <BibsIndex {id_curso} />
      {/if}
    </div>
  </div>
</StudentLayout>

