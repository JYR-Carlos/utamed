<script lang="ts">
  /**
   * usuarioDeleteConfirm — Confirmación de eliminación de un usuario.
   *
   * Totalmente controlado: el padre ejecuta deleteUsuario() en onConfirm.
   * El backend rechaza el borrado si el usuario tiene registros asociados.
   *
   * El diálogo nombra a la persona y pide escribir su RUT: antes decía
   * sólo «¿Eliminar Estudiante?» sobre una fila que el modal tapaba, de
   * modo que nada distinguía a un estudiante de otro al confirmar.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import { USER_TYPE_LABELS } from '@/constants/admin';
  import type { UsuarioItem } from '@/types/admin.types';

  type UserType = 'estudiante' | 'docente' | 'administrador';

  interface Props {
    isOpen: boolean;
    /** Solo afecta la redacción del título. */
    userType: UserType;
    /** Usuario afectado; se identifica en el diálogo. */
    usuario?: UsuarioItem | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading: boolean;
    onConfirm: () => void;
    onCancel: () => void;
  }

  let { isOpen, userType, usuario = null, isLoading, onConfirm, onCancel }: Props = $props();

  const nombre = $derived(
    usuario
      ? [
          usuario.usuario.nombre1,
          usuario.usuario.nombre2,
          usuario.usuario.apellido1,
          usuario.usuario.apellido2,
        ]
          .filter(Boolean)
          .join(' ')
      : null,
  );

  const meta = $derived(
    usuario ? [usuario.usuario.rut ?? '', usuario.usuario.email ?? ''] : [],
  );
</script>

<ConfirmationModal
  {isOpen}
  tone="danger"
  title="Eliminar {USER_TYPE_LABELS[userType].toLowerCase()}"
  recordName={nombre}
  recordMeta={meta}
  message="La cuenta y sus datos personales se eliminan de forma definitiva. Si la persona tiene inscripciones, notas u otros registros asociados, el sistema no permitirá eliminarla; en ese caso desactiva la cuenta."
  confirmPhrase={usuario?.usuario.rut ?? null}
  confirmLabel="Eliminar {USER_TYPE_LABELS[userType].toLowerCase()}"
  {onConfirm}
  {onCancel}
  {isLoading}
/>
