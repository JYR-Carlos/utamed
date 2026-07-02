<script lang="ts">
  import { Button } from '@/components/ui/button';
  import { Check, X, Eye, Edit2 } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';

  interface Programa {
    id_programa: number;
    version_programa: number;
    estado: string;
    creado_por: number;
    data_syllabus?: any;
  }

  interface Props {
    programa: Programa;
    cursoId: number;
    userRole: string;
    userId: number;
    onApprove: (id: number) => void;
    onReject: (id: number, razon: string) => void;
    onEdit: (id: number) => void;
  }

  let { programa, cursoId, userRole, userId, onApprove, onReject, onEdit }: Props = $props();

  let showApproveDialog = $state(false);
  let showRejectDialog = $state(false);
  let rejectReason = $state('');
  let isSubmitting = $state(false);

  const isAdmin = $derived(
    userRole === 'admin' || userRole === 'administrator' || userRole === 'Admin',
  );
  const canApprove = $derived(isAdmin && programa.estado === 'COMPLETO');
  const canReject = $derived(isAdmin && programa.estado === 'APROBADO');
  const canEdit = $derived(userId === programa.creado_por || isAdmin);

  function handleApprove() {
    isSubmitting = true;
    router.put(
      `/admin/cursos/${cursoId}/programa/aprobar`,
      {},
      {
        onSuccess: () => {
          onApprove(programa.id_programa);
          showApproveDialog = false;
        },
        onFinish: () => (isSubmitting = false),
      },
    );
  }

  function handleReject() {
    if (!rejectReason.trim()) {
      alert('Debe proporcionar una razón para rechazar');
      return;
    }
    isSubmitting = true;
    router.put(
      `/admin/cursos/${cursoId}/programa/rechazar`,
      { razon_rechazo: rejectReason, accion_tipo: 'rechazo' },
      {
        onSuccess: () => {
          onReject(programa.id_programa, rejectReason);
          showRejectDialog = false;
          rejectReason = '';
        },
        onFinish: () => (isSubmitting = false),
      },
    );
  }
</script>

<div class="flex flex-wrap items-center gap-2">
  <!-- Ver Detalles -->
  <Button variant="outline" size="sm" title="Ver detalles del programa">
    <Eye size={16} />
  </Button>

  <!-- Editar -->
  {#if canEdit}
    <Button
      variant="outline"
      size="sm"
      onclick={() => onEdit(programa.id_programa)}
      title="Editar programa"
    >
      <Edit2 size={16} />
    </Button>
  {/if}

  <!-- Aprobar (Solo Admin) -->
  {#if canApprove}
    <Button
      variant="default"
      size="sm"
      class="bg-green-600 hover:bg-green-700"
      onclick={() => (showApproveDialog = true)}
      title="Aprobar este programa"
    >
      <Check size={16} class="mr-1" />
      Aprobar
    </Button>
  {/if}

  <!-- Rechazar (Solo Admin) -->
  {#if canReject}
    <Button
      variant="destructive"
      size="sm"
      onclick={() => (showRejectDialog = true)}
      title="Rechazar este programa"
    >
      <X size={16} class="mr-1" />
      Rechazar
    </Button>
  {/if}
</div>

<!-- Diálogo de Aprobación -->
{#if showApproveDialog}
  <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
      <div class="bg-green-50 px-6 py-4 border-b border-green-200">
        <h3 class="text-lg font-bold text-green-900">Confirmar Aprobación</h3>
      </div>

      <div class="px-6 py-4">
        <p class="text-slate-700 mb-2">
          ¿Desea <strong>aprobar</strong> este programa?
        </p>
        <p class="text-sm text-slate-600 mb-4">
          El estado cambiará de <code class="bg-gray-100 px-1 rounded">COMPLETO</code> a
          <code class="bg-gray-100 px-1 rounded">APROBADO</code>.
        </p>
        <div class="bg-blue-50 p-3 rounded-lg text-sm text-blue-800 border border-blue-200">
          <strong>Nota:</strong> Los cambios serán guardados y se registrará como revisado por usted.
        </div>
      </div>

      <div class="bg-gray-50 px-6 py-4 border-t flex justify-end gap-3">
        <Button
          variant="outline"
          onclick={() => (showApproveDialog = false)}
          disabled={isSubmitting}>Cancelar</Button
        >
        <Button
          variant="default"
          class="bg-green-600 hover:bg-green-700"
          onclick={handleApprove}
          disabled={isSubmitting}
        >
          {isSubmitting ? '⏳ Aprobando...' : 'Sí, Aprobar'}
        </Button>
      </div>
    </div>
  </div>
{/if}

<!-- Diálogo de Rechazo -->
{#if showRejectDialog}
  <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
      <div class="bg-red-50 px-6 py-4 border-b border-red-200">
        <h3 class="text-lg font-bold text-red-900">Rechazar Programa</h3>
      </div>

      <div class="px-6 py-4">
        <p class="text-slate-700 mb-4">
          ¿Desea <strong>rechazar</strong> este programa?
        </p>

        <label for="reject-reason" class="block text-sm font-semibold text-slate-700 mb-2">
          Razón del rechazo <span class="text-red-500">*</span>
        </label>
        <textarea
          id="reject-reason"
          bind:value={rejectReason}
          placeholder="Describe por qué se rechaza el programa..."
          rows={4}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
        ></textarea>

        <div
          class="mt-4 bg-yellow-50 p-3 rounded-lg text-sm text-yellow-800 border border-yellow-200"
        >
          <strong>Nota:</strong> El estado volverá a
          <code class="bg-yellow-100 px-1 rounded">BASICO_COMPLETO</code>
          o
          <code class="bg-yellow-100 px-1 rounded">COMPLETO</code>
          según el tipo de syllabus.
        </div>
      </div>

      <div class="bg-gray-50 px-6 py-4 border-t flex justify-end gap-3">
        <Button variant="outline" onclick={() => (showRejectDialog = false)} disabled={isSubmitting}
          >Cancelar</Button
        >
        <Button variant="destructive" onclick={handleReject} disabled={isSubmitting}>
          {isSubmitting ? '⏳ Rechazando...' : 'Sí, Rechazar'}
        </Button>
      </div>
    </div>
  </div>
{/if}
