<script lang="ts">
  /**
   * usuarioImport — Modal de importación masiva de usuarios desde Excel/CSV.
   *
   * El flujo (formato → revisión → confirmación) vive en UserImport; aquí
   * sólo se envuelve y se controla el botón de confirmar, que permanece
   * deshabilitado mientras la revisión no dé el archivo por bueno.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import UserImport from '@/components/admin/UserImport.svelte';

  import { UserType } from '@/types/usuarios/tipos';

  interface ColumnaImportacion {
    campo: string;
    etiqueta: string;
    obligatorio: boolean;
    ejemplo: string;
  }

  interface Props {
    isOpen: boolean;
    /** Tipo de usuario a importar (define columnas esperadas del archivo). */
    userType: (typeof UserType)[keyof typeof UserType];
    /** Columnas esperadas; las declara el servidor. */
    columnas?: ColumnaImportacion[];
    isLoading: boolean;
    /** Archivo seleccionado; bindeable para que el padre lo envíe. */
    file?: File | null;
    onClose: () => void;
    onSubmit: () => void;
  }

  let {
    isOpen,
    userType,
    columnas = [],
    isLoading,
    file = $bindable(null),
    onClose,
    onSubmit,
  }: Props = $props();

  const titulos: Record<(typeof UserType)[keyof typeof UserType], string> = {
    [UserType.STUDENT]: 'Importar estudiantes',
    [UserType.TEACHER]: 'Importar docentes',
    [UserType.ADMIN]: 'Importar administradores',
  };

  let listoParaImportar = $state(false);

  // Cerrar descarta la revisión: al reabrir se empieza de cero.
  function handleClose() {
    listoParaImportar = false;
    onClose();
  }
</script>

<FormModal
  {isOpen}
  title={titulos[userType]}
  onClose={handleClose}
  {onSubmit}
  submitLabel="Importar"
  {isLoading}
  submitDisabled={!listoParaImportar}
>
  <UserImport tipo={userType} {columnas} bind:file bind:listoParaImportar />
</FormModal>
