<script lang="ts">
  /**
   * Dashboard principal del sistema UtaMed.
   *
   * Página de inicio que muestra diferentes vistas según el rol del usuario:
   * - Docente: Dashboard de cursos y secciones asignadas
   * - Estudiante: Dashboard con calificaciones y actividades
   * - Ayudante: Dashboard de cursos donde asiste
   * - Admin: Estadísticas globales y widgets CRUD
   */
  import AppLayout from '@/layouts/AppLayout.svelte';
  import { page, Link } from '@inertiajs/svelte';
  import { Users, BookOpen, GraduationCap, Building2, CheckCircle2 } from 'lucide-svelte';
  import SoftCard from '@/components/custom/dashboard/SoftCard.svelte';
  import BotonSgeq from '@/components/custom/common/BotonSgeq.svelte';
  import DashboardDocente from './dashboards/DashboardDocente.svelte';
  import DashboardAlumno from './dashboards/DashboardAlumno.svelte';
  import DashboardAyudante from './dashboards/DashboardAyudante.svelte';

  interface Stats {
    usuarios: number;
    cursos_total: number;
    cursos_pendientes: number;
    facultades: number;
    carreras: number;
  }

  /** Cosas que requieren que alguien haga algo. */
  interface Pendientes {
    cursos_sin_syllabus: number;
    cursos_sin_componentes: number;
    carreras_sin_director: number;
  }

  interface Props {
    stats: Stats;
    pendientes?: Pendientes | null;
    /** El servidor ya evaluó si esta persona puede entrar a SGEQ. */
    puedeAbrirSgeq?: boolean;
    ayudanteCourses?: Array<{
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre: string;
    }>;
  }

  let { stats, pendientes = null, puedeAbrirSgeq = false, ayudanteCourses = [] }: Props = $props();
  let user = $derived($page.props.auth.user);
  let roles = $derived(($page.props.auth.roles as string[]) || []);

  let isDocente = $derived(($page.props.auth?.docente as any) !== null && ($page.props.auth?.docente as any) !== undefined);
  let isEstudiante = $derived(($page.props.auth?.estudiante as any) !== null && ($page.props.auth?.estudiante as any) !== undefined);
  let isAyudante = $derived((($page.props.auth?.ayudante_courses as any[]) || []).length > 0);
  let isAdmin = $derived(($page.props.auth?.is_super_admin as boolean) || roles.includes('SuperAdmin') || roles.includes('Super Admin') || roles.includes('Administrador'));

  /**
   * Bandeja de pendientes.
   *
   * Sustituye a las tarjetas con líneas de tendencia decorativas: eran
   * trazados fijos escritos a mano, sin ejes, sin escala y sin periodo —una
   * de ellas dibujaba una «tendencia» para el valor 2—, y al bloque de
   * enlaces rápidos, que repetía cuatro entradas de la barra lateral y cuyas
   * dos «operaciones principales» llevaban a la misma página.
   */
  const bandeja = $derived(
    pendientes
      ? [
          {
            valor: pendientes.cursos_sin_syllabus,
            label: 'cursos abiertos sin syllabus',
            accion: 'Ir a Syllabus',
            href: '/admin/syllabus',
          },
          {
            valor: pendientes.cursos_sin_componentes,
            label: 'cursos abiertos sin componentes',
            accion: 'Ir a Cursos',
            href: '/admin/cursos',
          },
          {
            valor: pendientes.carreras_sin_director,
            label: 'carreras activas sin director',
            accion: 'Ir a Carreras',
            href: '/admin/carreras',
          },
          {
            valor: stats.cursos_pendientes,
            label: 'cursos abiertos sin acta enviada',
            accion: 'Ir a Cursos',
            href: '/admin/cursos',
          },
        ].filter((p) => p.valor > 0)
      : [],
  );
</script>

<svelte:head>
  <title>Dashboard | UTAMED</title>
</svelte:head>

<AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
  <div class="px-4 py-6 md:px-8 max-w-[1600px] mx-auto min-h-[calc(100vh-64px)]">
    <!-- Bienvenida -->
    <header class="mb-8 flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight">
        ¡Hola, {user.nombre1 || 'Usuario'}!
      </h1>

      <BotonSgeq visible={puedeAbrirSgeq} />
    </header>

    <!-- Una sola columna: la lateral derecha sólo contenía enlaces repetidos
         de la barra de navegación, y su ausencia dejaba dos tercios de la
         pantalla en blanco bajo las tarjetas. -->
    <div class="max-w-5xl space-y-8">
        {#if isAdmin}
          <!-- Estado del sistema: cifras sin adorno. -->
          <section>
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
              Estado del sistema
            </h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
              {#each [{ label: 'Usuarios', value: stats.usuarios, icon: Users }, { label: 'Cursos', value: stats.cursos_total, icon: BookOpen }, { label: 'Carreras', value: stats.carreras, icon: GraduationCap }, { label: 'Facultades', value: stats.facultades, icon: Building2 }] as card}
                <SoftCard class="p-5 flex items-center gap-3">
                  <div
                    class="w-11 h-11 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center shrink-0"
                  >
                    <card.icon size={22} />
                  </div>
                  <div class="min-w-0">
                    <p class="text-2xl font-extrabold text-slate-800 tabular-nums">{card.value}</p>
                    <p class="text-xs font-medium text-slate-500 leading-tight">{card.label}</p>
                  </div>
                </SoftCard>
              {/each}
            </div>
          </section>

          <!-- Requiere atención -->
          <section>
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
              Requiere atención
            </h2>
            {#if bandeja.length === 0}
              <SoftCard class="p-6 flex items-center gap-3">
                <span class="text-emerald-600"><CheckCircle2 size={20} /></span>
                <p class="text-sm text-slate-600">
                  No hay nada pendiente: todos los cursos abiertos tienen syllabus y componentes, y
                  todas las carreras activas tienen director.
                </p>
              </SoftCard>
            {:else}
              <SoftCard class="p-0 overflow-hidden">
                <ul class="divide-y divide-slate-100">
                  {#each bandeja as item (item.label)}
                    <li class="flex items-center gap-4 px-5 py-4">
                      <span class="text-2xl font-extrabold text-slate-800 tabular-nums min-w-[3ch]">
                        {item.valor}
                      </span>
                      <span class="flex-1 text-sm text-slate-600">{item.label}</span>
                      <Link href={item.href} class="btn btn-neutral btn-sm shrink-0">
                        {item.accion}
                      </Link>
                    </li>
                  {/each}
                </ul>
              </SoftCard>
            {/if}
          </section>

        {/if}

        <!-- Paneles de rol específico -->
        {#if isDocente}
          <section>
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
              <span class="p-2 bg-indigo-50 rounded-lg text-indigo-500"><Users size={20} /></span>
              Panel Docente
            </h2>
            <DashboardDocente {user} />
          </section>
        {/if}

        {#if isEstudiante}
          <section>
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
              <span class="p-2 bg-emerald-50 rounded-lg text-emerald-500"><GraduationCap size={20} /></span>
              Panel Estudiante
            </h2>
            <DashboardAlumno {user} />
          </section>
        {/if}

        {#if isAyudante}
          <section>
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
              <span class="p-2 bg-blue-50 rounded-lg text-blue-500"><BookOpen size={20} /></span>
              Panel Ayudante
            </h2>
            <DashboardAyudante {user} courses={ayudanteCourses} />
          </section>
        {/if}
    </div>
  </div>
</AppLayout>
