<script lang="ts">
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Link } from '@inertiajs/svelte';
    import { BookOpen, Users, Clock, Award } from 'lucide-svelte';

    interface Props {
        docente: {
            id_docente: number;
            grado?: string;
            titulo?: string;
            cargo?: string;
            id_usuario: number;
        };
        cursos: Array<{
            id_curso: number;
            nombre: string;
            cod_curso: string;
            asignatura_nombre: string;
            carrera_nombre: string;
            fecha_inicio: string;
            fecha_fin?: string;
        }>;
        stats: {
            total_cursos: number;
            nombre_completo: string;
        };
    }

    let { docente, cursos, stats }: Props = $props();

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

        <!-- Cursos Section -->
        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-900">Mis Cursos</h2>
                <Link href="/docente/cursos" class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                    Ver todos →
                </Link>
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
                                            href={`/docente/cursos/${curso.id_curso}/team`}
                                            class="text-blue-600 hover:text-blue-700 font-medium text-sm"
                                        >
                                            Gestionar
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
                    <p class="text-slate-600 mb-2">No tienes cursos asignados</p>
                    <p class="text-slate-500 text-sm">Contacta con administración para que te asigne cursos</p>
                </div>
            {/if}
        </div>
    </div>
</DocenteLayout>

<style>
    :global(body) {
        background-color: #f8fafc;
    }
</style>
