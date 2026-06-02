<script lang="ts">
  /**
   * Dashboard del estudiante - Vista Bento Grid
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem, SidebarCourse } from '@/types';
  import { page, router } from '@inertiajs/svelte'; // <-- Cambiamos Link por router
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

  // Variables de estado mutables
  let anoAcademico = $state('2026');
  let semestre = $state('1');

  // FUNCIÓN PARA EL BOTÓN VER TODOS: Navega al Index enviando las variables actuales
  function handleVerTodos() {
    router.visit('/estudiante/cursos', {
      method: 'get',
      data: {
        semestre: semestre,
        agno: anoAcademico
      }
    });
  }

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
  
  const mosaicClasses = [
    'md:col-span-1 col-span-2',
    'md:col-span-1 col-span-2',
    'md:col-span-2 col-span-2',
  ];

  const getGridClass = (index: number) => {
    if (index >= 3) return 'hidden';
    return mosaicClasses[index % mosaicClasses.length];
  };

  const cursosList = $derived(
    cursos.map((curso, index) => ({
      ...curso,
      progreso: curso.progreso,
      color: colorGradients[index % colorGradients.length],
      iconColor: 'text-white',
    })),
  );

  const ayudantecursosList = $derived(
    ayudanteCourses.map((curso, index) => ({
      ...curso,
      estudiantes: 0,
      proximaSesion: '—',
      color: colorGradients[index % colorGradients.length],
      iconColor: 'text-white',
    })),
  );

  const authUser = $derived(($page.props.auth as any)?.user);
  const nombreCompleto = $derived(stats?.nombre_completo || authUser?.name || 'Estudiante');
  const rut = $derived(estudiante?.rut || '20.000.000-0');
  const carrera = $derived(estudiante?.nombre_carrera || 'No disponible');

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
    <div class="relative mx-auto px-4">
      <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
          <h1 class="text-4xl font-bold bg-clip-text">
            Bienvenido, {nameParts.nombre}!
          </h1>
        </div>
        <p class="text-gray-500">Ambiente Estudiante · UTAMED</p>
      </div>

      <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-3 space-y-6">
          <ProfileCard
            nombre={nameParts.nombre}
            apellido1={nameParts.apellido1}
            apellido2={nameParts.apellido2}
            {rut}
            {carrera}
          />

          <div class="relative overflow-hidden rounded-3xl bg-linear-to from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-px">
            <div class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200">
              <div class="relative space-y-4">
                <div class="flex items-center gap-2 mb-4">
                  <Calendar class="w-4 h-4 text-purple-600" />
                  <h4 class="text-sm text-gray-700 font-medium">Periodo Académico</h4>
                </div>

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

                <div class="space-y-2">
                  <p class="text-xs text-gray-600 font-medium">Semestre</p>
                  <div class="grid grid-cols-2 gap-2">
                    <button
                      onclick={() => (semestre = '1')}
                      class={`py-3 rounded-xl font-medium text-sm transition-all ${
                        semestre === '1'
                          ? 'bg-cyan-600 text-white'
                          : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                      }`}
                    >
                      Sem 1
                    </button>
                    <button
                      onclick={() => (semestre = '2')}
                      class={`py-3 rounded-xl font-medium text-sm transition-all ${
                        semestre === '2'
                          ? 'bg-cyan-600 text-white'
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

        <div class="col-span-12 lg:col-span-8">
          <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">
            <div class="flex flex-col gap-1 flex-1 min-w-0">
              <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">Mis Cursos</h2>
              <p class="text-sm sm:text-base text-gray-600 break-words">
                {`${cursosList.length} asignaturas inscritas · Semestre ${semestre} ${anoAcademico}`}
              </p>
            </div>

            <button
              onclick={handleVerTodos}
              class="flex items-center justify-center md:justify-between w-full sm:w-fit md:w-60 gap-2 rounded-2xl px-4 py-3 sm:py-5 border-2 bg-primary text-secondary hover:bg-secondary hover:text-primary transition-colors shrink-0"
            >
              <p class="text-sm sm:text-md text-center font-bold">Ver todos</p>
              <ChevronRight class="w-4 h-4 shrink-0" />
            </button>
          </div>

          {#if cursosList.length > 0}
            <p class="font-semibold text-md p-4">Accesos directos</p>
            <div class="grid grid-cols-2 mx-auto md:grid-cols-2 gap-6 auto-rows-[200px]">
              {#each cursosList as curso, index}
                <div class={getGridClass(index)}>
                  <CourseCard {...curso} />
                </div>
              {/each}
            </div>
          {:else}
            <div class="rounded-3xl border border-gray-200 bg-gray-50 p-12 text-center">
              <div class="mb-4 flex justify-center">
                <div class="rounded-full bg-gray-100 p-4">
                  <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
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