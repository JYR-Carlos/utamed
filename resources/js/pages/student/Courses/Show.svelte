<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
  import CourseSidebar from '@/components/student/CourseSidebar.svelte';
  import ResourceCard from '@/components/student/ResourceCard.svelte';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import BibsIndex from './Bibs/Index.svelte';

  interface Props {
    curso?: {
      id_curso?: number;
      nombre?: string;
    };
  }

  let { curso }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');
  let activeView = $state<'actividades' | 'bibliografias'>('actividades');

  function toggleBibliografia() {
    activeView = activeView === 'actividades' ? 'bibliografias' : 'actividades';
  }

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: 'Contenido', href: `/estudiante/cursos/${id_curso}` },
  ]);

  // Estructura del curso
  const courseUnits = [
    {
      id: 'unit-1',
      title: 'Unidad 1: Fundamentos de UX',
    },
    {
      id: 'unit-2',
      title: 'Unidad 2: Research y Análisis',
    },
    {
      id: 'unit-3',
      title: 'Unidad 3: Wireframing y Prototipos',
    },
  ];

  const actividades = [
    {
      id_actividad: 2,
      nombre: 'Conociendo UTAMED',
      es_sumativa: true,
      con_entrega: true,
      es_grupal: true,
      max_integrantes: 2,
      fecha_limite: '2024-07-15T23:59:00Z',
      visible: true,
    },
    {
      id_actividad: 3,
      nombre: 'Evaluación Unidad 1',
      es_sumativa: false,
      con_entrega: true,
      es_grupal: false,
      max_integrantes: 1,
      fecha_limite: '2024-07-15T23:59:00Z',
      visible: true,
    },
    {
      id_actividad: 4,
      nombre: 'Evaluación Unidad 1 - 2',
      es_sumativa: true,
      con_entrega: false,
      es_grupal: false,
      max_integrantes: 1,
      fecha_limite: '2024-07-15T23:59:00Z',
      visible: true,
    },
  ];

  // Filter states
  let filterSumativa: boolean | null = $state(null);
  let filterEntrega: boolean | null = $state(null);
  let filterGrupal: boolean | null = $state(null);

  function toggleFilter(type: string) {
    if (type === 'sumativa') filterSumativa = filterSumativa === true ? null : true;
    if (type === 'entrega') filterEntrega = filterEntrega === true ? null : true;
    if (type === 'grupal') filterGrupal = filterGrupal === true ? null : true;
  }

  function clearFilters() {
    filterSumativa = null;
    filterEntrega = null;
    filterGrupal = null;
  }

  let filtered = $derived(
    actividades.filter((a) => {
      if (filterSumativa !== null && a.es_sumativa !== filterSumativa) return false;
      if (filterEntrega !== null && a.con_entrega !== filterEntrega) return false;
      if (filterGrupal !== null && a.es_grupal !== filterGrupal) return false;
      return true;
    }),
  );
</script>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen bg-white flex flex-col md:flex-row">
    
    <div class="w-full md:w-80 shrink-0 md:sticky  md:self-start">
      <CourseSidebar
        units={courseUnits}
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
          {filtered}
          onToggleFilter={toggleFilter}
          onClearFilters={clearFilters}
        />
      {:else if activeView === 'bibliografias'}
        <BibsIndex {id_curso} />
      {/if}
    </div>
  </div>
</StudentLayout>
