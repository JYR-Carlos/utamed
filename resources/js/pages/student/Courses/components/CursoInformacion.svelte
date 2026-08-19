<script lang="ts">
  /**
   * "Sobre el curso" — resumen académico que abre la ficha del alumno.
   *
   * Toma los mismos props que student/Courses/Syllabus.svelte pero muestra sólo
   * lo que el alumno necesita para situarse: de qué trata, quién lo dicta, cómo
   * se evalúa y qué unidades cubre. El documento completo (competencias,
   * bibliografía, normativa extendida) sigue en /estudiante/cursos/{id}/programa,
   * a un clic desde aquí.
   *
   * Estructura visual: tarjetas redondeadas con borde gris y encabezado de
   * icono + título, el mismo patrón que los paneles del dashboard.
   */
  import { Link } from '@inertiajs/svelte';
  import { ArrowRight, BookOpen, Info, ListOrdered, Mail, Scale, Users } from 'lucide-svelte';
  import type { Curso } from '@/types';
  import type { DatosSyllabusAlumno, DocenteAlumno } from '@/types/syllabus.types';
  import { initials } from '@/utils/formatters';

  interface Props {
    curso?: Curso | null;
    programa?: { version_programa: string } | null;
    docentes?: DocenteAlumno[];
    datos?: DatosSyllabusAlumno | null;
  }

  let { curso = null, programa = null, docentes = [], datos = null }: Props = $props();

  const descripcion = $derived(datos?.descripcion ?? '');
  const componentes = $derived(datos?.componentes ?? []);
  const normativa = $derived(datos?.normativa ?? '');
  const unidades = $derived(datos?.unidades ?? []);

  // Titular primero: es el interlocutor por defecto del alumno.
  const equipo = $derived(
    [...docentes].sort((a, b) => Number(b.es_titular) - Number(a.es_titular)),
  );
</script>

{#snippet encabezado(Icono: typeof BookOpen, texto: string)}
  <div class="flex items-center gap-2 mb-4">
    <Icono class="w-4 h-4 text-uta-blue" />
    <h3 class="text-sm text-gray-700 font-medium">{texto}</h3>
  </div>
{/snippet}

<div class="space-y-6">
  <!-- Descripción -->
  {#if descripcion}
    <div class="rounded-3xl border border-gray-200 bg-white p-6">
      {@render encabezado(BookOpen, 'De qué trata')}
      <p class="text-[15px] leading-relaxed text-gray-700 whitespace-pre-line">{descripcion}</p>
    </div>
  {/if}

  <!-- Equipo docente -->
  <div class="rounded-3xl border border-gray-200 bg-white p-6">
    {@render encabezado(Users, equipo.length === 1 ? 'Tu docente' : 'Equipo docente')}
    {#if equipo.length === 0}
      <p class="text-sm text-gray-500">
        Todavía no hay docentes asignados a tus componentes de este curso.
      </p>
    {:else}
      <ul class="grid gap-3 sm:grid-cols-2">
        {#each equipo as docente (docente.nombre + (docente.componente ?? ''))}
          <li class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50 border border-gray-100">
            <span
              class="w-10 h-10 shrink-0 rounded-full bg-uta-blue text-white flex items-center justify-center text-xs font-bold"
              aria-hidden="true"
            >
              {initials(docente.nombre)}
            </span>
            <div class="min-w-0">
              <p class="font-semibold text-gray-900 leading-tight">{docente.nombre}</p>
              <p class="text-xs text-gray-500 mt-0.5">
                {docente.es_titular ? 'Titular' : 'Docente'}{docente.componente
                  ? ` · ${docente.componente}`
                  : ''}
              </p>
              {#if docente.email}
                <a
                  href={`mailto:${docente.email}`}
                  class="inline-flex items-center gap-1.5 mt-1.5 text-xs font-medium text-uta-blue hover:underline break-all"
                >
                  <Mail class="w-3 h-3 shrink-0" />
                  {docente.email}
                </a>
              {/if}
            </div>
          </li>
        {/each}
      </ul>
    {/if}
  </div>

  <!-- Evaluación -->
  {#if componentes.length > 0}
    <div class="rounded-3xl border border-gray-200 bg-white p-6">
      {@render encabezado(Scale, 'Cómo se evalúa')}
      <ul class="space-y-5">
        {#each componentes as componente (componente.componente)}
          {@const asistencia = componente.asistencia_obligatoria ?? 0}
          <li>
            <div class="flex items-baseline justify-between gap-4 mb-2">
              <span class="font-semibold text-gray-900">{componente.componente}</span>
              <span class="text-lg font-bold text-uta-blue">{componente.porcentaje}%</span>
            </div>
            <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
              <div
                class="h-full rounded-full bg-uta-blue"
                style="width: {Math.min(componente.porcentaje, 100)}%"
              ></div>
            </div>
            {#if componente.aprobacion_obligatoria || asistencia > 0}
              <p class="mt-2 text-xs text-gray-500">
                {#if componente.aprobacion_obligatoria}Debes aprobarlo por separado{/if}
                {#if componente.aprobacion_obligatoria && asistencia > 0}{' · '}{/if}
                {#if asistencia > 0}Asistencia mínima {asistencia}%{/if}
              </p>
            {/if}
          </li>
        {/each}
      </ul>

      {#if normativa}
        <div class="mt-6 p-4 rounded-2xl bg-uta-red-light border border-uta-red/20">
          <p class="text-sm font-semibold text-uta-red mb-1">Requisito de aprobación</p>
          <p class="text-sm text-gray-700 leading-relaxed">{normativa}</p>
        </div>
      {/if}
    </div>
  {/if}

  <!-- Unidades: la numeración es real, marca el orden en que se pasan. -->
  {#if unidades.length > 0}
    <div class="rounded-3xl border border-gray-200 bg-white p-6">
      {@render encabezado(ListOrdered, 'Unidades del curso')}
      <ol class="divide-y divide-gray-100">
        {#each unidades as unidad (unidad.numero)}
          <li class="py-4 first:pt-0 last:pb-0">
            <details class="group">
              <summary class="flex items-start gap-4 cursor-pointer list-none rounded-lg">
                <span class="shrink-0 w-6 text-sm font-bold text-gray-400" aria-hidden="true">
                  {unidad.numero}
                </span>
                <span class="flex-1 font-semibold text-gray-900 leading-snug">
                  {unidad.titulo}
                </span>
                {#if unidad.contenidos_items?.length}
                  <span class="shrink-0 text-xs font-medium text-gray-400 group-open:hidden">
                    Ver contenidos
                  </span>
                  <span class="shrink-0 text-xs font-medium text-gray-400 hidden group-open:inline">
                    Ocultar
                  </span>
                {/if}
              </summary>
              {#if unidad.contenidos_items?.length}
                <div class="pl-10 pr-2 mt-3 space-y-2">
                  {#each unidad.contenidos_items as contenido, i (i)}
                    <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                      {contenido.item}
                    </p>
                  {/each}
                </div>
              {/if}
            </details>
          </li>
        {/each}
      </ol>
    </div>
  {/if}

  <!-- Programa completo / aviso si aún no está publicado -->
  {#if programa}
    <Link
      href={`/estudiante/cursos/${curso?.id_curso}/programa`}
      class="flex items-center gap-4 p-5 rounded-3xl border border-gray-200 bg-white no-underline hover:border-uta-blue/30 hover:bg-uta-blue-light/40 transition-colors group"
    >
      <span
        class="w-10 h-10 shrink-0 rounded-2xl bg-uta-blue-light text-uta-blue flex items-center justify-center"
      >
        <BookOpen class="w-[18px] h-[18px]" />
      </span>
      <span class="flex-1 min-w-0">
        <span class="block font-semibold text-gray-900">Programa completo del curso</span>
        <span class="block text-xs text-gray-500 mt-0.5">
          Competencias, resultados de aprendizaje y bibliografía · versión {programa.version_programa}
        </span>
      </span>
      <ArrowRight
        class="w-[18px] h-[18px] shrink-0 text-uta-blue transition-transform group-hover:translate-x-0.5"
      />
    </Link>
  {:else}
    <div class="flex items-start gap-4 p-5 rounded-3xl border border-gray-200 bg-gray-50">
      <span class="shrink-0 text-gray-400 mt-0.5">
        <Info class="w-[18px] h-[18px]" />
      </span>
      <p class="text-sm text-gray-600 leading-relaxed">
        El programa de este curso todavía no está publicado. Cuando tu escuela lo apruebe vas a
        poder revisarlo aquí mismo.
      </p>
    </div>
  {/if}
</div>
