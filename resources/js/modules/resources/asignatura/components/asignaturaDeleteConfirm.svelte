<script lang="ts">
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import { router } from '@inertiajs/svelte';
  import type { Asignatura } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    deletingAsignatura?: Asignatura | null;
    onSuccess?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    deletingAsignatura = null,
    onSuccess = () => {},
    onCancel = () => {},
  }: Props = $props();

  let isLoading = $state(false);

  function handleConfirm() {
    if (!deletingAsignatura) return;
    isLoading = true;
    router.delete(`/admin/asignaturas/${deletingAsignatura.id_asignatura}`, {
      onSuccess: () => {
        isOpen = false;
        isLoading = false;
        onSuccess();
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  function handleCancel() {
    onCancel();
  }
</script>

<DeleteConfirmation
  bind:isOpen
  title="¿Eliminar Asignatura?"
  message="Esta acción no se puede deshacer. Si la asignatura está asignada a planes, no podrá ser eliminada."
  onConfirm={handleConfirm}
  onCancel={handleCancel}
  {isLoading}
/>
