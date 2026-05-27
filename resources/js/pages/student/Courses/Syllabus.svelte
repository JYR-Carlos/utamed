<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, Curso } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import { ArrowLeft, BookOpen, Download, User, Award, Clock, ExternalLink } from 'lucide-svelte';

  // ─── Types ──────────────────────────────────────────────────────────────────

  interface Componente {
    componente: string;
    porcentaje: number;
    genera_acta: boolean;
    aprobacion_obligatoria: boolean;
    asistencia_obligatoria: number;
  }

  interface Recurso {
    descripcion: string;
    tipo: string;
    link?: string;
  }

  interface Competencia {
    titulo: string;
    descripcion?: string;
  }

  interface ResultadoAprendizaje {
    resultado: string;
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
    docente?: {
      nombre?: string;
      email?: string;
    } | null;
    datos?: {
      categoria?: string;
      descripcion?: string;
      competencias_especificas?: Competencia[];
      competencias_genericas?: Competencia[];
      componentes?: Componente[];
      normativa?: string;
      recursos?: Recurso[];
      resultados_aprendizaje?: ResultadoAprendizaje[];
      unidades?: Array<{
        numero: number;
        titulo: string;
        contenidos_items?: Array<{ item: string }>;
        resultados_aprendizaje?: ResultadoAprendizaje[];
      }>;
    } | null;
  }

  // ─── Props ───────────────────────────────────────────────────────────────────

  let { curso, programa, docente, datos }: Props = $props();

  const asignatura = $derived(curso?.asignatura);
  const asignaturaNombre = $derived(asignatura?.nombre ?? curso?.nombre ?? 'Sin nombre');
  const backUrl = $derived(`/estudiante/cursos/${curso?.id_curso}`);

  // Detectar si es versión básica o completa
  const isBasicoSyllabus = $derived(
    programa?.tipo_syllabus === 'BASICO' || programa?.tipo_syllabus === 'basico',
  );

  const breadcrumbs = $derived<BreadcrumbItem[]>([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: asignaturaNombre, href: backUrl },
    { title: 'Programa', href: '#' },
  ]);

  // ─── Derived data ────────────────────────────────────────────────────────────

  const categoria = $derived(datos?.categoria ?? '');
  const descripcion = $derived(datos?.descripcion ?? '');
  const normativa = $derived(datos?.normativa ?? '');
  const componentes = $derived(datos?.componentes ?? []);
  const recursos = $derived(datos?.recursos ?? []);
  const resultados = $derived(datos?.resultados_aprendizaje ?? []);
  const unidades = $derived(datos?.unidades ?? []);

  const todasCompetencias = $derived([
    ...(datos?.competencias_especificas ?? []),
    ...(datos?.competencias_genericas ?? []),
  ]);

  // ─── Donut chart ─────────────────────────────────────────────────────────────

  const CHART_COLORS = ['#4F46E5', '#7C3AED', '#EC4899', '#06B6D4', '#F59E0B', '#10B981'];
  const R = 95;
  const CIRC = 2 * Math.PI * R;

  const COMP_COLORS = [
    'bg-indigo-600',
    'bg-purple-600',
    'bg-pink-600',
    'bg-cyan-600',
    'bg-amber-500',
    'bg-emerald-600',
  ];

  interface DonutSegment {
    color: string;
    dashArray: string;
    dashOffset: number;
    label: string;
    porcentaje: number;
  }

  const donutSegments = $derived.by((): DonutSegment[] => {
    let accumulated = 0;
    return componentes.map((c, i) => {
      const len = (c.porcentaje / 100) * CIRC;
      const dashOffset = CIRC / 4 - accumulated;
      accumulated += len;
      return {
        color: CHART_COLORS[i % CHART_COLORS.length],
        dashArray: `${len} ${CIRC}`,
        dashOffset,
        label: c.componente,
        porcentaje: c.porcentaje,
      };
    });
  });

  // ─── Helpers ─────────────────────────────────────────────────────────────────

  function formatDate(dateStr?: string) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('es-CL', { year: 'numeric', month: 'long' });
  }
</script>

<!-- Main Content -->
    <div class="max-w-5xl mx-auto px-8 py-12">
      <!-- Document Header -->
      <div class="mb-12">
        <div class="flex items-center gap-2 mb-4">
          <BookOpen class="w-5 h-5 text-indigo-600" />
          <span class="text-sm font-semibold text-indigo-600 uppercase tracking-wider">
            Programa Oficial
          </span>
        </div>

        <h1 class="text-5xl font-extrabold text-gray-900 mb-8 leading-tight">
          {asignaturaNombre}
        </h1>

        <!-- Metadata Grid -->
        <div class="grid grid-cols-5 gap-6 p-6 bg-white rounded-2xl border border-gray-200">
          <!-- Categoría -->
          <div>
            <div class="flex items-center gap-2 mb-2">
              <BookOpen class="w-4 h-4 text-gray-500" />
              <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >Categoría</span
              >
            </div>
            <p class="font-semibold text-gray-900">{categoria || '—'}</p>
          </div>

          <!-- Docente -->
          <div>
            <div class="flex items-center gap-2 mb-2">
              <User class="w-4 h-4 text-gray-500" />
              <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >Docente</span
              >
            </div>
            {#if docente?.nombre}
              <p class="font-semibold text-gray-900">{docente.nombre}</p>
              {#if docente.email}
                <p class="text-sm text-gray-500 truncate">{docente.email}</p>
              {/if}
            {:else}
              <p class="text-sm text-gray-400 italic">No asignado</p>
            {/if}
          </div>

          <!-- Créditos SCT -->
          <div>
            <div class="flex items-center gap-2 mb-2">
              <Award class="w-4 h-4 text-gray-500" />
              <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >Créditos SCT</span
              >
            </div>
            <p class="text-2xl font-bold text-gray-900">{asignatura?.creditos_sct ?? '—'}</p>
          </div>

          <!-- Horas Cátedra -->
          <div>
            <div class="flex items-center gap-2 mb-2">
              <Clock class="w-4 h-4 text-gray-500" />
              <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >Horas Cátedra</span
              >
            </div>
            <p class="text-2xl font-bold text-gray-900">{asignatura?.horas_catedra ?? '—'}</p>
          </div>

          <!-- Horas Taller -->
          <div>
            <div class="flex items-center gap-2 mb-2">
              <Clock class="w-4 h-4 text-gray-500" />
              <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider"
                >Horas Taller</span
              >
            </div>
            <p class="text-2xl font-bold text-gray-900">{asignatura?.horas_taller ?? '—'}</p>
          </div>
        </div>
      </div>

      <!-- ── No programa ── -->
      {#if !programa}
        <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
          <BookOpen class="w-12 h-12 text-gray-300 mx-auto mb-4" />
          <h3 class="text-xl font-bold text-gray-700 mb-2">Programa no disponible</h3>
          <p class="text-gray-500">El programa de este curso aún no ha sido aprobado.</p>
        </div>
      {:else}
        <!-- ── Main Document Card ── -->
        <div class="bg-white rounded-2xl border border-gray-200 p-12 space-y-12">
          <!-- Descripción del Curso -->
          {#if descripcion}
            <section>
              <h2 class="text-2xl font-bold text-gray-900 mb-4">Descripción del Curso</h2>
              <p class="text-gray-700 leading-relaxed whitespace-pre-line">{descripcion}</p>
            </section>
          {/if}

          <!-- Unidades (Sección VI - BÁSICO) -->
          {#if unidades.length > 0}
            <section>
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Unidades</h2>
              <div class="space-y-6">
                {#each unidades as unidad}
                  <div class="border-l-4 border-indigo-600 pl-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                      Unidad {unidad.numero}: {unidad.titulo}
                    </h3>
                    {#if unidad.contenidos_items && unidad.contenidos_items.length > 0}
                      <div class="text-gray-700 space-y-2 mb-4">
                        {#each unidad.contenidos_items as contenido}
                          <p class="leading-relaxed whitespace-pre-line">{contenido.item}</p>
                        {/each}
                      </div>
                    {/if}
                    {#if unidad.resultados_aprendizaje && unidad.resultados_aprendizaje.length > 0}
                      <div class="mt-3 p-3 bg-indigo-50 rounded-lg border border-indigo-200">
                        <p class="text-xs font-semibold text-indigo-900 mb-2">
                          Resultados de Aprendizaje:
                        </p>
                        <ul class="space-y-1">
                          {#each unidad.resultados_aprendizaje as resultado}
                            <li class="text-sm text-indigo-900 flex gap-2">
                              <span class="flex-shrink-0">•</span>
                              <span>{resultado.resultado}</span>
                            </li>
                          {/each}
                        </ul>
                      </div>
                    {/if}
                  </div>
                {/each}
              </div>
            </section>
          {/if}

          <!-- Objetivos de Aprendizaje (solo en versión COMPLETA) -->
          {#if !isBasicoSyllabus && resultados.length > 0}
            <section>
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Objetivos de Aprendizaje</h2>
              <div class="space-y-4">
                {#each resultados as item, i}
                  <div class="flex gap-4">
                    <div
                      class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center mt-0.5"
                    >
                      <span class="text-sm font-bold text-indigo-600">{i + 1}</span>
                    </div>
                    <p class="text-gray-700 leading-relaxed">{item.resultado}</p>
                  </div>
                {/each}
              </div>
            </section>
          {/if}

          <!-- Competencias a Desarrollar (solo en versión COMPLETA) -->
          {#if !isBasicoSyllabus && todasCompetencias.length > 0}
            <section>
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Competencias a Desarrollar</h2>
              <div class="grid grid-cols-2 gap-4">
                {#each todasCompetencias.slice(0, 8) as comp, i}
                  <div class="p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-start gap-3">
                      <div
                        class="w-2 h-2 rounded-full mt-2 flex-shrink-0 {COMP_COLORS[
                          i % COMP_COLORS.length
                        ]}"
                      ></div>
                      <div>
                        <h4 class="font-semibold text-gray-900 mb-1">{comp.titulo}</h4>
                        {#if comp.descripcion}
                          <p class="text-sm text-gray-600">{comp.descripcion}</p>
                        {/if}
                      </div>
                    </div>
                  </div>
                {/each}
              </div>
            </section>
          {/if}

          <!-- Metodología de Evaluación -->
          {#if componentes.length > 0}
            <section>
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Metodología de Evaluación</h2>

              <div class="grid grid-cols-2 gap-8 items-center">
                <!-- SVG Donut Chart -->
                <div class="flex justify-center">
                  <div class="relative">
                    <svg width="260" height="260" viewBox="0 0 260 260">
                      <g transform="translate(130,130)">
                        {#each donutSegments as seg}
                          <circle
                            r={R}
                            cx="0"
                            cy="0"
                            fill="none"
                            stroke={seg.color}
                            stroke-width="30"
                            stroke-dasharray={seg.dashArray}
                            stroke-dashoffset={seg.dashOffset}
                          />
                        {/each}
                      </g>
                      <!-- Center label -->
                      <text
                        x="130"
                        y="124"
                        text-anchor="middle"
                        font-size="28"
                        font-weight="700"
                        fill="#111827">100%</text
                      >
                      <text x="130" y="148" text-anchor="middle" font-size="13" fill="#6B7280"
                        >Total</text
                      >
                    </svg>
                  </div>
                </div>

                <!-- Breakdown bars -->
                <div class="space-y-5">
                  {#each donutSegments as seg}
                    <div>
                      <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-3">
                          <div class="w-4 h-4 rounded" style="background-color: {seg.color}"></div>
                          <span class="font-semibold text-gray-900">{seg.label}</span>
                        </div>
                        <span class="text-2xl font-bold text-gray-900">{seg.porcentaje}%</span>
                      </div>
                      <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                        <div
                          class="h-full rounded-full"
                          style="width: {seg.porcentaje}%; background-color: {seg.color}"
                        ></div>
                      </div>
                    </div>
                  {/each}
                </div>
              </div>

              <!-- Normativa -->
              {#if normativa}
                <div class="mt-8 p-5 bg-amber-50 border border-amber-200 rounded-xl">
                  <p class="text-sm text-gray-700">
                    <strong class="text-gray-900">Requisito de Aprobación:</strong>
                    {normativa}
                  </p>
                </div>
              {/if}
            </section>
          {/if}

          <!-- Bibliografía -->
          {#if recursos.length > 0}
            <section>
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Bibliografía</h2>
              <div class="space-y-3">
                {#each recursos as recurso}
                  <div
                    class="flex items-start gap-4 p-4 border border-gray-200 rounded-xl hover:border-indigo-200 hover:bg-indigo-50/30 transition-all group"
                  >
                    <div
                      class="w-10 h-10 rounded-lg bg-gray-100 group-hover:bg-indigo-100 flex items-center justify-center flex-shrink-0 transition-colors"
                    >
                      <BookOpen
                        class="w-5 h-5 text-gray-600 group-hover:text-indigo-600 transition-colors"
                      />
                    </div>
                    <div class="flex-1 min-w-0">
                      <h4 class="font-semibold text-gray-900 mb-1">{recurso.descripcion}</h4>
                      <span
                        class="inline-block px-2 py-1 bg-gray-100 text-xs font-medium text-gray-700 rounded"
                        >{recurso.tipo}</span
                      >
                    </div>
                    {#if recurso.link}
                      <a
                        href={recurso.link}
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex-shrink-0 p-2 rounded-lg hover:bg-indigo-100 text-gray-600 hover:text-indigo-600 transition-colors"
                      >
                        <ExternalLink class="w-5 h-5" />
                      </a>
                    {/if}
                  </div>
                {/each}
              </div>
            </section>
          {/if}
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
          <p>
            Programa aprobado por el Consejo de Escuela · Versión {programa.version_programa}
            {#if programa.fecha_creacion}
              · {formatDate(programa.fecha_creacion)}
            {/if}
          </p>
          {#if programa.creado_por}
            <p class="mt-1">Elaborado por: {programa.creado_por}</p>
          {/if}
        </div>
      {/if}
    </div>
