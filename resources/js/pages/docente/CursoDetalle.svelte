<script lang="ts">
    /**
     * Página de detalles de curso para docentes.
     * 
     * Muestra información completa del curso incluyendo:
     * - Información general (nombre, código, asignatura, plan, carrera)
     * - Fechas y período académico
     * - Estadísticas de estudiantes inscritos
     * - Lista de secciones con tipo y cantidad de estudiantes
     */
    import { router } from '@inertiajs/svelte';
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import { Button } from "@/components/ui/button";
    import * as Card from "@/components/ui/card";
    import { Badge } from "@/components/ui/badge";
    import { 
        ArrowLeft, 
        Calendar, 
        BookOpen, 
        Users, 
        GraduationCap,
        Building2,
        FileText,
        BookOpenCheck
    } from "lucide-svelte";

    interface Seccion {
        id_seccion: number;
        tipo: string;
        total_estudiantes: number;
    }

    interface Curso {
        id_curso: number;
        nombre: string;
        cod_curso: string;
        fecha_inicio: string;
        fecha_fin: string;
        agno_real: number;
        semestre_real: number;
        estado_interno: string;
        es_plantilla: boolean;
        tiene_programa: boolean;
        asignatura: {
            nombre: string;
            cod_asignatura: string;
            descripcion: string;
        };
        plan: {
            nombre: string;
            carrera: string;
        };
        secciones: Seccion[];
        total_estudiantes: number;
    }

    export let curso: Curso;

    function goBack() {
        router.visit('/docente/cursos');
    }

    function formatDate(dateString: string) {
        return new Date(dateString).toLocaleDateString('es-CL', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
</script>

<DocenteLayout>
    <div class="space-y-6">
        <!-- Header con navegación -->
        <div class="flex items-center gap-4">
            <Button variant="ghost" onclick={goBack} class="gap-2">
                <ArrowLeft class="h-4 w-4" />
                Volver
            </Button>
            <div class="flex-1">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{curso.nombre}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    {curso.cod_curso} • {curso.asignatura.cod_asignatura}
                </p>
            </div>
            {#if curso.tiene_programa}
                <Button 
                    variant="outline" 
                    onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/programa`)}
                    class="gap-2"
                >
                    <BookOpenCheck class="h-4 w-4" />
                    Ver Programa
                </Button>
            {/if}
        </div>

        <!-- Grid de información -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- Card: Información General -->
            <Card.Root class="col-span-full lg:col-span-2">
                <Card.Header>
                    <Card.Title class="flex items-center gap-2">
                        <BookOpen class="h-5 w-5 text-blue-600" />
                        Información General
                    </Card.Title>
                </Card.Header>
                <Card.Content class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Asignatura</p>
                            <p class="text-base font-semibold text-slate-900">{curso.asignatura.nombre}</p>
                            <p class="text-sm text-slate-600">{curso.asignatura.cod_asignatura}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Código del Curso</p>
                            <p class="text-base font-semibold text-slate-900">{curso.cod_curso}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Plan de Estudios</p>
                            <p class="text-base font-semibold text-slate-900">{curso.plan.nombre}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500 flex items-center gap-1">
                                <Building2 class="h-4 w-4" />
                                Carrera
                            </p>
                            <p class="text-base font-semibold text-slate-900">{curso.plan.carrera}</p>
                        </div>
                    </div>
                    {#if curso.asignatura.descripcion}
                        <div class="pt-4 border-t">
                            <p class="text-sm font-medium text-slate-500 mb-2">Descripción</p>
                            <p class="text-sm text-slate-700">{curso.asignatura.descripcion}</p>
                        </div>
                    {/if}
                </Card.Content>
            </Card.Root>

            <!-- Card: Período Académico -->
            <Card.Root>
                <Card.Header>
                    <Card.Title class="flex items-center gap-2">
                        <Calendar class="h-5 w-5 text-indigo-600" />
                        Período Académico
                    </Card.Title>
                </Card.Header>
                <Card.Content class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Año</p>
                        <p class="text-lg font-semibold text-slate-900">{curso.agno_real}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Semestre</p>
                        <Badge variant="secondary" class="text-base">
                            {curso.semestre_real === 1 ? 'Primer Semestre' : 'Segundo Semestre'}
                        </Badge>
                    </div>
                    <div class="pt-3 border-t">
                        <p class="text-sm font-medium text-slate-500 mb-1">Inicio</p>
                        <p class="text-sm text-slate-700">{formatDate(curso.fecha_inicio)}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Término</p>
                        <p class="text-sm text-slate-700">{formatDate(curso.fecha_fin)}</p>
                    </div>
                    <div class="pt-3 border-t">
                        <p class="text-sm font-medium text-slate-500 mb-1">Estado</p>
                        <Badge variant={curso.es_plantilla ? "outline" : "default"}>
                            {curso.es_plantilla ? 'Plantilla' : 'Activo'}
                        </Badge>
                    </div>
                </Card.Content>
            </Card.Root>

            <!-- Card: Estadísticas -->
            <Card.Root class="col-span-full lg:col-span-1">
                <Card.Header>
                    <Card.Title class="flex items-center gap-2">
                        <Users class="h-5 w-5 text-emerald-600" />
                        Estadísticas
                    </Card.Title>
                </Card.Header>
                <Card.Content class="space-y-4">
                    <div class="text-center p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg">
                        <p class="text-sm font-medium text-slate-600 mb-2">Total de Estudiantes</p>
                        <p class="text-4xl font-bold text-blue-600">{curso.total_estudiantes}</p>
                    </div>
                    <div class="text-center p-4 bg-slate-50 rounded-lg">
                        <p class="text-sm font-medium text-slate-600 mb-1">Secciones</p>
                        <p class="text-2xl font-bold text-slate-900">{curso.secciones.length}</p>
                    </div>
                </Card.Content>
            </Card.Root>

            <!-- Card: Secciones -->
            <Card.Root class="col-span-full lg:col-span-2">
                <Card.Header>
                    <Card.Title class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5 text-purple-600" />
                        Secciones del Curso
                    </Card.Title>
                </Card.Header>
                <Card.Content>
                    {#if curso.secciones.length === 0}
                        <div class="text-center py-8 text-slate-500">
                            <p>No hay secciones registradas para este curso</p>
                        </div>
                    {:else}
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-slate-200">
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Tipo de Sección</th>
                                        <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Estudiantes Inscritos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each curso.secciones as seccion}
                                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                            <td class="py-3 px-4">
                                                <div class="flex items-center gap-2">
                                                    <Badge variant="outline">{seccion.tipo}</Badge>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <Users class="h-4 w-4 text-slate-400" />
                                                    <span class="font-medium text-slate-900">{seccion.total_estudiantes}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </Card.Content>
            </Card.Root>
        </div>

        <!-- Acciones rápidas -->
        <div class="flex gap-3 pt-4 border-t">
            <Button 
                onclick={() => router.visit(`/docente/cursos/${curso.id_curso}/actividades`)}
                class="gap-2"
            >
                <FileText class="h-4 w-4" />
                Gestionar Actividades
            </Button>
        </div>
    </div>
</DocenteLayout>
