<script lang="ts">
  /**
   * Ficha del curso para el alumno.
   *
   * Navegación por pestañas, como la UTAMED anterior a la que los alumnos
   * están habituados: Actividades · Programa · Resumen de notas · Biblioteca ·
   * Calendario · Mensajería · Bitácora. Todas cargan aquí mismo; las que
   * dependen de datos que cambian (notas, mensajes, bitácora) los piden al
   * abrirse.
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
  import ActividadesView, { coincideConFiltro, type FiltroActividad } from '../Activities/ActividadesView.svelte';
  import CursoEncabezado, { SECCIONES_CURSO, type SeccionCurso } from './components/CursoEncabezado.svelte';
  import CursoBitacora, { type Bitacora } from './components/CursoBitacora.svelte';
  import CursoCalendario, { type AlcanceCalendario } from './components/CursoCalendario.svelte';
  import CursoMensajeria, { type BandejaMensajeria } from './components/CursoMensajeria.svelte';
  import CursoProximasEntregas from './components/CursoProximasEntregas.svelte';
  import CursoResumenNotas, { type Rendimiento } from './components/CursoResumenNotas.svelte';
  import ProgramaAlumno, {
    type DocenteSyllabus,
    type ProgramaPublicado,
    type SeccionPrograma,
  } from './components/ProgramaAlumno.svelte';
  import CursoBiblioteca from './components/CursoBiblioteca.svelte';
  import { gruposDeRecursos } from '@/lib/programaAlumno';
  import { Link, page, router } from '@inertiajs/svelte';
  import { ChevronRight, MessageSquare, Printer } from 'lucide-svelte';
  import { useFormatName } from '@/hooks';
  import { porEntregar, type ActividadAlumno } from '@/lib/actividadAlumno';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { btnSecundario, tarjeta } from '@/lib/dm';
  import type { SidebarCourse } from '@/types';

  type Actividad = ActividadAlumno;

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
    /** Prop diferida: llega al abrir «Mensajería». */
    mensajeria?: BandejaMensajeria | null;
    /** Mensajes del curso sin leer, para la pestaña antes de abrirla. */
    mensajes_sin_leer?: number;
    /** Prop diferida: llega al abrir «Bitácora». */
    bitacora?: Bitacora | null;
    /** Prop diferida: llega al elegir «Ver todos» en el calendario. */
    calendario_semestre?: Actividad[] | null;
  }

  let {
    curso,
    actividades = [],
    programa = null,
    docentes = [],
    datos = null,
    secciones = [],
    rendimiento = null,
    mensajeria = null,
    mensajes_sin_leer = 0,
    bitacora = null,
    calendario_semestre = null,
  }: Props = $props();

  const id_curso = $derived(curso?.id_curso || 0);

  const nombreCurso = $derived(
    curso?.asignatura_nombre && curso.asignatura_nombre !== 'N/A' ? curso.asignatura_nombre : curso?.nombre || 'Curso',
  );

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis cursos', href: '/estudiante/cursos' },
    { title: nombreCurso, href: '' },
  ]);

  // El mismo color que el curso tiene en el menú y en «Mis cursos».
  const colorCurso = $derived(
    estiloCurso(numeroColorCurso(id_curso, ($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? [])),
  );

  const { formatName } = useFormatName();

  // El titular de la sección; si no hay titular marcado, el primer docente.
  const docente = $derived.by(() => {
    const d = docentes.find((x) => x.es_titular) ?? docentes[0];
    return d ? `${formatName(d.nombre)} · ${d.es_titular ? 'docente titular' : 'docente'}` : '';
  });

  // ─── Pestañas ───────────────────────────────────────────────────────────────
  function seccionDeUrl(url: string): SeccionCurso {
    const pedida = new URL(url, 'http://x').searchParams.get('seccion');
    return SECCIONES_CURSO.includes(pedida as SeccionCurso) ? (pedida as SeccionCurso) : 'actividades';
  }

  const seccion = $derived(seccionDeUrl($page.url));

  /** `arriba`: viene de un enlace al pie y la pestaña nueva se lee desde el principio. */
  function cambiarSeccion(nueva: SeccionCurso, arriba = false) {
    if (nueva === seccion) return;
    // Visita sólo de cliente: cambia la URL sin pedir nada al servidor.
    router.push({
      url: `/estudiante/cursos/${id_curso}?seccion=${nueva}`,
      preserveState: true,
      preserveScroll: !arriba,
    });
  }

  // ─── Pestañas con datos diferidos ───────────────────────────────────────────
  // Resumen de notas (T30), Mensajería y Bitácora se piden cada vez que se
  // entra a la pestaña: pueden haber cambiado desde la última vez.
  const DIFERIDAS: Partial<Record<SeccionCurso, { prop: string; error: string }>> = {
    notas: { prop: 'rendimiento', error: 'No se pudo calcular tu rendimiento. Intenta de nuevo.' },
    mensajeria: { prop: 'mensajeria', error: 'No se pudieron cargar los mensajes. Intenta de nuevo.' },
    bitacora: { prop: 'bitacora', error: 'No se pudo cargar la bitácora. Intenta de nuevo.' },
  };

  let diferidaCargando = $state(false);
  let diferidaError = $state<string | null>(null);

  function cargarDiferida(s: SeccionCurso) {
    const diferida = DIFERIDAS[s];
    if (!diferida) return;
    diferidaError = null;
    diferidaCargando = true;
    router.reload({
      only: [diferida.prop],
      onError: () => (diferidaError = diferida.error),
      onFinish: () => (diferidaCargando = false),
    });
  }

  $effect(() => cargarDiferida(seccion));

  const reintentar = () => cargarDiferida(seccion);

  // Abierta la bandeja, manda su conteo: abrirla marca como leído lo que muestra.
  const mensajesSinLeer = $derived(
    mensajeria ? mensajeria.componentes.reduce((total, c) => total + c.no_leidos, 0) : mensajes_sin_leer,
  );

  // ─── Biblioteca ─────────────────────────────────────────────────────────────
  // Es la sección VIII del programa, agrupada por sus subtítulos.
  const gruposBiblioteca = $derived(gruposDeRecursos(secciones));
  const totalRecursos = $derived(gruposBiblioteca.reduce((total, g) => total + g.recursos.length, 0));

  // ─── Calendario ─────────────────────────────────────────────────────────────
  // El alcance vive aquí para que sobreviva al cambiar de pestaña. «Ver
  // calendario» del Inicio llega con ?alcance=semestre.
  let alcanceCalendario = $state<AlcanceCalendario>(
    new URL($page.url, 'http://x').searchParams.get('alcance') === 'semestre' ? 'semestre' : 'curso',
  );

  // Fechas límite que el calendario puede dibujar en el alcance elegido.
  const totalFechas = $derived(
    (alcanceCalendario === 'semestre' ? (calendario_semestre ?? []) : actividades).filter(
      (a) => a.visible && a.fecha_limite,
    ).length,
  );

  // ─── Próximas entregas ──────────────────────────────────────────────────────
  // Las que todavía se pueden entregar, de la más cercana a la más lejana (las
  // atrasadas quedan primero).
  const proximasEntregas = $derived(
    actividades
      .filter((a) => porEntregar(a) && a.fecha_limite)
      .sort((a, b) => a.fecha_limite.localeCompare(b.fecha_limite)),
  );

  // Con actividades de más de un componente, cada fila dice de cuál es.
  const conComponente = $derived(new Set(actividades.map((a) => a.componente_tipo).filter(Boolean)).size > 1);

  // ─── Filtros de actividades ─────────────────────────────────────────────────
  let filtro = $state<FiltroActividad>('todas');

  const actividadesFiltradas = $derived(actividades.filter((a) => coincideConFiltro(a, filtro)));

  const resumenActividades = $derived.by(() => {
    const n = actividades.length;
    if (filtro !== 'todas') return `${actividadesFiltradas.length} de ${n}`;
    const pendientes = actividades.filter(porEntregar).length;
    return `${n} ${n === 1 ? 'actividad' : 'actividades'}${pendientes > 0 ? ` · ${pendientes} por entregar` : ''}`;
  });
</script>

{#snippet titulo(texto: string, detalle?: string, id?: string)}
  <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
    <h2 {id} class="text-[22px] leading-7 font-bold">{texto}</h2>
    {#if detalle}
      <p class="text-[13px] leading-[18px] text-dm-ink-3">{detalle}</p>
    {/if}
  </div>
{/snippet}

<StudentLayout {breadcrumbs}>
  <div style={colorCurso} class="mx-auto flex w-full max-w-280 flex-col gap-5 pb-10 lg:gap-6 lg:px-10 lg:pt-8 lg:pb-12">
    <CursoEncabezado
      {curso}
      {seccion}
      {docente}
      totalActividades={actividades.length}
      {totalRecursos}
      {mensajesSinLeer}
      onCambiarSeccion={cambiarSeccion}
    />

    <div class="flex flex-col gap-4 px-4 lg:px-0">
      {#if seccion === 'actividades'}
        <div class="flex flex-col gap-6 xl:grid xl:grid-cols-[minmax(0,1fr)_300px] xl:items-start">
          <section aria-labelledby="t-actividades" class="flex min-w-0 flex-col gap-4">
            {@render titulo('Actividades', resumenActividades, 't-actividades')}
            <ActividadesView
              {id_curso}
              {filtro}
              {conComponente}
              filtered={actividadesFiltradas}
              onSelectFiltro={(f) => (filtro = f)}
            />
          </section>

          <aside class="flex flex-col gap-4" aria-label="Accesos del curso">
            <!-- En pantallas angostas la tabla ya dice qué falta entregar. -->
            <CursoProximasEntregas idCurso={id_curso} entregas={proximasEntregas} class="max-xl:hidden" />

            <a
              href={`/estudiante/cursos/${id_curso}?seccion=mensajeria`}
              onclick={(e) => {
                e.preventDefault();
                cambiarSeccion('mensajeria', true);
              }}
              class="{tarjeta} flex min-h-16 items-center gap-3 px-4 py-3 text-dm-ink no-underline hover:bg-dm-sutil xl:px-5 xl:py-4"
            >
              <span class="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-dm-accion-suave text-dm-accion-texto">
                <MessageSquare size={20} aria-hidden="true" />
              </span>
              <span class="flex min-w-0 flex-1 flex-col">
                <span class="text-[15px] font-semibold">Mensajería del curso</span>
                {#if mensajesSinLeer > 0}
                  <span class="text-[13px] leading-[18px] font-semibold text-dm-accion-texto">
                    {mensajesSinLeer}
                    {mensajesSinLeer === 1 ? 'mensaje sin leer' : 'mensajes sin leer'}
                  </span>
                {:else}
                  <span class="text-[13px] leading-[18px] text-dm-ink-2">Avisos y consultas con el equipo docente</span>
                {/if}
              </span>
              <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-dm-ink-3" />
            </a>
          </aside>
        </div>
      {:else if seccion === 'programa'}
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-[22px] leading-7 font-bold">Programa</h2>
          {#if programa}
            <!-- En móvil el botón va al pie del programa. -->
            <Link href={`/estudiante/cursos/${id_curso}/programa`} class="{btnSecundario} min-h-10 max-lg:hidden">
              <Printer size={16} aria-hidden="true" />Versión para imprimir
            </Link>
          {/if}
        </div>
        <ProgramaAlumno
          {curso}
          {programa}
          {docentes}
          {secciones}
          categoria={datos?.categoria ?? ''}
          hrefImprimir={`/estudiante/cursos/${id_curso}/programa`}
        />
      {:else if seccion === 'calendario'}
        {@render titulo(
          'Calendario',
          alcanceCalendario === 'semestre' && calendario_semestre === null
            ? undefined
            : `${totalFechas} ${totalFechas === 1 ? 'fecha límite' : 'fechas límite'}${alcanceCalendario === 'semestre' ? ' en el semestre' : ''}`,
        )}
        <CursoCalendario
          idCurso={id_curso}
          {actividades}
          semestre={calendario_semestre}
          bind:alcance={alcanceCalendario}
        />
      {:else if seccion === 'notas'}
        {@render titulo('Resumen de notas', 'Escala de 1,0 a 7,0 · las notas aparecen cuando se publica la calificación')}
        <CursoResumenNotas cargando={diferidaCargando} error={diferidaError} {rendimiento} onReintentar={reintentar} />
      {:else if seccion === 'mensajeria'}
        <!-- El título va con los componentes en la misma fila: lo pone la pestaña. -->
        <CursoMensajeria
          idCurso={id_curso}
          bandeja={mensajeria}
          cargando={diferidaCargando}
          error={diferidaError}
          onReintentar={reintentar}
        />
      {:else if seccion === 'bitacora'}
        {@render titulo('Bitácora', 'Entregas, mensajes, retroalimentaciones y notas de todas tus actividades')}
        <CursoBitacora idCurso={id_curso} {bitacora} error={diferidaError} onReintentar={reintentar} />
      {:else if seccion === 'biblioteca'}
        {@render titulo(
          'Biblioteca',
          totalRecursos > 0
            ? `${totalRecursos} ${totalRecursos === 1 ? 'recurso' : 'recursos'} · bibliografía y recursos indicados en el programa`
            : 'Bibliografía y recursos de aprendizaje indicados en el programa.',
        )}
        <CursoBiblioteca grupos={gruposBiblioteca} hayPrograma={!!programa} />
      {/if}
    </div>
  </div>
</StudentLayout>
