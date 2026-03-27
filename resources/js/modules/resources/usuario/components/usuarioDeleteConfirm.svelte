<script lang="ts">
  /**
   * Componente confirmación de eliminación de usuario.
   *
   * Wrapper alrededor del DeleteConfirmation genérico con mensaje específico para usuarios.
   */
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';

  type UserType = 'estudiante' | 'docente' | 'administrador';

  const USER_TYPE_LABELS: Record<UserType, string> = {
    estudiante: 'Estudiante',
    docente: 'Docente',
    administrador: 'Administrador',
  };

  interface Props {
    isOpen: boolean;
    userType: UserType;
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
