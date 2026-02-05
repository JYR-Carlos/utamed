<script lang="ts">
    /**
     * Dashboard principal del sistema UtaMed.
     * 
     * Página de inicio que muestra diferentes vistas según el rol del usuario:
     * - Docente: Dashboard de cursos y secciones asignadas
     * - Estudiante: Dashboard con calificaciones y actividades
     * - Ayudante: Dashboard de cursos donde asiste
     * - Admin: Estadísticas globales y widgets CRUD
     * 
     * Utiliza layouts responsivos y componentes reutilizables de SoftUI.
     */
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';
    import { 
        Users, 
        BookOpen, 
        GraduationCap, 
        Building2,
        Calendar,
        MessageSquare,
        UserPlus,
        Clock,
        ArrowUpRight,
        ClipboardList,
        Settings
    } from 'lucide-svelte';
    import SoftCard from '@/components/custom/dashboard/SoftCard.svelte';
    import IllustrationWidget from '@/components/custom/dashboard/IllustrationWidget.svelte';
    import DashboardDocente from './dashboards/DashboardDocente.svelte';
    import DashboardAlumno from './dashboards/DashboardAlumno.svelte';
    import DashboardAyudante from './dashboards/DashboardAyudante.svelte';

    /**
     * Estadísticas globales del sistema.
     * @type {{usuarios: number, cursos: number, facultades: number, carreras: number}}
     */
    interface Stats {
        usuarios: number;
        cursos: number;
        facultades: number;
        carreras: number;
    }

    /**
     * Props que recibe el componente Dashboard.
     */
    interface Props {
        stats: Stats;
    }

    let { stats }: Props = $props();
    let user = $derived($page.props.auth.user);
    let roles = $derived(($page.props.auth.roles as string[]) || []);
    
    let isDocente = $derived(roles.includes('Docente'));
    let isEstudiante = $derived(roles.includes('Estudiante'));
    let isAyudante = $derived(roles.includes('Ayudante'));
    let isAdmin = $derived(roles.includes('Super Admin') || roles.includes('Administrador') || roles.length === 0);

    // Mock data for new UI elements
    const crudStats = [
        { label: 'Registros Nuevos Hoy', value: '12', color: 'blue', change: '+3 desde ayer' },
        { label: 'Actualizaciones', value: '28', color: 'purple', change: '+8 esta sesión' },
        { label: 'Datos Pendientes', value: '5', color: 'orange', change: 'Requiere acción' },
        { label: 'Cambios Validados', value: '156', color: 'emerald', change: 'Este período' },
    ];
</script>

<svelte:head>
    <title>Dashboard | UTAMED</title>
</svelte:head>

<AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/dashboard' }]}>
    <div class="dashboard-page px-4 py-6 md:px-8 max-w-[1600px] mx-auto">
        <!-- Top Section: Welcome & Actions -->
        <header class="dashboard-header mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="welcome-text">
                <h1 class="title text-3xl md:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight">¡Hola, {user.nombre1 || 'Usuario'}! 👋</h1>
                <p class="subtitle text-slate-500 mt-1 text-sm md:text-base lg:text-lg">Aquí tienes un resumen de lo que está pasando hoy en UTAMED.</p>
            </div>
        </header>

        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Activity & Stats -->
            <div class="lg:col-span-12 xl:col-span-9 space-y-6">
                
                <!-- System Operations Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <IllustrationWidget 
                        title="Cambios Registrados" 
                        description="Registro de auditoría: 156 cambios validados en el período actual."
                        color="purple"
                        icon={Users}
                        stats="156 registros"
                    />
                    <IllustrationWidget 
                        title="Backups Sistema" 
                        description="Último backup completado: hace 6 horas. Próximo: en 18 horas."
                        color="orange"
                        icon={Calendar}
                        stats="Automático"
                    />
                </div>

                <!-- Main Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-4">
                    <SoftCard class="stat-card p-5 flex items-center gap-4">
                        <div class="stat-icon bg-blue-50 text-blue-500 w-12 h-12 rounded-2xl flex items-center justify-center shrink-0">
                            <Users size={24} />
                        </div>
                        <div class="stat-info min-w-0 flex-1">
                            <span class="stat-label block text-[10px] md:text-xs lg:text-xs font-bold text-slate-400 uppercase tracking-wider">Usuarios</span>
                            <span class="stat-value text-xl md:text-2xl lg:text-2xl font-bold text-slate-800 truncate block">{stats.usuarios}</span>
                        </div>
                    </SoftCard>

                    <SoftCard class="stat-card p-5 flex items-center gap-4">
                        <div class="stat-icon bg-orange-50 text-orange-500 w-12 h-12 rounded-2xl flex items-center justify-center shrink-0">
                            <BookOpen size={24} />
                        </div>
                        <div class="stat-info min-w-0 flex-1">
                            <span class="stat-label block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cursos</span>
                            <span class="stat-value text-xl font-bold text-slate-800 truncate block">{stats.cursos}</span>
                        </div>
                    </SoftCard>

                    <SoftCard class="stat-card p-5 flex items-center gap-4">
                        <div class="stat-icon bg-purple-50 text-purple-500 w-12 h-12 rounded-2xl flex items-center justify-center shrink-0">
                            <Building2 size={24} />
                        </div>
                        <div class="stat-info min-w-0 flex-1">
                            <span class="stat-label block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Facultades</span>
                            <span class="stat-value text-xl font-bold text-slate-800 truncate block">{stats.facultades}</span>
                        </div>
                    </SoftCard>

                    <SoftCard class="stat-card p-5 flex items-center gap-4">
                        <div class="stat-icon bg-emerald-50 text-emerald-500 w-12 h-12 rounded-2xl flex items-center justify-center shrink-0">
                            <GraduationCap size={24} />
                        </div>
                        <div class="stat-info min-w-0 flex-1">
                            <span class="stat-label block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Carreras</span>
                            <span class="stat-value text-xl font-bold text-slate-800 truncate block">{stats.carreras}</span>
                        </div>
                    </SoftCard>
                </div>

                <!-- Tables & Special Panels -->
                <div class="space-y-6 pt-4">
                    {#if isDocente}
                        <section class="pt-4">
                            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <span class="p-2 bg-indigo-50 rounded-lg text-indigo-500"><Users size={20} /></span>
                                Panel Docente
                            </h2>
                            <DashboardDocente {user} />
                        </section>
                    {/if}

                    {#if isEstudiante}
                        <section class="pt-4">
                            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <span class="p-2 bg-emerald-50 rounded-lg text-emerald-500"><GraduationCap size={20} /></span>
                                Panel Estudiante
                            </h2>
                            <DashboardAlumno {user} />
                        </section>
                    {/if}
                </div>
            </div>

            <!-- Right Column: Info & Shortcuts -->
            <div class="lg:col-span-12 xl:col-span-3 space-y-6">
                <SoftCard class="p-6">
                    <h3 class="font-bold text-slate-800 mb-4 text-lg md:text-xl">Enlaces Rápidos</h3>
                    <div class="flex flex-col gap-2">
                        <Link href="/admin/usuarios" class="flex items-center gap-3 p-3 rounded-xl text-sm md:text-base lg:text-base font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-all">
                            <div class="p-2 bg-slate-100 rounded-lg group-hover:bg-blue-50">
                                <Users size={18} />
                            </div>
                            Usuarios
                        </Link>
                        <Link href="/admin/cursos" class="flex items-center gap-3 p-3 rounded-xl text-sm md:text-base lg:text-base font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-all">
                            <div class="p-2 bg-slate-100 rounded-lg group-hover:bg-blue-50">
                                <BookOpen size={18} />
                            </div>
                            Cursos
                        </Link>
                        <Link href="/admin/planes" class="flex items-center gap-3 p-3 rounded-xl text-sm md:text-base lg:text-base font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-all">
                            <div class="p-2 bg-slate-100 rounded-lg group-hover:bg-blue-50">
                                <ClipboardList size={18} />
                            </div>
                            Planes
                        </Link>
                        <Link href="/settings" class="flex items-center gap-3 p-3 rounded-xl text-sm md:text-base lg:text-base font-medium text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-all">
                            <div class="p-2 bg-slate-100 rounded-lg group-hover:bg-blue-50">
                                <Settings size={18} />
                            </div>
                            Configuración
                        </Link>
                    </div>
                </SoftCard>

                <div class="p-6 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl text-white shadow-xl shadow-blue-500/20">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-white/20 rounded-xl">
                            <Users size={20} />
                        </div>
                        <span class="font-bold">Operaciones Principales</span>
                    </div>
                    <div class="space-y-3 text-sm opacity-90">
                        <div class="flex justify-between">
                            <span>Crear Usuario</span>
                            <span class="font-semibold cursor-pointer hover:opacity-100 transition-opacity">→</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Gestionar Roles</span>
                            <span class="font-semibold cursor-pointer hover:opacity-100 transition-opacity">→</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Validar Datos</span>
                            <span class="font-semibold cursor-pointer hover:opacity-100 transition-opacity">→</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Ver Auditoría</span>
                            <span class="font-semibold cursor-pointer hover:opacity-100 transition-opacity">→</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AppLayout>

<style>
    .dashboard-page {
        min-height: calc(100vh - 64px);
    }
</style>
