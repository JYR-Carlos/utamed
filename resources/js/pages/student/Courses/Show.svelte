<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';
  import CourseSidebar from '@/components/student/CourseSidebar.svelte';
  import ResourceCard from '@/components/student/ResourceCard.svelte';
  import ActividadCard from '@/modules/resources/actividad/components/actividadCard.svelte';

  interface Props {
    curso?: {
      id_curso?: number;
      nombre?: string;
    };
  }

  let { curso }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');

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
    <!-- Sidebar -->
    <div class="w-full md:w-80 shrink-0 md:sticky  md:self-start">
      <CourseSidebar
        units={courseUnits}
        {activeModuleId}
        onModuleClick={(id: string) => (activeModuleId = id)}
        courseName={curso?.nombre ?? 'Diseño de Interfaces Digitales'}
      />
    </div>

    <!-- Main Content -->
    <div class="w-full md:flex-1 p-2">
      <p class="text-2xl font-bold text-gray-900 my-4 mx-6">Actividades</p>
      <p class="text-xl font-semibold visible sm:hidden mb-4 mx-6">Unidad {activeModuleId}</p>
      <div class="flex flex-col gap-4 mx-6">
        <!-- Filter Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
          <div
            class="flex gap-2 items-center overflow-x-auto pb-2 sm:pb-0 w-full sm:w-auto no-scrollbar"
          >
            <button
              class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterSumativa
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-900 border-gray-300'}"
              onclick={() => toggleFilter('sumativa')}
            >
              {filterSumativa ? 'Sumativas' : 'Ver Sumativas'}
            </button>

            <button
              class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterEntrega
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-900 border-gray-300'}"
              onclick={() => toggleFilter('entrega')}
            >
              {filterEntrega ? 'Con Entrega' : 'Ver Con Entrega'}
            </button>

            <button
              class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterGrupal
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-900 border-gray-300'}"
              onclick={() => toggleFilter('grupal')}
            >
              {filterGrupal ? 'Grupal' : 'Ver Grupal'}
            </button>
          </div>

          <button
            class="w-full sm:w-auto px-4 py-2 rounded-lg border border-gray-300 sm:ml-auto hover:bg-gray-100 text-sm"
            onclick={clearFilters}
          >
            Limpiar filtros
          </button>
        </div>

        <!-- Activities List -->
        <div class="flex flex-col gap-4">
          {#each filtered as act (act.id_actividad)}
            <Link href={`/estudiante/cursos/${id_curso}/actividad/${act.id_actividad}`}>
              <ActividadCard actividad={act} idCurso={id_curso} />
            </Link>
            
          {/each}
        </div>
      </div>
    </div>
  </div>
</StudentLayout>
