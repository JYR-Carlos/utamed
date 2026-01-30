<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { BookOpen, Users, GraduationCap, Calendar, Clock, ArrowRight } from 'lucide-svelte';
    import { page } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { type BreadcrumbItem } from '@/types';
    import DashboardDocente from './dashboards/DashboardDocente.svelte';
    import DashboardAlumno from './dashboards/DashboardAlumno.svelte';
    import DashboardAyudante from './dashboards/DashboardAyudante.svelte';

    interface Stats {
        usuarios: number;
        cursos: number;
        facultades: number;
        carreras: number;
    }

    interface Props {
        stats: Stats;
    }

    let { stats }: Props = $props();
    let user = $derived($page.props.auth.user);
    // Cast roles to array of strings safely
    let roles = $derived(($page.props.auth.roles as string[]) || []);
    
    let isDocente = $derived(roles.includes('Docente'));
    let isEstudiante = $derived(roles.includes('Estudiante'));
    let isAyudante = $derived(roles.includes('Ayudante'));
    let isAdmin = $derived(roles.includes('Super Admin') || roles.includes('Administrador') || roles.length === 0);
</script>

<svelte:head>
    <title>Dashboard</title>
</svelte:head>

<AdminLayout>
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    Hola, {user.nombre1 ? user.nombre1 : (user.username || 'Usuario')} 👋
                </h1>
                <p class="page-description">
                    {#if roles.length > 0}
                        Tus roles: {roles.join(', ')}
                    {:else}
                        Bienvenido al Panel de Administración UtaMed.
                    {/if}
                </p>
            </div>
        </div>

        <!-- Role Specific Dashboards (Stacked for multi-role users) -->
        
        {#if isDocente}
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="text-2xl">👨‍🏫</span> Panel Docente
                </h2>
                <DashboardDocente {user} />
            </div>
        {/if}

        {#if isAyudante}
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="text-2xl">📚</span> Panel Ayudante
                </h2>
                <DashboardAyudante {user} />
            </div>
        {/if}

        {#if isEstudiante}
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="text-2xl">🎓</span> Panel Estudiante
                </h2>
                <DashboardAlumno {user} />
            </div>
        {/if}

        <!-- Admin Stats & Quick Actions (Only for Admin or fallback) -->
        {#if isAdmin}
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
                <Card class="bg-white border text-card-foreground shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-[#374151]">Usuarios Registrados</CardTitle>
                        <Users class="h-4 w-4 text-[#6b7280]" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-[#111827]">{stats.usuarios}</div>
                        <p class="text-xs text-[#6b7280]">Total en el sistema</p>
                    </CardContent>
                </Card>
                <Card class="bg-white border text-card-foreground shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-[#374151]">Cursos</CardTitle>
                        <BookOpen class="h-4 w-4 text-[#6b7280]" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-[#111827]">{stats.cursos}</div>
                        <p class="text-xs text-[#6b7280]">Cursos creados</p>
                    </CardContent>
                </Card>
                <Card class="bg-white border text-card-foreground shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-[#374151]">Facultades</CardTitle>
                        <Users class="h-4 w-4 text-[#6b7280]" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-[#111827]">{stats.facultades}</div>
                        <p class="text-xs text-[#6b7280]">Facultades activas</p>
                    </CardContent>
                </Card>
                <Card class="bg-white border text-card-foreground shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium text-[#374151]">Carreras</CardTitle>
                        <GraduationCap class="h-4 w-4 text-[#6b7280]" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-[#111827]">{stats.carreras}</div>
                        <p class="text-xs text-[#6b7280]">Carreras impartidas</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 md:grid-cols-1">
                <!-- Quick Actions -->
                <Card class="bg-white border text-card-foreground shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-[#111827] text-lg">Accesos Rápidos</CardTitle>
                        <CardDescription class="text-[#6b7280]">
                            Gestión frecuente del sistema.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 md:grid-cols-3">
                        <Button 
                            variant="outline" 
                            class="h-auto py-4 justify-start px-4 group border-[#d1d5db] text-[#374151] hover:bg-gray-50 hover:text-[#111827]" 
                            href="/admin/usuarios"
                        >
                            <div class="flex flex-col items-start gap-1">
                                <div class="flex items-center gap-2 font-medium">
                                    <Users class="h-4 w-4" />
                                    Administrar Usuarios
                                </div>
                                <span class="text-xs font-normal text-muted-foreground">Crear y editar usuarios</span>
                            </div>
                        </Button>
                        <Button 
                            variant="outline" 
                            class="h-auto py-4 justify-start px-4 group border-[#d1d5db] text-[#374151] hover:bg-gray-50 hover:text-[#111827]" 
                            href="/admin/cursos"
                        >
                            <div class="flex flex-col items-start gap-1">
                                <div class="flex items-center gap-2 font-medium">
                                    <BookOpen class="h-4 w-4" />
                                    Cursos
                                </div>
                                <span class="text-xs font-normal text-muted-foreground">Gestionar cursos académicos</span>
                            </div>
                        </Button>
                        <Button 
                            variant="outline" 
                            class="h-auto py-4 justify-start px-4 group border-[#d1d5db] text-[#374151] hover:bg-gray-50 hover:text-[#111827]" 
                            href="/admin/planes"
                        >
                            <div class="flex flex-col items-start gap-1">
                                <div class="flex items-center gap-2 font-medium">
                                    <GraduationCap class="h-4 w-4" />
                                    Planes de Estudio
                                </div>
                                <span class="text-xs font-normal text-muted-foreground">Mallas y asignaturas</span>
                            </div>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        {/if}
    </div>
</AdminLayout>

<style>
    .page-container {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 0.25rem 0;
    }

    .page-description {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0;
    }
</style>
