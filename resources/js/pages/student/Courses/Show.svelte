<script lang="ts">
  /**
   * Ficha del curso para el alumno.
   *
   * Navegación por pestañas, como la UTAMED anterior a la que los alumnos
   * están habituados: Programa · Actividades · Resumen de notas · Biblioteca.
   * Antes la ficha resumía el syllabus («Sobre el curso»: descripción,
   * ponderaciones, unidades, equipo docente) y luego enlazaba al syllabus
   * completo, así que todo se veía dos veces; ahora el programa se lee una
   * sola vez, en su pestaña.
   *
   * La pestaña activa viaja en ?seccion= para que «Volver al curso» y el
   * botón Atrás del navegador caigan donde el alumno estaba. Sin parámetro se
   * abre Actividades, que es lo que el alumno viene a hacer.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import ActividadesView from '../Activities/ActividadesView.svelte';
  import CursoEncabezado, { SECCIONES_CURSO, type SeccionCurso } from './components/CursoEncabezado.svelte';
  import CursoProximasEntregas from './components/CursoProximasEntregas.svelte';
  import CursoResumenNotas, { type Rendimiento } from './components/CursoResumenNotas.svelte';
  import ProgramaAlumno, {
    type DocenteSyllabus,
    type ProgramaPublicado,
    type SeccionPrograma,
  } from './components/ProgramaAlumno.svelte';
  import ProgramaDocument from '@/modules/resources/programa/components/ProgramaDocument.svelte';
  import { textoDeSeccion } from '@/modules/resources/programa/utils/syllabusTexto';
  import { Link, page, router } from '@inertiajs/svelte';
  import { BookMarked, Printer } from 'lucide-svelte';
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
    componente_tipo?: string | null;
  }

  interface Props {
    curso?: Curso;
    actividades?: Actividad[];
    programa?: ProgramaPublicado | null;
    docentes?: DocenteSyllabus[];
    datos?: { categoria?: string } | null;
    /** Programa íntegro; alimenta las pestañas Programa y Biblioteca. */
    secciones?: SeccionPrograma[];
    /** Prop diferida: llega al abrir «Resumen de notas». */
    rendimiento?: Rendimiento | null;
  }

  let {
    curso,
    actividades = [],
    programa = null,
    docentes = [],
    datos = null,
    secciones = [],
    rendimiento = null,
  }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.etiqueta ?? curso?.asignatura_nombre ?? 'Curso', href: '' },
  ]);

  // ─── Pestañas ───────────────────────────────────────────────────────────────
  function seccionDeUrl(url: string): SeccionCurso {
    const pedida = new URL(url, 'http://x').searchParams.get('seccion');
    return SECCIONES_CURSO.includes(pedida as SeccionCurso) ? (pedida as SeccionCurso) : 'actividades';
  }

  const seccion = $derived(seccionDeUrl($page.url));

  function cambiarSeccion(nueva: SeccionCurso) {
    if (nueva === seccion) return;
    // Visita sólo de cliente: cambia la URL sin pedir nada al servidor.
    router.push({
      url: `/estudiante/cursos/${id_curso}?seccion=${nueva}`,
      preserveState: true,
      preserveScroll: true,
    });
  }

  // ─── Resumen de notas (T30) ─────────────────────────────────────────────────
  // Se recalcula cada vez que se entra a la pestaña: las notas pueden haber
  // cambiado desde la última vez.
  let notasCargando = $state(false);
  let notasError = $state<string | null>(null);

  $effect(() => {
    if (seccion !== 'notas') return;
    notasError = null;
    notasCargando = true;
    router.reload({
      only: ['rendimiento'],
      onError: () => (notasError = 'No se pudo calcular tu rendimiento. Intenta de nuevo.'),
      onFinish: () => (notasCargando = false),
    });
  });

  // ─── Biblioteca ─────────────────────────────────────────────────────────────
  // Es la sección VIII del programa; se muestra igual que en el documento.
  const bibliografia = $derived(
    secciones.filter((s) => s.numeral_romano === 'VIII' && textoDeSeccion(s.contenidos).trim() !== ''),
  );

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
</script>

{#snippet titulo(texto: string, detalle?: string)}
  <div class="mb-6 flex flex-col gap-1">
    <h2 class="text-xl font-semibold text-gray-900 sm:text-2xl">{texto}</h2>
    {#if detalle}
      <p class="text-sm text-gray-600">{detalle}</p>
    {/if}
  </div>
{/snippet}

<StudentLayout {breadcrumbs}>
  <div class="h-full px-5 md:px-10 lg:px-20 bg-white relative">
    <div class="relative mx-auto px-4 pb-10">
      <CursoEncabezado
        {curso}
        {seccion}
        totalActividades={actividades.length}
        onCambiarSeccion={cambiarSeccion}
      />

      {#if seccion === 'programa'}
        {#if programa}
          <div class="mb-4 flex justify-end">
            <Link
              href={`/estudiante/cursos/${id_curso}/programa`}
              class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-uta-blue"
            >
              <Printer class="h-4 w-4" />
              Versión para imprimir o guardar en PDF
            </Link>
          </div>
        {/if}
        <ProgramaAlumno {curso} {programa} {docentes} {secciones} categoria={datos?.categoria ?? ''} />
      {:else if seccion === 'actividades'}
        <section class="mb-10">
          {@render titulo('Próximas entregas')}
          <CursoProximasEntregas idCurso={id_curso} entregas={proximasEntregas} />
        </section>

        <section>
          {@render titulo(
            'Todas las actividades',
            hayFiltros
              ? `${actividadesFiltradas.length} de ${actividades.length} actividades`
              : `${actividades.length} ${actividades.length === 1 ? 'actividad publicada' : 'actividades publicadas'}`,
          )}
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
      {:else if seccion === 'notas'}
        {@render titulo('Resumen de notas', 'Tu promedio y tu asistencia en cada componente del curso.')}
        <CursoResumenNotas cargando={notasCargando} error={notasError} {rendimiento} />
      {:else if seccion === 'biblioteca'}
        {@render titulo('Biblioteca', 'Bibliografía y recursos de aprendizaje indicados en el programa.')}
        {#if bibliografia.length > 0}
          <ProgramaDocument secciones={bibliografia} variante="tarjetas" />
        {:else}
          <div class="rounded-2xl border border-dashed border-gray-200 p-10 text-center">
            <BookMarked class="mx-auto mb-3 h-10 w-10 text-gray-300" />
            <p class="text-sm text-gray-600">
              {programa
                ? 'El programa de este curso no registra bibliografía.'
                : 'La bibliografía se publica con el programa del curso, que aún no ha sido aprobado.'}
            </p>
          </div>
        {/if}
      {/if}
    </div>
  </div>
</StudentLayout>
