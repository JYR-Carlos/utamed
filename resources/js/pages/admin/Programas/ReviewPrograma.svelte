<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import Button from '@/components/ui/button/button.svelte';
  import Alert from '@/components/ui/alert/alert.svelte';
  import { AlertCircle, CheckCircle, ArrowLeft } from 'lucide-svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import ProgramaDocument from '@/components/custom/common/ProgramaDocument.svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import type { Permission } from '@/types/permissions.types';

  interface Props {
    programa: any;
    curso: any;
    userPermissions?: Permission[];
  }

  let { programa, curso, userPermissions = [] }: Props = $props();

  let isApproving = $state(false);
  let isRejecting = $state(false);
  let showRejectionReason = $state(false);
  let rejectionReason = $state('');
  let isSyllabusModalOpen = $state(false);

  const canApprovePrograma = $derived(
    hasPermission(userPermissions, 'cursos/programas:*') || hasPermission(userPermissions, 'cursos/programas:crear'),
  );

  // Curso preparado para SyllabusModal (requiere has_programa para pre-cargar)
  const cursoForModal = $derived({
    ...curso,
    asignatura_nombre: curso.asignatura_nombre ?? curso.nombre,
    cod_curso: curso.cod_curso ?? '',
    id_contexto: curso.id_contexto ?? 0,
    id_asignacion_plan: curso.id_asignacion_plan ?? 0,
    has_programa: true,
  });

  const secciones = $derived(programa?.data_syllabus?.secciones ?? []);
  const metadata = $derived({
    version: programa?.version_programa,
    creado_por: programa?.creado_por,
    fecha_creacion: programa?.fecha_creacion,
  });

  function handleApprove() {
    isApproving = true;
    router.put(
      `/admin/cursos/${curso.id_curso}/programa/aprobar`,
      {},
      {
        onFinish: () => (isApproving = false),
      },
    );
  }

  function handleReject() {
    isRejecting = true;
    router.put(`/admin/cursos/${curso.id_curso}/programa/rechazar`, { razon: rejectionReason }, { onFinish: () => (isRejecting = false) });
  }

  function handleCompleteSuccess() {
    isSyllabusModalOpen = false;
    router.reload();
  }
</script>

<div class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-4">
        <Button onclick={() => router.get('/admin/cursos')} variant="outline" class="inline-flex items-center gap-2">
          <ArrowLeft class="w-4 h-4" />
          Volver
        </Button>
        <div>
          <h1 class="text-4xl font-bold text-gray-900">PROGRAMA DE ASIGNATURA</h1>
          <p class="text-gray-600 mt-2 text-lg">{curso.asignatura_nombre}</p>
        </div>
      </div>
    </div>

    <!-- Documento del programa -->
    <ProgramaDocument {secciones} {metadata}>
      {#snippet actions()}
        <!-- Panel de Acciones -->
        {#if programa.estado === 'PENDIENTE' && canApprovePrograma}
          <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Acciones</h2>
            <div class="space-y-4">
              <Alert variant="default">
                <AlertCircle class="h-4 w-4" />
                <div>
                  <p class="font-medium">Este programa está en revisión</p>
                  <p class="text-sm">Puedes aprobarlo o rechazarlo para que el docente lo revise nuevamente.</p>
                </div>
              </Alert>

              {#if showRejectionReason}
                <div>
                  <label for="reason" class="block text-sm font-medium text-gray-900 mb-2">Motivo de rechazo (opcional)</label>
                  <textarea
                    id="reason"
                    bind:value={rejectionReason}
                    rows="4"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Proporciona retroalimentación al docente sobre qué necesita mejorarse..."
                  ></textarea>
                </div>
              {/if}

              <div class="flex gap-3 pt-4">
                <Button onclick={handleApprove} disabled={isApproving} class="flex-1 bg-green-600 hover:bg-green-700 text-white">
                  {isApproving ? 'Aprobando...' : 'Aprobar Programa'}
                </Button>
                <Button
                  onclick={() => {
                    showRejectionReason = !showRejectionReason;
                    if (!showRejectionReason) rejectionReason = '';
                  }}
                  variant="outline"
                  class="px-6"
                >
                  {showRejectionReason ? 'Cancelar Rechazo' : 'Rechazar'}
                </Button>
                {#if showRejectionReason}
                  <Button onclick={handleReject} disabled={isRejecting} class="flex-1 bg-red-600 hover:bg-red-700 text-white">
                    {isRejecting ? 'Rechazando...' : 'Confirmar Rechazo'}
                  </Button>
                {/if}
              </div>
            </div>
          </div>
        {:else if programa.estado === 'RECHAZADO'}
          <Alert variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <div>
              <p class="font-medium">Este programa ha sido rechazado</p>
              <p class="text-sm">El docente puede editarlo nuevamente y enviarlo para revisión.</p>
            </div>
          </Alert>
        {:else if programa.estado === 'APROBADO'}
          <Alert variant="default">
            <CheckCircle class="h-4 w-4" />
            <div>
              <p class="font-medium">Este programa ha sido aprobado</p>
              <p class="text-sm">Está disponible para que los estudiantes lo visualicen.</p>
            </div>
          </Alert>
        {:else if programa.estado === 'BASICO_COMPLETO'}
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-6">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="font-medium text-amber-900">📝 Programa básico creado</p>
                <p class="text-sm text-amber-700 mt-1">
                  Este programa tiene las secciones básicas completadas. Puedes ampliarlo con el programa completo (secciones III, IV, V, VII y IX).
                </p>
              </div>
              <Button onclick={() => (isSyllabusModalOpen = true)} class="bg-amber-600 hover:bg-amber-700 text-white shrink-0">
                Completar Syllabus
              </Button>
            </div>
          </div>
        {/if}
      {/snippet}
    </ProgramaDocument>
  </div>
</div>

{#if isSyllabusModalOpen}
  <SyllabusModal
    bind:isOpen={isSyllabusModalOpen}
    curso={cursoForModal}
    syllabusType="combined"
    onClose={() => (isSyllabusModalOpen = false)}
    onSuccess={handleCompleteSuccess}
  />
{/if}
