<script lang="ts">
  /**
   * cursoDeleteConfirm — Confirmación de eliminación de un curso ofertado.
   *
   * Este diálogo era el único del panel que ya identificaba el registro;
   * ahora esa identificación es el comportamiento por defecto de
   * ConfirmationModal y aquí sólo se aportan los datos del curso.
   *
   * Totalmente controlado: el padre ejecuta la eliminación en onConfirm.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import type { Curso } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    curso?: Curso | null;
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    curso = $bindable<Curso | null>(null),
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  function handleCancel() {
    isOpen = false;
    onCancel();
  }

  // cod_curso viene como número desde el backend; el diálogo lo trata como
  // texto para mostrarlo y para compararlo con lo que el usuario escribe.
  const codigo = $derived(curso?.cod_curso != null ? String(curso.cod_curso) : null);
</script>

<ConfirmationModal
  bind:isOpen
  tone="danger"
  title="Eliminar curso"
  recordName={curso?.asignatura_nombre ?? null}
  recordMeta={[codigo ?? '', curso?.carrera_nombre ?? '']}
  message="El curso, sus secciones y sus inscripciones se eliminan de forma definitiva."
  confirmPhrase={codigo}
  confirmLabel="Eliminar curso"
  {isLoading}
  {onConfirm}
  onCancel={handleCancel}
/>
