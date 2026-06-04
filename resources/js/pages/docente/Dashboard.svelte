<script lang="ts">
  /**
   * Dashboard del docente — Teacher Portal.
   * Layout compacto sin scroll vertical en pantallas de 16 pulgadas.
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { page, Link } from '@inertiajs/svelte';
  import {
    BookOpen,
    Users,
    Award,
    ClipboardList,
    MessageSquare,
    Calendar,
    GraduationCap,
    BarChart3,
    ArrowRight,
    CheckCircle2,
    Clock,
    Sparkles,
    ShieldCheck,
  } from 'lucide-svelte';

  interface Curso {
    id_curso: number;
    nombre: string;
    cod_curso?: string;
    tiene_programa?: boolean;
  }

  interface Props {
    docente: {
      id_docente: number;
      grado?: string;
      titulo?: string;
      cargo?: string;
      id_usuario: number;
    };
    stats: {
      total_cursos: number;
      nombre_completo: string;
    };
    cursos?: Curso[];
    jefatura?: {
      has_access: boolean;
      id_contexto?: number | null;
      carrera?: {
        id_carrera: number;
        nombre: string;
      } | null;
    };
  }

  let { docente, stats, cursos = [], jefatura }: Props = $props();

  let sharedCourses = $derived(($page.props.auth as any)?.docente_courses ?? []);
  let allCursos = $derived(cursos.length > 0 ? cursos : sharedCourses);

  const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/docente/dashboard' }];

  const now = new Date();
  const periodoActual = `${now.getFullYear()} · Semestre ${now.getMonth() < 6 ? 1 : 2}`;
</script>

<DocenteLayout {breadcrumbs}>
  <div
    class="flex flex-col gap-5 2xl:gap-7 p-4 md:p-5 lg:p-6 2xl:p-8 min-[1920px]:p-10 max-w-[1400px] 2xl:max-w-[1700px] min-[1920px]:max-w-[2000px] mx-auto w-full"
  >
    <!-- ── Header ─────────────────────────────────────────── -->
    <header class="flex items-center justify-between gap-4 flex-wrap">
      <div class="flex flex-col gap-1">
        <span
          class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-full px-3 py-0.5 w-fit"
        >
          {periodoActual}
        </span>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Portal Docente
        </h1>
        <p class="text-sm text-slate-500">
          Bienvenido, <strong class="text-slate-700 font-semibold">{stats.nombre_completo}</strong
          >{#if docente.grado}
            — {docente.grado}{/if}
        </p>
      </div>
      <div class="flex gap-2 items-center shrink-0">
        {#if jefatura?.has_access}
          <Link
            href="/docente/jefe-carrera/dashboard"
            class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors no-underline"
            title="Entrar a ambiente Jefe de Carrera"
          >
            <ShieldCheck size={15} />
            Entrar a Jefatura
          </Link>
        {/if}
        <span
          class="hidden sm:inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 border border-dashed border-slate-200 bg-slate-50 rounded-lg px-3 py-2"
          title="Notificaciones y Vista Estudiante disponibles próximamente"
        >
          <Clock size={13} />
          Próximamente
        </span>
      </div>
    </header>

    <!-- ── Metric Cards ──────────────────────────────────── -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 2xl:gap-5">
      <div
        class="flex items-center gap-4 2xl:gap-5 px-5 py-4 2xl:px-6 2xl:py-5 rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200"
      >
        <div
          class="w-11 h-11 2xl:w-13 2xl:h-13 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0"
        >
          <BookOpen size={20} />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-2xl 2xl:text-3xl font-extrabold text-indigo-900 leading-none"
            >{stats.total_cursos}</span
          >
          <span class="text-xs font-semibold text-indigo-400 uppercase tracking-widest mt-0.5"
            >Cursos Asignados</span
          >
        </div>
      </div>
      <div
        class="flex items-center gap-4 2xl:gap-5 px-5 py-4 2xl:px-6 2xl:py-5 rounded-xl bg-gradient-to-br from-violet-50 to-violet-100 border border-violet-200"
      >
        <div
          class="w-11 h-11 2xl:w-13 2xl:h-13 rounded-xl bg-violet-600 text-white flex items-center justify-center shrink-0"
        >
          <Award size={20} />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-base md:text-lg font-bold text-violet-900 leading-tight truncate"
            >{docente.grado || '—'}</span
          >
          <span class="text-xs font-semibold text-violet-400 uppercase tracking-widest mt-0.5"
            >Grado Académico</span
          >
        </div>
      </div>
      <div
        class="flex items-center gap-4 2xl:gap-5 px-5 py-4 2xl:px-6 2xl:py-5 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200"
      >
        <div
          class="w-11 h-11 2xl:w-13 2xl:h-13 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0"
        >
          <Users size={20} />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-base md:text-lg font-bold text-emerald-900 leading-tight truncate"
            >{docente.cargo || '—'}</span
          >
          <span class="text-xs font-semibold text-emerald-400 uppercase tracking-widest mt-0.5"
            >Cargo</span
          >
        </div>
      </div>
      <div
        class="flex items-center gap-4 2xl:gap-5 px-5 py-4 2xl:px-6 2xl:py-5 rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200"
      >
        <div
          class="w-11 h-11 2xl:w-13 2xl:h-13 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0"
        >
          <BarChart3 size={20} />
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-2xl font-extrabold text-amber-900 leading-none">—</span>
          <span class="text-xs font-semibold text-amber-400 uppercase tracking-widest mt-0.5"
            >Act. Pendientes</span
          >
          <span class="text-[10px] text-amber-400/70 leading-none mt-0.5">No disponible aún</span>
        </div>
      </div>
    </div>

    <!-- ── Master Grid: 2/3 + 1/3 ──────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 2xl:gap-7 items-start">
      <!-- Left Column: Acciones Rápidas — 3 cols × 2 rows -->
      <section class="lg:col-span-2">
        <h2 class="text-base font-bold text-slate-700 mb-3 tracking-tight">Acciones Rápidas</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 2xl:gap-5">
          <!-- Mis Cursos — activo -->
          <Link
            href="/docente/cursos"
            class="flex flex-col gap-3 p-5 rounded-xl border bg-gradient-to-br from-indigo-50 to-indigo-100 border-indigo-200 no-underline transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md"
          >
            <div class="flex items-start justify-between">
              <div
                class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0"
              >
                <BookOpen size={18} />
              </div>
              <span class="text-xl font-extrabold text-indigo-900">{stats.total_cursos}</span>
            </div>
            <div>
              <h3 class="text-sm font-bold text-indigo-900 m-0 leading-tight">Mis Cursos</h3>
              <p class="text-xs text-indigo-700/70 leading-snug m-0 mt-1">
                Gestiona tus cursos y programas
              </p>
            </div>
            <div class="flex items-center gap-1 text-xs font-semibold text-indigo-700 mt-auto">
              Ver todos <ArrowRight size={13} />
            </div>
          </Link>

          <!-- Inscripciones — activo -->
          <Link
            href="/docente/inscripciones"
            class="flex flex-col gap-3 p-5 rounded-xl border bg-gradient-to-br from-indigo-50 to-indigo-100 border-indigo-200 no-underline transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md"
          >
            <div class="flex items-start justify-between">
              <div
                class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0"
              >
                <Users size={18} />
              </div>
            </div>
            <div>
              <h3 class="text-sm font-bold text-indigo-900 m-0 leading-tight">Inscripciones</h3>
              <p class="text-xs text-indigo-700/70 leading-snug m-0 mt-1">
                Estudiantes inscritos en tus secciones
              </p>
            </div>
            <div class="flex items-center gap-1 text-xs font-semibold text-indigo-700 mt-auto">
              Ver inscripciones <ArrowRight size={13} />
            </div>
          </Link>

          <!-- Asistencia — próximamente -->
          <button
            class="flex flex-col gap-3 p-5 rounded-xl border bg-slate-50 border-slate-200 cursor-not-allowed opacity-65 text-left font-[inherit]"
            disabled
          >
            <div class="flex items-start justify-between">
              <div
                class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center shrink-0"
              >
                <ClipboardList size={18} />
              </div>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 uppercase tracking-wider"
                >Próximamente</span
              >
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-500 m-0 leading-tight">Asistencia</h3>
              <p class="text-xs text-slate-400 leading-snug m-0 mt-1">
                Registra asistencia de estudiantes
              </p>
            </div>
          </button>

          <!-- Calificaciones — próximamente -->
          <button
            class="flex flex-col gap-3 p-5 rounded-xl border bg-slate-50 border-slate-200 cursor-not-allowed opacity-65 text-left font-[inherit]"
            disabled
          >
            <div class="flex items-start justify-between">
              <div
                class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center shrink-0"
              >
                <BarChart3 size={18} />
              </div>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 uppercase tracking-wider"
                >Próximamente</span
              >
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-500 m-0 leading-tight">Calificaciones</h3>
              <p class="text-xs text-slate-400 leading-snug m-0 mt-1">
                Ingresa y gestiona las notas
              </p>
            </div>
          </button>

          <!-- Mensajes — próximamente -->
          <button
            class="flex flex-col gap-3 p-5 rounded-xl border bg-slate-50 border-slate-200 cursor-not-allowed opacity-65 text-left font-[inherit]"
            disabled
          >
            <div class="flex items-start justify-between">
              <div
                class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center shrink-0"
              >
                <MessageSquare size={18} />
              </div>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 uppercase tracking-wider"
                >Próximamente</span
              >
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-500 m-0 leading-tight">Mensajes</h3>
              <p class="text-xs text-slate-400 leading-snug m-0 mt-1">
                Comunicación con estudiantes
              </p>
            </div>
          </button>

          <!-- Calendario — próximamente -->
          <button
            class="flex flex-col gap-3 p-5 rounded-xl border bg-slate-50 border-slate-200 cursor-not-allowed opacity-65 text-left font-[inherit]"
            disabled
          >
            <div class="flex items-start justify-between">
              <div
                class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center shrink-0"
              >
                <Calendar size={18} />
              </div>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 uppercase tracking-wider"
                >Próximamente</span
              >
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-500 m-0 leading-tight">Calendario</h3>
              <p class="text-xs text-slate-400 leading-snug m-0 mt-1">
                Clases, evaluaciones y eventos
              </p>
            </div>
          </button>
        </div>
      </section>

      <!-- Right Column: Sidebar Widgets -->
      <aside class="lg:col-span-1 flex flex-col gap-4 pt-8">
        <!-- Estado de Programas -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
          <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">
            Estado de Programas
          </h3>
          <div class="flex flex-col">
            {#if allCursos.length === 0}
              <p class="text-sm text-slate-400 text-center py-2">Sin cursos asignados</p>
            {:else}
              {#each allCursos.slice(0, 5) as curso (curso.id_curso)}
                <div
                  class="flex items-center justify-between gap-2 py-2 border-b border-slate-100 last:border-b-0"
                >
                  <div class="flex flex-col min-w-0">
                    <span class="text-sm font-semibold text-slate-700 truncate">{curso.nombre}</span
                    >
                    {#if curso.cod_curso}
                      <span class="text-xs text-slate-400">{curso.cod_curso}</span>
                    {/if}
                  </div>
                  {#if curso.tiene_programa}
                    <span
                      class="flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 whitespace-nowrap shrink-0"
                    >
                      <CheckCircle2 size={11} /> Generado
                    </span>
                  {:else}
                    <span
                      class="flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 whitespace-nowrap shrink-0"
                    >
                      <Clock size={11} /> Pendiente
                    </span>
                  {/if}
                </div>
              {/each}
              {#if allCursos.length > 5}
                <Link
                  href="/docente/cursos"
                  class="text-sm text-indigo-600 font-semibold no-underline block mt-2 hover:underline"
                >
                  Ver todos ({allCursos.length}) →
                </Link>
              {/if}
            {/if}
          </div>
        </div>

        <!-- Recursos Docentes -->
        <div class="rounded-xl p-5 bg-gradient-to-br from-indigo-600 to-violet-700 text-white">
          <div class="flex items-center gap-2 text-sm font-bold mb-3">
            <GraduationCap size={17} />
            <span>Recursos Docentes</span>
          </div>
          <div class="flex flex-col gap-1.5">
            <button
              class="flex justify-between items-center px-3 py-2 rounded-lg bg-white/10 text-sm font-medium text-white/90 cursor-not-allowed opacity-60 text-left w-full border-0 font-[inherit]"
              disabled
            >
              Documentación del Sistema <span>→</span>
            </button>
            <button
              class="flex justify-between items-center px-3 py-2 rounded-lg bg-white/10 text-sm font-medium text-white/90 cursor-not-allowed opacity-60 text-left w-full border-0 font-[inherit]"
              disabled
            >
              Guía de Programas <span>→</span>
            </button>
            <button
              class="flex justify-between items-center px-3 py-2 rounded-lg bg-white/10 text-sm font-medium text-white/90 cursor-not-allowed opacity-60 text-left w-full border-0 font-[inherit]"
              disabled
            >
              Soporte Técnico <span>→</span>
            </button>
          </div>
        </div>
      </aside>
    </div>
  </div>
</DocenteLayout>
