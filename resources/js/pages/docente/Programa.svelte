<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import DocenteLayout from '@/layouts/DocenteLayout.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from "@/components/ui/card";
    import * as Table from "@/components/ui/table";
    import { Button } from "@/components/ui/button";
    import { Badge } from "@/components/ui/badge";
    import { ArrowLeft, Printer, Save, Eye, Edit, Trash2 } from "lucide-svelte";
    import type { Curso, Asignatura } from "@/types/admin.types";
    import SyllabusEditor from '@/components/SyllabusEditor.svelte';
    import SyllabusViewer from '@/components/SyllabusViewer.svelte';
    import { toast } from "svelte-sonner";

    interface Props {
        curso: Curso;
        asignatura: Asignatura;
        programa: any;
    }

    let { curso, asignatura, programa }: Props = $props();

    let title = `Programa: ${asignatura.nombre}`;
    let syllabusData: any[] = $state([]);
    
    // Mode: 'view' or 'edit'
    // Default to 'view' if program exists, 'edit' if creating new
    let mode: 'view' | 'edit' = $state(programa && programa.secciones ? 'view' : 'edit');
    let hasUnsavedChanges = $state(false);

    // Initialize syllabus data from backend if available
    $effect(() => {
        if (programa && programa.secciones) {
            syllabusData = programa.secciones.map((s: any) => ({
                nombre_seccion: s.nombre_seccion,
                numeral_romano: s.numeral_romano,
                es_lista: s.es_lista,
                orden: s.orden,
                contenidos: s.contenidos_programa ? s.contenidos_programa.map((c: any) => ({
                    texto_contenido: c.texto_contenido,
                    orden_item: c.orden_item,
                    valor_numerico: c.valor_numerico
                })) : []
            }));
        } else {
            // Default template if no program exists
            syllabusData = [
                { nombre_seccion: "Descripción de la Asignatura", numeral_romano: "I", orden: 1, contenidos: [{ texto_contenido: asignatura.descripcion || "", orden_item: 1 }] },
                { nombre_seccion: "Competencias", numeral_romano: "II", orden: 2, contenidos: [] },
                { nombre_seccion: "Resultados de Aprendizaje", numeral_romano: "III", orden: 3, contenidos: [] },
                { nombre_seccion: "Contenidos", numeral_romano: "IV", orden: 4, contenidos: [] },
                { nombre_seccion: "Metodología", numeral_romano: "V", orden: 5, contenidos: [] },
                { nombre_seccion: "Evaluación", numeral_romano: "VI", orden: 6, contenidos: [] },
            ];
        }
    });

    function handleSave(event: CustomEvent) {
        const data = event.detail;
        
        router.post(`/docente/cursos/${curso.id_curso}/programa`, {
            secciones: data
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Programa guardado correctamente");
                hasUnsavedChanges = false;
                mode = 'view';
            },
            onError: (errors) => {
                toast.error("Error al guardar el programa");
                console.error(errors);
            }
        });
    }

    function handleDelete() {
        if (!programa) {
            toast.error("No hay programa para eliminar");
            return;
        }

        if (confirm("¿Estás seguro de eliminar este programa? Esta acción no se puede deshacer.")) {
            router.delete(`/docente/cursos/${curso.id_curso}/programa`, {
                onSuccess: () => {
                    toast.success("Programa eliminado correctamente");
                },
                onError: (errors) => {
                    toast.error("Error al eliminar el programa");
                    console.error(errors);
                }
            });
        }
    }

    function switchToEdit() {
        mode = 'edit';
    }

    function switchToView() {
        if (hasUnsavedChanges) {
            if (confirm("Tienes cambios sin guardar. ¿Deseas descartarlos?")) {
                hasUnsavedChanges = false;
                mode = 'view';
            }
        } else {
            mode = 'view';
        }
    }
</script>

<DocenteLayout>
    <div class="p-6 max-w-5xl mx-auto space-y-6">
        <!-- Header with Back Button and Mode Controls -->
        <div class="flex items-center justify-between no-print">
            <div class="flex items-center gap-4">
                 <Link href="/docente/cursos">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="size-5" />
                    </Button>
                </Link>
                <div>
                     <h1 class="text-2xl font-bold tracking-tight text-foreground">{title}</h1>
                     <p class="text-muted-foreground">{curso.nombre} - {asignatura.cod_asignatura}</p>
                </div>
            </div>
            <div class="flex gap-2">
                {#if programa && programa.secciones}
                    <!-- View/Edit toggle buttons -->
                    {#if mode === 'view'}
                        <Button variant="outline" onclick={switchToEdit}>
                            <Edit class="mr-2 size-4" />
                            Editar
                        </Button>
                    {:else}
                        <Button variant="outline" onclick={switchToView}>
                            <Eye class="mr-2 size-4" />
                            Ver
                        </Button>
                    {/if}
                    
                    <!-- Delete button -->
                    <Button variant="destructive" onclick={handleDelete}>
                        <Trash2 class="mr-2 size-4" />
                        Eliminar
                    </Button>
                {/if}
                
                <!-- Print button -->
                 <Button variant="outline" onclick={() => window.print()}>
                    <Printer class="mr-2 size-4" />
                    Imprimir
                 </Button>
            </div>
        </div>

        <!-- Main Info Card -->
        <Card class="no-print">
            <CardHeader>
                <CardTitle>Información General</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="space-y-1">
                    <p class="text-sm font-medium leading-none text-muted-foreground">Código</p>
                    <p class="text-base font-semibold">{asignatura.cod_asignatura}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-medium leading-none text-muted-foreground">Créditos SCT</p>
                    <p class="text-base font-semibold">{asignatura.creditos_sct}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-medium leading-none text-muted-foreground">Horas Cátedra</p>
                    <p class="text-base font-semibold">{asignatura.horas_catedra}</p>
                </div>
                 <div class="space-y-1">
                    <p class="text-sm font-medium leading-none text-muted-foreground">Versión Programa</p>
                    {#if programa}
                        <Badge variant="secondary">v{programa.version_programa}</Badge>
                    {:else}
                        <Badge variant="outline">No generado</Badge>
                    {/if}
                </div>
            </CardContent>
        </Card>


        <!-- Syllabus Content Area -->
        <div class="print:block">
            {#if mode === 'view'}
                <!-- View Mode: Read-only document view -->
                <SyllabusViewer 
                    sections={syllabusData}
                    {asignatura}
                    {curso}
                />
            {:else}
                <!-- Edit Mode: Editor with save functionality -->
                <SyllabusEditor 
                    bind:modelValue={syllabusData} 
                    on:save={handleSave}
                    on:change={() => hasUnsavedChanges = true}
                />
            {/if}
        </div>
        
    </div>
</DocenteLayout>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>
