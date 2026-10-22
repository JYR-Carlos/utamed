<script lang="ts">
  /**
   * Programa (syllabus) del curso para imprimir o guardar en PDF.
   *
   * Es el mismo documento íntegro de la pestaña Programa, compuesto como una
   * sola hoja: encabezado con los datos del curso, las secciones separadas por
   * filetes y las Unidades primero (T24). «Imprimir» usa la impresión del
   * navegador; la hoja @media print de abajo deja sólo el documento, y los
   * colores salen siempre claros porque el modo oscuro sólo aplica en pantalla.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import CuerpoSeccion from '@/components/student/programa/CuerpoSeccion.svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { useFormatName } from '@/hooks';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import { btnPrimario, enlace, etiqueta, tituloCond } from '@/lib/dm';
  import {
    mesYAgno,
    seccionesParaLeer,
    type DocenteSyllabus,
    type ProgramaPublicado,
    type SeccionPrograma,
  } from '@/lib/programaAlumno';
  import type { BreadcrumbItem, Curso, SidebarCourse } from '@/types';
  import { Link, page } from '@inertiajs/svelte';
  import { BookOpen, ChevronLeft, Printer } from 'lucide-svelte';

  interface Props {
    curso?: Curso | null;
    programa?: ProgramaPublicado | null;
    docentes?: DocenteSyllabus[];
    secciones?: SeccionPrograma[];
    datos?: { categoria?: string } | null;
  }

  let { curso, programa, docentes = [], secciones = [] }: Props = $props();

  const { formatName } = useFormatName();

  const asignatura = $derived(curso?.asignatura);
  const nombre = $derived(asignatura?.nombre ?? curso?.asignatura_nombre ?? curso?.nombre ?? 'Sin nombre');
  const hrefCurso = $derived(`/estudiante/cursos/${curso?.id_curso}`);

  const breadcrumbs = $derived<BreadcrumbItem[]>([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis cursos', href: '/estudiante/cursos' },
    { title: nombre, href: hrefCurso },
    { title: 'Programa', href: '' },
  ]);

  const colorCurso = $derived(
    estiloCurso(numeroColorCurso(curso?.id_curso ?? 0, ($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? [])),
  );

  const ordenadas = $derived(seccionesParaLeer(secciones));

  const codigo = $derived(curso?.cod_asignatura || curso?.cod_curso || '');

  const lineaPrograma = $derived(
    [
      codigo,
      programa ? `Versión ${programa.version_programa}` : '',
      programa?.fecha_creacion ? `aprobado por el Consejo de Escuela en ${mesYAgno(programa.fecha_creacion)}` : '',
    ]
      .filter(Boolean)
      .join(' · '),
  );

  const lineaDocentes = $derived(
    docentes
      .map((d) => `${formatName(d.nombre)} (${d.es_titular ? 'titular' : (d.componente ?? 'docente').toLowerCase()})`)
      .join(' · '),
  );

  const lineaHoras = $derived(
    [
      [asignatura?.horas_catedra, 'cátedra'],
      [asignatura?.horas_taller, 'taller'],
      [asignatura?.horas_laboratorio, 'laboratorio'],
    ]
      .filter(([h]) => Number(h) > 0)
      .map(([h, tipo]) => `${h} ${tipo}`)
      .join(' · '),
  );
</script>

<svelte:head>
  <title>Programa · {nombre} | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div style={colorCurso} class="syllabus-print mx-auto flex w-full max-w-280 flex-col gap-6 px-4 py-5 lg:px-10 lg:pt-8 lg:pb-12">
    <div class="no-print mx-auto flex w-full max-w-215 flex-wrap items-center justify-between gap-3">
      <Link href={`${hrefCurso}?seccion=programa`} class={enlace}>
        <ChevronLeft size={18} aria-hidden="true" />Volver al curso
      </Link>
      {#if programa}
        <button type="button" class="{btnPrimario} text-[15px] font-bold" onclick={() => window.print()}>
          <Printer size={18} aria-hidden="true" />Imprimir o guardar como PDF
        </button>
      {/if}
    </div>

    {#if !programa}
      <EstadoVacio
        icono={BookOpen}
        titulo="Programa no disponible"
        texto="El programa de este curso todavía no ha sido aprobado."
        class="mx-auto w-full max-w-215"
      />
    {:else}
      <article
        class="mx-auto flex w-full max-w-215 flex-col gap-8 rounded-xl border border-t-6 border-dm-linea border-t-(--curso) bg-dm-tarjeta px-5 py-7 sm:px-14 sm:py-12 print:border-0 print:p-0"
      >
        <header class="flex flex-col gap-2.5 border-b-2 border-dm-ink pb-6">
          <span class="{etiqueta} tracking-[0.08em] text-dm-ink-3">
            Programa oficial{curso?.carrera_nombre ? ` · ${curso.carrera_nombre}` : ''}
          </span>
          <h1 class="{tituloCond} text-[36px] leading-[36px] sm:text-[52px] sm:leading-[50px]">{nombre}</h1>
          {#if lineaPrograma}<span class="text-[15px] leading-[22px] text-dm-ink-2">{lineaPrograma}</span>{/if}
          <dl class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-4">
            {#if lineaDocentes}
              <div class="col-span-2 flex flex-col gap-0.5">
                <dt class="{etiqueta} text-dm-ink-3">{docentes.length === 1 ? 'Docente' : 'Docentes'}</dt>
                <dd class="m-0 text-[15px] leading-[22px]">{lineaDocentes}</dd>
              </div>
            {/if}
            <div class="flex flex-col gap-0.5">
              <dt class="{etiqueta} text-dm-ink-3">Créditos SCT</dt>
              <dd class="m-0 text-[15px] leading-[22px]">{asignatura?.creditos_sct ?? '—'}</dd>
            </div>
            {#if lineaHoras}
              <div class="flex flex-col gap-0.5">
                <dt class="{etiqueta} text-dm-ink-3">Horas</dt>
                <dd class="m-0 text-[15px] leading-[22px]">{lineaHoras}</dd>
              </div>
            {/if}
          </dl>
        </header>

        {#each ordenadas as s, i (s.numeral_romano ?? s.nombre_seccion)}
          <section class="flex flex-col gap-3.5 {i > 0 ? 'border-t border-dm-linea pt-6' : ''}">
            <h2 class="flex items-baseline gap-3 font-dm-cond text-[26px] leading-[30px] font-extrabold uppercase">
              <span class="font-black text-(--curso-t)">{s.numeral_romano}</span>{s.nombre_seccion}
            </h2>
            <CuerpoSeccion seccion={s} variante="documento" />
          </section>
        {/each}

        <footer class="flex flex-col gap-0.5 border-t-2 border-dm-ink pt-5 text-[13px] leading-[18px] text-dm-ink-2">
          <span>
            Programa aprobado por el Consejo de Escuela · Versión {programa.version_programa}{programa.fecha_creacion
              ? ` · ${mesYAgno(programa.fecha_creacion)}`
              : ''}
          </span>
          {#if programa.creado_por}<span>Elaborado por {programa.creado_por}</span>{/if}
        </footer>
      </article>
    {/if}
  </div>
</StudentLayout>

<style>
  /*
   * Impresión / PDF: sólo el documento. Se oculta todo lo demás con
   * visibility (el layout es compartido y no conviene tocarlo) y se libera la
   * altura/overflow de los contenedores con scroll propio, que si no recortan
   * la impresión a una sola pantalla. Márgenes pensados para carta y A4.
   */
  @media print {
    @page {
      margin: 16mm 14mm;
    }

    :global(html),
    :global(body),
    :global(body *) {
      overflow: visible !important;
      height: auto !important;
      max-height: none !important;
      box-shadow: none !important;
    }

    :global(body *) {
      visibility: hidden;
    }

    .syllabus-print,
    .syllabus-print :global(*) {
      visibility: visible;
    }

    .syllabus-print {
      position: absolute;
      inset: 0 auto auto 0;
      width: 100%;
      max-width: none;
      padding: 0;
      font-size: 11pt;
    }

    .no-print {
      display: none !important;
    }

    .syllabus-print :global(a) {
      color: inherit;
      text-decoration: underline;
    }
  }
</style>
