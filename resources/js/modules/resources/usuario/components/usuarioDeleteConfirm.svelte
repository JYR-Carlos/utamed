<script lang="ts">
  /**
   * usuarioDeleteConfirm — Confirmación de eliminación de un usuario, con
   * título según su tipo.
   *
   * Totalmente controlado: el padre ejecuta deleteUsuario() en onConfirm.
   * El backend rechaza el borrado si el usuario tiene registros asociados.
   */
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import { USER_TYPE_LABELS } from '@/constants/admin';

  type UserType = 'estudiante' | 'docente' | 'administrador';

  interface Props {
    isOpen: boolean;
    /** Solo afecta el título del diálogo. */
    userType: UserType;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading: boolean;
    onConfirm: () => void;
    onCancel: () => void;
  }

  let { isOpen, userType, isLoading, onConfirm, onCancel }: Props = $props();
</script>

<DeleteConfirmation
  {isOpen}
  title="¿Eliminar {USER_TYPE_LABELS[userType]}?"
  message="Esta acción no se puede deshacer. Si el usuario tiene registros asociados, no podrá ser eliminado."
  {onConfirm}
  {onCancel}
  {isLoading}
/>
