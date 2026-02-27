<script lang="ts">
  import { Link, router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
  import { Button } from '@/components/ui/button';
  import { Badge } from '@/components/ui/badge';
  import { ArrowLeft, Printer, AlertCircle, Plus, Edit2 } from 'lucide-svelte';
  import type { Curso, Asignatura, Programa } from '@/types/admin.types';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import SyllabusTypeSelector from '@/components/custom/admin/SyllabusTypeSelector.svelte';
  import ProgramaDocument from '@/components/custom/common/ProgramaDocument.svelte';
  import { toast } from 'svelte-sonner';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions.types';

  interface Props {
    curso: Curso;
    asignatura: Asignatura;
    programa: any;
    canApprove?: boolean;
    canEdit?: boolean;
    userPermissions?: Permission[];
  }

  let { curso, asignatura, programa, canApprove = false, canEdit = false, userPermissions = [] }: Props = $props();

  let title = $derived(`Programa: ${asignatura.nombre}`);

  // Datos normalizados para ProgramaDocument
  const secciones = $derived(programa?.secciones ?? []);
  const metadata = $derived(
    programa
      ? {
          version: programa.version_programa,
          fecha_creacion: programa.fecha_creacion,
        }
      : undefined,
  );

  // Curso preparado para los modales
  const preparedCurso = $derived({
    ...curso,
    asignatura_nombre: asignatura?.nombre ?? curso?.nombre,
    has_programa: !!programa,
  });
  let isSyllabusTypeOpen = $state(false);
  let isSyllabusModalOpen = $state(false);
  let selectedSyllabusType = $state<'simplified' | 'combined' | 'complete' | null>(null);
  let isLoading = $state(false);
  let permissionError = $state<string | null>(null);

  // Permisos: servidor ya calculó canEdit via policy; frontend puede complementar con permisos explícitos
  const canEditPrograma = $derived(
    canEdit || hasPermission(userPermissions, 'cursos/programas:modificar:modulo_1') || hasPermission(userPermissions, 'cursos/programas:*'),
  );

  console.log('📄 Docente Programa.svelte - programa recibido:', programa);

  function openSyllabusTypeSelector() {
    isSyllabusTypeOpen = true;
    selectedSyllabusType = null;
  }

  function openCompleteWizard() {
    // Salta el selector y abre directamente el wizard COMPLETO pre-poblado desde BASICO
    selectedSyllabusType = 'combined';
    isSyllabusTypeOpen = false;
    isSyllabusModalOpen = true;
  }

  function closeSyllabusTypeSelector() {
    isSyllabusTypeOpen = false;
    selectedSyllabusType = null;
  }

  function handleSyllabusTypeSelect(type: 'simplified' | 'combined' | 'complete') {
    selectedSyllabusType = type;
    isSyllabusTypeOpen = false;
    isSyllabusModalOpen = true;
  }

  function closeSyllabusModal() {
    isSyllabusModalOpen = false;
    selectedSyllabusType = null;
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

    {#if programa}
      <!-- Estado del programa (no-print) -->
      <Card class="no-print">
        <CardContent class="pt-4 pb-3">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-3">
              <span class="text-sm font-medium text-muted-foreground">Estado:</span>
              {#if programa.estado === 'BORRADOR'}
                <Badge variant="secondary" class="bg-yellow-100 text-yellow-800 border-yellow-200">Borrador</Badge>
              {:else if programa.estado === 'BASICO_COMPLETO'}
                <Badge class="bg-amber-100 text-amber-800 border-amber-200">Básico Completo</Badge>
              {:else if programa.estado === 'ENVIADO'}
                <Badge class="bg-blue-100 text-blue-800 border-blue-200">Pendiente de Aprobación</Badge>
              {:else if programa.estado === 'APROBADO'}
                <Badge class="bg-green-100 text-green-800 border-green-200">✅ Aprobado</Badge>
              {:else}
                <Badge variant="secondary">{programa.estado}</Badge>
              {/if}
              <span class="text-sm text-muted-foreground">v{programa.version_programa}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Documento del programa -->
      <ProgramaDocument {secciones} {metadata}>
        {#snippet actions()}
          {#if programa.estado === 'BASICO_COMPLETO' && canEditPrograma}
            <div class="mt-8 flex flex-col items-center gap-3 pt-6 border-t border-slate-200">
              <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 w-full max-w-xl text-center">
                <p class="text-sm font-medium text-amber-900 mb-1">📝 Programa básico creado</p>
                <p class="text-sm text-amber-700 mb-3">Puedes completarlo con todas las secciones del programa completo (III, IV, V, VII, IX).</p>
                <button
                  onclick={openCompleteWizard}
                  class="inline-flex items-center gap-2 px-6 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-medium"
                >
                  Completar Syllabus
                </button>
              </div>
              <button
                onclick={openSyllabusTypeSelector}
                class="flex items-center gap-2 px-4 py-1.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
              >
                <Edit2 size={14} />
                Editar sección básica
              </button>
            </div>
          {:else if programa.estado !== 'APROBADO' && canEditPrograma}
            <div class="mt-8 flex justify-center pt-6 border-t border-slate-200">
              <button
                onclick={openSyllabusTypeSelector}
                class="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
              >
                <Edit2 size={18} />
                Editar Contenidos
              </button>
            </div>
          {/if}
        {/snippet}
      </ProgramaDocument>
    {:else}
      <!-- No hay programa - mostrar botón para crear -->
      <Card class="border-blue-200 bg-blue-50 no-print">
        <CardContent class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-blue-900 mb-2">Crear Programa de Cátedra</h3>
              <p class="text-sm text-blue-800">Inicia la creación del programa seleccionando el tipo de syllabus que deseas.</p>
            </div>
            <Button variant="default" size="lg" onclick={openSyllabusTypeSelector}>
              <Plus class="mr-2 size-5" />
              Crear Programa
            </Button>
          </div>
        </CardContent>
      </Card>
    {/if}

    <!-- SyllabusTypeSelector para elegir tipo de programa -->
    {#if isSyllabusTypeOpen}
      <SyllabusTypeSelector
        bind:isOpen={isSyllabusTypeOpen}
        onClose={closeSyllabusTypeSelector}
        onSelect={handleSyllabusTypeSelect}
        existingSyllabusType={programa?.estado === 'BASICO_COMPLETO' ? 'BASICO' : programa ? 'COMPLETO' : null}
      />
    {/if}

    <!-- Syllabus Modal Editor -->
    {#if isSyllabusModalOpen}
      <SyllabusModal
        bind:isOpen={isSyllabusModalOpen}
        curso={preparedCurso}
        syllabusType={selectedSyllabusType}
        onClose={closeSyllabusModal}
        onSuccess={handleSyllabusSuccess}
      />
    {/if}
  </div>
</DocenteLayout>
