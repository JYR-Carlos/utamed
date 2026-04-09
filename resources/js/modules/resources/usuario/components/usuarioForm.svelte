<script lang="ts">
  /**
   * Componente formulario de usuarios.
   *
   * Formulario modal para crear y editar usuarios (estudiante, docente, administrador).
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import UserForm from '@/components/admin/UserForm.svelte';
  import type { UsuarioItem, Carrera } from '@/types/admin.types';
  import type {
    EstudianteFormData,
    DocenteFormData,
    AdministradorFormData,
  } from '@/types/admin.types';

  type UserType = 'estudiante' | 'docente' | 'administrador';
  type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

  const USER_TYPE_LABELS: Record<UserType, string> = {
    estudiante: 'Estudiante',
    docente: 'Docente',
    administrador: 'Administrador',
  };

  interface Props {
    isOpen: boolean;
    editingUsuario: UsuarioItem | null;
    userType: UserType;
    formData: UserFormData;
    deshabilitarCampos: boolean;
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
