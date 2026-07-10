<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import ActividadesView from '../Activities/ActividadesView.svelte';
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

  interface Programa {
    id_programa: number;
    version_programa: string;
    estado: string;
    creado_por?: string;
    fecha_creacion?: string;
    tipo_syllabus?: string;
  }

  interface Props {
    curso?: Curso;
    actividades?: Actividad[];
    programa?: Programa | null;
    docente?: { nombre?: string; email?: string } | null;
    datos?: Record<string, unknown> | null;
  }

  let { curso, actividades = [], programa = null, docente = null, datos = null }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  let activeModuleId = $state('module-1-2');

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: '' },
  ]);

  // Estados de los filtros
  let filterSumativa = $state(false);
  let filterEntrega = $state(false);
  let filterGrupal = $state(false);

  const actividadesBase = $derived(actividades);

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
</script>



<StudentLayout {breadcrumbs}>
  <div class="flex flex-col gap-4 px-2 py-4">
    <!-- Heritage stripe -->

    <!-- Content Views -->
    <div class="w-full md:flex-1">
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
              <Syllabus {curso} {programa} {docente} {datos} />
            </div>
          {/if}

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
        </div>
    </div>
  </div>
</StudentLayout>
