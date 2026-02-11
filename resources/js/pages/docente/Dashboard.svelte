<script lang="ts">
    /**
     * Dashboard del docente.
     * 
     * Página principal para docentes que muestra:
     * - Información personal (nombre, grado, cargo)
     * - Estadísticas de cursos asignados
     * - Lista de cursos con información de asignatura y carrera
     * - Acceso rápido a funciones de gestión de cursos
     * 
     * Tabla relacionada:
     * - usuario.docente: Perfil del docente autenticado
     * - curso.curso: Cursos ofertados
     * - curso.seccion: Secciones donde el docente es responsable
     */
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Link } from '@inertiajs/svelte';
    import { BookOpen, Users, Clock, Award } from 'lucide-svelte';

    /**
     * Props recibidas del servidor.
     */
    interface Props {
        /** Información del docente autenticado */
        docente: {
            id_docente: number;
            grado?: string;
            titulo?: string;
            cargo?: string;
            id_usuario: number;
        };
        /** Estadísticas y datos globales del docente */
        stats: {
            total_cursos: number;
            nombre_completo: string;
        };
    }

    let { docente, stats }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/docente/dashboard' }
    ];
</script>

<DocenteLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Bienvenido, {stats.nombre_completo}</h1>
            <p class="text-slate-600">Gestiona tus cursos y asigna ayudantes</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-medium">Cursos Asignados</p>
                        <p class="text-3xl font-bold text-slate-900 mt-2">{stats.total_cursos}</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <BookOpen class="text-blue-600" size={24} />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-medium">Grado Académico</p>
                        <p class="text-xl font-bold text-slate-900 mt-2">{docente.grado || 'N/A'}</p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <Award class="text-purple-600" size={24} />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-medium">Cargo</p>
                        <p class="text-lg font-bold text-slate-900 mt-2">{docente.cargo || 'N/A'}</p>
                    </div>
                    <div class="bg-green-50 p-3 rounded-lg">
                        <Users class="text-green-600" size={24} />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-medium">Título</p>
                        <p class="text-sm font-bold text-slate-900 mt-2">{docente.titulo || 'N/A'}</p>
                    </div>
                    <div class="bg-orange-50 p-3 rounded-lg">
                        <Clock class="text-orange-600" size={24} />
                    </div>
                </div>
            </div>
        </div>


        <!-- Quick Actions Section -->
        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-900">Acciones Rápidas</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Ver Cursos -->
                <Link href="/docente/cursos" class="group block p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border border-blue-200 hover:border-blue-300 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-3 bg-blue-100 rounded-lg group-hover:bg-blue-200 transition-colors">
                            <BookOpen class="text-blue-600" size={24} />
                        </div>
                        <span class="text-2xl font-bold text-blue-900">{stats.total_cursos}</span>
                    </div>
                    <h3 class="font-semibold text-lg text-slate-900 mb-1">Mis Cursos</h3>
                    <p class="text-sm text-slate-600">Gestiona tus cursos, programas y equipos</p>
                </Link>

                <!-- Placeholder para futuras funcionalidades -->
                <div class="p-6 bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg border border-slate-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-3 bg-slate-200 rounded-lg">
                            <Clock class="text-slate-500" size={24} />
                        </div>
                    </div>
                    <h3 class="font-semibold text-lg text-slate-900 mb-1">Actividades Recientes</h3>
                    <p class="text-sm text-slate-600">Próximamente: historial de actividades</p>
                </div>
            </div>
        </div>
    </div>
</DocenteLayout>

<style>
    :global(body) {
        background-color: #f8fafc;
    }
</style>
