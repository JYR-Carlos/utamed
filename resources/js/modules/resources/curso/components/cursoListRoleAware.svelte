<script lang="ts">
  /**
   * Componente: Lista de Cursos Role-Aware
   *
   * Vista compartida para docente, alumno y ayudante.
   * Se adapta según:
   * - Rol del usuario
   * - Permisos disponibles
   * - Datos del curso
   *
   * Props:
   * - cursosData: any[] - Array de cursos (flat o agrupados por semestre)
   * - viewMode: 'grid' | 'list' - Modo de vista persistente
   * - showTeamAction: boolean - Mostrar botón de equipo
   * - showSyllabusAction: boolean - Mostrar botón de programa
   * - showCalificationProgress: boolean - Mostrar progreso de calificaciones (docente)
   * - onViewModeChange: (mode: 'grid' | 'list') => void
   * - onTeam: (curso: any) => void
   * - onSyllabus: (curso: any) => void
   * - onCourseClick: (curso: any) => void - Click en curso para detalles
   * - groupBySemestre: boolean - Agrupar por semestre (para alumno)
   * - mode: 'admin' | 'docente' | 'alumno' | 'ayudante'
   */
  import { LayoutGrid, List, Users, BookOpenCheck } from 'lucide-svelte';
  import type { Curso } from '../types/curso.types';

  interface Props {
    cursosData?: any[];
    viewMode?: 'grid' | 'list';
    showTeamAction?: boolean;
    showSyllabusAction?: boolean;
    showCalificationProgress?: boolean;
    groupBySemestre?: boolean;
    mode?: 'docente' | 'alumno' | 'ayudante';
    onViewModeChange?: (mode: 'grid' | 'list') => void;
    onTeam?: (curso: any) => void;
    onSyllabus?: (curso: any) => void;
    onCourseClick?: (curso: any) => void;
  }

  let {
    cursosData = [],
    viewMode = $bindable('grid'),
    showTeamAction = false,
    showSyllabusAction = false,
    showCalificationProgress = false,
    groupBySemestre = false,
    mode = 'docente',
    onViewModeChange = () => {},
    onTeam = () => {},
    onSyllabus = () => {},
    onCourseClick = () => {},
  }: Props = $props();

  function getCalificacionProgress(curso: any): number {
    if (!showCalificationProgress) return 0;
    const total = curso.total_estudiantes || 0;
    const pendientes = curso.pendientes_calificar ?? 0;
    if (total === 0) return 100;
    return Math.round(((total - pendientes) / total) * 100);
  }

  function handleViewModeChange(mode: 'grid' | 'list') {
    viewMode = mode;
    onViewModeChange(mode);
  }

  // Agrupar cursos por semestre si es necesario
  const cursosAgrupados = $derived(
    groupBySemestre
      ? {
          semestre1: cursosData.filter((c) => (c.semestre_real ?? 1) === 1),
          semestre2: cursosData.filter((c) => (c.semestre_real ?? 1) === 2),
        }
      : null,
  );
</script>

<div class="space-y-6">
  <!-- Header con toggle de vista -->
  <div class="flex items-start justify-between">
    <div>
      <h1 class="text-3xl font-bold tracking-tight text-slate-900">Mis Cursos</h1>
      <p class="text-sm text-slate-500 mt-1">
        {cursosData.length} asignaturas
      </p>
    </div>
    <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
      <button
        onclick={() => handleViewModeChange('grid')}
        class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors {viewMode ===
        'grid'
          ? 'bg-slate-100 text-slate-900'
          : 'text-slate-500 hover:text-slate-700'}"
        aria-label="Vista grilla"
      >
        <LayoutGrid class="w-4 h-4" />
        Grilla
      </button>
      <button
        onclick={() => handleViewModeChange('list')}
        class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors {viewMode ===
        'list'
          ? 'bg-slate-100 text-slate-900'
          : 'text-slate-500 hover:text-slate-700'}"
        aria-label="Vista lista"
      >
        <List class="w-4 h-4" />
        Lista
      </button>
    </div>
  </div>

  {#if viewMode === 'grid'}
    {#if cursosAgrupados}
      <!-- Vista de Grilla con Semesters -->
      {#if cursosAgrupados.semestre1.length > 0}
        <div>
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Primer Semestre</h2>
          <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {#each cursosAgrupados.semestre1 as curso}
              {@const progress = getCalificacionProgress(curso)}
              <div
                class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col cursor-pointer"
                onclick={() => onCourseClick(curso)}
                onkeydown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onCourseClick(curso);
                  }
                }}
                role="button"
                tabindex="0"
              >
                <div class="flex items-start justify-between mb-3">
                  <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-slate-900 truncate">{curso.nombre}</h3>
                    <p class="text-xs text-slate-500">{curso.cod_curso}</p>
                  </div>
                </div>

                <p class="text-xs text-slate-500 mb-4 truncate">
                  {curso.carrera_nombre || 'Sin carrera'}
                </p>

                <!-- Stats -->
                <div class="mb-4 space-y-2">
                  {#if curso.total_estudiantes}
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-600">Estudiantes</span>
                      <span class="font-semibold text-sm text-slate-900"
                        >{curso.total_estudiantes}</span
                      >
                    </div>
                  {/if}
                  {#if showCalificationProgress && curso.pendientes_calificar}
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-600">Por calificar</span>
                      <span class="font-semibold text-sm text-amber-600"
                        >{curso.pendientes_calificar}</span
                      >
                    </div>
                  {/if}
                </div>

                <!-- Barra de progreso -->
                {#if showCalificationProgress}
                  <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-xs font-medium text-slate-600">Calificaciones</span>
                      <span class="text-xs text-slate-600">{progress}%</span>
                    </div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        class="h-full transition-all {progress >= 100
                          ? 'bg-emerald-500'
                          : 'bg-blue-500'}"
                        style={`width: ${progress}%`}
                      ></div>
                    </div>
                  </div>
                {/if}

                <!-- Actions -->
                <div class="flex items-center gap-2 mt-auto">
                  {#if showTeamAction}
                    <button
                      onclick={(e) => {
                        e.stopPropagation();
                        onTeam(curso);
                      }}
                      class="flex-1 px-3 py-2 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition"
                    >
                      <Users class="w-3.5 h-3.5 inline mr-1" />
                      Equipo
                    </button>
                  {/if}
                  {#if showSyllabusAction}
                    <button
                      onclick={(e) => {
                        e.stopPropagation();
                        onSyllabus(curso);
                      }}
                      class="flex-1 px-3 py-2 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition"
                    >
                      <BookOpenCheck class="w-3.5 h-3.5 inline mr-1" />
                      Programa
                    </button>
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        </div>
      {/if}

      {#if cursosAgrupados.semestre2.length > 0}
        <div>
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Segundo Semestre</h2>
          <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {#each cursosAgrupados.semestre2 as curso}
              {@const progress = getCalificacionProgress(curso)}
              <div
                class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col cursor-pointer"
                onclick={() => onCourseClick(curso)}
                onkeydown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onCourseClick(curso);
                  }
                }}
                role="button"
                tabindex="0"
              >
                <div class="flex items-start justify-between mb-3">
                  <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-slate-900 truncate">{curso.nombre}</h3>
                    <p class="text-xs text-slate-500">{curso.cod_curso}</p>
                  </div>
                </div>

                <p class="text-xs text-slate-500 mb-4 truncate">
                  {curso.carrera_nombre || 'Sin carrera'}
                </p>

                <!-- Stats -->
                <div class="mb-4 space-y-2">
                  {#if curso.total_estudiantes}
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-600">Estudiantes</span>
                      <span class="font-semibold text-sm text-slate-900"
                        >{curso.total_estudiantes}</span
                      >
                    </div>
                  {/if}
                  {#if showCalificationProgress && curso.pendientes_calificar}
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-600">Por calificar</span>
                      <span class="font-semibold text-sm text-amber-600"
                        >{curso.pendientes_calificar}</span
                      >
                    </div>
                  {/if}
                </div>

                <!-- Barra de progreso -->
                {#if showCalificationProgress}
                  <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-xs font-medium text-slate-600">Calificaciones</span>
                      <span class="text-xs text-slate-600">{progress}%</span>
                    </div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        class="h-full transition-all {progress >= 100
                          ? 'bg-emerald-500'
                          : 'bg-blue-500'}"
                        style={`width: ${progress}%`}
                      ></div>
                    </div>
                  </div>
                {/if}

                <!-- Actions -->
                <div class="flex items-center gap-2 mt-auto">
                  {#if showTeamAction}
                    <button
                      onclick={(e) => {
                        e.stopPropagation();
                        onTeam(curso);
                      }}
                      class="flex-1 px-3 py-2 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition"
                    >
                      <Users class="w-3.5 h-3.5 inline mr-1" />
                      Equipo
                    </button>
                  {/if}
                  {#if showSyllabusAction}
                    <button
                      onclick={(e) => {
                        e.stopPropagation();
                        onSyllabus(curso);
                      }}
                      class="flex-1 px-3 py-2 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition"
                    >
                      <BookOpenCheck class="w-3.5 h-3.5 inline mr-1" />
                      Programa
                    </button>
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        </div>
      {/if}
    {:else}
      <!-- Vista de Grilla sin Semesters -->
      {#if cursosData.length === 0}
        <div class="text-center py-12 text-slate-500">
          <p>No tienes cursos asignados</p>
        </div>
      {:else}
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {#each cursosData as curso}
            {@const progress = getCalificacionProgress(curso)}
            <div
              class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col cursor-pointer"
              onclick={() => onCourseClick(curso)}
              onkeydown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  onCourseClick(curso);
                }
              }}
              role="button"
              tabindex="0"
            >
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1 min-w-0">
                  <h3 class="font-semibold text-slate-900 truncate">{curso.nombre}</h3>
                  <p class="text-xs text-slate-500">{curso.cod_curso}</p>
                </div>
              </div>

              <p class="text-xs text-slate-500 mb-4 truncate">
                {curso.carrera_nombre || 'Sin carrera'}
              </p>

              <!-- Stats -->
              <div class="mb-4 space-y-2">
                {#if curso.total_estudiantes}
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-600">Estudiantes</span>
                    <span class="font-semibold text-sm text-slate-900"
                      >{curso.total_estudiantes}</span
                    >
                  </div>
                {/if}
                {#if showCalificationProgress && curso.pendientes_calificar}
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-600">Por calificar</span>
                    <span class="font-semibold text-sm text-amber-600"
                      >{curso.pendientes_calificar}</span
                    >
                  </div>
                {/if}
              </div>

              <!-- Barra de progreso -->
              {#if showCalificationProgress}
                <div class="mb-4">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-600">Calificaciones</span>
                    <span class="text-xs text-slate-600">{progress}%</span>
                  </div>
                  <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div
                      class="h-full transition-all {progress >= 100
                        ? 'bg-emerald-500'
                        : 'bg-blue-500'}"
                      style={`width: ${progress}%`}
                    ></div>
                  </div>
                </div>
              {/if}

              <!-- Actions -->
              <div class="flex items-center gap-2 mt-auto">
                {#if showTeamAction}
                  <button
                    onclick={(e) => {
                      e.stopPropagation();
                      onTeam(curso);
                    }}
                    class="flex-1 px-3 py-2 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition"
                  >
                    <Users class="w-3.5 h-3.5 inline mr-1" />
                    Equipo
                  </button>
                {/if}
                {#if showSyllabusAction}
                  <button
                    onclick={(e) => {
                      e.stopPropagation();
                      onSyllabus(curso);
                    }}
                    class="flex-1 px-3 py-2 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition"
                  >
                    <BookOpenCheck class="w-3.5 h-3.5 inline mr-1" />
                    Programa
                  </button>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      {/if}
    {/if}
  {:else}
    <!-- Vista de Lista -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th
                class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide"
                >Curso</th
              >
              <th
                class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide"
                >Carrera</th
              >
              <th
                class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide"
                >Estudiantes</th
              >
              {#if showCalificationProgress}
                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide w-40"
                  >Calificaciones</th
                >
              {/if}
              {#if showTeamAction || showSyllabusAction}
                <th
                  class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide"
                  >Acciones</th
                >
              {/if}
            </tr>
          </thead>
          <tbody>
            {#if cursosData.length === 0}
              <tr>
                <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-sm">
                  No tienes cursos asignados
                </td>
              </tr>
            {:else}
              {#each cursosData as curso}
                {@const progress = getCalificacionProgress(curso)}
                <tr
                  class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer"
                  onclick={() => onCourseClick(curso)}
                  onkeydown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      onCourseClick(curso);
                    }
                  }}
                  role="button"
                  tabindex="0"
                >
                  <td class="px-6 py-4">
                    <div>
                      <p class="font-medium text-slate-900">{curso.nombre}</p>
                      <p class="text-sm text-slate-500">{curso.cod_curso}</p>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-slate-700">
                    {curso.carrera_nombre || '-'}
                  </td>
                  <td class="px-6 py-4 text-sm text-slate-700">
                    {curso.total_estudiantes || '-'}
                  </td>
                  {#if showCalificationProgress}
                    <td class="px-6 py-4">
                      <div class="space-y-2">
                        <div class="flex items-center gap-2">
                          <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                              class="h-full transition-all {progress >= 100
                                ? 'bg-emerald-500'
                                : 'bg-blue-500'}"
                              style={`width: ${progress}%`}
                            ></div>
                          </div>
                          <span class="text-xs text-slate-600 w-8">{progress}%</span>
                        </div>
                      </div>
                    </td>
                  {/if}
                  {#if showTeamAction || showSyllabusAction}
                    <td
                      class="px-6 py-4"
                      onclick={(e) => {
                        e.stopPropagation();
                      }}
                    >
                      <div class="flex items-center gap-2">
                        {#if showTeamAction}
                          <button
                            onclick={() => onTeam(curso)}
                            class="px-3 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition"
                          >
                            Equipo
                          </button>
                        {/if}
                        {#if showSyllabusAction}
                          <button
                            onclick={() => onSyllabus(curso)}
                            class="px-3 py-1 text-xs font-medium bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100 transition"
                          >
                            Programa
                          </button>
                        {/if}
                      </div>
                    </td>
                  {/if}
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>
    </div>
  {/if}
</div>
