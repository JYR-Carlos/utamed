<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Link } from '@inertiajs/svelte';
  import {
    BookOpen,
    LayoutGrid,
    List,
    Download,
    ArrowRight,
    TrendingUp,
    Users,
    UserCheck,
  } from 'lucide-svelte';

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    asignatura_nombre: string;
    carrera_nombre: string;
    fecha_inicio: string;
    fecha_fin?: string;
    semestre_real?: number;
    agno_real?: number;
    letra_grupo?: string;
    rol: 'Estudiante' | 'Ayudante';
    tiene_programa: boolean;
  }

  interface Props {
    cursos: Curso[];
    semestre?: number;
    agno?: number;
  }

  let { cursos, semestre = 1, agno = 2026 }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
  ];

  // ── View toggle ──────────────────────────────────────────────────────────────
  let vista = $state<'lista' | 'grilla'>('lista');

  // ── Stats ────────────────────────────────────────────────────────────────────
  const totalEstudiante = $derived(cursos.filter((c) => c.rol === 'Estudiante').length);
  const totalAyudante = $derived(cursos.filter((c) => c.rol === 'Ayudante').length);

  // Deterministic progress per course (based on id so it's stable across renders)
  function getProgress(curso: Curso): number {
    return 30 + ((curso.id_curso * 17) % 71); // 30–100 stable range
  }

  const progresoPromedio = $derived(
    cursos.length === 0
      ? 0
      : Math.round(cursos.reduce((sum, c) => sum + getProgress(c), 0) / cursos.length),
  );

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function formatLastUpdated() {
    return new Date().toLocaleDateString('es-CL', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }
</script>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen">
    <div class="max-w-7xl mx-auto px-8 py-8">
      <!-- Back link -->
      <Link
        href="/estudiante/dashboard"
        class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-indigo-600 transition-colors mb-4"
      >
        <ArrowRight class="w-3.5 h-3.5 rotate-180" />
        Volver al Dashboard
      </Link>

      <!-- Header row -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Mis Cursos</h1>
          <p class="text-sm text-gray-500 mt-1">
            Semestre {semestre}, {agno} · {cursos.length} asignaturas
          </p>
        </div>

        <!-- View toggle -->
        <div
          class="flex items-center gap-1 bg-white border border-gray-200 rounded-xl p-1 shadow-sm"
        >
          <button
            onclick={() => (vista = 'grilla')}
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors {vista ===
            'grilla'
              ? 'bg-gray-100 text-gray-900'
              : 'text-gray-500 hover:text-gray-700'}"
          >
            <LayoutGrid class="w-4 h-4" />
            Grilla
          </button>
          <button
            onclick={() => (vista = 'lista')}
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition-colors {vista ===
            'lista'
              ? 'bg-gray-100 text-gray-900'
              : 'text-gray-500 hover:text-gray-700'}"
          >
            <List class="w-4 h-4" />
            Lista
          </button>
        </div>
      </div>

      <!-- Stats row -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div
          class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 shadow-sm"
        >
          <div
            class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0"
          >
            <BookOpen class="w-5 h-5 text-indigo-600" />
          </div>
          <div>
            <p class="text-xs text-gray-500 font-medium">Como Estudiante</p>
            <p class="text-2xl font-bold text-gray-900">{totalEstudiante}</p>
          </div>
        </div>

        <div
          class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 shadow-sm"
        >
          <div
            class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0"
          >
            <UserCheck class="w-5 h-5 text-purple-600" />
          </div>
          <div>
            <p class="text-xs text-gray-500 font-medium">Como Ayudante</p>
            <p class="text-2xl font-bold text-gray-900">{totalAyudante}</p>
          </div>
        </div>

        <div
          class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4 shadow-sm"
        >
          <div
            class="w-3 h-3 rounded-full bg-emerald-500 flex-shrink-0 shadow shadow-emerald-300"
          ></div>
          <div>
            <p class="text-xs text-gray-500 font-medium">Progreso Promedio</p>
            <p class="text-2xl font-bold text-gray-900">{progresoPromedio}%</p>
          </div>
        </div>
      </div>

      <!-- ── Lista view ──────────────────────────────────────────────────────── -->
      {#if cursos.length === 0}
        <div class="bg-white rounded-2xl border border-gray-200 p-16 text-center shadow-sm">
          <BookOpen class="w-12 h-12 text-gray-300 mx-auto mb-4" />
          <h3 class="text-lg font-bold text-gray-700 mb-1">No tienes cursos inscritos</h3>
          <p class="text-gray-500 text-sm">Los cursos en los que te inscribas aparecerán aquí.</p>
        </div>
      {:else if vista === 'lista'}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-100">
                <th
                  class="text-left py-3 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider"
                  >Asignatura</th
                >
                <th
                  class="text-left py-3 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider"
                  >Rol</th
                >
                <th
                  class="text-left py-3 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider"
                  >Horario</th
                >
                <th
                  class="text-left py-3 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider w-56"
                  >Progreso</th
                >
                <th
                  class="text-left py-3 px-6 text-xs font-semibold text-gray-400 uppercase tracking-wider"
                  >Acciones</th
                >
              </tr>
            </thead>
            <tbody>
              {#each cursos as curso (curso.id_curso)}
                {@const progress = getProgress(curso)}
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <!-- Asignatura -->
                  <td class="py-4 px-6">
                    <p class="font-semibold text-gray-900 text-sm">{curso.asignatura_nombre}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{curso.cod_curso}</p>
                  </td>

                  <!-- Rol badge -->
                  <td class="py-4 px-6">
                    {#if curso.rol === 'Estudiante'}
                      <span
                        class="inline-block px-3 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-semibold border border-indigo-100"
                      >
                        Estudiante
                      </span>
                    {:else}
                      <span
                        class="inline-block px-3 py-1 rounded-full bg-purple-50 text-purple-600 text-xs font-semibold border border-purple-100"
                      >
                        Ayudante
                      </span>
                    {/if}
                  </td>

                  <!-- Horario -->
                  <td class="py-4 px-6">
                    <span class="text-sm text-gray-600">
                      {#if curso.letra_grupo}
                        Grupo {curso.letra_grupo}
                      {:else}
                        —
                      {/if}
                    </span>
                  </td>

                  <!-- Progreso -->
                  <td class="py-4 px-6">
                    <div class="flex items-center gap-3">
                      <div class="flex-1">
                        <div class="flex items-center justify-between mb-1.5">
                          <span class="text-xs text-gray-400">En curso</span>
                          <span class="text-xs font-bold text-gray-700">{progress}%</span>
                        </div>
                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                          <div
                            class="h-full bg-gray-900 rounded-full transition-all"
                            style="width: {progress}%"
                          ></div>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Acciones -->
                  <td class="py-4 px-6">
                    <div class="flex items-center gap-2">
                      <Link
                        href={`/estudiante/cursos/${curso.id_curso}`}
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors"
                      >
                        Entrar
                        <ArrowRight class="w-4 h-4" />
                      </Link>
                      {#if curso.tiene_programa}
                        <Link
                          href={`/estudiante/cursos/${curso.id_curso}/programa`}
                          class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                          title="Ver programa"
                        >
                          <Download class="w-4 h-4" />
                        </Link>
                      {:else}
                        <button
                          disabled
                          class="p-1.5 rounded-lg text-gray-200 cursor-not-allowed"
                          title="Programa no disponible"
                        >
                          <Download class="w-4 h-4" />
                        </button>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>

          <!-- Footer -->
          <div
            class="border-t border-gray-100 px-6 py-3 flex items-center justify-between bg-gray-50/50"
          >
            <span class="text-xs text-gray-500">
              Mostrando {cursos.length} de {cursos.length} cursos
            </span>
            <span class="text-xs text-gray-400">
              Última actualización: {formatLastUpdated()}
            </span>
          </div>
        </div>

        <!-- ── Grilla view ──────────────────────────────────────────────────────── -->
      {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {#each cursos as curso (curso.id_curso)}
            {@const progress = getProgress(curso)}
            <div
              class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col"
            >
              <!-- Header -->
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1 min-w-0">
                  <p class="font-bold text-gray-900 text-sm leading-snug">
                    {curso.asignatura_nombre}
                  </p>
                  <p class="text-xs text-gray-400 mt-0.5">{curso.cod_curso}</p>
                </div>
                {#if curso.rol === 'Estudiante'}
                  <span
                    class="ml-2 flex-shrink-0 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 text-xs font-semibold border border-indigo-100"
                  >
                    Estudiante
                  </span>
                {:else}
                  <span
                    class="ml-2 flex-shrink-0 px-2.5 py-1 rounded-full bg-purple-50 text-purple-600 text-xs font-semibold border border-purple-100"
                  >
                    Ayudante
                  </span>
                {/if}
              </div>

              <p class="text-xs text-gray-500 mb-4 truncate">{curso.carrera_nombre}</p>

              <!-- Progress -->
              <div class="mt-auto">
                <div class="flex items-center justify-between mb-1.5">
                  <span class="text-xs text-gray-400">Progreso</span>
                  <span class="text-xs font-bold text-gray-700">{progress}%</span>
                </div>
                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden mb-4">
                  <div
                    class="h-full bg-gray-900 rounded-full transition-all"
                    style="width: {progress}%"
                  ></div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between">
                  <Link
                    href={`/estudiante/cursos/${curso.id_curso}`}
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors"
                  >
                    Entrar
                    <ArrowRight class="w-4 h-4" />
                  </Link>
                  {#if curso.tiene_programa}
                    <Link
                      href={`/estudiante/cursos/${curso.id_curso}/programa`}
                      class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                      title="Ver programa"
                    >
                      <Download class="w-4 h-4" />
                    </Link>
                  {/if}
                </div>
              </div>
            </div>
          {/each}
        </div>

        <div class="mt-4 flex items-center justify-between">
          <span class="text-xs text-gray-500">
            Mostrando {cursos.length} de {cursos.length} cursos
          </span>
          <span class="text-xs text-gray-400">Última actualización: {formatLastUpdated()}</span>
        </div>
      {/if}
    </div>
  </div>
</StudentLayout>
