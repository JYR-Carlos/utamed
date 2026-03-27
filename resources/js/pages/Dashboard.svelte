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
  import {
    Users,
    BookOpen,
    GraduationCap,
    Building2,
    Calendar,
    Clock,
    ClipboardList,
    Settings,
    UserPlus,
    ShieldCheck,
    CheckCircle2,
    FileText,
    MoreHorizontal,
  } from 'lucide-svelte';
  import SoftCard from '@/components/custom/dashboard/SoftCard.svelte';
  import IllustrationWidget from '@/components/custom/dashboard/IllustrationWidget.svelte';
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

  interface Props {
    stats: Stats;
    ayudanteCourses?: Array<{
      id_curso: number;
      nombre: string;
      cod_curso: string;
      asignatura_nombre: string;
    }>;
  }

  let { stats, ayudanteCourses = [] }: Props = $props();
  let user = $derived($page.props.auth.user);
  let roles = $derived(($page.props.auth.roles as string[]) || []);

  let isDocente = $derived(roles.includes('Docente') || roles.includes('docente'));
  let isEstudiante = $derived(roles.includes('Estudiante') || roles.includes('estudiante'));
  let isAyudante = $derived(roles.includes('Ayudante') || roles.includes('ayudante'));
  let isAdmin = $derived(roles.includes('SuperAdmin') || roles.includes('Super Admin') || roles.includes('Administrador'));

  // Actividad reciente (mock representativo)
  const recentActivity = [
    { usuario: 'Super Admin', username: 'superadmin@utamed...', actividad: 'Crear usuario nuevo en el sistema', hecha: 'hace 2 h' },
    { usuario: 'Super Admin', username: 'superadmin@utamed...', actividad: 'Curso actualizado en acta docente', hecha: 'hace 5 h' },
    { usuario: 'Super Admin', username: 'superadmin@utamed...', actividad: 'Rol asignado a usuario', hecha: 'hace 1 d' },
    { usuario: 'Super Admin', username: 'superadmin@utamed...', actividad: 'Plan de estudio modificado', hecha: 'hace 2 d' },
  ];

  const operaciones = [
    { icon: UserPlus, label: 'Crear Nuevo Usuario', href: '/admin/usuarios', bg: 'bg-blue-500/40 hover:bg-blue-500/60' }, 
    { icon: ShieldCheck, label: 'Gestionar Roles', href: '/admin/usuarios', bg: 'bg-indigo-500/40 hover:bg-indigo-500/60' },
    //{ icon: CheckCircle2, label: 'Validar Datos', href: '/admin', bg: 'bg-violet-500/40 hover:bg-violet-500/60' }, 
    //{ icon: FileText, label: 'Ver Auditoría', href: '/admin', bg: 'bg-purple-500/40 hover:bg-purple-500/60' }
  ]

  // Sparkline paths decorativos (distintos por tarjeta)
  const sparklines = [
    'M0 20 Q10 15 20 18 Q30 21 40 14 Q50 8  60 12 Q70 16 80 10',
    'M0 18 Q10 22 20 16 Q30 10 40 14 Q50 18 60 12 Q70 8  80 14',
    'M0 14 Q10 18 20 22 Q30 16 40 20 Q50 14 60 18 Q70 12 80 16',
    'M0 20 Q10 12 20 16 Q30 20 40 10 Q50 14 60 8  Q70 12 80 6 ',
  ];
</script>

<svelte:head>
  <title>Dashboard | UTAMED</title>
</svelte:head>

<AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
  <div class="px-4 py-6 md:px-8 max-w-[1600px] mx-auto min-h-[calc(100vh-64px)]">
    <!-- Bienvenida -->
    <header class="mb-8">
      <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 tracking-tight">
        ¡Hola, {user.nombre1 || 'Usuario'}!
      </h1>
      
    </header>

    <!-- Grid principal -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <!-- ── Columna izquierda / centro ── -->
      <div class="xl:col-span-9 space-y-6">
        {#if isAdmin}
          <h2 class="text-xl">
            Dashboard de Administrador
          </h2>
          <!-- Tarjetas de estadísticas con sparklines -->
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {#each [{ label: 'Usuarios Activos', value: stats.usuarios, icon: Users, bg: 'bg-blue-50', text: 'text-blue-500', spark: sparklines[0], sparkColor: '#3b82f6' }, { label: 'Cursos Totales', value: stats.cursos_total, icon: BookOpen, bg: 'bg-orange-50', text: 'text-orange-500', spark: sparklines[1], sparkColor: '#f97316' }, { label: 'Cursos Activos (Sin Acta)', value: stats.cursos_pendientes, icon: Clock, bg: 'bg-amber-50', text: 'text-amber-500', spark: sparklines[2], sparkColor: '#f59e0b' }, { label: 'Nuevas Carreras', value: stats.carreras, icon: GraduationCap, bg: 'bg-emerald-50', text: 'text-emerald-500', spark: sparklines[3], sparkColor: '#10b981' }] as card}
              <SoftCard class="p-5 flex flex-col gap-3 overflow-hidden">
                <div class="flex items-start gap-3">
                  <div class="w-11 h-11 rounded-2xl {card.bg} {card.text} flex items-center justify-center shrink-0">
                    <card.icon size={22} />
                  </div>
                  <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-tight">{card.label}</p>
                    <p class="text-2xl font-extrabold text-slate-800 mt-0.5">{card.value}</p>
                  </div>
                </div>
                <!-- Sparkline decorativa -->
                <svg viewBox="0 0 80 28" class="w-full h-7 -mx-1" fill="none" preserveAspectRatio="none">
                  <path d={card.spark} stroke={card.sparkColor} stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.55" />
                </svg>
              </SoftCard>
            {/each}
          </div>

          <!--
          
          <SoftCard class="p-0 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
              <h3 class="font-bold text-slate-800">Actividad Reciente</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                    <th class="px-6 py-3 text-left">Usuario</th>
                    <th class="px-6 py-3 text-left">Actividad</th>
                    <th class="px-6 py-3 text-left">Hace</th>
                    <th class="px-6 py-3 text-left"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  {#each recentActivity as row}
                    <tr class="hover:bg-slate-50/60 transition-colors">
                      <td class="px-6 py-3">
                        <div class="flex items-center gap-3">
                          <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
                            {row.usuario.charAt(0)}
                          </div>
                          <div class="min-w-0">
                            <p class="font-semibold text-slate-700 truncate">{row.usuario}</p>
                            <p class="text-[11px] text-slate-400 truncate">{row.username}</p>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-3 text-slate-600">{row.actividad}</td>
                      <td class="px-6 py-3 text-slate-400 whitespace-nowrap">{row.hecha}</td>
                      <td class="px-6 py-3">
                        <button class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100">
                          <MoreHorizontal size={16} />
                        </button>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </SoftCard>
          -->
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

      <!-- ── Columna derecha ── -->
      {#if isAdmin}
        <div class="xl:col-span-3 space-y-6">
          <!-- Enlaces Rápidos -->
          <SoftCard class="p-6">
            <h3 class="font-bold text-slate-800 mb-4">Enlaces Rápidos</h3>
            <div class="flex flex-col gap-1">
              {#each [{ href: '/admin/usuarios', icon: Users, label: 'Usuarios' }, { href: '/admin/cursos', icon: BookOpen, label: 'Cursos' }, { href: '/admin/planes', icon: ClipboardList, label: 'Planes' }, { href: '/settings', icon: Settings, label: 'Configuración' }] as link}
                <Link
                  href={link.href}
                  class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-all group"
                >
                  <div class="p-1.5 bg-slate-100 rounded-lg group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
                    <link.icon size={16} />
                  </div>
                  {link.label}
                </Link>
              {/each}
            </div>
          </SoftCard>

          <!-- Operaciones Principales -->
          <div class="rounded-2xl bg-linear-to-br from-blue-600 to-purple-900 p-6 text-white shadow-xl shadow-blue-500/25">
            <div class="flex items-center gap-3 mb-5">
              <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
                <Users size={18} />
              </div>
              <span class="font-bold text-base">Operaciones Principales</span>
            </div>

            <div class="space-y-2.5">
              {#each operaciones as op}
                <Link href={op.href} class="flex items-center gap-3 w-full {op.bg} transition-colors rounded-xl px-4 py-3 text-sm font-semibold">
                  <op.icon size={16} class="shrink-0" />
                  {op.label}
                </Link>
              {/each}
            </div>
          </div>
        </div>
      {/if}
    </div>
  </div>
</AppLayout>
