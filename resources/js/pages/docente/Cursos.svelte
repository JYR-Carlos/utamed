<script lang="ts">
  /**
   * Página de gestión de cursos para docentes.
   *
   * Lista todos los cursos asignados al docente y permite:
   * - Ver información de cada curso (asignatura, carrera, fechas)
   * - Gestionar equipos de cátedra (docentes auxiliares, ayudantes)
   * - Asignar roles y permisos a miembros del equipo
   * - Acceso a funciones de gestión de actividades y calificaciones
   *
   * Tablas relacionadas:
   * - curso.curso: Cursos ofertados
   * - curso.seccion: Secciones donde el docente es responsable
   * - usuario.usuario_rol_asignación: Roles en contexto del curso
   * - usuario.usuario_permiso_especial: Permisos especiales
   */
  import { router, Link, usePage } from '@inertiajs/svelte';
  import { toast } from 'svelte-sonner';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import {
    BookOpen,
    BookOpenCheck,
    Loader2,
    CheckCircle2,
    FilePlus,
    Info,
    Users,
    MoreVertical,
    UserCheck,
    Bell,
    ArrowRight,
    LayoutGrid,
    List,
  } from 'lucide-svelte';
  import { Badge } from '@/components/ui/badge';
  import * as Tabs from '@/components/ui/tabs';
  import type { Programa } from '@/types/admin.types';

  /**
   * Props recibidas del servidor.
   */
  export let cursosSemestre1: any[] = [];
  export let cursosSemestre2: any[] = [];
  export let availableRoles: any[] = [];
  export let availablePermissions: Record<string, any[]> = {};

  // Unificar cursos para la tabla
  const cursosAll = [...cursosSemestre1, ...cursosSemestre2];

  // Toggle persistente (localStorage)
  import { onMount } from 'svelte';
  let viewMode = 'grid';
  onMount(() => {
    const saved = localStorage.getItem('docente-cursos-view');
    if (saved === 'list' || saved === 'grid') viewMode = saved;
  });
  $: if (viewMode) localStorage.setItem('docente-cursos-view', viewMode);

  let isTeamModalOpen = false;
  let isSyllabusModalOpen = false;
  let selectedCurso: any = null;

  function getCalificacionProgress(curso: any): number {
    const total = curso.total_estudiantes || 0;
    const pendientes = curso.pendientes_calificar ?? 0;
    if (total === 0) return 100;
    return Math.round(((total - pendientes) / total) * 100);
  }

  function openTeamModal(curso: any) {
    selectedCurso = curso;
    isTeamModalOpen = true;
  }

  function openSyllabusModal(curso: any) {
    selectedCurso = curso;
    isSyllabusModalOpen = true;
  }

  function closeSyllabusModal() {
    isSyllabusModalOpen = false;
    selectedCurso = null;
  }

  function handleSyllabusSuccess(programa: Programa) {
    closeSyllabusModal();
    toast.success('Programa generado correctamente');
    router.reload({ only: ['cursosSemestre1', 'cursosSemestre2'] });
  }

  // Mapear cursos con propiedades formateadas
  $: cursosFormateados = cursosAll.map((curso) => ({
    ...curso,
    tieneAlerta: (curso.pendientes_calificar ?? 0) > 0,
    estadoFormato: curso.estado_operativo || 'Sin estado',
  }));
</script>

<DocenteLayout>
  <div class="space-y-6">
    <!-- Header con toggle -->
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Mis Cursos</h1>
        <p class="text-sm text-slate-500 mt-1">
          {cursosAll.length} asignaturas
        </p>
      </div>
      <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
        <button
          onclick={() => (viewMode = 'grid')}
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
          onclick={() => (viewMode = 'list')}
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
      <!-- Vista de Grilla -->
      <Tabs.Root value="semestre1" class="w-full">
        <Tabs.List class="grid w-full grid-cols-2 mb-6">
          <Tabs.Trigger
            value="semestre1"
            class="data-[state=active]:bg-blue-50 data-[state=active]:text-blue-700"
          >
            Primer Semestre
            <Badge variant="secondary" class="ml-2 bg-blue-100 text-blue-800">
              {cursosSemestre1.length}
            </Badge>
          </Tabs.Trigger>
          <Tabs.Trigger
            value="semestre2"
            class="data-[state=active]:bg-indigo-50 data-[state=active]:text-indigo-700"
          >
            Segundo Semestre
            <Badge variant="secondary" class="ml-2 bg-indigo-100 text-indigo-800">
              {cursosSemestre2.length}
            </Badge>
          </Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="semestre1">
          {#if cursosSemestre1.length === 0}
            <div class="text-center py-12 text-slate-500">
              <p>No tienes cursos asignados en el primer semestre</p>
            </div>
          {:else}
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {#each cursosSemestre1 as curso}
                {@const progress = getCalificacionProgress(curso)}
                <div
                  class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col"
                >
                  <!-- Header con nombre y código -->
                  <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                      <p class="font-bold text-slate-900 text-sm leading-snug">
                        {curso.nombre}
                      </p>
                      <p class="text-xs text-slate-400 mt-0.5">{curso.cod_asignatura}</p>
                    </div>
                  </div>

                  <!-- Información de carrera -->
                  <p class="text-xs text-slate-500 mb-4 truncate">{curso.carrera_nombre || 'Sin carrera'}</p>

                  <!-- Stats -->
                  <div class="mb-4 space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-500">Estudiantes</span>
                      <span class="text-sm font-semibold text-slate-900">{curso.total_estudiantes}</span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-500">Por calificar</span>
                      <span class="text-sm font-semibold {(curso.pendientes_calificar ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'}">
                        {curso.pendientes_calificar ?? 0}
                      </span>
                    </div>
                  </div>

                  <!-- Barra de progreso de calificaciones -->
                  <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                      <span class="text-xs text-slate-400">Calificaciones</span>
                      <span class="text-xs font-bold text-slate-700">{progress}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        class="h-full bg-indigo-600 rounded-full transition-all"
                        style="width: {progress}%"
                      ></div>
                    </div>
                  </div>

                  <!-- Estado -->
                  {#if curso.estado_operativo}
                    <div class="mb-4">
                      <span
                        class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold"
                        style="background: {curso.estado_operativo_color ?? '#F3F4F6'}; color: {curso.estado_operativo_text ?? '#256029'}"
                      >
                        {curso.estado_operativo_icon ?? '🟢'}
                        {curso.estado_operativo}
                      </span>
                    </div>
                  {/if}

                  <!-- Actions -->
                  <div class="flex items-center gap-2 mt-auto">
                    <button
                      type="button"
                      onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                      class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors py-2 px-3 rounded-lg hover:bg-indigo-50"
                    >
                      Entrar
                      <ArrowRight class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              {/each}
            </div>
          {/if}
        </Tabs.Content>
        <Tabs.Content value="semestre2">
          {#if cursosSemestre2.length === 0}
            <div class="text-center py-12 text-slate-500">
              <p>No tienes cursos asignados en el segundo semestre</p>
            </div>
          {:else}
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {#each cursosSemestre2 as curso}
                {@const progress = getCalificacionProgress(curso)}
                <div
                  class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col"
                >
                  <!-- Header con nombre y código -->
                  <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                      <p class="font-bold text-slate-900 text-sm leading-snug">
                        {curso.nombre}
                      </p>
                      <p class="text-xs text-slate-400 mt-0.5">{curso.cod_asignatura}</p>
                    </div>
                  </div>

                  <!-- Información de carrera -->
                  <p class="text-xs text-slate-500 mb-4 truncate">{curso.carrera_nombre || 'Sin carrera'}</p>

                  <!-- Stats -->
                  <div class="mb-4 space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-500">Estudiantes</span>
                      <span class="text-sm font-semibold text-slate-900">{curso.total_estudiantes}</span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-slate-500">Por calificar</span>
                      <span class="text-sm font-semibold {(curso.pendientes_calificar ?? 0) > 0 ? 'text-red-600' : 'text-emerald-600'}">
                        {curso.pendientes_calificar ?? 0}
                      </span>
                    </div>
                  </div>

                  <!-- Barra de progreso de calificaciones -->
                  <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                      <span class="text-xs text-slate-400">Calificaciones</span>
                      <span class="text-xs font-bold text-slate-700">{progress}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        class="h-full bg-indigo-600 rounded-full transition-all"
                        style="width: {progress}%"
                      ></div>
                    </div>
                  </div>

                  <!-- Estado -->
                  {#if curso.estado_operativo}
                    <div class="mb-4">
                      <span
                        class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold"
                        style="background: {curso.estado_operativo_color ?? '#F3F4F6'}; color: {curso.estado_operativo_text ?? '#256029'}"
                      >
                        {curso.estado_operativo_icon ?? '🟢'}
                        {curso.estado_operativo}
                      </span>
                    </div>
                  {/if}

                  <!-- Actions -->
                  <div class="flex items-center gap-2 mt-auto">
                    <button
                      type="button"
                      onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                      class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors py-2 px-3 rounded-lg hover:bg-indigo-50"
                    >
                      Entrar
                      <ArrowRight class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              {/each}
            </div>
          {/if}
        </Tabs.Content>
      </Tabs.Root>
    {:else}
      <!-- Vista de Lista -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide">Curso</th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide">Carrera</th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide">Estudiantes</th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide w-40">Calificaciones</th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-600 tracking-wide">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {#if cursosFormateados.length === 0}
                <tr>
                  <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-sm">
                    No hay cursos registrados
                  </td>
                </tr>
              {:else}
                {#each cursosFormateados as curso}
                  {@const progress = getCalificacionProgress(curso)}
                  <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-3 text-sm">
                      <span class="font-semibold text-slate-900 block">{curso.nombre}</span>
                      <span class="text-xs text-slate-400">{curso.cod_asignatura}</span>
                    </td>
                    <td class="px-6 py-3 text-sm text-slate-600">{curso.carrera_nombre || '—'}</td>
                    <td class="px-6 py-3 text-sm">
                      <div class="flex items-center gap-2">
                        <Users class="w-4 h-4 text-slate-400" />
                        <span class="font-medium text-slate-900">{curso.total_estudiantes}</span>
                      </div>
                    </td>
                    <td class="px-6 py-3 text-sm">
                      <div class="flex items-center gap-3 min-w-max">
                        <div class="flex-1 w-32">
                          <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-slate-500">{progress}%</span>
                          </div>
                          <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div
                              class="h-full bg-indigo-600 rounded-full transition-all"
                              style="width: {progress}%"
                            ></div>
                          </div>
                        </div>
                        {#if (curso.pendientes_calificar ?? 0) > 0}
                          <span class="text-xs font-semibold text-red-600 whitespace-nowrap">
                            {curso.pendientes_calificar} por calificar
                          </span>
                        {/if}
                      </div>
                    </td>
                    <td class="px-6 py-3 text-sm">
                      {#if curso.estado_operativo}
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold" style="background: {curso.estado_operativo_color ?? '#F3F4F6'}; color: {curso.estado_operativo_text ?? '#256029'}">
                          {curso.estado_operativo_icon ?? '🟢'}
                          {curso.estado_operativo}
                        </span>
                      {:else}
                        <span class="text-slate-400 text-xs">—</span>
                      {/if}
                    </td>
                    <td class="px-6 py-3 text-sm flex items-center gap-2">
                      <button
                        type="button"
                        onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                        class="px-3 py-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 text-sm font-medium transition-colors"
                      >
                        Entrar
                      </button>
                      <button
                        type="button"
                        onclick={() => openTeamModal(curso)}
                        class="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-medium transition-colors"
                      >
                        Equipo
                      </button>
                    </td>
                  </tr>
                {/each}
              {/if}
            </tbody>
          </table>
        </div>
        <!-- Footer -->
        {#if cursosFormateados.length > 0}
          <div class="border-t border-slate-100 px-6 py-3 flex items-center justify-between bg-slate-50/50">
            <span class="text-xs text-slate-500">
              Mostrando {cursosFormateados.length} de {cursosFormateados.length} cursos
            </span>
          </div>
        {/if}
      </div>
    {/if}
  </div>

  <!-- Modal de Equipo -->
  {#if selectedCurso}
    <CourseTeamModal
      bind:isOpen={isTeamModalOpen}
      onClose={() => {
        isTeamModalOpen = false;
        selectedCurso = null;
      }}
      curso={selectedCurso}
      urlPrefix="docente"
    />
  {/if}

  <!-- Modal de Programa -->
  {#if isSyllabusModalOpen}
    <SyllabusModal
      bind:isOpen={isSyllabusModalOpen}
      curso={selectedCurso}
      onClose={closeSyllabusModal}
      onSuccess={handleSyllabusSuccess}
    />
  {/if}
</DocenteLayout>
