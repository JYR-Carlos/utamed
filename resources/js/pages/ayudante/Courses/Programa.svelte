<script lang="ts">
    import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
    import type { BreadcrumbItem } from '@/types';
    import { Undo2, BookOpen, Edit2, Save, X } from 'lucide-svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import * as Card from '@/components/ui/card';
    import * as Button from '@/components/ui/button';

    interface ContenidoPrograma {
        id_contenido_programa?: number;
        texto_contenido: string | null;
        orden_item: number;
    }

    interface SeccionPrograma {
        id_estructura_programa?: number;
        nombre_seccion: string;
        numeral_romano?: string;
        orden: number;
        contenidos_programa: ContenidoPrograma[];
    }

    interface Props {
        programa: {
            id_programa: number;
            id_curso: number;
            version: number;
            estado: string;
            secciones: SeccionPrograma[];
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
        mode?: 'view' | 'edit';
    }

    let { programa, curso, mode = 'view' }: Props = $props();

    let isEditing = $state(mode === 'edit' && programa && programa.estado !== 'APROBADO');
    let editedSections = $state<SeccionPrograma[]>([]);
    let isSaving = $state(false);

    // Inicializar formulario para edición
    const form = useForm({
        secciones: [] as Array<{
            nombre_seccion: string;
            numeral_romano: string;
            orden: number;
            contenidos: Array<{ texto_contenido: string; orden_item: number }>;
        }>
    });

    $effect.pre(() => {
        if (isEditing && programa) {
            editedSections = JSON.parse(JSON.stringify(programa.secciones));
            $form.secciones = editedSections.map(s => ({
                nombre_seccion: s.nombre_seccion,
                numeral_romano: s.numeral_romano ?? '',
                orden: s.orden,
                contenidos: s.contenidos_programa.map(c => ({
                    texto_contenido: c.texto_contenido ?? '',
                    orden_item: c.orden_item
                }))
            }));
        }
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/ayudante/dashboard' },
        { title: 'Mis Cursos', href: '/ayudante/cursos' },
        { title: curso.nombre, href: `/ayudante/cursos/${curso.id_curso}` },
        { title: 'Programa', href: `/ayudante/cursos/${curso.id_curso}/programa` }
    ];

    function toggleEditMode() {
        if (programa && programa.estado !== 'APROBADO') {
            isEditing = !isEditing;
            if (isEditing) {
                editedSections = JSON.parse(JSON.stringify(programa.secciones));
                $form.secciones = editedSections.map(s => ({
                    nombre_seccion: s.nombre_seccion,
                    numeral_romano: s.numeral_romano ?? '',
                    orden: s.orden,
                    contenidos: s.contenidos_programa.map(c => ({
                        texto_contenido: c.texto_contenido ?? '',
                        orden_item: c.orden_item
                    }))
                }));
            }
        }
    }

    function updateSectionName(index: number, value: string) {
        if (editedSections[index]) {
            editedSections[index].nombre_seccion = value;
            $form.secciones[index].nombre_seccion = value;
        }
    }

    function updateSectionNumeral(index: number, value: string) {
        if (editedSections[index]) {
            editedSections[index].numeral_romano = value;
            $form.secciones[index].numeral_romano = value;
        }
    }

    function updateContentText(sectionIndex: number, contentIndex: number, value: string) {
        if (editedSections[sectionIndex]?.contenidos_programa[contentIndex]) {
            editedSections[sectionIndex].contenidos_programa[contentIndex].texto_contenido = value;
            $form.secciones[sectionIndex].contenidos[contentIndex].texto_contenido = value;
        }
    }

    function addContent(sectionIndex: number) {
        if (editedSections[sectionIndex]) {
            const newOrder = (editedSections[sectionIndex].contenidos_programa?.length ?? 0) + 1;
            editedSections[sectionIndex].contenidos_programa.push({
                texto_contenido: '',
                orden_item: newOrder
            });
            $form.secciones[sectionIndex].contenidos.push({
                texto_contenido: '',
                orden_item: newOrder
            });
        }
    }

    function removeContent(sectionIndex: number, contentIndex: number) {
        if (editedSections[sectionIndex]) {
            editedSections[sectionIndex].contenidos_programa.splice(contentIndex, 1);
            $form.secciones[sectionIndex].contenidos.splice(contentIndex, 1);
            // Recalculate order
            editedSections[sectionIndex].contenidos_programa.forEach((c, i) => {
                c.orden_item = i + 1;
                $form.secciones[sectionIndex].contenidos[i].orden_item = i + 1;
            });
        }
    }

    async function handleSaveEdits() {
        isSaving = true;
        $form.post(`/ayudante/cursos/${curso.id_curso}/programa`, {
            onSuccess: () => {
                isEditing = false;
                isSaving = false;
            },
            onError: () => {
                isSaving = false;
            }
        });
    }
</script>

<AyudanteLayout {breadcrumbs}>
    <div class="container mx-auto px-6 py-8">
        <!-- Encabezado -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 mb-2">Programa de Cátedra</h1>
                <p class="text-slate-600">{curso.asignatura?.nombre} - {curso.cod_curso}</p>
                <p class="text-sm text-slate-500">{curso.carrera?.nombre}</p>
            </div>
            {#if programa && programa.estado !== 'APROBADO' && !isEditing}
                <Button.Root
                    onclick={toggleEditMode}
                    class="flex items-center gap-2"
                    variant="secondary"
                >
                    <Edit2 size={18} />
                    Editar
                </Button.Root>
            {/if}
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
                                El programa de cátedra para este curso aún no ha sido aprobado o creado.
                            </p>
                            <p class="text-amber-700 text-xs">
                                Por favor, intenta más tarde o contacta con el docente del curso.
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
                            <p class="text-sm font-medium text-slate-500">Estado</p>
                            <p class="text-lg font-semibold"
                                class:text-green-600={programa.estado === 'APROBADO'}
                                class:text-amber-600={programa.estado !== 'APROBADO'}
                            >
                                {programa.estado}
                            </p>
                        </div>
                    </div>
                </Card.Content>
            </Card.Root>

            {#if isEditing}
                <!-- Modo Edición -->
                <div class="space-y-6 mb-8">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-slate-900">Editar Contenidos</h2>
                        <div class="flex gap-2">
                            <Button.Root
                                onclick={() => { isEditing = false; }}
                                variant="outline"
                            >
                                <X size={18} />
                                Cancelar
                            </Button.Root>
                            <Button.Root
                                onclick={handleSaveEdits}
                                disabled={$form.processing || isSaving}
                                class="flex items-center gap-2"
                            >
                                <Save size={18} />
                                {$form.processing || isSaving ? 'Guardando...' : 'Guardar Cambios'}
                            </Button.Root>
                        </div>
                    </div>

                    {#each editedSections as section, sectionIndex}
                        <Card.Root>
                            <Card.Content class="p-6">
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <input
                                            type="text"
                                            placeholder="Nombre de la sección"
                                            value={section.nombre_seccion}
                                            onchange={(e) => updateSectionName(sectionIndex, e.target.value)}
                                            class="px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                        <input
                                            type="text"
                                            placeholder="Numeral romano (I, II, III, ...)"
                                            value={section.numeral_romano ?? ''}
                                            onchange={(e) => updateSectionNumeral(sectionIndex, e.target.value)}
                                            class="px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                        <div class="text-slate-600 text-sm pt-2">
                                            Orden: {sectionIndex + 1}
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <h4 class="font-semibold text-slate-900">Contenidos</h4>
                                        {#each section.contenidos_programa as contenido, contentIndex}
                                            <div class="flex gap-2">
                                                <textarea
                                                    placeholder="Contenido..."
                                                    value={contenido.texto_contenido ?? ''}
                                                    onchange={(e) => updateContentText(sectionIndex, contentIndex, e.target.value)}
                                                    class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                                    rows="3"
                                                />
                                                <Button.Root
                                                    onclick={() => removeContent(sectionIndex, contentIndex)}
                                                    variant="destructive"
                                                    size="sm"
                                                >
                                                    <X size={16} />
                                                </Button.Root>
                                            </div>
                                        {/each}
                                        <Button.Root
                                            onclick={() => addContent(sectionIndex)}
                                            variant="outline"
                                            size="sm"
                                            class="w-full"
                                        >
                                            + Agregar Contenido
                                        </Button.Root>
                                    </div>
                                </div>
                            </Card.Content>
                        </Card.Root>
                    {/each}
                </div>
            {:else}
                <!-- Modo Visualización -->
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

                                    {#if seccion.contenidos_programa && seccion.contenidos_programa.length > 0}
                                        <div class="space-y-3">
                                            {#each seccion.contenidos_programa as contenido}
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
        {/if}

        <!-- Botón volver -->
        <div class="mt-8 flex justify-center">
            <Link
                href={`/ayudante/cursos/${curso.id_curso}`}
                class="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium"
            >
                <Undo2 size={18} />
                Volver al Curso
            </Link>
        </div>
    </div>
</AyudanteLayout>
