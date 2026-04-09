<script lang="ts">
  /**
   * Componente formulario de usuarios.
   *
   * Formulario modal para importar usuarios (estudiante, docente, administrador).
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import UserImport from '@/components/admin/UserImport.svelte';

  import { UserType } from '@/types/usuarios/tipos';

  const USER_TYPE_LABELS: Record<typeof UserType[keyof typeof UserType], string> = {
    [UserType.STUDENT]: 'Estudiante',
    [UserType.TEACHER]: 'Docente',
    [UserType.ADMIN]: 'Administrador',
  };

  interface Props {
    isOpen: boolean;
    userType: typeof UserType[keyof typeof UserType];
    isLoading: boolean;
    // 1. NUEVO: Agregamos file a la interfaz
    file?: File | null; 
    onClose: () => void;
    onSubmit: () => void;
  }

  let {
    isOpen,
    userType,
    isLoading,
    // 2. NUEVO: Le decimos a Svelte que esta variable es bindable
    file = $bindable(null), 
    onClose,
    onSubmit,
  }: Props = $props();

</script>

<FormModal
  {isOpen}
  title="Importar desde archivo Excel o CSV"
  {onClose}
  {onSubmit}
  {isLoading}
>
  <UserImport tipo={userType} bind:file={file} />
</FormModal>