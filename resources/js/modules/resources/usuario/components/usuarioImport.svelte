<script lang="ts">
  /**
   * usuarioImport — Modal de importación masiva de usuarios desde Excel/CSV
   * para el tipo indicado. La selección de archivo la maneja UserImport;
   * aquí solo se envuelve en FormModal y se bindea el archivo al padre, que
   * es quien hace el POST.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import UserImport from '@/components/admin/UserImport.svelte';

  import { UserType } from '@/types/usuarios/tipos';

  interface Props {
    isOpen: boolean;
    /** Tipo de usuario a importar (define columnas esperadas del archivo). */
    userType: typeof UserType[keyof typeof UserType];
    isLoading: boolean;
    /** Archivo seleccionado; bindeable para que el padre lo envíe. */
    file?: File | null;
    onClose: () => void;
    onSubmit: () => void;
  }

  let {
    isOpen,
    userType,
    isLoading,
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