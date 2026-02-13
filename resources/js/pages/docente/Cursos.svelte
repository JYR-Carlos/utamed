<script lang="ts">
    /**
     * Página de gestión de cursos para docentes.
     * 
     * Lista todos los cursos asignados al docente y permite:
     * - Ver información de cada curso (asignatura, carrera, fechas)
     * - Gestionar equipos de cátedra (docentes auxiliares, ayudantes)
     * - Asignar roles y permisos a miembros del equipo
     * - Acceso a funciones de gestión de actividades y calificaciones
     * 
     * Tablas relacionadas:
     * - curso.curso: Cursos ofertados
     * - curso.seccion: Secciones donde el docente es responsable
     * - usuario.usuario_rol_asignación: Roles en contexto del curso
     * - usuario.usuario_permiso_especial: Permisos especiales
     */
    import { router, Link, usePage } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
    import { BookOpen, BookOpenCheck, Loader2, CheckCircle2, FilePlus, Info } from "lucide-svelte";
    import * as Tooltip from "@/components/ui/tooltip";
    import * as Tabs from "@/components/ui/tabs";
    import { Badge } from "@/components/ui/badge";

    /**
     * Props recibidas del servidor.
     */
    export let cursosSemestre1: any[] = [];
    export let cursosSemestre2: any[] = [];
    export let availableRoles: any[] = [];
    export let availablePermissions: Record<string, any[]> = {};

    let isTeamModalOpen = false;
    let selectedCurso: any = null;

    function openTeamModal(curso: any) {
        selectedCurso = curso;
        isTeamModalOpen = true;
    }

    function generateProgram(curso: any) {
        if (confirm(`¿Estás seguro de generar el programa para el curso ${curso.cod_curso}?`)) {
            const toastId = toast.loading(`Generando programa para ${curso.cod_curso}...`);
            
            router.post(`/docente/cursos/${curso.id_curso}/programa`, {}, {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    // Check if there was a flash error despite "success" request
                    const flashError = page.props.flash?.error;
                    if (flashError) {
                        toast.error(flashError, { id: toastId });
                    } else {
                        toast.success("Programa generado correctamente", { id: toastId });
                    }
                },
                onError: (errors) => {
                    console.error('Error generating program:', errors);
                    const errorMessage = Object.values(errors).flat().join(', ') || "Error al generar el programa";
                    toast.error(errorMessage, { id: toastId });
                }
            });
        }
    }
</script>

<DocenteLayout>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Mis Cursos</h1>
        </div>

        <Tabs.Root value="semestre1" class="w-full">
            <Tabs.List class="grid w-full grid-cols-2 mb-6">
                <Tabs.Trigger value="semestre1" class="data-[state=active]:bg-blue-50 data-[state=active]:text-blue-700">
                    Primer Semestre
                    <Badge variant="secondary" class="ml-2 bg-blue-100 text-blue-800">
                        {cursosSemestre1.length}
                    </Badge>
                </Tabs.Trigger>
                <Tabs.Trigger value="semestre2" class="data-[state=active]:bg-indigo-50 data-[state=active]:text-indigo-700">
                    Segundo Semestre
                    <Badge variant="secondary" class="ml-2 bg-indigo-100 text-indigo-800">
                        {cursosSemestre2.length}
                    </Badge>
                </Tabs.Trigger>
            </Tabs.List>

            <Tabs.Content value="semestre1">
                {#if cursosSemestre1.length === 0}
                    <div class="text-center py-12 text-slate-500">
                        <p>No tienes cursos asignados en el primer semestre</p>
                    </div>
                {:else}
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {#each cursosSemestre1 as curso}
                            <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md">
                                <!-- Top accent bar -->
                                <div class="h-1.5 w-full bg-gradient-to-r from-blue-500 to-indigo-500"></div>

                                <div class="p-5">
                                    <div class="mb-4 flex items-start justify-between">
                                        <div>
                                            <h3 class="font-semibold text-lg text-slate-900 line-clamp-1 cursor-pointer hover:text-blue-600 transition-colors" title={curso.nombre} on:click={() => router.visit(`/docente/cursos/${curso.id_curso}`)}>
                                                {curso.nombre}
                                            </h3>
                                            <p class="text-sm text-slate-500 font-medium">
                                                {curso.cod_asignatura}
                                            </p>
                                        </div>
                                        <span class={`rounded-full px-2.5 py-0.5 text-xs font-medium border ${
                                            curso.es_plantilla 
                                            ? 'bg-amber-50 text-amber-700 border-amber-200' 
                                            : 'bg-slate-50 text-slate-600 border-slate-200'
                                        }`}>
                                            {curso.es_plantilla ? 'Plantilla' : 'Regular'}
                                        </span>
                                    </div>

                                    <div class="space-y-2 mb-6">
                                        <div class="flex items-center text-sm text-slate-600">
                                            <span class="font-medium mr-2">Asignatura:</span>
                                            <span class="truncate" title={curso.asignatura_nombre}>{curso.asignatura_nombre}</span>
                                        </div>
                                        <div class="flex items-center text-sm text-slate-600">
                                            <span class="font-medium mr-2">Calendario:</span>
                                            <span>{new Date(curso.fecha_inicio).toLocaleDateString()} - {new Date(curso.fecha_fin).toLocaleDateString()}</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-100">
                                        <div class="flex gap-2 w-full">
                                            <button
                                                class="flex-1 inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors"
                                                on:click={() => openTeamModal(curso)}
                                            >
                                                <FilePlus class="mr-2 h-4 w-4" />
                                                Equipo
                                            </button>
                                            
                                            <Tooltip.Root>
                                                <Tooltip.Trigger>
                                                {#snippet child({ props })}
                                                    <button
                                                        {...props}
                                                        class="inline-flex items-center justify-center rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 transition-colors"
                                                        on:click={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
                                                    >
                                                        <BookOpen class="h-4 w-4" />
                                                    </button>
                                                {/snippet}
                                            </Tooltip.Trigger>
                                                <Tooltip.Content>
                                                    <p>Gestionar Actividades</p>
                                                </Tooltip.Content>
                                            </Tooltip.Root>
                                        </div>

                                        {#if !curso.tiene_programa}
                                            <button
                                                class="w-full mt-2 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors"
                                                on:click={() => generateProgram(curso)}
                                            >
                                                <BookOpenCheck class="mr-2 h-4 w-4" />
                                                Generar Programa
                                            </button>
                                        {:else}
                                        <Link href="/docente/cursos/{curso.id_curso}/programa">
                                            <Badge
                                                variant="secondary"
                                                class="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border-emerald-200 transition-colors cursor-pointer"
                                            >
                                                <CheckCircle2 class="w-3 h-3 mr-1" />
                                                Programa Generado
                                            </Badge>
                                        </Link>
                                    {/if}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </Tabs.Content>

            <Tabs.Content value="semestre2">
                {#if cursosSemestre2.length === 0}
                    <div class="text-center py-12 text-slate-500">
                        <p>No tienes cursos asignados en el segundo semestre</p>
                    </div>
                {:else}
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {#each cursosSemestre2 as curso}
                            <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md">
                                <!-- Top accent bar -->
                                <div class="h-1.5 w-full bg-gradient-to-r from-blue-500 to-indigo-500"></div>

                                <div class="p-5">
                                    <div class="mb-4 flex items-start justify-between">
                                        <div>
                                            <h3 class="font-semibold text-lg text-slate-900 line-clamp-1 cursor-pointer hover:text-blue-600 transition-colors" title={curso.nombre} on:click={() => router.visit(`/docente/cursos/${curso.id_curso}`)}>
                                                {curso.nombre}
                                            </h3>
                                            <p class="text-sm text-slate-500 font-medium">
                                                {curso.cod_asignatura}
                                            </p>
                                        </div>
                                        <span class={`rounded-full px-2.5 py-0.5 text-xs font-medium border ${
                                            curso.es_plantilla 
                                            ? 'bg-amber-50 text-amber-700 border-amber-200' 
                                            : 'bg-slate-50 text-slate-600 border-slate-200'
                                        }`}>
                                            {curso.es_plantilla ? 'Plantilla' : 'Regular'}
                                        </span>
                                    </div>

                                    <div class="space-y-2 mb-6">
                                        <div class="flex items-center text-sm text-slate-600">
                                            <span class="font-medium mr-2">Asignatura:</span>
                                            <span class="truncate" title={curso.asignatura_nombre}>{curso.asignatura_nombre}</span>
                                        </div>
                                        <div class="flex items-center text-sm text-slate-600">
                                            <span class="font-medium mr-2">Calendario:</span>
                                            <span>{new Date(curso.fecha_inicio).toLocaleDateString()} - {new Date(curso.fecha_fin).toLocaleDateString()}</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-100">
                                        <div class="flex gap-2 w-full">
                                            <button
                                                class="flex-1 inline-flex items-center justify-center rounded-lg bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition-colors"
                                                on:click={() => openTeamModal(curso)}
                                            >
                                                <FilePlus class="mr-2 h-4 w-4" />
                                                Equipo
                                            </button>
                                            
                                            <Tooltip.Root>
                                                <Tooltip.Trigger>
                                                {#snippet child({ props })}
                                                    <button
                                                        {...props}
                                                        class="inline-flex items-center justify-center rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 transition-colors"
                                                        on:click={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
                                                    >
                                                        <BookOpen class="h-4 w-4" />
                                                    </button>
                                                {/snippet}
                                            </Tooltip.Trigger>
                                                <Tooltip.Content>
                                                    <p>Gestionar Actividades</p>
                                                </Tooltip.Content>
                                            </Tooltip.Root>
                                        </div>

                                        {#if !curso.tiene_programa}
                                            <button
                                                class="w-full mt-2 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition-colors"
                                                on:click={() => generateProgram(curso)}
                                            >
                                                <BookOpenCheck class="mr-2 h-4 w-4" />
                                                Generar Programa
                                            </button>
                                        {:else}
                                        <Link href="/docente/cursos/{curso.id_curso}/programa">
                                            <Badge
                                                variant="secondary"
                                                class="bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border-emerald-200 transition-colors cursor-pointer"
                                            >
                                                <CheckCircle2 class="w-3 h-3 mr-1" />
                                                Programa Generado
                                            </Badge>
                                        </Link>
                                    {/if}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </Tabs.Content>
        </Tabs.Root>
    </div>

    <!-- Modal de Equipo -->
    {#if selectedCurso}
        <CourseTeamModal
            bind:isOpen={isTeamModalOpen}
            onClose={() => { isTeamModalOpen = false; selectedCurso = null; }}
            curso={selectedCurso}
            urlPrefix="docente"
            {availableRoles}
            {availablePermissions}
        />
    {/if}
</DocenteLayout>
