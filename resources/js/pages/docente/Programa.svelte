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
    import { ArrowLeft, Printer, Save, Eye, Edit, Trash2, AlertCircle, Loader2 } from "lucide-svelte";
    import type { Curso, Asignatura } from "@/types/admin.types";
    import SyllabusEditor from '@/components/SyllabusEditor.svelte';
    import SyllabusViewer from '@/components/SyllabusViewer.svelte';
    import { toast } from "svelte-sonner";

    interface Props {
        curso: Curso;
        asignatura: Asignatura;
        programa: any;
        canApprove?: boolean;
    }

    let { curso, asignatura, programa, canApprove = false }: Props = $props();

    let title = `Programa: ${asignatura.nombre}`;
    let syllabusData: any[] = $state([]);
    
    // Mode: 'view' or 'edit'
    // Default to 'view' if program exists, 'edit' if creating new
    let mode: 'view' | 'edit' = $state(programa && programa.secciones ? 'view' : 'edit');
    let hasUnsavedChanges = $state(false);
    let isLoading = $state(false);
    let permissionError = $state<string | null>(null);

    // Template de 9 secciones que siempre usamos como base (estructura del PDF)
    function getDefaultTemplate() {
        return [
            { 
                nombre_seccion: "I. Descripción de la Asignatura", 
                numeral_romano: "I", 
                orden: 1, 
                contenidos: [
                    { texto_contenido: asignatura.descripcion || "Descripción general de la asignatura", orden_item: 1 }
                ] 
            },
            { 
                nombre_seccion: "II. Competencias", 
                numeral_romano: "II", 
                orden: 2, 
                contenidos: [] 
            },
            { 
                nombre_seccion: "III. Resultados de Aprendizaje", 
                numeral_romano: "III", 
                orden: 3, 
                contenidos: [] 
            },
            { 
                nombre_seccion: "IV. Contenidos", 
                numeral_romano: "IV", 
                orden: 4, 
                contenidos: [] 
            },
            { 
                nombre_seccion: "V. Metodología", 
                numeral_romano: "V", 
                orden: 5, 
                contenidos: [] 
            },
            { 
                nombre_seccion: "VI. Evaluación", 
                numeral_romano: "VI", 
                orden: 6, 
                contenidos: [
                    { texto_contenido: "Parcial 1: [Porcentaje]%", orden_item: 1 },
                    { texto_contenido: "Parcial 2: [Porcentaje]%", orden_item: 2 },
                    { texto_contenido: "Actividades: [Porcentaje]%", orden_item: 3 },
                    { texto_contenido: "Examen Final: [Porcentaje]%", orden_item: 4 }
                ] 
            },
            { 
                nombre_seccion: "VII. Bibliografía Recomendada", 
                numeral_romano: "VII", 
                orden: 7, 
                contenidos: [] 
            },
            { 
                nombre_seccion: "VIII. Políticas del Curso", 
                numeral_romano: "VIII", 
                orden: 8, 
                contenidos: [
                    { texto_contenido: "Asistencia mínima requerida: [Porcentaje]%", orden_item: 1 },
                    { texto_contenido: "Puntualidad: Los estudiantes deben llegar a tiempo", orden_item: 2 },
                    { texto_contenido: "Académica: Se espera originalidad en los trabajos", orden_item: 3 }
                ] 
            },
        ];
    }

    // Initialize syllabus data from backend if available
    $effect(() => {
        const template = getDefaultTemplate();
        
        if (programa && programa.secciones && programa.secciones.length > 0) {
            // Fusiona contenido existente con el template
            const existingMap = new Map();
            programa.secciones.forEach((s: any) => {
                existingMap.set(s.orden || s.nombre_seccion, {
                    nombre_seccion: s.nombre_seccion,
                    numeral_romano: s.numeral_romano,
                    es_lista: s.es_lista,
                    orden: s.orden,
                    contenidos: s.contenidos_programa ? s.contenidos_programa.map((c: any) => ({
                        texto_contenido: c.texto_contenido,
                        orden_item: c.orden_item,
                        valor_numerico: c.valor_numerico
                    })) : []
                });
            });
            
            // Si el programa tiene menos de 8 secciones, complementa con el template
            if (programa.secciones.length < template.length) {
                syllabusData = template.map((templateSection, index) => {
                    const existing = existingMap.get(index + 1) || existingMap.get(templateSection.nombre_seccion);
                    return existing || templateSection;
                });
            } else {
                syllabusData = template.map((templateSection, index) => {
                    const existing = existingMap.get(index + 1) || existingMap.get(templateSection.nombre_seccion);
                    return existing || templateSection;
                });
            }
        } else {
            // Nuevo programa: usar template completo
            syllabusData = template;
        }
    });

    function handleSave(event: CustomEvent) {
        const data = event.detail;
        isLoading = true;
        permissionError = null;
        
        router.post(`/docente/cursos/${curso.id_curso}/programa`, {
            secciones: data
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Programa guardado correctamente");
                hasUnsavedChanges = false;
                mode = 'view';
                isLoading = false;
            },
            onError: (errors) => {
                isLoading = false;
                // Manejo específico de errores 403 (sin permiso)
                const errorMessage = errors[0] || "Error al guardar el programa";
                
                if (errorMessage.includes("403") || errorMessage.includes("Forbidden") || errorMessage.includes("permiso")) {
                    permissionError = "No tienes permiso para crear/editar programas en este curso. Solo un docente autorizado puede hacerlo.";
                    toast.error(permissionError);
                } else {
                    toast.error(errorMessage);
                }
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
            isLoading = true;
            permissionError = null;
            
            router.delete(`/docente/cursos/${curso.id_curso}/programa`, {
                onSuccess: () => {
                    toast.success("Programa eliminado correctamente");
                    isLoading = false;
                },
                onError: (errors) => {
                    isLoading = false;
                    const errorMessage = errors[0] || "Error al eliminar el programa";
                    
                    if (errorMessage.includes("403") || errorMessage.includes("Forbidden") || errorMessage.includes("permiso")) {
                        permissionError = "No tienes permiso para eliminar programas en este curso.";
                        toast.error(permissionError);
                    } else {
                        toast.error(errorMessage);
                    }
                    console.error(errors);
                }
            });
        }
    }

    function switchToEdit() {
        if (!isLoading) {
            mode = 'edit';
        }
    }

    function switchToView() {
        if (isLoading) return;
        
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
        <!-- Permission Error Alert -->
        {#if permissionError}
            <div class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
                <AlertCircle class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div class="flex-1">
                    <p class="text-sm font-medium text-red-900">Acceso Denegado</p>
                    <p class="text-sm text-red-700 mt-1">{permissionError}</p>
                </div>
            </div>
        {/if}

        <!-- Header with Back Button and Mode Controls -->
        <div class="flex items-center justify-between no-print">
            <div class="flex items-center gap-4">
                 <Link href="/docente/cursos">
                    <Button variant="ghost" size="icon" disabled={isLoading}>
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
                        <Button variant="outline" onclick={switchToEdit} disabled={isLoading}>
                            <Edit class="mr-2 size-4" />
                            Editar
                        </Button>
                    {:else}
                        <Button variant="outline" onclick={switchToView} disabled={isLoading}>
                            <Eye class="mr-2 size-4" />
                            Ver
                        </Button>
                    {/if}
                    
                    <!-- Delete button -->
                    <Button variant="destructive" onclick={handleDelete} disabled={isLoading}>
                        {#if isLoading}
                            <Loader2 class="mr-2 size-4 animate-spin" />
                            Eliminando...
                        {:else}
                            <Trash2 class="mr-2 size-4" />
                            Eliminar
                        {/if}
                    </Button>
                {/if}
                
                <!-- Print button -->
                 <Button variant="outline" onclick={() => window.print()} disabled={isLoading}>
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

        <!-- Program Status Card -->
        {#if programa}
            <Card class="border-blue-200 bg-blue-50 no-print">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <span>Estado del Programa</span>
                            {#if programa.estado === 'BORRADOR'}
                                <Badge variant="secondary" class="bg-yellow-100 text-yellow-800 border-yellow-200">
                                    Borrador
                                </Badge>
                            {:else if programa.estado === 'ENVIADO'}
                                <Badge class="bg-blue-100 text-blue-800 border-blue-200">
                                    Pendiente de Aprobación
                                </Badge>
                            {:else if programa.estado === 'APROBADO'}
                                <Badge variant="outline" class="bg-green-100 text-green-800 border-green-200">
                                    Aprobado
                                </Badge>
                            {/if}
                        </CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Versión</p>
                            <p class="text-lg font-semibold">v{programa.version_programa}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Creado el</p>
                            <p class="text-sm">{new Date(programa.fecha_creacion).toLocaleDateString('es-ES')}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm text-muted-foreground">Última actualización</p>
                            <p class="text-sm">{new Date(programa.fecha_creacion).toLocaleDateString('es-ES')}</p>
                        </div>
                    </div>
                    
                    {#if canApprove && programa.estado !== 'APROBADO'}
                        <div class="border-t pt-4 flex gap-2">
                            <Button class="bg-green-600 hover:bg-green-700" disabled={isLoading}>
                                <span>Aprobar Programa</span>
                            </Button>
                            <Button variant="outline" disabled={isLoading}>
                                <span>Rechazar</span>
                            </Button>
                        </div>
                    {/if}
                    
                    {#if programa.estado === 'APROBADO'}
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <p class="text-sm text-green-800">
                                ✅ <strong>Programa Aprobado</strong>
                            </p>
                        </div>
                    {/if}
                </CardContent>
            </Card>
        {/if}
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
                    {isLoading}
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
