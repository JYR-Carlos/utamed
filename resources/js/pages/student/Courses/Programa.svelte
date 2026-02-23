<script lang="ts">
    import StudentLayout from '@/layouts/StudentLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Undo2, BookOpen } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';
    import * as Card from '@/components/ui/card';

    interface Props {
        programa: {
            id_programa: number;
            id_curso: number;
            version: number;
            estado: string;
            secciones: Array<{
                nombre_seccion: string;
                numeral_romano?: string;
                orden: number;
                contenidos: Array<{
                    texto_contenido: string;
                    orden_item: number;
                }>;
            }>;
            creado_por: string;
            fecha_creacion: string;
        } | null;
        curso: {
            id_curso: number;
            nombre: string;
            cod_curso: string;
            asignatura: any;
            carrera: any;
        };
    }

    let { programa, curso }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/estudiante/dashboard' },
        { title: 'Mis Cursos', href: '/estudiante/cursos' },
        { title: curso.nombre, href: `/estudiante/cursos/${curso.id_curso}` },
        { title: 'Programa', href: `/estudiante/cursos/${curso.id_curso}/programa` }
    ];
</script>

<StudentLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <!-- Encabezado -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 mb-2">Programa de Cátedra</h1>
                <p class="text-slate-600">{curso.asignatura?.nombre} - {curso.cod_curso}</p>
                <p class="text-sm text-slate-500">{curso.carrera?.nombre}</p>
            </div>
        </div>

        {#if programa === null}
            <!-- Mensaje cuando no hay programa disponible -->
            <Card.Root class="mb-8 bg-amber-50 border-amber-200">
                <Card.Content class="p-8">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <BookOpen class="text-amber-600" size={32} />
                        </div>
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-amber-900 mb-2">Programa aún no disponible</h2>
                            <p class="text-amber-800 text-sm mb-3">
                                El programa de cátedra para este curso aún no ha sido aprobado por los administradores.
                            </p>
                            <p class="text-amber-700 text-xs">
                                Por favor, intenta más tarde o contacta con la coordinación del programa.
                            </p>
                        </div>
                    </div>
                </Card.Content>
            </Card.Root>
        {:else}
            <!-- Información del programa -->
            <Card.Root class="mb-8">
                <Card.Content class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Versión</p>
                            <p class="text-lg font-semibold text-slate-900">{programa.version}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Preparado por</p>
                            <p class="text-lg font-semibold text-slate-900">{programa.creado_por}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-500">Fecha de Aprobación</p>
                            <p class="text-lg font-semibold text-slate-900">
                                {new Date(programa.fecha_creacion).toLocaleDateString('es-CL')}
                            </p>
                        </div>
                    </div>
                </Card.Content>
            </Card.Root>

            <!-- Secciones del programa -->
            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-slate-900 mb-4">Contenidos</h2>
                
                {#if programa.secciones && programa.secciones.length > 0}
                    {#each programa.secciones as seccion, index}
                        <Card.Root>
                            <Card.Content class="p-6">
                                <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
                                    <span class="flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                                        {seccion.numeral_romano || index + 1}
                                    </span>
                                    {seccion.nombre_seccion}
                                </h3>

                                {#if seccion.contenidos && seccion.contenidos.length > 0}
                                    <div class="space-y-3">
                                        {#each seccion.contenidos as contenido}
                                            <div class="bg-slate-50 p-4 rounded border border-slate-200">
                                                <p class="text-slate-700 whitespace-pre-wrap text-sm leading-relaxed">
                                                    {contenido.texto_contenido || '(vacío)'}
                                                </p>
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <p class="text-slate-500 italic text-sm">Sin contenido especificado</p>
                                {/if}
                            </Card.Content>
                        </Card.Root>
                    {/each}
                {:else}
                    <div class="text-center py-12 bg-slate-50 rounded-lg border border-slate-200">
                        <BookOpen class="mx-auto text-slate-400 mb-3" size={40} />
                        <p class="text-slate-600">El programa no tiene contenido disponible</p>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- Botón volver -->
        <div class="mt-8 flex justify-center">
            <Link
                href={`/estudiante/cursos/${curso.id_curso}`}
                class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium"
            >
                <Undo2 size={18} />
                Volver al Curso
            </Link>
        </div>
    </div>
</StudentLayout>
