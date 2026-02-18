<script lang="ts">
    /**
     * Dashboard del estudiante - Vista Unificada (Hybrid)
     */
    import StudentLayout from '@/layouts/StudentLayout.svelte';
    import type { BreadcrumbItem, SidebarCourse } from '@/types';
    import { Link, page } from '@inertiajs/svelte';
    import { BookOpen, Clock, GraduationCap, TrendingUp, Calendar, AlertCircle, FileText, CheckCircle2 } from 'lucide-svelte';

    /**
     * Props recibidas del servidor.
     */
    interface Props {
        estudiante: {
            id_estudiante: number;
            rut: string;
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
        isAyudante?: boolean;
    }

    let { estudiante, cursos, stats, isAyudante = false }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/estudiante/dashboard' }
    ];

    // Get TA courses from shared props
    let ayudanteCourses = $derived(($page.props.auth?.ayudante_courses as SidebarCourse[]) || []);
    
    // Mock data for UI demo (as per prompt)
    const academicStats = {
        average: 6.5,
        attendance: 92,
        nextDeadline: {
            course: 'Taller de Diseño',
            date: 'Mañana',
            task: 'Entrega Final'
        }
    };

    function getRandomProgress() {
        return Math.floor(Math.random() * (85 - 15) + 15);
    }
</script>

<StudentLayout {breadcrumbs}>
    <div class="p-8 max-w-[1600px] mx-auto space-y-10">
        
        <!-- 1. Academic Overview (Top) -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Hola, {stats.nombre_completo.split(' ')[0]} 👋</h1>
                    <p class="text-slate-500 font-medium">Aquí tienes tu resumen académico de hoy.</p>
                </div>
                <div class="hidden md:flex items-center gap-2 text-sm text-slate-500 bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                    <Calendar size={16} />
                    <span>Semestre 1 - 2026</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Weighted Average -->
                <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <TrendingUp size={80} class="text-emerald-500" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                                <GraduationCap size={20} />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-wider">Promedio Ponderado</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-extrabold text-slate-900">{academicStats.average}</span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Excelencia</span>
                        </div>
                    </div>
                </div>

                <!-- Global Attendance -->
                <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <CheckCircle2 size={80} class="text-blue-500" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                                <Clock size={20} />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-wider">Asistencia Global</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-extrabold text-slate-900">{academicStats.attendance}%</span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">Regular</span>
                        </div>
                    </div>
                </div>

                <!-- Next Deadline -->
                <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                     <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <AlertCircle size={80} class="text-amber-500" />
                    </div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                                <AlertCircle size={20} />
                            </div>
                            <span class="text-sm font-bold text-slate-500 uppercase tracking-wider">Próxima Entrega</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-slate-900 leading-tight">{academicStats.nextDeadline.task}</p>
                            <p class="text-sm text-slate-500 font-medium">{academicStats.nextDeadline.course}</p>
                            <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 border border-amber-100 text-xs font-bold text-amber-700">
                                <Clock size={12} />
                                {academicStats.nextDeadline.date}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Teaching Assistant Zone (Hybrid Feature) -->
        {#if isAyudante && ayudanteCourses.length > 0}
            <section class="animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-2xl p-6 md:p-8">
                     <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">
                            <BookOpen size={24} />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-indigo-950">Panel de Ayudante</h2>
                            <p class="text-indigo-600/80 text-sm font-medium">Gestiona tus ayudantías asignadas</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {#each ayudanteCourses as curso}
                            <div class="bg-white p-5 rounded-xl border border-indigo-100/50 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all group">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                        <BookOpen size={20} />
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-500 border border-slate-200">TA</span>
                                </div>
                                <h3 class="font-bold text-slate-900 text-lg mb-1 leading-tight">{curso.nombre}</h3>
                                <p class="text-xs text-slate-500 font-medium mb-4">Código: {curso.cod_curso || 'N/A'}</p>
                                
                                <div class="grid grid-cols-2 gap-2 mt-auto">
                                    <Link href={`/ayudante/cursos/${curso.id_curso}/notas`} class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition-colors">
                                        <FileText size={14} /> Notas
                                    </Link>
                                    <Link href={`/ayudante/cursos/${curso.id_curso}/actividades`} class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50 transition-colors">
                                        <Calendar size={14} /> Actividad
                                    </Link>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </section>
        {/if}

        <!-- 3. My Learning Path (Main Grid) -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                    <BookOpen class="text-slate-400" size={24} />
                    Mis Cursos <span class="text-slate-400 font-normal text-lg">· Semestre 1 2026</span>
                </h2>
            </div>

            {#if cursos.length > 0}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {#each cursos as curso}
                        {@const progress = getRandomProgress()}
                        <Link 
                            href={`/estudiante/cursos/${curso.id_curso}`}
                            class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group flex flex-col h-full"
                        >
                            <!-- Header -->
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-[10px] font-bold px-2 py-1 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                    {curso.cod_curso}
                                </span>
                                <!-- Download Syllabus Icon (Mock) -->
                                <button class="text-slate-300 hover:text-slate-600 transition-colors p-1" title="Descargar Programa">
                                    <FileText size={16} />
                                </button>
                            </div>

                            <!-- Content -->
                            <div class="mb-4 flex-1">
                                <h3 class="font-bold text-slate-900 text-lg leading-tight mb-1 group-hover:text-blue-600 transition-colors">
                                    {curso.asignatura_nombre}
                                </h3>
                                <p class="text-xs text-slate-500">{curso.carrera_nombre}</p>
                            </div>

                            <!-- Footer / Progress -->
                            <div class="mt-auto">
                                <div class="flex justify-between text-xs font-semibold text-slate-500 mb-1.5">
                                    <span>Progreso</span>
                                    <span>{progress}%</span>
                                </div>
                                <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full bg-blue-500 rounded-full transition-all duration-1000 ease-out group-hover:bg-blue-600"
                                        style="width: {progress}%"
                                    ></div>
                                </div>
                            </div>
                        </Link>
                    {/each}
                </div>
            {:else}
                <div class="bg-slate-50 rounded-xl border border-slate-200 border-dashed p-12 text-center">
                    <div class="bg-white w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                        <BookOpen class="text-slate-300" size={32} />
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-1">No tienes cursos inscritos</h3>
                    <p class="text-slate-500 text-sm">Los cursos en los que te inscribas aparecerán aquí.</p>
                </div>
            {/if}
        </section>

    </div>
</StudentLayout>
