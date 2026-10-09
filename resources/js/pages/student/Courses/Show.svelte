<script lang="ts">
  /**
   * Ficha del curso para el alumno.
   *
   * Bajo el encabezado van exactamente cuatro secciones, en este orden (T26,
   * reunión del 23-09): Sobre el curso → Próximas entregas → Actividades →
   * Equipo docente. Nada más.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import type { DatosSyllabusAlumno, DocenteAlumno } from '@/types/syllabus.types';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import CursoEncabezado from './components/CursoEncabezado.svelte';
  import CursoInformacion from './components/CursoInformacion.svelte';
  import CursoProximasEntregas from './components/CursoProximasEntregas.svelte';
  import CursoEquipoDocente from './components/CursoEquipoDocente.svelte';
  import CursoRendimientoPanel, { type Rendimiento } from './components/CursoRendimientoPanel.svelte';
  import { router } from '@inertiajs/svelte';
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
    estado?: string;
    ya_entregada?: boolean;
    ya_evaluada?: boolean;
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
    /** Prop diferida: llega al abrir el panel «Rendimiento». */
    rendimiento?: Rendimiento | null;
  }

  let {
    curso,
    actividades = [],
    programa = null,
    docentes = [],
    datos = null,
    rendimiento = null,
  }: Props = $props();

  // ─── Panel «Rendimiento» (T30) ──────────────────────────────────────────────
  let rendimientoAbierto = $state(false);
  let rendimientoCargando = $state(false);
  let rendimientoError = $state<string | null>(null);

  function abrirRendimiento() {
    rendimientoAbierto = true;
    rendimientoError = null;
    // Se recalcula en cada apertura: las notas pueden haber cambiado.
    rendimientoCargando = true;
    router.reload({
      only: ['rendimiento'],
      onError: () => (rendimientoError = 'No se pudo calcular tu rendimiento. Intenta de nuevo.'),
      onFinish: () => (rendimientoCargando = false),
    });
  }

  const id_curso = $derived(curso?.id_curso || 0);

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.asignatura_nombre ?? curso?.nombre ?? 'Curso', href: '' },
  ]);

  // ─── Próximas entregas ──────────────────────────────────────────────────────
  // Las que aún no vencen, de la más cercana a la más lejana.
  const proximasEntregas = $derived.by(() => {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    return actividades
      .filter(
        (a) =>
          a.con_entrega &&
          a.fecha_limite &&
          !a.ya_entregada &&
          !a.ya_evaluada,
      )
      .sort((a, b) => {
        const da = parseFechaSoloDia(a.fecha_limite).getTime();
        const db = parseFechaSoloDia(b.fecha_limite).getTime();
        const aVencida = da < hoy.getTime();
        const bVencida = db < hoy.getTime();
        if (aVencida && !bVencida) return -1;
        if (!aVencida && bVencida) return 1;
        return da - db;
      });
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
        tienePrograma={!!programa}
        totalActividades={actividades.length}
        onIrAActividades={irAActividades}
        onAbrirRendimiento={abrirRendimiento}
      />

      <section class="mb-10">
        <div class="flex flex-col gap-1 mb-6">
          <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">Sobre el curso</h2>
          <p class="text-sm text-gray-600">Contenidos y ponderaciones de la asignatura.</p>
        </div>
        <CursoInformacion {curso} {programa} {datos} />
      </section>

      <section class="mb-10">
        <div class="flex flex-col gap-1 mb-6">
          <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">Próximas entregas</h2>
        </div>
        <CursoProximasEntregas idCurso={id_curso} entregas={proximasEntregas} />
      </section>

      <section id="actividades" class="mb-10 scroll-mt-6">
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

      <section class="pb-10">
        <div class="flex flex-col gap-1 mb-6">
          <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">Equipo docente</h2>
        </div>
        <CursoEquipoDocente {docentes} />
      </section>

      <CursoRendimientoPanel
        abierto={rendimientoAbierto}
        cargando={rendimientoCargando}
        error={rendimientoError}
        {rendimiento}
        onCerrar={() => (rendimientoAbierto = false)}
      />
    </div>
  </div>
</StudentLayout>
