<script lang="ts" module>
  /**
   * Un docente de la sección del alumno. Cada componente (cátedra, laboratorio,
   * taller) tiene un titular y puede tener un docente extra propio.
   */
  export interface DocenteSyllabus {
    nombre: string;
    email?: string | null;
    es_titular: boolean;
    componente?: string | null;
  }

  /** Sección tal como la arma App\Traits\ParsesSyllabus (igual que al docente). */
  export interface SeccionPrograma {
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

  export interface ProgramaPublicado {
    id_programa: number;
    version_programa: string;
    estado: string;
    creado_por?: string;
    fecha_creacion?: string;
    tipo_syllabus?: string;
  }
</script>

<script lang="ts">
  /**
   * Programa (syllabus) del curso tal como lo lee el alumno: ficha de
   * identificación (categoría, docentes, créditos, horas) y el documento
   * íntegro en tarjetas (T28), con las Unidades primero (T24).
   *
   * Lo comparten la pestaña «Programa» de la ficha del curso y la página
   * /estudiante/cursos/{id}/programa, que añade la impresión a PDF.
   */
  import ProgramaDocument from '@/modules/resources/programa/components/ProgramaDocument.svelte';
  import type { Curso } from '@/types';
  import { Award, BookOpen, Clock, User } from 'lucide-svelte';

  interface Props {
    curso?: Curso | null;
    programa?: ProgramaPublicado | null;
    docentes?: DocenteSyllabus[];
    secciones?: SeccionPrograma[];
    categoria?: string;
  }

  let { curso, programa = null, docentes = [], secciones = [], categoria = '' }: Props = $props();

  const asignatura = $derived(curso?.asignatura);

  /** Unidades (VI) primero; el resto conserva el orden del programa. */
  const seccionesOrdenadas = $derived([
    ...secciones.filter((s) => s.numeral_romano === 'VI'),
    ...secciones.filter((s) => s.numeral_romano !== 'VI'),
  ]);

  function formatDate(dateStr?: string) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('es-CL', { year: 'numeric', month: 'long' });
  }
</script>

<div
  class="mb-8 grid grid-cols-2 gap-6 rounded-2xl border border-gray-200 bg-white p-6 sm:grid-cols-3 lg:grid-cols-5"
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
