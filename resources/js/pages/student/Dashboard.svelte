<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { page, Link } from '@inertiajs/svelte';
  import { BookOpen, Award, CalendarClock, Bell, LifeBuoy, ArrowUpRight, ClipboardX } from 'lucide-svelte';
  import ProfileCard from '@/components/student/ProfileCard.svelte';
  import CourseCard from '@/components/student/CourseCard.svelte';
  import MensajesSinLeerCard from '@/components/student/MensajesSinLeerCard.svelte';
  import PropuestaCard from '@/components/student/PropuestaCard.svelte';

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
      semestre_real: number;
      agno_real: number;
    }>;
    stats: {
      total_cursos: number;
      nombre_completo: string;
    };
    /** Mensajes de curso (curso.mensaje) que el alumno aún no abre. */
    mensajeria?: {
      no_leidos: number;
      cursos: Array<{ id_curso: number; nombre: string; cod_curso: string; no_leidos: number }>;
    };
    isAyudante?: boolean;
    semestreActual: number;
  }

  let { estudiante, cursos, stats, mensajeria, isAyudante = false, semestreActual }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/estudiante/dashboard' }];

  const anoAcademico = new Date().getFullYear();

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
    <div class="relative mx-auto max-w-6xl px-4 py-6">
      <header class="flex items-center justify-between gap-4 flex-wrap mb-8">
        <div class="flex flex-col gap-1">
          <span class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-full px-3 py-0.5 w-fit">
            Semestre {semestreActual} · {anoAcademico}
          </span>
          <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
            Portal Estudiante
          </h1>
          <p class="text-sm text-slate-500">
            Bienvenido, <strong class="text-slate-700 font-semibold">{nameParts.nombre}</strong>
          </p>
        </div>
      </header>

      <div class="flex flex-col gap-6">
        <ProfileCard
          nombre={nameParts.nombre}
          apellido1={nameParts.apellido1}
          apellido2={nameParts.apellido2}
          {rut}
          {carrera}
          semestre={semestreActual}
          agno={anoAcademico}
        />

        {#if isAyudante}
          <section
            class="flex flex-wrap items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#22213F]/10">
              <LifeBuoy class="h-[18px] w-[18px] text-[#22213F]" />
            </div>
            <div class="flex flex-col gap-0.5">
              <span class="text-sm font-semibold text-slate-900">También eres ayudante</span>
              <span class="text-[12.5px] text-slate-500">
                Redactas syllabus y respondes mensajería como ayudante — es un área de trabajo
                aparte, no otro curso más.
              </span>
            </div>
            <Link
              href="/ayudante/dashboard"
              class="ml-auto flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-[13.5px] font-medium text-slate-900 transition-colors hover:bg-slate-50"
            >
              Ir a mi área de ayudante
              <ArrowUpRight class="h-3.5 w-3.5 text-slate-500" />
            </Link>
          </section>
        {/if}

        {#if cursos.length === 0}
          <div
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
          >
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100">
              <ClipboardX class="h-5 w-5 text-slate-400" />
            </div>
            <span class="text-base font-semibold text-slate-900">Aún no tienes cursos matriculados</span>
            <p class="max-w-sm text-sm text-slate-500">
              Tu matrícula de este semestre está en trámite. En cuanto se confirme, tus cursos
              aparecerán aquí.
            </p>
          </div>
        {:else}
          <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <!-- Columna principal: cursos + notas. En mobile va DESPUÉS de la
                 columna lateral (order-2): entregas y mensajes son más urgentes
                 que una lista de cursos que el alumno ya conoce. -->
            <div class="order-2 flex flex-col gap-4 lg:order-1 lg:min-w-0 lg:flex-1">
              <div class="flex items-center gap-2">
                <BookOpen class="h-4 w-4 text-slate-500" />
                <h2 class="text-base font-semibold text-slate-900">Tus cursos</h2>
                <span class="ml-auto text-xs text-slate-500">
                  {cursos.length} {cursos.length === 1 ? 'curso' : 'cursos'}
                </span>
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {#each cursos as curso (curso.id_curso)}
                  <CourseCard {...curso} />
                {/each}
              </div>

              <PropuestaCard
                icon={Award}
                title="Notas recientes"
                emptyTitle="Aún no hay notas"
                emptyDescription="Aparecerán aquí cuando se evalúe tu primera actividad sumativa."
              />
            </div>

            <div class="order-1 flex flex-col gap-4 lg:order-2 lg:w-[360px] lg:flex-none">
              <PropuestaCard
                icon={CalendarClock}
                title="Próximas entregas"
                emptyTitle="Aún no hay entregas"
                emptyDescription="Tus cursos todavía no han publicado actividades con fecha."
              />

              <MensajesSinLeerCard
                total={mensajeria?.no_leidos ?? 0}
                cursos={mensajeria?.cursos ?? []}
              />

              <PropuestaCard
                icon={Bell}
                title="Novedades de tus actividades"
                emptyTitle="Sin novedades todavía"
                emptyDescription="Aparecerán cuando el equipo docente interactúe en una actividad."
              />
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</StudentLayout>
