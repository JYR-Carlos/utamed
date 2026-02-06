<script lang="ts">
    import { Link } from '@inertiajs/svelte';
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
    import { ArrowLeft, Printer } from "lucide-svelte";
    import type { Curso, Asignatura } from "@/types/admin.types";

    export let curso: Curso;
    export let asignatura: Asignatura;
    // Define types if not available
    export let unities: any[] = [];
    export let programa: any;

    let title = `Programa: ${asignatura.nombre}`;
</script>

<DocenteLayout {title}>
    <div class="p-6 max-w-5xl mx-auto space-y-6">
        <!-- Header with Back Button -->
        <div class="flex items-center justify-between">
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
                 <Button variant="outline" on:click={() => window.print()}>
                    <Printer class="mr-2 size-4" />
                    Imprimir
                 </Button>
            </div>
        </div>

        <!-- Main Info Card -->
        <Card>
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
                    <Badge variant="secondary">v{programa.version}</Badge>
                </div>
            </CardContent>
        </Card>

        <!-- Description -->
        {#if asignatura.descripcion}
        <Card>
             <CardHeader>
                <CardTitle>Descripción de la Asignatura</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground leading-relaxed">
                    {asignatura.descripcion}
                </p>
            </CardContent>
        </Card>
        {/if}

        <!-- Units (if we have them) -->
        {#if unities && unities.length > 0}
        <Card>
            <CardHeader>
                <CardTitle>Unidades de Aprendizaje</CardTitle>
            </CardHeader>
            <CardContent>
                 <Table.Root>
                    <Table.Header>
                        <Table.Row>
                            <Table.Head class="w-[100px]">Unidad</Table.Head>
                            <Table.Head>Nombre</Table.Head>
                        </Table.Row>
                    </Table.Header>
                    <Table.Body>
                        {#each unities as unidad}
                            <Table.Row>
                                <Table.Cell class="font-medium">{unidad.num_unidad}</Table.Cell>
                                <Table.Cell>{unidad.nombre}</Table.Cell>
                            </Table.Row>
                        {/each}
                    </Table.Body>
                </Table.Root>
            </CardContent>
        </Card>
        {/if}
    </div>
</DocenteLayout>
