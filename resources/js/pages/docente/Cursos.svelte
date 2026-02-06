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
    import { router, Link } from '@inertiajs/svelte';
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
    import { BookOpen, BookOpenCheck, Loader2, CheckCircle2, FilePlus } from "lucide-svelte";
    import * as Tooltip from "@/components/ui/tooltip";
    import { Badge } from "@/components/ui/badge";

    /**
     * Props recibidas del servidor.
     */
    export let cursos: any[] = []; // Using any[] to bypass strict typing for now if types aren't perfect
    export let availableRoles: any[] = [];
    export let availablePermissions: any[] = [];

    let isTeamModalOpen = false;
    let selectedCurso: any = null;

    function openTeamModal(curso: any) {
        selectedCurso = curso;
        isTeamModalOpen = true;
    }

    function generateProgram(curso: any) {
        if (confirm(`¿Estás seguro de generar el programa para el curso ${curso.cod_curso}?`)) {
            router.post(route('docente.cursos.programa.store', curso.id_curso), {}, {
                preserveScroll: true,
                onSuccess: () => {
                   // Success notification handling if global toast exists
                },
                onError: (errors) => {
                    alert('Error al generar el programa: ' + JSON.stringify(errors));
                }
            });
        }
    }
</script>

<DocenteLayout title="Mis Cursos">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Mis Cursos</h1>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {#each cursos as curso}
                <div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md">
                    <!-- Top accent bar -->
                    <div class="h-1.5 w-full bg-gradient-to-r from-blue-500 to-indigo-500"></div>

                    <div class="p-5">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="font-semibold text-lg text-slate-900 line-clamp-1" title={curso.nombre}>
                                    {curso.nombre}
                                </h3>
                                <p class="text-sm text-slate-500 font-medium">
                                    {curso.cod_asignatura} - Secc. {curso.grupo_indice}
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
                                    <Tooltip.Trigger asChild let:builder>
                                        <button
                                            builders={[builder]}
                                            class="inline-flex items-center justify-center rounded-lg bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 transition-colors"
                                            on:click={() => router.visit(route('docente.cursos.actividades.index', curso.id_curso))}
                                        >
                                            <BookOpen class="h-4 w-4" />
                                        </button>
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
    </div>

    <!-- Modal de Equipo -->
    {#if selectedCurso}
        <CourseTeamModal
            bind:isOpen={isTeamModalOpen}
            curso={selectedCurso}
            {availableRoles}
            {availablePermissions}
        />
    {/if}
</DocenteLayout>


