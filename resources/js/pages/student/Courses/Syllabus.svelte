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
  import ProgramaDocument from '@/modules/resources/programa/components/ProgramaDocument.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import { ArrowLeft, Award, BookOpen, Clock, Printer, User } from 'lucide-svelte';
  import { Link } from '@inertiajs/svelte';

  // ─── Types ──────────────────────────────────────────────────────────────────

  /**
   * Un docente de la sección del alumno. Cada componente (cátedra, laboratorio,
   * taller) tiene un titular y puede tener un docente extra propio.
   */
  interface DocenteSyllabus {
    nombre: string;
    email?: string | null;
    es_titular: boolean;
    componente?: string | null;
  }

  /** Sección tal como la arma App\Traits\ParsesSyllabus (igual que al docente). */
  interface Seccion {
    numeral_romano?: string;
    nombre_seccion: string;
    contenidos?: Array<{ texto_contenido: string | null }>;
    componentes?: Array<{
      componente: string;
      porcentaje: number | string;
      genera_acta?: boolean;
      aprobacion_obligatoria?: boolean;
      asistencia_obligatoria?: number | string | null;
    }>;
    ponderacion_optativa?: { porcentaje?: number } | null;
  }

  interface Props {
    curso?: Curso | null;
    programa?: {
      id_programa: number;
      version_programa: string;
      estado: string;
      creado_por?: string;
      fecha_creacion?: string;
      tipo_syllabus?: string;
    } | null;
    docentes?: DocenteSyllabus[];
    secciones?: Seccion[];
    datos?: { categoria?: string } | null;
  }

  // ─── Props ───────────────────────────────────────────────────────────────────

  let { curso, programa, docentes = [], secciones = [], datos }: Props = $props();

  const asignatura = $derived(curso?.asignatura);
  const asignaturaNombre = $derived(asignatura?.nombre ?? curso?.nombre ?? 'Sin nombre');
  const backUrl = $derived(`/estudiante/cursos/${curso?.id_curso}`);
  const categoria = $derived(datos?.categoria ?? '');

  const breadcrumbs = $derived<BreadcrumbItem[]>([
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: asignaturaNombre, href: backUrl },
    { title: 'Programa', href: '#' },
  ]);

  /** Unidades (VI) primero; el resto conserva el orden del programa. */
  const seccionesOrdenadas = $derived([
    ...secciones.filter((s) => s.numeral_romano === 'VI'),
    ...secciones.filter((s) => s.numeral_romano !== 'VI'),
  ]);

  function formatDate(dateStr?: string) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('es-CL', { year: 'numeric', month: 'long' });
  }

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
    <header class="mb-8">
      <div class="mb-3 flex items-center gap-2">
        <BookOpen class="h-5 w-5 text-uta-blue" />
        <span class="text-sm font-semibold uppercase tracking-wider text-uta-blue">
          Programa Oficial
        </span>
      </div>

      <h1 class="mb-6 text-3xl font-extrabold leading-tight text-gray-900 sm:text-4xl">
        {asignaturaNombre}
      </h1>

      <div
        class="grid grid-cols-2 gap-6 rounded-2xl border border-gray-200 bg-white p-6 sm:grid-cols-3 lg:grid-cols-5"
      >
        <div>
          <div class="mb-2 flex items-center gap-2">
            <BookOpen class="h-4 w-4 text-gray-500" />
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-600">Categoría</span>
          </div>
          <p class="font-semibold text-gray-900">{categoria || '—'}</p>
        </div>

        <!-- Docentes de la sección del alumno (titular primero) -->
        <div class="col-span-2 sm:col-span-1">
          <div class="mb-2 flex items-center gap-2">
            <User class="h-4 w-4 text-gray-500" />
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-600"
              >{docentes.length > 1 ? 'Docentes' : 'Docente'}</span
            >
          </div>
          {#if docentes.length}
            <ul class="space-y-3">
              {#each docentes as docente (docente.nombre + (docente.componente ?? ''))}
                <li>
                  <p class="font-semibold text-gray-900">{docente.nombre}</p>
                  <p class="text-xs text-gray-500">
                    {docente.es_titular ? 'Titular' : 'Docente'}{docente.componente
                      ? ` · ${docente.componente}`
                      : ''}
                  </p>
                  {#if docente.email}
                    <p class="truncate text-sm text-gray-500">{docente.email}</p>
                  {/if}
                </li>
              {/each}
            </ul>
          {:else}
            <p class="text-sm italic text-gray-400">No asignado</p>
          {/if}
        </div>

        <div>
          <div class="mb-2 flex items-center gap-2">
            <Award class="h-4 w-4 text-gray-500" />
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-600">Créditos SCT</span>
          </div>
          <p class="text-2xl font-bold text-gray-900">{asignatura?.creditos_sct ?? '—'}</p>
        </div>

        <div>
          <div class="mb-2 flex items-center gap-2">
            <Clock class="h-4 w-4 text-gray-500" />
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-600">Horas Cátedra</span>
          </div>
          <p class="text-2xl font-bold text-gray-900">{asignatura?.horas_catedra ?? '—'}</p>
        </div>

        <div>
          <div class="mb-2 flex items-center gap-2">
            <Clock class="h-4 w-4 text-gray-500" />
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-600">Horas Taller</span>
          </div>
          <p class="text-2xl font-bold text-gray-900">{asignatura?.horas_taller ?? '—'}</p>
        </div>
      </div>
    </header>

    {#if !programa}
      <div class="rounded-2xl border border-gray-200 bg-white p-16 text-center">
        <BookOpen class="mx-auto mb-4 h-12 w-12 text-gray-300" />
        <h3 class="mb-2 text-xl font-bold text-gray-700">Programa no disponible</h3>
        <p class="text-gray-500">El programa de este curso aún no ha sido aprobado.</p>
      </div>
    {:else}
      <ProgramaDocument secciones={seccionesOrdenadas} variante="tarjetas" />

      <footer class="mt-8 text-center text-sm text-gray-500">
        <p>
          Programa aprobado por el Consejo de Escuela · Versión {programa.version_programa}
          {#if programa.fecha_creacion}
            · {formatDate(programa.fecha_creacion)}
          {/if}
        </p>
        {#if programa.creado_por}
          <p class="mt-1">Elaborado por: {programa.creado_por}</p>
        {/if}
      </footer>
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

    .syllabus-print :global(.programa-tarjeta) {
      break-inside: avoid-page;
      border-color: #d6d9e0;
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
