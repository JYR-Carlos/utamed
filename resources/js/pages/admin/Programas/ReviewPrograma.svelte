<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import Button from '@/components/ui/button/button.svelte';
  import Alert from '@/components/ui/alert/alert.svelte';
  import { AlertCircle, CheckCircle, CalendarDays, Pencil, Save, X } from 'lucide-svelte';
  import { hasPermission } from '@/services/permissionValidator';
  import ProgramaDetailView from '@/modules/resources/programa/components/ProgramaDetailView.svelte';
  import SyllabusModal from '@/modules/resources/programa/components/SyllabusModal.svelte';
  import type { Permission } from '@/types/permissions/permissions';
  import { formatFechaCorta } from '@/utils/formatters';

  interface Props {
    programa: any;
    curso: any;
    userPermissions?: Permission[];
    userRole?: string;
    userId?: number;
  }

  let { programa, curso, userPermissions = [], userRole = 'admin', userId = 0 }: Props = $props();

  // ── Approval actions ──────────────────────────────────────────────────────
  let isApproving = $state(false);
  let isRejecting = $state(false);
  let showRejectionReason = $state(false);
  let rejectionReason = $state('');

  // ── Complete-syllabus modal ───────────────────────────────────────────────
  let isSyllabusModalOpen = $state(false);

  const cursoForModal = $derived({
    ...curso,
    asignatura_nombre: curso.asignatura_nombre ?? curso.nombre,
    cod_curso: curso.cod_curso ?? '',
    id_contexto: curso.id_contexto ?? 0,
    id_asignacion_plan: curso.id_asignacion_plan ?? 0,
    has_programa: true,
  });

  // ── Deadline editor ───────────────────────────────────────────────────────
  let editingDates = $state(false);
  let isSavingDates = $state(false);
  let dateBasico = $state<string>(toDateInput(curso.fecha_limite_entrega_basico));
  let dateSyllabus = $state<string>(toDateInput(curso.fecha_limite_entrega_syllabus));

  function toDateInput(val: string | null | undefined): string {
    if (!val) return '';
    return val.slice(0, 10);
  }

  function formatDate(val: string | null | undefined): string {
    return val ? formatFechaCorta(val) : '—';
  }

  function isOverdue(val: string | null | undefined): boolean {
    if (!val) return false;
    return new Date(val) < new Date();
  }

  function saveDates() {
    isSavingDates = true;
    router.put(
      `/admin/cursos/${curso.id_curso}/programa/fechas`,
      {
        fecha_limite_entrega_basico: dateBasico || null,
        fecha_limite_entrega_syllabus: dateSyllabus || null,
      },
      {
        onSuccess: () => {
          editingDates = false;
        },
        onFinish: () => {
          isSavingDates = false;
        },
      },
    );
  }

  function cancelDateEdit() {
    dateBasico = toDateInput(curso.fecha_limite_entrega_basico);
    dateSyllabus = toDateInput(curso.fecha_limite_entrega_syllabus);
    editingDates = false;
  }

  // ── Permissions / panel visibility ────────────────────────────────────────
  const canApprovePrograma = $derived(
    hasPermission(userPermissions, 'cursos/programas:*') ||
      hasPermission(userPermissions, 'cursos/programas:crear'),
  );

  // La versión BASICO no requiere aprobación (BASICO_COMPLETO es estado final válido).
  // La versión COMPLETO sí requiere aprobación: estado COMPLETO → admin aprueba → APROBADO.
  const showActionPanel = $derived(
    (programa.estado === 'COMPLETO' && canApprovePrograma) ||
      programa.estado === 'APROBADO' ||
      programa.estado === 'BASICO_COMPLETO',
  );

  function handleApprove() {
    isApproving = true;
    router.put(
      `/admin/cursos/${curso.id_curso}/programa/aprobar`,
      {},
      { onFinish: () => (isApproving = false) },
    );
  }

  function handleReject() {
    isRejecting = true;
    router.put(
      `/admin/cursos/${curso.id_curso}/programa/rechazar`,
      { razon_rechazo: rejectionReason, accion_tipo: 'rechazo' },
      { onFinish: () => (isRejecting = false) },
    );
  }

  function handleCompleteSuccess() {
    isSyllabusModalOpen = false;
    router.reload();
  }
</script>

<div class="min-h-screen bg-gray-50 p-6">
  <div class="max-w-5xl mx-auto space-y-6">
    <!-- ── Fechas límite de entrega (siempre visible para admin) ──────────── -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <CalendarDays class="h-5 w-5 text-blue-600" />
          <h2 class="text-base font-semibold text-gray-900">Fechas límite de entrega</h2>
        </div>
        {#if !editingDates}
          <Button
            variant="ghost"
            size="sm"
            onclick={() => (editingDates = true)}
            class="gap-1.5 text-gray-600"
          >
            <Pencil class="h-3.5 w-3.5" />
            Editar
          </Button>
        {/if}
      </div>

      {#if editingDates}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="date-basico" class="block text-sm font-medium text-gray-700 mb-1">
              Fecha límite — Básico
              <span class="text-xs text-gray-400 font-normal ml-1"
                >(plazo para entregar el programa básico)</span
              >
            </label>
            <input
              id="date-basico"
              type="date"
              bind:value={dateBasico}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label for="date-syllabus" class="block text-sm font-medium text-gray-700 mb-1">
              Fecha límite — Syllabus completo
              <span class="text-xs text-gray-400 font-normal ml-1"
                >(debe ser posterior al básico)</span
              >
            </label>
            <input
              id="date-syllabus"
              type="date"
              bind:value={dateSyllabus}
              min={dateBasico || undefined}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
        <div class="flex gap-2 mt-4 justify-end">
          <Button
            variant="outline"
            size="sm"
            onclick={cancelDateEdit}
            disabled={isSavingDates}
            class="gap-1.5"
          >
            <X class="h-3.5 w-3.5" />
            Cancelar
          </Button>
          <Button
            size="sm"
            onclick={saveDates}
            disabled={isSavingDates}
            class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white"
          >
            <Save class="h-3.5 w-3.5" />
            {isSavingDates ? 'Guardando...' : 'Guardar fechas'}
          </Button>
        </div>
      {:else}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Básico</p>
            <p
              class={`text-sm font-semibold ${isOverdue(curso.fecha_limite_entrega_basico) && programa?.estado === 'BORRADOR' ? 'text-red-600' : 'text-gray-900'}`}
            >
              {formatDate(curso.fecha_limite_entrega_basico)}
            </p>
            {#if isOverdue(curso.fecha_limite_entrega_basico) && programa?.estado === 'BORRADOR'}
              <p class="text-xs text-red-500 mt-0.5">Plazo vencido</p>
            {/if}
          </div>
          <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">
              Syllabus completo
            </p>
            <p
              class={`text-sm font-semibold ${isOverdue(curso.fecha_limite_entrega_syllabus) && programa?.estado === 'BASICO_COMPLETO' ? 'text-red-600' : 'text-gray-900'}`}
            >
              {formatDate(curso.fecha_limite_entrega_syllabus)}
            </p>
            {#if isOverdue(curso.fecha_limite_entrega_syllabus) && programa?.estado === 'BASICO_COMPLETO'}
              <p class="text-xs text-red-500 mt-0.5">Plazo vencido</p>
            {/if}
          </div>
        </div>
        {#if !curso.fecha_limite_entrega_basico && !curso.fecha_limite_entrega_syllabus}
          <p class="text-sm text-gray-400 italic mt-1">
            No se han definido fechas límite para este curso.
          </p>
        {/if}
      {/if}
    </div>

    <!-- ── Visualización centralizada del programa ────────────────────────── -->
    <ProgramaDetailView {programa} {userRole} {userId} />

    <!-- ── Panel de acciones (permisos y estado) ─────────────────────────── -->
    {#if showActionPanel}
      <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Acciones</h2>

        {#if programa.estado === 'COMPLETO' && canApprovePrograma}
          <div class="space-y-4">
            <Alert variant="default">
              <AlertCircle class="h-4 w-4" />
              <div>
                <p class="font-medium">Syllabus completo pendiente de aprobación</p>
                <p class="text-sm">
                  El docente ha completado todas las secciones. Puedes aprobarlo o devolverlo para
                  revisión.
                </p>
              </div>
            </Alert>

            {#if showRejectionReason}
              <div>
                <label for="reason" class="block text-sm font-medium text-gray-900 mb-2"
                  >Motivo (opcional)</label
                >
                <textarea
                  id="reason"
                  bind:value={rejectionReason}
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                  placeholder="Indica al docente qué debe corregir..."
                ></textarea>
              </div>
            {/if}

            <div class="flex gap-3 pt-2">
              <Button
                onclick={handleApprove}
                disabled={isApproving}
                class="flex-1 bg-green-600 hover:bg-green-700 text-white"
              >
                {isApproving ? 'Aprobando...' : 'Aprobar Syllabus'}
              </Button>
              <Button
                onclick={() => {
                  showRejectionReason = !showRejectionReason;
                  if (!showRejectionReason) rejectionReason = '';
                }}
                variant="outline"
                class="px-6"
              >
                {showRejectionReason ? 'Cancelar' : 'Devolver para revisión'}
              </Button>
              {#if showRejectionReason}
                <Button
                  onclick={handleReject}
                  disabled={isRejecting}
                  class="flex-1 bg-red-600 hover:bg-red-700 text-white"
                >
                  {isRejecting ? 'Devolviendo...' : 'Confirmar'}
                </Button>
              {/if}
            </div>
          </div>
        {:else if programa.estado === 'APROBADO'}
          <Alert variant="default">
            <CheckCircle class="h-4 w-4" />
            <div>
              <p class="font-medium">Syllabus completo aprobado</p>
              <p class="text-sm">El programa está publicado y disponible para los estudiantes.</p>
            </div>
          </Alert>
        {:else if programa.estado === 'BASICO_COMPLETO'}
          <div
            class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 flex items-center justify-between gap-4"
          >
            <div>
              <p class="font-medium text-emerald-900">✅ Programa básico entregado</p>
              <p class="text-sm text-emerald-700 mt-1">
                La versión básica no requiere aprobación. Una vez que el docente complete el
                syllabus (secciones III–IX), aparecerá aquí para aprobación.
              </p>
            </div>
            <Button
              onclick={() => (isSyllabusModalOpen = true)}
              class="bg-emerald-600 hover:bg-emerald-700 text-white shrink-0"
            >
              Completar Syllabus
            </Button>
          </div>
        {/if}
      </div>
    {/if}
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
