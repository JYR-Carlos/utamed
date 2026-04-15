<script lang="ts">
  /**
   * Página de detalles de curso para docentes.
   *
   * Vista bifurcada:
   * - Titular: panorámica completa del curso con estadísticas y resumen de componentes.
   *   La gestión de docentes y permisos se hace desde /cursos/{id}/docentes.
   * - Colegiado: vista centrada en su componente y sus alumnos.
   */
  import { router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Button } from '@/components/ui/button';
  import * as Card from '@/components/ui/card';
  import { Badge } from '@/components/ui/badge';
  import {
    ArrowLeft,
    Calendar,
    BookOpen,
    Users,
    GraduationCap,
    Building2,
    FileText,
    BookOpenCheck,
    Crown,
    UserCheck,
    UsersRound,
    Settings,
    ChevronRight,
    Hash,
    Layers,
    Shield,
  } from 'lucide-svelte';
  import { Separator } from '@/components/ui/separator';
  import {
    SyllabusPermisosModal,
    ComponentePermisosModal,
  } from '@/modules/resources/curso/components';

  interface Componente {
    id_componente: number;
    tipo_componente: string;
    es_titular: boolean;
    total_docentes: number;
    total_estudiantes: number;
  }

  interface EstudianteComponente {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    estudiante: {
      id_estudiante: number;
      nombre: string;
      username: string;
    };
  }

  interface DocenteComponenteCurso {
    id_docente_componente: number;
    id_docente: number;
    id_usuario: number;
    nombre: string;
    es_titular: boolean;
  }

  interface ComponenteCurso {
    id_componente: number;
    tipo_componente: string;
    total_estudiantes: number;
    docentes: DocenteComponenteCurso[];
  }

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    fecha_inicio: string;
    fecha_fin: string;
    agno_real: number;
    semestre_real: number;
    estado_interno: string;
    es_plantilla: boolean;
    tiene_programa: boolean;
    es_titular_curso: boolean;
    asignatura: {
      nombre: string;
      cod_asignatura: string;
      descripcion: string;
    };
    plan: {
      nombre: string;
      carrera: string;
    };
    secciones: any[];
    total_estudiantes: number;
  }

  interface Props {
    curso: Curso;
    mis_componentes: Componente[];
    mis_estudiantes: EstudianteComponente[];
    todos_componentes: ComponenteCurso[];
  }

  let { curso, mis_componentes, mis_estudiantes, todos_componentes = [] }: Props = $props();

  // Pestaña activa en la sección "Mi Grupo"
  let componenteActivo = $state<number | null>(null);

  // ─── Modales de permisos ───
  let showSyllabusPermisos = $state(false);
  let showComponentePermisos = $state(false);
  let componentePermisoId = $state<number>(0);
  let componentePermisoTipo = $state('');

  $effect.pre(() => {
    if (componenteActivo === null && mis_componentes.length > 0) {
      componenteActivo = mis_componentes[0].id_componente;
    }
  });

  const estudiantesActivos = $derived(
    mis_estudiantes.filter((e) => e.id_componente === componenteActivo),
  );

  const totalDocentesCurso = $derived(
    new Set(todos_componentes.flatMap((c) => c.docentes.map((d) => d.id_docente))).size,
  );

  function goBack() {
    router.visit('/docente/cursos');
  }

  function formatDate(dateString: string) {
    return new Date(dateString).toLocaleDateString('es-CL', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }
</script>

<DocenteLayout>
  <div class="space-y-8 pb-6">
    <!-- ─── Hero Header ─── -->
    <div
      class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 px-6 py-8 sm:px-8 sm:py-10"
    >
      <!-- Decorative grid -->
      <div
        class="pointer-events-none absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsMjU1LDI1NSwwLjAzKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-60"
      ></div>

      <div class="relative z-10">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400 mb-4">
          <button onclick={goBack} class="hover:text-white transition-colors">Mis Cursos</button>
          <ChevronRight class="h-3.5 w-3.5" />
          <span class="text-slate-200">{curso.cod_curso}</span>
        </nav>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div class="space-y-2">
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                {curso.nombre}
              </h1>
              {#if curso.es_titular_curso}
                <span
                  class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/20 px-3 py-1 text-xs font-semibold text-amber-300 ring-1 ring-amber-500/30"
                >
                  <Crown class="h-3 w-3" />
                  Titular
                </span>
              {:else}
                <span
                  class="inline-flex items-center gap-1.5 rounded-full bg-slate-500/20 px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-slate-500/30"
                >
                  <UserCheck class="h-3 w-3" />
                  Colaborador
                </span>
              {/if}
              <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {curso.es_plantilla
                  ? 'bg-slate-500/20 text-slate-300 ring-1 ring-slate-500/30'
                  : 'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/30'}"
              >
                {curso.es_plantilla ? 'Plantilla' : 'Activo'}
              </span>
            </div>
            <p class="text-sm text-slate-400">
              {curso.asignatura.nombre} · {curso.asignatura.cod_asignatura}
            </p>
            <p class="text-xs text-slate-500">
              {curso.plan.carrera} · {curso.plan.nombre}
            </p>
          </div>

          <!-- Header actions -->
          <div class="flex items-center gap-2 shrink-0">
            {#if curso.tiene_programa}
              <Button
                variant="secondary"
                onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
                class="gap-2 bg-white/10 text-white border-white/10 hover:bg-white/20"
              >
                <BookOpenCheck class="h-4 w-4" />
                Programa
              </Button>
            {/if}
            {#if curso.es_titular_curso}
              <Button
                variant="secondary"
                onclick={() => (showSyllabusPermisos = true)}
                class="gap-2 bg-white/10 text-white border-white/10 hover:bg-white/20"
              >
                <Shield class="h-4 w-4" />
                Permisos Syllabus
              </Button>
            {/if}
            <Button
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
              class="gap-2 bg-indigo-500 hover:bg-indigo-400 text-white"
            >
              <FileText class="h-4 w-4" />
              Actividades
            </Button>
          </div>
        </div>

        <!-- Stat pills -->
        <div class="flex flex-wrap items-center gap-3 mt-6 pt-5 border-t border-white/10">
          <div class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 ring-1 ring-white/10">
            <Calendar class="h-4 w-4 text-indigo-400" />
            <span class="text-sm text-slate-300">
              {curso.agno_real} · {curso.semestre_real === 1 ? '1er Sem.' : '2do Sem.'}
            </span>
          </div>
          <div class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 ring-1 ring-white/10">
            <Users class="h-4 w-4 text-blue-400" />
            <span class="text-sm font-semibold text-white">{curso.total_estudiantes}</span>
            <span class="text-sm text-slate-400">estudiantes</span>
          </div>
          {#if curso.es_titular_curso && todos_componentes.length > 0}
            <div
              class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 ring-1 ring-white/10"
            >
              <Layers class="h-4 w-4 text-purple-400" />
              <span class="text-sm font-semibold text-white">{todos_componentes.length}</span>
              <span class="text-sm text-slate-400">componentes</span>
            </div>
            <div
              class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 ring-1 ring-white/10"
            >
              <UsersRound class="h-4 w-4 text-emerald-400" />
              <span class="text-sm font-semibold text-white">{totalDocentesCurso}</span>
              <span class="text-sm text-slate-400">docentes</span>
            </div>
          {/if}
          <div class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 ring-1 ring-white/10">
            <Hash class="h-4 w-4 text-slate-400" />
            <span class="text-sm text-slate-300">{curso.cod_curso}</span>
          </div>
        </div>
      </div>
    </div>

    {#if curso.es_titular_curso}
      <!-- ══════════════════════════════════════════════════════════════════
           VISTA TITULAR
           ══════════════════════════════════════════════════════════════════ -->

      <!-- Información del curso -->
      <div class="grid gap-5 lg:grid-cols-5">
        <!-- Info general — 3 cols -->
        <Card.Root class="lg:col-span-3 shadow-sm border-slate-200/80">
          <Card.Header class="pb-4">
            <Card.Title class="text-base font-semibold text-slate-800 flex items-center gap-2">
              <div
                class="flex items-center justify-center h-8 w-8 rounded-lg bg-blue-50 text-blue-600"
              >
                <BookOpen class="h-4 w-4" />
              </div>
              Información General
            </Card.Title>
          </Card.Header>
          <Card.Content>
            <div class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">
                  Asignatura
                </p>
                <p class="text-sm font-semibold text-slate-900">{curso.asignatura.nombre}</p>
                <p class="text-xs text-slate-500">{curso.asignatura.cod_asignatura}</p>
              </div>
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">
                  Código Curso
                </p>
                <p class="text-sm font-semibold text-slate-900">{curso.cod_curso}</p>
              </div>
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">
                  Plan de Estudios
                </p>
                <p class="text-sm font-semibold text-slate-900">{curso.plan.nombre}</p>
              </div>
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Carrera</p>
                <div class="flex items-center gap-1.5">
                  <Building2 class="h-3.5 w-3.5 text-slate-400" />
                  <p class="text-sm font-semibold text-slate-900">{curso.plan.carrera}</p>
                </div>
              </div>
            </div>
            {#if curso.asignatura.descripcion}
              <Separator class="my-4" />
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">
                  Descripción
                </p>
                <p class="text-sm leading-relaxed text-slate-600">{curso.asignatura.descripcion}</p>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>

        <!-- Período — 2 cols -->
        <Card.Root class="lg:col-span-2 shadow-sm border-slate-200/80">
          <Card.Header class="pb-4">
            <Card.Title class="text-base font-semibold text-slate-800 flex items-center gap-2">
              <div
                class="flex items-center justify-center h-8 w-8 rounded-lg bg-indigo-50 text-indigo-600"
              >
                <Calendar class="h-4 w-4" />
              </div>
              Período Académico
            </Card.Title>
          </Card.Header>
          <Card.Content class="space-y-4">
            <div class="flex items-baseline justify-between">
              <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Año</span>
              <span class="text-2xl font-bold text-slate-900">{curso.agno_real}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium uppercase tracking-wider text-slate-400"
                >Semestre</span
              >
              <Badge variant="secondary" class="text-sm font-semibold">
                {curso.semestre_real === 1 ? '1er Semestre' : '2do Semestre'}
              </Badge>
            </div>
            <Separator />
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Inicio</p>
                <p class="text-sm text-slate-700">{formatDate(curso.fecha_inicio)}</p>
              </div>
              <div class="space-y-1">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Término</p>
                <p class="text-sm text-slate-700">{formatDate(curso.fecha_fin)}</p>
              </div>
            </div>
          </Card.Content>
        </Card.Root>
      </div>

      <!-- Docentes del Curso -->
      {#if todos_componentes.length > 0}
        <Card.Root class="shadow-sm border-slate-200/80">
          <Card.Header class="flex-row items-center justify-between space-y-0 pb-4">
            <div class="flex items-center gap-3">
              <div
                class="flex items-center justify-center h-8 w-8 rounded-lg bg-blue-50 text-blue-600"
              >
                <UsersRound class="h-4 w-4" />
              </div>
              <div>
                <Card.Title class="text-base font-semibold text-slate-800">
                  Docentes del Curso
                </Card.Title>
                <p class="text-xs text-slate-500 mt-0.5">
                  {todos_componentes.length} componente(s) · {totalDocentesCurso} docente(s)
                </p>
              </div>
            </div>
            <Button
              variant="outline"
              size="sm"
              onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/docentes`)}
              class="gap-2"
            >
              <Settings class="h-3.5 w-3.5" />
              Gestionar
            </Button>
          </Card.Header>
          <Card.Content>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {#each todos_componentes as comp}
                <div
                  class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 transition-colors hover:bg-slate-100/80"
                >
                  <div
                    class="flex items-center justify-center h-9 w-9 rounded-lg bg-indigo-100 text-indigo-600 text-xs font-bold shrink-0"
                  >
                    {comp.tipo_componente.slice(0, 3).toUpperCase()}
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-800 truncate">
                      {comp.tipo_componente}
                    </p>
                    <p class="text-xs text-slate-500">
                      {comp.docentes.length} doc. · {comp.total_estudiantes} est.
                    </p>
                  </div>
                  {#if comp.docentes.length > 1}
                    <button
                      onclick={() => {
                        componentePermisoId = comp.id_componente;
                        componentePermisoTipo = comp.tipo_componente;
                        showComponentePermisos = true;
                      }}
                      class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 rounded-lg hover:bg-indigo-100 text-slate-400 hover:text-indigo-600"
                      title="Permisos del componente"
                    >
                      <Shield class="h-4 w-4" />
                    </button>
                  {/if}
                </div>
              {/each}
            </div>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- Mi Grupo (titular) -->
      {#if mis_componentes.length > 0}
        <Card.Root class="shadow-sm border-slate-200/80">
          <Card.Header class="pb-4">
            <Card.Title class="text-base font-semibold text-slate-800 flex items-center gap-2">
              <div
                class="flex items-center justify-center h-8 w-8 rounded-lg bg-purple-50 text-purple-600"
              >
                <GraduationCap class="h-4 w-4" />
              </div>
              Mi Grupo
            </Card.Title>
            <p class="text-xs text-slate-500 mt-1">Estudiantes inscritos en tus componentes</p>
          </Card.Header>
          <Card.Content class="space-y-4">
            <!-- Component tabs -->
            {#if mis_componentes.length > 1}
              <div class="flex gap-1.5 p-1 bg-slate-100 rounded-xl overflow-x-auto">
                {#each mis_componentes as comp}
                  <button
                    onclick={() => (componenteActivo = comp.id_componente)}
                    class={`flex items-center gap-2 px-4 py-2 text-sm font-medium whitespace-nowrap rounded-lg transition-all ${
                      componenteActivo === comp.id_componente
                        ? 'bg-white text-indigo-700 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700 hover:bg-white/50'
                    }`}
                  >
                    {comp.tipo_componente}
                    {#if comp.es_titular}
                      <Crown class="h-3 w-3 text-amber-500" />
                    {/if}
                    <span
                      class="inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-slate-200/80 px-1.5 text-xs font-medium text-slate-600"
                    >
                      {comp.total_estudiantes}
                    </span>
                  </button>
                {/each}
              </div>
            {:else if mis_componentes.length === 1}
              <div class="flex items-center gap-3">
                <Badge variant="secondary" class="text-sm font-medium">
                  {mis_componentes[0].tipo_componente}
                </Badge>
                {#if mis_componentes[0].es_titular}
                  <span
                    class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full ring-1 ring-amber-200"
                  >
                    <Crown class="h-3 w-3" />
                    Titular
                  </span>
                {/if}
              </div>
            {/if}

            <!-- Students table -->
            {#if estudiantesActivos.length === 0}
              <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-full bg-slate-100 mb-3"
                >
                  <Users class="h-6 w-6" />
                </div>
                <p class="text-sm">Sin estudiantes inscritos aún</p>
              </div>
            {:else}
              <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-slate-50/80">
                      <th
                        class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >#</th
                      >
                      <th
                        class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >Estudiante</th
                      >
                      <th
                        class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >Usuario</th
                      >
                      <th
                        class="text-right py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >Nota</th
                      >
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    {#each estudiantesActivos as item, i}
                      <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3 px-4 text-slate-400 tabular-nums">{i + 1}</td>
                        <td class="py-3 px-4">
                          <div class="flex items-center gap-3">
                            <div
                              class="flex items-center justify-center h-8 w-8 rounded-full bg-gradient-to-br from-indigo-100 to-blue-100 text-xs font-bold text-indigo-600 shrink-0"
                            >
                              {item.estudiante.nombre.charAt(0)}
                            </div>
                            <span class="font-medium text-slate-900">{item.estudiante.nombre}</span>
                          </div>
                        </td>
                        <td class="py-3 px-4">
                          <span class="text-slate-500 font-mono text-xs"
                            >{item.estudiante.username}</span
                          >
                        </td>
                        <td class="py-3 px-4 text-right">
                          {#if item.nota_componente !== null}
                            <span
                              class={`inline-flex items-center justify-center h-7 min-w-10 rounded-lg text-xs font-bold ${item.nota_componente >= 4 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-red-50 text-red-600 ring-1 ring-red-200'}`}
                            >
                              {item.nota_componente}
                            </span>
                          {:else}
                            <span class="text-slate-300">—</span>
                          {/if}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-slate-400 text-right">
                {estudiantesActivos.length} estudiante(s) en total
              </p>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}
    {:else}
      <!-- ══════════════════════════════════════════════════════════════════
           VISTA COLEGIADO
           ══════════════════════════════════════════════════════════════════ -->

      <!-- Info compacta -->
      <Card.Root class="shadow-sm border-slate-200/80">
        <Card.Content class="py-4">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-1">
              <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Asignatura</p>
              <p class="text-sm font-semibold text-slate-900">{curso.asignatura.nombre}</p>
            </div>
            <div class="space-y-1">
              <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Plan</p>
              <p class="text-sm font-semibold text-slate-900">{curso.plan.nombre}</p>
            </div>
            <div class="space-y-1">
              <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Carrera</p>
              <p class="text-sm font-semibold text-slate-900">{curso.plan.carrera}</p>
            </div>
            <div class="space-y-1">
              <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Período</p>
              <p class="text-sm font-semibold text-slate-900">
                {curso.agno_real} — {curso.semestre_real === 1 ? '1er Sem.' : '2do Sem.'}
              </p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <!-- Mi Componente (colegiado) -->
      {#if mis_componentes.length > 0}
        <Card.Root class="shadow-sm border-slate-200/80">
          <Card.Header class="pb-4">
            <Card.Title class="text-base font-semibold text-slate-800 flex items-center gap-2">
              <div
                class="flex items-center justify-center h-8 w-8 rounded-lg bg-purple-50 text-purple-600"
              >
                <GraduationCap class="h-4 w-4" />
              </div>
              Mi Componente
            </Card.Title>
            <p class="text-xs text-slate-500 mt-1">Tu grupo de estudiantes</p>
          </Card.Header>
          <Card.Content class="space-y-4">
            <!-- Tabs -->
            {#if mis_componentes.length > 1}
              <div class="flex gap-1.5 p-1 bg-slate-100 rounded-xl overflow-x-auto">
                {#each mis_componentes as comp}
                  <button
                    onclick={() => (componenteActivo = comp.id_componente)}
                    class={`flex items-center gap-2 px-4 py-2 text-sm font-medium whitespace-nowrap rounded-lg transition-all ${
                      componenteActivo === comp.id_componente
                        ? 'bg-white text-indigo-700 shadow-sm'
                        : 'text-slate-500 hover:text-slate-700 hover:bg-white/50'
                    }`}
                  >
                    {comp.tipo_componente}
                    {#if comp.es_titular}
                      <Crown class="h-3 w-3 text-amber-500" />
                    {/if}
                    <span
                      class="inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-slate-200/80 px-1.5 text-xs font-medium text-slate-600"
                    >
                      {comp.total_estudiantes}
                    </span>
                  </button>
                {/each}
              </div>
            {:else}
              <div class="flex items-center gap-3">
                <Badge variant="secondary" class="text-sm font-medium">
                  {mis_componentes[0].tipo_componente}
                </Badge>
                {#if mis_componentes[0].es_titular}
                  <span
                    class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full ring-1 ring-amber-200"
                  >
                    <Crown class="h-3 w-3" />
                    Titular
                  </span>
                {/if}
                {#if mis_componentes[0].total_docentes > 1}
                  <span class="text-xs text-slate-500">
                    {mis_componentes[0].total_docentes} docentes en componente
                  </span>
                {/if}
              </div>
            {/if}

            <!-- KPI -->
            <div
              class="flex items-center justify-center gap-4 p-5 rounded-xl bg-gradient-to-br from-purple-50/80 to-indigo-50/80 ring-1 ring-purple-100"
            >
              <div
                class="flex items-center justify-center h-12 w-12 rounded-full bg-white shadow-sm"
              >
                <Users class="h-5 w-5 text-purple-600" />
              </div>
              <div>
                <p class="text-3xl font-bold text-purple-700">{estudiantesActivos.length}</p>
                <p class="text-xs font-medium text-purple-500">Estudiantes</p>
              </div>
            </div>

            <!-- Table -->
            {#if estudiantesActivos.length === 0}
              <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-full bg-slate-100 mb-3"
                >
                  <Users class="h-6 w-6" />
                </div>
                <p class="text-sm">Sin estudiantes inscritos aún</p>
              </div>
            {:else}
              <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-slate-50/80">
                      <th
                        class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >#</th
                      >
                      <th
                        class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >Estudiante</th
                      >
                      <th
                        class="text-left py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >Usuario</th
                      >
                      <th
                        class="text-right py-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500"
                        >Nota</th
                      >
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    {#each estudiantesActivos as item, i}
                      <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-3 px-4 text-slate-400 tabular-nums">{i + 1}</td>
                        <td class="py-3 px-4">
                          <div class="flex items-center gap-3">
                            <div
                              class="flex items-center justify-center h-8 w-8 rounded-full bg-gradient-to-br from-indigo-100 to-blue-100 text-xs font-bold text-indigo-600 shrink-0"
                            >
                              {item.estudiante.nombre.charAt(0)}
                            </div>
                            <span class="font-medium text-slate-900">{item.estudiante.nombre}</span>
                          </div>
                        </td>
                        <td class="py-3 px-4">
                          <span class="text-slate-500 font-mono text-xs"
                            >{item.estudiante.username}</span
                          >
                        </td>
                        <td class="py-3 px-4 text-right">
                          {#if item.nota_componente !== null}
                            <span
                              class={`inline-flex items-center justify-center h-7 min-w-10 rounded-lg text-xs font-bold ${item.nota_componente >= 4 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-red-50 text-red-600 ring-1 ring-red-200'}`}
                            >
                              {item.nota_componente}
                            </span>
                          {:else}
                            <span class="text-slate-300">—</span>
                          {/if}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-slate-400 text-right">
                {estudiantesActivos.length} estudiante(s) en total
              </p>
            {/if}
          </Card.Content>
        </Card.Root>
      {:else}
        <Card.Root class="border-dashed shadow-sm">
          <Card.Content class="flex flex-col items-center justify-center py-12 text-slate-400">
            <div class="flex items-center justify-center h-12 w-12 rounded-full bg-slate-100 mb-3">
              <GraduationCap class="h-6 w-6" />
            </div>
            <p class="text-sm">No estás asignado a ningún componente de este curso.</p>
          </Card.Content>
        </Card.Root>
      {/if}
    {/if}
  </div>

  <!-- ─── Modales de Permisos ─── -->
  {#if curso.es_titular_curso}
    <SyllabusPermisosModal
      bind:isOpen={showSyllabusPermisos}
      onClose={() => (showSyllabusPermisos = false)}
      cursoId={curso.id_curso}
      cursoNombre={curso.nombre}
    />
  {/if}

  {#if componentePermisoId}
    <ComponentePermisosModal
      bind:isOpen={showComponentePermisos}
      onClose={() => {
        showComponentePermisos = false;
        componentePermisoId = 0;
      }}
      cursoId={curso.id_curso}
      componenteId={componentePermisoId}
      tipoComponente={componentePermisoTipo}
    />
  {/if}
</DocenteLayout>
