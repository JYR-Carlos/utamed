<script lang="ts">
  /**
   * usuarioForm — Modal de creación/edición de usuarios de cualquier tipo
   * (estudiante, docente, administrador). Los campos concretos los renderiza
   * UserForm según el tipo; este componente solo envuelve en FormModal.
   *
   * Componente controlado: formData vive en el padre (que hace el HTTP vía
   * usuarioApi) y llega bindeable.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import UserForm from '@/components/admin/UserForm.svelte';
  import type { UsuarioItem, Carrera } from '@/types/admin.types';
  import type {
    EstudianteFormData,
    DocenteFormData,
    AdministradorFormData,
  } from '@/types/admin.types';
  import { USER_TYPE_LABELS } from '@/constants/admin';

  type UserType = 'estudiante' | 'docente' | 'administrador';
  type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

  interface Props {
    isOpen: boolean;
    /** Usuario a editar; null para modo creación. */
    editingUsuario: UsuarioItem | null;
    userType: UserType;
    /** Estado del formulario; vive en el padre y se bindea aquí. */
    formData: UserFormData;
    /** Bloquea los campos personales cuando el RUT ya existe en el sistema. */
    deshabilitarCampos: boolean;
    /** Opciones de carrera (solo relevante para estudiantes). */
    carreras: Carrera[];
    isLoading: boolean;
    onClose: () => void;
    onSubmit: () => void;
  }

  let {
    isOpen,
    editingUsuario,
    userType,
    formData = $bindable(),
    deshabilitarCampos,
    carreras,
    isLoading,
    onClose,
    onSubmit,
  }: Props = $props();
</script>

<FormModal
  {isOpen}
  title={editingUsuario
    ? `Editar ${USER_TYPE_LABELS[userType]}`
    : `Nuevo ${USER_TYPE_LABELS[userType]}`}
  {onClose}
  {onSubmit}
  {isLoading}
>
  <UserForm deshabilitarCampos={deshabilitarCampos} {formData} tipo={userType} isEditing={!!editingUsuario} {carreras} />
</FormModal>
