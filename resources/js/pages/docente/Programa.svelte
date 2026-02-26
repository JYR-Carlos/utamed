<script lang="ts">
  import { Link, router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
  import * as Table from '@/components/ui/table';
  import { Button } from '@/components/ui/button';
  import { Badge } from '@/components/ui/badge';
  import { ArrowLeft, Printer, Save, Eye, Edit, Trash2, AlertCircle, Loader2 } from 'lucide-svelte';
  import type { Curso, Asignatura, Programa } from '@/types/admin.types';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import { toast } from 'svelte-sonner';

  interface Props {
    curso: Curso;
    asignatura: Asignatura;
    programa: any;
    canApprove?: boolean;
  }

  let { curso, asignatura, programa, canApprove = false }: Props = $props();

  let title = $derived(`Programa: ${asignatura.nombre}`);
  let isSyllabusModalOpen = $state(true);
  let isLoading = $state(false);
  let permissionError = $state<string | null>(null);

  console.log('📄 Programa.svelte - programa recibido:', programa);

  function closeSyllabusModal() {
    isSyllabusModalOpen = false;
  }

  function handleSyllabusSuccess(updatedPrograma: Programa) {
    closeSyllabusModal();
    toast.success('Programa guardado correctamente');
    router.reload({ only: ['programa'] });
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
                <Badge variant="secondary" class="bg-yellow-100 text-yellow-800 border-yellow-200">Borrador</Badge>
              {:else if programa.estado === 'ENVIADO'}
                <Badge class="bg-blue-100 text-blue-800 border-blue-200">Pendiente de Aprobación</Badge>
              {:else if programa.estado === 'APROBADO'}
                <Badge variant="outline" class="bg-green-100 text-green-800 border-green-200">Aprobado</Badge>
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

    <!-- Syllabus Modal Editor -->
    <SyllabusModal
      bind:isOpen={isSyllabusModalOpen}
      curso={{ ...curso, has_programa: !!programa }}
      onClose={closeSyllabusModal}
      onSuccess={handleSyllabusSuccess}
    />
  </div>
</DocenteLayout>
