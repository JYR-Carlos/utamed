<script lang="ts">
  /**
   * Ficha del curso para el alumno.
   *
   * Navegación por pestañas, como la UTAMED anterior a la que los alumnos
   * están habituados: Programa · Actividades · Resumen de notas · Biblioteca ·
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
  import CursoCalendario from './components/CursoCalendario.svelte';
  import CursoMensajeria, { type BandejaMensajeria } from './components/CursoMensajeria.svelte';
  import CursoProximasEntregas from './components/CursoProximasEntregas.svelte';
  import CursoResumenNotas, { type Rendimiento } from './components/CursoResumenNotas.svelte';
  import ProgramaAlumno, {
    type DocenteSyllabus,
    type ProgramaPublicado,
    type SeccionPrograma,
  } from './components/ProgramaAlumno.svelte';
  import ProgramaDocument from '@/modules/resources/programa/components/ProgramaDocument.svelte';
  import { bloquesDeSeccion, textoDeSeccion } from '@/modules/resources/programa/utils/syllabusTexto';
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
    /** Día del último envío vigente (YYYY-MM-DD); sólo si ya_entregada. */
    fecha_entrega?: string | null;
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
    /** Prop diferida: llega al abrir «Mensajería». */
    mensajeria?: BandejaMensajeria | null;
    /** Prop diferida: llega al abrir «Bitácora». */
    bitacora?: Bitacora | null;
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
    bitacora = null,
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

  $effect(() => {
    const diferida = DIFERIDAS[seccion];
    if (!diferida) return;
    diferidaError = null;
    diferidaCargando = true;
    router.reload({
      only: [diferida.prop],
      onError: () => (diferidaError = diferida.error),
      onFinish: () => (diferidaCargando = false),
    });
  });

  const mensajesSinLeer = $derived(
    (mensajeria?.componentes ?? []).reduce((total, c) => total + c.no_leidos, 0),
  );

  // ─── Biblioteca ─────────────────────────────────────────────────────────────
  // Es la sección VIII del programa; se muestra igual que en el documento.
  const bibliografia = $derived(
    secciones.filter((s) => s.numeral_romano === 'VIII' && textoDeSeccion(s.contenidos).trim() !== ''),
  );

  // Recursos = ítems de las listas de la bibliografía.
  const totalRecursos = $derived(
    bibliografia.reduce(
      (total, s) =>
        total +
        bloquesDeSeccion(textoDeSeccion(s.contenidos), s.numeral_romano).reduce(
          (n, b) => n + (b.tipo === 'lista' ? b.items.length : 0),
          0,
        ),
      0,
    ),
  );

  // Fechas límite que el calendario puede dibujar.
  const totalFechas = $derived(actividades.filter((a) => a.visible && a.fecha_limite).length);

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
  let filtro = $state<FiltroActividad>('todas');

  const hayFiltros = $derived(filtro !== 'todas');

  const actividadesFiltradas = $derived(actividades.filter((a) => coincideConFiltro(a, filtro)));
</script>

<!-- `enLinea`: el detalle va a la derecha del título («Todas las actividades · 6 en total»)
     en vez de debajo. -->
{#snippet titulo(texto: string, detalle?: string, enLinea = false, detalleMovil?: string)}
  <div class="mb-4 flex gap-x-3 gap-y-1 {enLinea ? 'flex-wrap items-baseline justify-between sm:justify-start' : 'flex-col'}">
    <h2 class="text-xl font-bold text-slate-900 sm:text-2xl sm:font-semibold">{texto}</h2>
    {#if detalle}
      <p class="text-[13px] font-medium text-slate-600 sm:text-sm {detalleMovil ? 'max-sm:hidden' : ''}">{detalle}</p>
    {/if}
    {#if detalleMovil}
      <p class="text-[13px] font-medium text-slate-600 sm:hidden">{detalleMovil}</p>
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
        {totalRecursos}
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
              : `${actividades.length} ${actividades.length === 1 ? 'actividad' : 'actividades'}`,
            true,
            hayFiltros ? undefined : `${actividades.length} en total`,
          )}
          <ActividadesView
            {id_curso}
            {filtro}
            filtered={actividadesFiltradas}
            onSelectFiltro={(f) => (filtro = f)}
          />
        </section>
      {:else if seccion === 'calendario'}
        <section>
          {@render titulo(
            'Calendario',
            `${totalFechas} ${totalFechas === 1 ? 'fecha límite' : 'fechas límite'}`,
            true,
          )}
          <CursoCalendario idCurso={id_curso} {actividades} />
        </section>
      {:else if seccion === 'notas'}
        {@render titulo('Resumen de notas')}
        <CursoResumenNotas cargando={diferidaCargando} error={diferidaError} {rendimiento} />
      {:else if seccion === 'mensajeria'}
        {@render titulo(
          'Mensajería',
          mensajesSinLeer > 0
            ? `${mensajesSinLeer} sin leer`
            : 'Avisos y consultas con el equipo docente de cada componente.',
          mensajesSinLeer > 0,
        )}
        <CursoMensajeria idCurso={id_curso} bandeja={mensajeria} cargando={diferidaCargando} error={diferidaError} />
      {:else if seccion === 'bitacora'}
        {@render titulo(
          'Bitácora',
          'Entregas, mensajes, retroalimentaciones y evaluaciones de todas tus actividades.',
        )}
        <CursoBitacora idCurso={id_curso} {bitacora} error={diferidaError} />
      {:else if seccion === 'biblioteca'}
        {@render titulo(
          'Biblioteca',
          totalRecursos > 0
            ? `${totalRecursos} ${totalRecursos === 1 ? 'recurso' : 'recursos'}`
            : 'Bibliografía y recursos de aprendizaje indicados en el programa.',
          totalRecursos > 0,
        )}
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
