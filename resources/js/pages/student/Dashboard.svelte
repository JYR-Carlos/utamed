<script lang="ts">
  /**
   * Dashboard del estudiante - Vista Bento Grid
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import { Link, page } from '@inertiajs/svelte';
  import { Sparkles, Calendar } from 'lucide-svelte';
  import ProfileCard from '@/components/student/ProfileCard.svelte';
  import CourseCard from '@/components/student/CourseCard.svelte';
  import AyudantiaCard from '@/components/student/AyudantiaCard.svelte';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    estudiante: {
      id_estudiante: number;
      rut: string;
      id_usuario: number;
    };
    cursos: Array<{
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre: string;
      carrera_nombre: string;
      fecha_inicio: string;
      fecha_fin?: string;
      profesor: string;
    }>;
    stats: {
      total_cursos: number;
      nombre_completo: string;
    };
    isAyudante?: boolean;
  }

  let { estudiante, cursos, stats, isAyudante = false }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/estudiante/dashboard' }];

  // State
  let modoAyudante = $state(false);
  let anoAcademico = $state('2026');
  let semestre = $state('1');

  // Get TA courses from shared props
  let ayudanteCourses = $derived(($page.props.auth?.ayudante_courses as SidebarCourse[]) || []);

  // Colores para los cursos (rotativos)
  const colorGradients = [
    'from-purple-600 to-purple-400',
    'from-cyan-600 to-cyan-400',
    'from-orange-600 to-orange-400',
    'from-pink-600 to-pink-400',
    'from-emerald-600 to-emerald-400',
  ];

  // Mapear cursos a formato CourseCard
  const cursosEnriquecidos = $derived(
    cursos.map((curso, index) => ({
      ...curso,
      progreso: Math.floor(Math.random() * (90 - 30) + 30),
      color: colorGradients[index % colorGradients.length],
      iconColor: 'text-white',
    })),
  );

  // Enriquecer cursos de ayudantía con colores y campos de presentación
  const ayudanteCursosEnriquecidos = $derived(
    ayudanteCourses.map((curso, index) => ({
      ...curso,
      estudiantes: 0,
      proximaSesion: '—',
      color: colorGradients[index % colorGradients.length],
      iconColor: 'text-white',
    })),
  );

  // Get auth data
  const authUser = $derived(($page.props.auth as any)?.user);
  const nombreCompleto = $derived(stats?.nombre_completo || authUser?.name || 'Estudiante');
  const rut = $derived(estudiante?.rut || '20.000.000-0');

  // Parse full name
  const nameParts = $derived.by(() => {
    const parts = nombreCompleto.split(' ');
    return {
      nombre: parts[0] || '',
      apellido1: parts[1] || '',
      apellido2: parts[2] || '',
    };
  });

  // Calculate average progress
  const progresoPromedio = $derived(
    cursosEnriquecidos.length > 0
      ? Math.round(
          cursosEnriquecidos.reduce((acc, c) => acc + c.progreso, 0) / cursosEnriquecidos.length,
        )
      : 0,
  );
</script>

<StudentLayout {breadcrumbs}>
  <div class="min-h-screen bg-white relative overflow-hidden">
    <!-- Animated background blobs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div
        class="absolute top-0 left-1/4 w-96 h-96 bg-purple-600/5 rounded-full blur-3xl animate-pulse"
      />
      <div
        class="absolute bottom-0 right-1/4 w-96 h-96 bg-cyan-600/5 rounded-full blur-3xl animate-pulse"
        style="animation-delay: 1s"
      />
      <div
        class="absolute top-1/2 right-1/3 w-96 h-96 bg-orange-600/5 rounded-full blur-3xl animate-pulse"
        style="animation-delay: 2s"
      />
    </div>

    <div class="relative max-w-[1800px] mx-auto p-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
          <div class="p-2 rounded-xl bg-gradient-to-br from-purple-600 to-cyan-600">
            <Sparkles class="w-6 h-6 text-white" />
          </div>
          <h1
            class="text-4xl font-bold bg-gradient-to-r from-purple-400 via-cyan-400 to-orange-400 bg-clip-text text-transparent"
          >
            Utamed
          </h1>
        </div>
        <p class="text-gray-500 ml-14">Plataforma académica · Diseño Multimedia</p>
      </div>

      <!-- Bento Grid Layout -->
      <div class="grid grid-cols-12 gap-6">
        <!-- Left Sidebar - Profile & Filters -->
        <div class="col-span-12 lg:col-span-3 space-y-6">
          <!-- Profile Card -->
          <ProfileCard
            nombre={nameParts.nombre}
            apellido1={nameParts.apellido1}
            apellido2={nameParts.apellido2}
            {rut}
          />

          <!-- Mode Switcher (solo visible si el usuario es ayudante) -->
          {#if isAyudante}
            <div
              class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-[1px]"
            >
              <div
                class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200"
              >
                <div
                  class="absolute inset-0 bg-gradient-to-br from-white/50 to-transparent rounded-3xl"
                />

                <div class="relative space-y-4">
                  <h4 class="text-sm text-gray-700 mb-3 font-medium">Modo de Vista</h4>

                  <div class="relative flex rounded-2xl bg-gray-100 p-1">
                    <div
                      class={`absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-xl bg-gradient-to-r from-purple-600 to-cyan-600 transition-transform duration-300 ${
                        modoAyudante ? 'translate-x-[calc(100%+8px)]' : 'translate-x-0'
                      }`}
                    />
                    <button
                      onclick={() => (modoAyudante = false)}
                      class={`relative z-10 flex-1 py-2 text-sm font-medium transition-colors ${
                        !modoAyudante ? 'text-white' : 'text-gray-600'
                      }`}
                    >
                      Alumno
                    </button>
                    <button
                      onclick={() => (modoAyudante = true)}
                      class={`relative z-10 flex-1 py-2 text-sm font-medium transition-colors ${
                        modoAyudante ? 'text-white' : 'text-gray-600'
                      }`}
                    >
                      Ayudante
                    </button>
                  </div>
                </div>
              </div>
            </div>
          {/if}

          <!-- Temporal Context Selectors -->
          <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-[1px]"
          >
            <div class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200">
              <div
                class="absolute inset-0 bg-gradient-to-br from-white/50 to-transparent rounded-3xl"
              />

              <div class="relative space-y-4">
                <div class="flex items-center gap-2 mb-4">
                  <Calendar class="w-4 h-4 text-purple-600" />
                  <h4 class="text-sm text-gray-700 font-medium">Periodo Académico</h4>
                </div>

                <!-- Año Selector -->
                <div class="space-y-2">
                  <label class="text-xs text-gray-600 font-medium">Año</label>
                  <select
                    bind:value={anoAcademico}
                    class="w-full px-4 py-3 rounded-xl bg-white border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-purple-500/50 transition-all"
                  >
                    <option value="2026">2026</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                  </select>
                </div>

                <!-- Semestre Selector -->
                <div class="space-y-2">
                  <label class="text-xs text-gray-600 font-medium">Semestre</label>
                  <div class="grid grid-cols-2 gap-2">
                    <button
                      onclick={() => (semestre = '1')}
                      class={`py-3 rounded-xl font-medium text-sm transition-all ${
                        semestre === '1'
                          ? 'bg-gradient-to-r from-purple-600 to-cyan-600 text-white'
                          : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                      }`}
                    >
                      Sem 1
                    </button>
                    <button
                      onclick={() => (semestre = '2')}
                      class={`py-3 rounded-xl font-medium text-sm transition-all ${
                        semestre === '2'
                          ? 'bg-gradient-to-r from-purple-600 to-cyan-600 text-white'
                          : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                      }`}
                    >
                      Sem 2
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Stats Card -->
          <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-[1px]"
          >
            <div class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200">
              <div
                class="absolute inset-0 bg-gradient-to-br from-white/50 to-transparent rounded-3xl"
              />

              <div class="relative space-y-3">
                <h4 class="text-sm text-gray-700 mb-4 font-medium">Resumen</h4>

                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-600">Total Cursos</span>
                  <span
                    class="px-2.5 py-1 rounded-full bg-purple-100 text-purple-700 border border-purple-200 text-xs font-bold"
                  >
                    {modoAyudante ? ayudanteCursosEnriquecidos.length : cursosEnriquecidos.length}
                  </span>
                </div>

                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-600">Promedio Progreso</span>
                  <span class="text-sm font-semibold text-gray-900">
                    {modoAyudante ? '—' : `${progresoPromedio}%`}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content - Courses/Ayudantías Grid -->
        <div class="col-span-12 lg:col-span-9">
          <div class="mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 mb-2">
              {modoAyudante ? 'Mis Ayudantías' : 'Mis Cursos'}
            </h2>
            <p class="text-gray-600">
              {modoAyudante
                ? `Gestionando ${ayudanteCursosEnriquecidos.length} ayudantía${ayudanteCursosEnriquecidos.length !== 1 ? 's' : ''}`
                : `${cursosEnriquecidos.length} asignaturas inscritas · Semestre ${semestre} ${anoAcademico}`}
            </p>
          </div>

          <!-- Bento Grid - Asymmetric Layout -->
          {#if !modoAyudante}
            {#if cursosEnriquecidos.length > 0}
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 auto-rows-fr">
                {#each cursosEnriquecidos as curso, index}
                  <div class={index === 0 ? 'md:col-span-2 md:row-span-1' : ''}>
                    <CourseCard {...curso} />
                  </div>
                {/each}
              </div>
            {:else}
              <div class="rounded-3xl border border-gray-200 bg-gray-50 p-12 text-center">
                <div class="mb-4 flex justify-center">
                  <div class="rounded-full bg-gray-100 p-4">
                    <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                      <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                      />
                    </svg>
                  </div>
                </div>
                <h3 class="mb-2 text-lg font-bold text-gray-900">No tienes cursos inscritos</h3>
                <p class="text-gray-600">Los cursos en los que te inscribas aparecerán aquí.</p>
              </div>
            {/if}
          {:else if ayudanteCursosEnriquecidos.length > 0}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 auto-rows-fr">
              {#each ayudanteCursosEnriquecidos as ayudantia, index}
                <div class={index === 0 ? 'md:col-span-2' : ''}>
                  <AyudantiaCard {...ayudantia} />
                </div>
              {/each}
            </div>
          {:else}
            <div class="rounded-3xl border border-gray-200 bg-gray-50 p-12 text-center">
              <div class="mb-4 flex justify-center">
                <div class="rounded-full bg-gray-100 p-4">
                  <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                    <path
                      d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
                    />
                  </svg>
                </div>
              </div>
              <h3 class="mb-2 text-lg font-bold text-gray-900">No tienes ayudantías asignadas</h3>
              <p class="text-gray-600">Los cursos donde eres ayudante aparecerán aquí.</p>
            </div>
          {/if}
        </div>
      </div>
    </div>
  </div>
</StudentLayout>
