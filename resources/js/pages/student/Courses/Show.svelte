<script lang="ts">
  /**
   * Ficha del curso para el alumno.
   *
   * Orden de lectura: primero qué curso es (encabezado), después de qué trata y
   * cómo se evalúa, y al final las actividades. Antes la página abría
   * directamente con la lista de entregas y el programa quedaba escondido tras
   * un acordeón cerrado, lo que dejaba al alumno sin contexto.
   *
   * El dato urgente —la próxima entrega— y un atajo a la lista viajan en el
   * encabezado, para que ordenar el contenido no le cueste al alumno el plazo
   * que necesita ver.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import type { DatosSyllabusAlumno, DocenteAlumno } from '@/types/syllabus.types';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import CursoEncabezado from './components/CursoEncabezado.svelte';
  import CursoInformacion from './components/CursoInformacion.svelte';
  import { parseFechaSoloDia } from '@/utils/formatters';

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
    docentes?: DocenteAlumno[];
    datos?: DatosSyllabusAlumno | null;
  }

  let { curso, actividades = [], programa = null, docentes = [], datos = null }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.asignatura_nombre ?? curso?.nombre ?? 'Curso', href: '' },
  ]);

  // ─── Próxima entrega ────────────────────────────────────────────────────────
  // La más cercana entre las que aún no vencen; las vencidas ya no son "próxima".
  const proximaEntrega = $derived.by(() => {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    return (
      actividades
        .filter((a) => a.con_entrega && parseFechaSoloDia(a.fecha_limite) >= hoy)
        .sort(
          (a, b) =>
            parseFechaSoloDia(a.fecha_limite).getTime() -
            parseFechaSoloDia(b.fecha_limite).getTime(),
        )[0] ?? null
    );
  });

  // ─── Filtros de actividades ─────────────────────────────────────────────────
  let filterSumativa = $state(false);
  let filterEntrega = $state(false);
  let filterGrupal = $state(false);

  const hayFiltros = $derived(filterSumativa || filterEntrega || filterGrupal);

  const actividadesFiltradas = $derived(
    actividades.filter(
      (actividad) =>
        (!filterSumativa || actividad.es_sumativa) &&
        (!filterEntrega || actividad.con_entrega) &&
        (!filterGrupal || actividad.es_grupal),
    ),
  );

  function clearFilters() {
    filterSumativa = false;
    filterEntrega = false;
    filterGrupal = false;
  }

  function toggleFilter(type: string) {
    if (type === 'sumativa') filterSumativa = !filterSumativa;
    else if (type === 'entrega') filterEntrega = !filterEntrega;
    else if (type === 'grupal') filterGrupal = !filterGrupal;
  }

  function irAActividades() {
    const destino = document.getElementById('actividades');
    if (!destino) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    destino.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }
</script>

<StudentLayout {breadcrumbs}>
  <div class="h-full px-5 md:px-10 lg:px-20 bg-white relative">
    <div class="relative mx-auto px-4">
      <CursoEncabezado
        {curso}
        {proximaEntrega}
        tienePrograma={!!programa}
        totalActividades={actividades.length}
        onIrAActividades={irAActividades}
      />

      <section class="mb-10">
        <div class="flex flex-col gap-1 mb-6">
          <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">Sobre el curso</h2>
          <p class="text-sm text-gray-600">
            Contenidos, equipo docente y ponderaciones de la asignatura.
          </p>
        </div>
        <CursoInformacion {curso} {programa} {docentes} {datos} />
      </section>

      <section id="actividades" class="pb-10 scroll-mt-6">
        <div class="flex flex-col gap-1 mb-6">
          <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">Actividades</h2>
          <p class="text-sm text-gray-600">
            {#if hayFiltros}
              {actividadesFiltradas.length} de {actividades.length} actividades
            {:else}
              {actividades.length}
              {actividades.length === 1 ? 'actividad publicada' : 'actividades publicadas'}
            {/if}
          </p>
        </div>
        <ActividadesView
          {id_curso}
          {filterSumativa}
          {filterEntrega}
          {filterGrupal}
          {hayFiltros}
          filtered={actividadesFiltradas}
          onToggleFilter={toggleFilter}
          onClearFilters={clearFilters}
        />
      </section>
    </div>
  </div>
</StudentLayout>
