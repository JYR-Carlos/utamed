<script lang="ts">
    import StudentLayout from '@/layouts/StudentLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Link } from '@inertiajs/svelte';
    import { BookOpen } from 'lucide-svelte';

    interface Props {
        cursos: Array<{
            id_curso: number;
            nombre: string;
            cod_curso: string;
            asignatura_nombre: string;
            carrera_nombre: string;
            fecha_inicio: string;
            fecha_fin?: string;
        }>;
    }

    let { cursos }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/estudiante/dashboard' },
        { title: 'Mis Cursos', href: '/estudiante/cursos' }
    ];
</script>

<StudentLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Mis Cursos</h1>
            <p class="text-slate-600">Listado de todos tus cursos inscritos</p>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
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
