<script lang="ts">
  /**
   * Programa (syllabus) del curso, visto por el alumno.
   *
   * Muestra el documento íntegro —las mismas secciones que ve el docente, sin
   * resumir— en tarjetas (T28). Las Unidades van primero (T24, reunión del
   * 23-09); el resto sigue el orden del programa. «Imprimir / Guardar como
   * PDF» usa la impresión del navegador y la hoja @media print de abajo deja
   * sólo el documento.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import ProgramaAlumno, {
    type DocenteSyllabus,
    type ProgramaPublicado,
    type SeccionPrograma,
  } from './components/ProgramaAlumno.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import { ArrowLeft, BookOpen, Printer } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';

  interface Props {
    curso?: Curso | null;
    programa?: ProgramaPublicado | null;
    docentes?: DocenteSyllabus[];
    secciones?: SeccionPrograma[];
    datos?: { categoria?: string } | null;
  }

  // ─── Props ───────────────────────────────────────────────────────────────────

  let { curso, programa, docentes = [], secciones = [], datos }: Props = $props();

  const asignatura = $derived(curso?.asignatura);
  const asignaturaNombre = $derived(asignatura?.nombre ?? curso?.nombre ?? 'Sin nombre');
  const backUrl = $derived(`/estudiante/cursos/${curso?.id_curso}?seccion=programa`);
  const categoria = $derived(datos?.categoria ?? '');

  const breadcrumbs = $derived<BreadcrumbItem[]>([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.etiqueta ?? asignaturaNombre, href: `/estudiante/cursos/${curso?.id_curso}` },
    { title: 'Programa', href: '#' },
  ]);

  function imprimir() {
    window.print();
  }
</script>

<svelte:head>
  <title>Programa · {asignaturaNombre} | UTAMED</title>
</svelte:head>

<StudentLayout {breadcrumbs}>
  <div class="syllabus-print mx-auto max-w-5xl px-4 py-8 sm:px-8 sm:py-12">
    <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-3">
      <Link
        href={backUrl}
        class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-uta-blue"
      >
        <ArrowLeft class="h-4 w-4" />
        Volver al curso
      </Link>
      {#if programa}
        <button type="button" class="btn btn-primary" onclick={imprimir}>
          <Printer class="h-4 w-4" />
          Imprimir / Guardar como PDF
        </button>
      {/if}
    </div>

    <!-- Encabezado del documento -->
    <header class="mb-6">
      <div class="mb-3 flex items-center gap-2">
        <BookOpen class="h-5 w-5 text-uta-blue" />
        <span class="text-sm font-semibold uppercase tracking-wider text-uta-blue">
          Programa Oficial
        </span>
      </div>

      <h1 class="text-3xl font-extrabold leading-tight text-gray-900 sm:text-4xl">
        {asignaturaNombre}
      </h1>
    </header>

    <ProgramaAlumno {curso} {programa} {docentes} {secciones} {categoria} />
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

    .syllabus-print :global(.programa-tarjeta) {
      break-inside: avoid-page;
      border-color: var(--color-line, #d6d9e0);
      padding: 12pt 14pt;
    }

    .syllabus-print :global(table) {
      break-inside: avoid;
    }

    .syllabus-print :global(a) {
      color: inherit;
      text-decoration: underline;
    }
  }
</style>
