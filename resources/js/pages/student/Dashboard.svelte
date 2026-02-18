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

    const parseDate = (d: string | undefined) => d ? new Date(d) : new Date();
    const currentYear = $derived(cursos.length ? parseDate(cursos[0].fecha_inicio).getFullYear() : new Date().getFullYear());
    const byYear = $derived({
        actual: cursos.filter(c => parseDate(c.fecha_inicio).getFullYear() === currentYear),
        anteriores: cursos.filter(c => parseDate(c.fecha_inicio).getFullYear() !== currentYear),
    });
    const semestre1 = $derived(byYear.actual.filter(c => parseDate(c.fecha_inicio).getMonth() <= 5));
    const semestre2 = $derived(byYear.actual.filter(c => parseDate(c.fecha_inicio).getMonth() > 5));
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

        <!-- Academic Year Timeline -->
        <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-slate-900">{currentYear} Año Académico</h2>
                <div class="text-slate-500 text-sm">{byYear.actual.length} cursos</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div class="text-sm font-semibold text-slate-700">Semestre 1</div>
                    {#if semestre1.length > 0}
                        <div class="grid grid-cols-1 gap-4">
                            {#each semestre1 as c (c.id_curso)}
                                <div class="rounded-xl border border-slate-200 shadow-sm p-4 bg-white">
                                    <div class="flex items-center justify-between">
                                        <div class="text-slate-900 font-semibold">{c.asignatura_nombre}</div>
                                        <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600">Actual</span>
                                    </div>
                                    <div class="mt-1 text-slate-600 text-sm">Código {c.cod_curso}</div>
                                    <div class="mt-1 text-slate-500 text-xs">{c.carrera_nombre}</div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <Link href={`/estudiante/cursos/${c.id_curso}`} class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs hover:opacity-90">Ver Curso</Link>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="syllabus">Syllabus</button>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="mensajes">Mensajes</button>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="recursos">Recursos</button>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="actividades">Actividades</button>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-slate-500 text-sm">No hay cursos en este semestre</div>
                    {/if}
                </div>
                <div class="space-y-3">
                    <div class="text-sm font-semibold text-slate-700">Semestre 2</div>
                    {#if semestre2.length > 0}
                        <div class="grid grid-cols-1 gap-4">
                            {#each semestre2 as c (c.id_curso)}
                                <div class="rounded-xl border border-slate-200 shadow-sm p-4 bg-white">
                                    <div class="flex items-center justify-between">
                                        <div class="text-slate-900 font-semibold">{c.asignatura_nombre}</div>
                                        <span class="text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-600">Actual</span>
                                    </div>
                                    <div class="mt-1 text-slate-600 text-sm">Código {c.cod_curso}</div>
                                    <div class="mt-1 text-slate-500 text-xs">{c.carrera_nombre}</div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <Link href={`/estudiante/cursos/${c.id_curso}`} class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs hover:opacity-90">Ver Curso</Link>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="syllabus">Syllabus</button>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="mensajes">Mensajes</button>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="recursos">Recursos</button>
                                        <button class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-xs cursor-not-allowed" aria-disabled="true" data-pending="actividades">Actividades</button>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-slate-500 text-sm">Próximos cursos aparecerán aquí</div>
                    {/if}
                </div>
            </div>
            <div class="mt-6 rounded-xl border border-slate-200 p-4 text-slate-500 text-sm">{currentYear - 1} Año Académico</div>
        </div>
    </div>
</StudentLayout>

<style>
    :global(body) {
        background-color: #f8fafc;
    }
</style>
