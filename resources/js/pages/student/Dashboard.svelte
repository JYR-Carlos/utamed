<script lang="ts">
    /**
     * Dashboard del estudiante.
     */
    import StudentLayout from '@/layouts/StudentLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Link } from '@inertiajs/svelte';
    import { BookOpen, User, Clock } from 'lucide-svelte';

    /**
     * Props recibidas del servidor.
     */
    interface Props {
        /** Información del estudiante autenticado */
        estudiante: {
            id_estudiante: number;
            rut: string;
            id_usuario: number;
        };
        /** Cursos inscritos */
        cursos: Array<{
            id_curso: number;
            nombre: string;
            cod_curso: string;
            asignatura_nombre: string;
            carrera_nombre: string;
            fecha_inicio: string;
            fecha_fin?: string;
        }>;
        /** Estadísticas */
        stats: {
            total_cursos: number;
            nombre_completo: string;
        };
        /** Es ayudante? */
        isAyudante?: boolean;
    }

    let { estudiante, cursos, stats, isAyudante = false }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/estudiante/dashboard' }
    ];
</script>

<StudentLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Bienvenido, {stats.nombre_completo}</h1>
            <p class="text-slate-600">Revisa tus cursos y actividades pendientes</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600 text-sm font-medium">Cursos Inscritos</p>
                        <p class="text-3xl font-bold text-slate-900 mt-2">{stats.total_cursos}</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <BookOpen class="text-blue-600" size={24} />
                    </div>
                </div>
            </div>

            <!-- Add more stats if needed -->
            {#if isAyudante}
                <div class="bg-indigo-50 rounded-lg border border-indigo-100 p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-indigo-600 text-sm font-medium">Rol Ayudante Detectado</p>
                            <Link href="/ayudante/dashboard" class="text-lg font-bold text-indigo-900 mt-1 hover:underline flex items-center gap-2">
                                Ir al Panel de Ayudante →
                            </Link>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-lg">
                            <BookOpen class="text-indigo-600" size={24} />
                        </div>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Cursos Section -->
        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-900">Mis Cursos</h2>
                <!-- Link to all courses if implemented -->
                <!-- <Link href="/estudiante/cursos" class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Ver todos →
                </Link> -->
            </div>

            {#if cursos.length > 0}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Asignatura</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Código</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Carrera</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Inicio</th>
                                <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each cursos as curso (curso.id_curso)}
                                <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors">
                                    <td class="py-3 px-4 text-slate-900 font-medium">{curso.asignatura_nombre}</td>
                                    <td class="py-3 px-4 text-slate-600">{curso.cod_curso}</td>
                                    <td class="py-3 px-4 text-slate-600">{curso.carrera_nombre}</td>
                                    <td class="py-3 px-4 text-slate-600">
                                        {new Date(curso.fecha_inicio).toLocaleDateString('es-CL')}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <Link
                                            href={`/estudiante/cursos/${curso.id_curso}`}
                                            class="text-blue-600 hover:text-blue-700 font-medium text-sm"
                                        >
                                            Ver Curso
                                        </Link>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {:else}
                <div class="text-center py-12">
                    <BookOpen class="mx-auto text-slate-400 mb-3" size={32} />
                    <p class="text-slate-600 mb-2">No tienes cursos inscritos</p>
                </div>
            {/if}
        </div>
    </div>
</StudentLayout>

<style>
    :global(body) {
        background-color: #f8fafc;
    }
</style>
