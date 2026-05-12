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
  import ChevronRight from '@lucide/svelte/icons/chevron-right';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    estudiante: {
      id_estudiante: number;
      rut: string;
      id_usuario: number;
      nombre_carrera: string;
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
      progreso: number;
    }>;
    stats: {
      total_cursos: number;
      nombre_completo: string;
    };
    isAyudante?: boolean;
  }

  let { estudiante, cursos, stats, isAyudante = false }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/estudiante/dashboard' }];

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
  // Define tu patrón estilo mosaico
  const mosaicClasses = [
    "md:col-span-2 md:row-span-1",
    "md:col-span-2 md:row-span-1", 
    "md:col-span-3 md:row-span-1", 
    "md:col-span-2 md:row-span-1",
    "md:col-span-1 md:row-span-1", 
  ];

  // Función para obtener la clase según el índice
  const getGridClass = (index: number) => {
    if (index >= 3) return "hidden"; // muestra solo los primeros 3 cursos en el dashboard como acceso directo
    return mosaicClasses[index % mosaicClasses.length];
  };

  // Mapear cursos a formato CourseCard
  const cursosEnriquecidos = $derived(
    cursos.map((curso, index) => ({
      ...curso,
      progreso: curso.progreso,
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
  const carrera = $derived(estudiante?.nombre_carrera || 'No disponible');

  // Parse full name
  const nameParts = $derived.by(() => {
    const parts = nombreCompleto.split(' ');
    return {
      nombre: parts[0] || '',
      apellido1: parts[1] || '',
      apellido2: parts[2] || '',
    };
  });
</script>

<StudentLayout {breadcrumbs}>
  <div class="h-full px-5 md:px-10 lg:px-20 bg-white relative">
    <!-- Animated background blobs -->
    <div class="relative mx-auto px-4">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
          <h1 class="text-4xl font-bold bg-clip-text"
>
            Bienvenido, {nameParts.nombre}!
          </h1>
        </div>
        <p class="text-gray-500">Ambiente Estudiante · UTAMED</p>
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
            rut={rut}
            carrera={carrera}
          />

          
          <!-- Temporal Context Selectors -->
          <div
            class="relative overflow-hidden rounded-3xl bg-linear-to from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-px"
          >
            <div class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200">
              

              <div class="relative space-y-4">
                <div class="flex items-center gap-2 mb-4">
                  <Calendar class="w-4 h-4 text-purple-600" />
                  <h4 class="text-sm text-gray-700 font-medium">Periodo Académico</h4>
                </div>

                <!-- Año Selector -->
                <div class="space-y-2">
                  <p class="text-xs text-gray-600 font-medium">Año</p>
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
                  <p class="text-xs text-gray-600 font-medium">Semestre</p>
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
        </div>

        <!-- Main Content - Courses/Ayudantías Grid -->
        <div class="col-span-12 lg:col-span-8">
          <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <div class="flex flex-col gap-1 mr-auto">
              <h2 class="text-2xl font-semibold text-gray-900 mb-2">
                {'Mis Cursos'}
              </h2>
              <p class="text-gray-600">
                {`${cursosEnriquecidos.length} asignaturas inscritas · Semestre ${semestre} ${anoAcademico}`}
              </p>

            </div>

            <!-- BOTON DE VER TODOS LOS CURSOS -->
            <Link 
              class="flex justify-between items-center w-[30%] md:w-60 gap-2 rounded-2xl px-4 py-2 border-4 bg-primary text-secondary hover:bg-secondary hover:text-primary transition-colors"
              href="/estudiante/cursos"
            >
              <p class="text-md text-center font-bold "> Ver todos </p>
              <ChevronRight class="w-4 h-4" />
            </Link>

          </div>

          {#if cursosEnriquecidos.length > 0}
              <p class="font-semibold text-md p-4">Accesos directos</p>

              <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 auto-rows-[200px]">
                {#each cursosEnriquecidos as curso, index}
                  <div class="{getGridClass(index)} flex"> 
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

        </div>
      </div>
    </div>
  </div>
</StudentLayout>
