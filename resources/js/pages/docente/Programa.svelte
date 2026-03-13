<script lang="ts">
  import { Link, router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import AyudanteLayout from '@/layouts/AyudanteLayout.svelte';
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import Alert from '@/components/ui/alert/alert.svelte';
  import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
  import { Button } from '@/components/ui/button';
  import { Badge } from '@/components/ui/badge';
  import { ArrowLeft, Printer, AlertCircle, CheckCircle, Plus, Edit2, CalendarDays, Clock, Save, Pencil, X } from 'lucide-svelte';
  import type { BreadcrumbItem } from '@/types';
  import type { Curso, Asignatura, Programa } from '@/types/admin.types';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import DatePickerCL from '@/components/custom/common/DatePickerCL.svelte';
  import ProgramaDocument from '@/components/custom/common/ProgramaDocument.svelte';
  import { toast } from 'svelte-sonner';
  import { hasPermission } from '@/services/permissionValidator';
  import type { Permission } from '@/types/permissions/permissions';

  interface Props {
    curso: Curso & { asignatura?: Asignatura | null };
    asignatura?: Asignatura | null;
    programa: any;
    canApprove?: boolean;
    canEdit?: boolean;
    userPermissions?: Permission[];
    userId?: number;
    layoutType?: 'docente' | 'ayudante' | 'estudiante' | 'admin';
    backUrl?: string;
    breadcrumbs?: BreadcrumbItem[];
    mode?: string;
  }

  let {
    curso,
    asignatura: asignaturaProp = null,
    programa,
    canApprove = false,
    canEdit = false,
    userPermissions = [],
    userId = 0,
    layoutType = 'docente',
    backUrl,
    breadcrumbs = [],
    mode = 'view',
  }: Props = $props();

  // Normalise: some controllers send asignatura separately, others embed it in curso
  const asignatura = $derived(asignaturaProp ?? (curso as any)?.asignatura ?? null);

  const resolvedBackUrl = $derived(
    backUrl ??
      (layoutType === 'ayudante'
        ? `/ayudante/cursos/${curso?.id_curso}`
        : layoutType === 'estudiante'
          ? `/estudiante/cursos/${curso?.id_curso}`
          : layoutType === 'admin'
            ? '/admin/cursos'
            : '/docente/cursos'),
  );

  let title = $derived(`Programa: ${asignatura?.nombre ?? curso?.nombre ?? ''}`);

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
    asignatura_nombre: asignatura?.nombre ?? (curso as any)?.asignatura_nombre ?? curso?.nombre,
    has_programa: !!programa,
    // Embed asignatura fields so SyllabusModal.initializeWizard can read them
    creditos_sct: asignatura?.creditos_sct ?? curso?.creditos_sct,
    horas_catedra: asignatura?.horas_catedra ?? curso?.horas_catedra,
    horas_taller: asignatura?.horas_taller ?? (asignatura as any)?.horas_taller ?? curso?.horas_taller,
    horas_laboratorio: asignatura?.horas_laboratorio ?? (asignatura as any)?.horas_laboratorio ?? curso?.horas_laboratorio,
  });
  let isSyllabusModalOpen = $state(false);
  let selectedSyllabusType = $state<'simplified' | 'combined' | 'complete' | null>(null);
  let isLoading = $state(false);
  let permissionError = $state<string | null>(null);

  // Permisos: servidor ya calculó canEdit via policy; frontend puede complementar con permisos explícitos
  const canEditPrograma = $derived(
    canEdit || hasPermission(userPermissions, 'cursos/programas:modificar:modulo_1') || hasPermission(userPermissions, 'cursos/programas:*'),
  );

  console.log('📄 Docente Programa.svelte - programa recibido:', programa);

  function openCompleteWizard() {
    // Salta el selector y abre directamente el wizard COMPLETO pre-poblado desde BASICO
    selectedSyllabusType = 'combined';
    isSyllabusModalOpen = true;
  }

  function openEditBasico() {
    selectedSyllabusType = 'simplified';
    isSyllabusModalOpen = true;
  }

  function openEditCompleto() {
    selectedSyllabusType = 'complete';
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

  // ── Deadline display ──────────────────────────────────────────────────────
  /**
   * Parsea una fecha de límite de entrega como fecha local (sin conversión UTC).
   * Las fechas vienen del servidor en UTC (ej. "2026-03-03T00:00:00.000000Z"),
   * pero representan fechas del calendario en Chile. Se extrae solo la parte
   * YYYY-MM-DD y se construye como fecha local para evitar el desfase de zona horaria.
   */
  function parseDeadlineDate(val: string): Date {
    const [year, month, day] = val.slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day);
  }

  /** ISO/timestamp → localised string */
  function formatDeadline(val: string | null | undefined): string {
    if (!val) return '';
    return parseDeadlineDate(val).toLocaleDateString('es-CL', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
  }

  function daysLeft(val: string | null | undefined): number | null {
    if (!val) return null;
    const diff = Math.ceil((parseDeadlineDate(val).getTime() - Date.now()) / 86_400_000);
    return diff;
  }

  /**
   * Returns which deadline is relevant for the docente right now:
   * - No program / BORRADOR → basic deadline
   * - BASICO_COMPLETO        → syllabus deadline
   * - anything else          → null (nothing pending)
   */
  const activeDeadline = $derived.by(() => {
    if (layoutType !== 'docente') return null;
    const estado = programa?.estado ?? null;
    if (!estado || estado === 'BORRADOR') {
      const d = (curso as any).fecha_limite_entrega_basico;
      return d ? { label: 'Fecha límite — entrega del programa básico', value: d } : null;
    }
    if (estado === 'BASICO_COMPLETO') {
      const d = (curso as any).fecha_limite_entrega_syllabus;
      return d ? { label: 'Fecha límite — entrega del syllabus completo', value: d } : null;
    }
    return null;
  });

  // ── Admin: deadline editor ─────────────────────────────────────────────────
  /** ISO string → "YYYY-MM-DD" para el DatePicker */
  function toDateInput(val: string | null | undefined): string {
    if (!val) return '';
    return val.slice(0, 10);
  }

  function formatDate(val: string | null | undefined): string {
    if (!val) return '—';
    return parseDeadlineDate(val).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function isOverdue(val: string | null | undefined): boolean {
    if (!val) return false;
    return parseDeadlineDate(val) < new Date();
  }

  let editingDates = $state(false);
  let isSavingDates = $state(false);
  let dateBasico = $state<string | null>(toDateInput((curso as any).fecha_limite_entrega_basico) || null);
  let dateSyllabus = $state<string | null>(toDateInput((curso as any).fecha_limite_entrega_syllabus) || null);

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
    dateBasico = toDateInput((curso as any).fecha_limite_entrega_basico) || null;
    dateSyllabus = toDateInput((curso as any).fecha_limite_entrega_syllabus) || null;
    editingDates = false;
  }

  // ── Admin: approval actions ────────────────────────────────────────────────
  let isApproving = $state(false);
  let isRejecting = $state(false);
  let showRejectionReason = $state(false);
  let rejectionReason = $state('');

  const canApprovePrograma = $derived(canApprove);

  const showActionPanel = $derived(
    layoutType === 'admin' &&
      canApprovePrograma &&
      (programa?.estado === 'COMPLETO' || programa?.estado === 'APROBADO' || programa?.estado === 'BASICO_COMPLETO'),
  );

  function handleApprove() {
    isApproving = true;
    router.put(`/admin/cursos/${curso.id_curso}/programa/aprobar`, {}, { onFinish: () => (isApproving = false) });
  }

  function handleReject() {
    isRejecting = true;
    router.put(`/admin/cursos/${curso.id_curso}/programa/rechazar`, { razon_rechazo: rejectionReason }, { onFinish: () => (isRejecting = false) });
  }

  function openAdminSyllabusModal() {
    selectedSyllabusType = 'combined';
    isSyllabusModalOpen = true;
  }

  function handleAdminSyllabusSuccess() {
    isSyllabusModalOpen = false;
    router.reload();
  }
</script>

{#snippet pageContent()}
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
        <Link href={resolvedBackUrl}>
          <Button variant="ghost" size="icon" disabled={isLoading}>
            <ArrowLeft class="size-5" />
          </Button>
        </Link>
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-foreground">{title}</h1>
          <p class="text-muted-foreground">
            {curso?.nombre ?? ''}{#if asignatura?.cod_asignatura}
              - {asignatura.cod_asignatura}{/if}
          </p>
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
    {#if asignatura}
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
              <Badge variant="secondary">v{programa.version_programa ?? programa.version}</Badge>
            {:else}
              <Badge variant="outline">No generado</Badge>
            {/if}
          </div>
        </CardContent>
      </Card>
    {/if}

    <!-- Admin: Fechas límite de entrega (siempre visible para admin) -->
    {#if layoutType === 'admin'}
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm no-print">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2">
            <CalendarDays class="h-5 w-5 text-blue-600" />
            <h2 class="text-base font-semibold text-gray-900">Fechas límite de entrega</h2>
          </div>
          {#if !editingDates}
            <Button variant="ghost" size="sm" onclick={() => (editingDates = true)} class="gap-1.5 text-gray-600">
              <Pencil class="h-3.5 w-3.5" />
              Editar
            </Button>
          {/if}
        </div>

        {#if editingDates}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="admin-date-basico" class="block text-sm font-medium text-gray-700 mb-1">
                Fecha límite — Básico
                <span class="text-xs text-gray-400 font-normal ml-1">(plazo para entregar el programa básico)</span>
              </label>
              <DatePickerCL id="admin-date-basico" value={dateBasico} onchange={(v) => (dateBasico = v)} disabled={isSavingDates} />
            </div>
            <div>
              <label for="admin-date-syllabus" class="block text-sm font-medium text-gray-700 mb-1">
                Fecha límite — Syllabus completo
                <span class="text-xs text-gray-400 font-normal ml-1">(debe ser posterior al básico)</span>
              </label>
              <DatePickerCL
                id="admin-date-syllabus"
                value={dateSyllabus}
                minValue={dateBasico}
                onchange={(v) => (dateSyllabus = v)}
                disabled={isSavingDates}
              />
            </div>
          </div>
          <div class="flex gap-2 mt-4 justify-end">
            <Button variant="outline" size="sm" onclick={cancelDateEdit} disabled={isSavingDates} class="gap-1.5">
              <X class="h-3.5 w-3.5" />
              Cancelar
            </Button>
            <Button size="sm" onclick={saveDates} disabled={isSavingDates} class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white">
              <Save class="h-3.5 w-3.5" />
              {isSavingDates ? 'Guardando...' : 'Guardar fechas'}
            </Button>
          </div>
        {:else}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
              <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Básico</p>
              <p
                class={`text-sm font-semibold ${isOverdue((curso as any).fecha_limite_entrega_basico) && programa?.estado === 'BORRADOR' ? 'text-red-600' : 'text-gray-900'}`}
              >
                {formatDate((curso as any).fecha_limite_entrega_basico)}
              </p>
              {#if isOverdue((curso as any).fecha_limite_entrega_basico) && programa?.estado === 'BORRADOR'}
                <p class="text-xs text-red-500 mt-0.5">Plazo vencido</p>
              {/if}
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
              <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Syllabus completo</p>
              <p
                class={`text-sm font-semibold ${isOverdue((curso as any).fecha_limite_entrega_syllabus) && programa?.estado === 'BASICO_COMPLETO' ? 'text-red-600' : 'text-gray-900'}`}
              >
                {formatDate((curso as any).fecha_limite_entrega_syllabus)}
              </p>
              {#if isOverdue((curso as any).fecha_limite_entrega_syllabus) && programa?.estado === 'BASICO_COMPLETO'}
                <p class="text-xs text-red-500 mt-0.5">Plazo vencido</p>
              {/if}
            </div>
          </div>
          {#if !(curso as any).fecha_limite_entrega_basico && !(curso as any).fecha_limite_entrega_syllabus}
            <p class="text-sm text-gray-400 italic mt-1">No se han definido fechas límite para este curso.</p>
          {/if}
        {/if}
      </div>
    {/if}

    <!-- Deadline Banner (no-print, docente only) -->
    {#if activeDeadline}
      {@const days = daysLeft(activeDeadline.value)}
      {@const overdue = days !== null && days < 0}
      {@const urgent = days !== null && days >= 0 && days <= 3}
      <div
        class="no-print flex items-start gap-3 rounded-lg border p-4 {overdue
          ? 'border-red-200 bg-red-50'
          : urgent
            ? 'border-yellow-200 bg-yellow-50'
            : 'border-blue-200 bg-blue-50'}"
      >
        <CalendarDays class="h-5 w-5 flex-shrink-0 mt-0.5 {overdue ? 'text-red-600' : urgent ? 'text-yellow-600' : 'text-blue-600'}" />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium {overdue ? 'text-red-900' : urgent ? 'text-yellow-900' : 'text-blue-900'}">{activeDeadline.label}</p>
          <p class="text-sm {overdue ? 'text-red-700' : urgent ? 'text-yellow-700' : 'text-blue-700'} mt-0.5">
            {formatDeadline(activeDeadline.value)}
            {#if days !== null}
              &nbsp;·&nbsp;
              {#if overdue}
                <span class="font-semibold">Vencido hace {Math.abs(days)} día{Math.abs(days) !== 1 ? 's' : ''}</span>
              {:else if days === 0}
                <span class="font-semibold">Vence hoy</span>
              {:else}
                <Clock class="inline h-3.5 w-3.5 -mt-0.5" />
                <span> {days} día{days !== 1 ? 's' : ''} restante{days !== 1 ? 's' : ''}</span>
              {/if}
            {/if}
          </p>
        </div>
      </div>
    {/if}

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
              {:else if programa.estado === 'ENVIADO' || programa.estado === 'COMPLETO'}
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
          {#if canEditPrograma && layoutType !== 'admin'}
            {#if programa.estado === 'BASICO_COMPLETO'}
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
                  onclick={openEditBasico}
                  class="flex items-center gap-2 px-4 py-1.5 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                >
                  <Edit2 size={14} />
                  Editar sección básica
                </button>
              </div>
            {:else if programa.estado !== 'APROBADO'}
              {@const esTipoBasico = programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO'}
              <div class="mt-8 flex justify-center pt-6 border-t border-slate-200">
                <button
                  onclick={esTipoBasico ? openEditBasico : openEditCompleto}
                  class="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                >
                  <Edit2 size={18} />
                  {esTipoBasico ? 'Editar programa básico' : 'Editar Contenidos'}
                </button>
              </div>
            {/if}
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
              <p class="text-sm text-blue-800">Inicia la creación del programa básico de la asignatura.</p>
            </div>
            <Button
              variant="default"
              size="lg"
              onclick={() => {
                selectedSyllabusType = 'simplified';
                isSyllabusModalOpen = true;
              }}
            >
              <Plus class="mr-2 size-5" />
              Crear Programa
            </Button>
          </div>
        </CardContent>
      </Card>
    {/if}

    <!-- Admin: Panel de acciones (aprobación / rechazo) -->
    {#if showActionPanel}
      <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm no-print">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Acciones</h2>

        {#if programa.estado === 'COMPLETO' && canApprovePrograma}
          <div class="space-y-4">
            <Alert variant="default">
              <AlertCircle class="h-4 w-4" />
              <div>
                <p class="font-medium">Syllabus completo pendiente de aprobación</p>
                <p class="text-sm">El docente ha completado todas las secciones. Puedes aprobarlo o devolverlo para revisión.</p>
              </div>
            </Alert>

            {#if showRejectionReason}
              <div>
                <label for="rejection-reason" class="block text-sm font-medium text-gray-900 mb-2">Motivo (opcional)</label>
                <textarea
                  id="rejection-reason"
                  bind:value={rejectionReason}
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                  placeholder="Indica al docente qué debe corregir..."
                ></textarea>
              </div>
            {/if}

            <div class="flex gap-3 pt-2">
              <Button onclick={handleApprove} disabled={isApproving} class="flex-1 bg-green-600 hover:bg-green-700 text-white">
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
                <Button onclick={handleReject} disabled={isRejecting} class="flex-1 bg-red-600 hover:bg-red-700 text-white">
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
          <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 flex items-center justify-between gap-4">
            <div>
              <p class="font-medium text-emerald-900">✅ Programa básico entregado</p>
              <p class="text-sm text-emerald-700 mt-1">
                La versión básica no requiere aprobación. Una vez que el docente complete el syllabus (secciones III–IX), aparecerá aquí para
                aprobación.
              </p>
            </div>
            <Button onclick={openAdminSyllabusModal} class="bg-emerald-600 hover:bg-emerald-700 text-white shrink-0">Completar Syllabus</Button>
          </div>
        {/if}
      </div>
    {/if}

    <!-- Syllabus Modal Editor -->
    {#if isSyllabusModalOpen}
      <SyllabusModal
        bind:isOpen={isSyllabusModalOpen}
        curso={preparedCurso}
        syllabusType={selectedSyllabusType}
        onClose={closeSyllabusModal}
        onSuccess={layoutType === 'admin' ? handleAdminSyllabusSuccess : handleSyllabusSuccess}
        canApprove={canApprovePrograma}
      />
    {/if}
  </div>
{/snippet}

{#if layoutType === 'ayudante'}
  <AyudanteLayout {breadcrumbs}>
    {@render pageContent()}
  </AyudanteLayout>
{:else if layoutType === 'estudiante'}
  <StudentLayout {breadcrumbs}>
    {@render pageContent()}
  </StudentLayout>
{:else if layoutType === 'admin'}
  <AdminLayout {breadcrumbs}>
    {@render pageContent()}
  </AdminLayout>
{:else}
  <DocenteLayout>
    {@render pageContent()}
  </DocenteLayout>
{/if}
