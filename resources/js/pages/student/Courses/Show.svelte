<script lang="ts">
    import StudentLayout from '@/layouts/StudentLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Undo2, BookOpen, Eye } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';
    import * as Card from '@/components/ui/card';

    interface Props {
        curso: {
            id_curso: number;
            nombre: string;
            cod_curso: string;
            fecha_inicio?: string;
            fecha_fin?: string;
            asignatura?: {
                id_asignatura: number;
                nombre: string;
                cod_asignatura: string;
                descripcion?: string;
                creditos_sct?: number;
            };
            carrera?: {
                id_carrera: number;
                nombre: string;
            };
            secciones?: any[];
        };
    }

    let { curso }: Props = $props();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/estudiante/dashboard' },
        { title: 'Mis Cursos', href: '/estudiante/cursos' },
        { title: curso.nombre, href: `/estudiante/cursos/${curso.id_curso}` }
    ];
</script>

<StudentLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <div class="mb-8 flex items-center gap-4">
            <Link href="/estudiante/cursos" class="bg-white p-2 rounded-full shadow-sm hover:shadow-md transition-shadow text-slate-600">
                <Undo2 size={20} />
            </Link>
            <div>
                <h1 class="text-3xl font-bold text-slate-900">{curso.nombre}</h1>
                <p class="text-slate-600">{curso.asignatura?.nombre}</p>
            </div>
        </div>

        <!-- Información del Curso -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <Card.Root>
                <Card.Content class="p-6">
                    <p class="text-sm font-medium text-slate-500 mb-1">Código de Curso</p>
                    <p class="text-2xl font-bold text-slate-900">{curso.cod_curso}</p>
                </Card.Content>
            </Card.Root>

            <Card.Root>
                <Card.Content class="p-6">
                    <p class="text-sm font-medium text-slate-500 mb-1">Carrera</p>
                    <p class="text-lg font-semibold text-slate-900">{curso.carrera?.nombre || 'N/A'}</p>
                </Card.Content>
            </Card.Root>

            <Card.Root>
                <Card.Content class="p-6">
                    <p class="text-sm font-medium text-slate-500 mb-1">Créditos SCT</p>
                    <p class="text-2xl font-bold text-slate-900">{curso.asignatura?.creditos_sct || '-'}</p>
                </Card.Content>
            </Card.Root>
        </div>

        <!-- Descripción de la Asignatura -->
        {#if curso.asignatura?.descripcion}
            <Card.Root class="mb-8">
                <Card.Content class="p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-3">Descripción</h2>
                    <p class="text-slate-700 leading-relaxed">{curso.asignatura.descripcion}</p>
                </Card.Content>
            </Card.Root>
        {/if}

        <!-- Card para ver el programa -->
        <Card.Root class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200">
            <Card.Content class="p-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <BookOpen class="text-blue-600" size={28} />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Programa de Cátedra</h3>
                            <p class="text-sm text-slate-600">Consulta los objetivos, contenidos y evaluación del curso</p>
                        </div>
                    </div>
                    <Link
                        href={`/estudiante/cursos/${curso.id_curso}/programa`}
                        class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
                    >
                        <Eye size={18} />
                        Ver Programa
                    </Link>
                </div>
            </Card.Content>
        </Card.Root>

        <!-- Secciones del Curso -->
        {#if curso.secciones && curso.secciones.length > 0}
            <Card.Root>
                <Card.Content class="p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Secciones</h2>
                    <div class="space-y-3">
                        {#each curso.secciones as seccion}
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded border border-slate-200">
                                <div>
                                    <p class="font-medium text-slate-900">{seccion.tipo_seccion?.tipo || 'Sección'}</p>
                                    {#if seccion.docente}
                                        <p class="text-sm text-slate-600">{seccion.docente.nombre_completo}</p>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>
                </Card.Content>
            </Card.Root>
        {/if}
    </div>
</StudentLayout>
