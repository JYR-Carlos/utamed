<script lang="ts">
  /**
   * inscripcionDeleteConfirm — Confirmación de borrado definitivo de una
   * inscripción; el mensaje sugiere preferir los estados Retirado/Anulado
   * para conservar el historial académico.
   *
   * Totalmente controlado: el padre ejecuta deleteInscripcion() en onConfirm.
   */
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';

  /**
   * Sólo lo que el diálogo necesita mostrar. Se declara estructuralmente
   * (y no como RosterItem) porque la página de edición trabaja con su
   * propia forma de inscripción, más estrecha que la de la nómina.
   */
  interface InscripcionIdentificable {
    num_intento?: number;
    estudiante?: { usuario?: { nombre1: string; apellido1: string; username: string } };
    curso?: { asignatura_nombre?: string; nombre?: string };
  }

  interface Props {
    isOpen?: boolean;
    /** Inscripción afectada; identifica al estudiante en el diálogo. */
    inscripcion?: InscripcionIdentificable | null;
    /** Borrando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onConfirm?: () => void;
    onCancel?: () => void;
  }

  let {
    isOpen = $bindable(false),
    inscripcion = null,
    isLoading = false,
    onConfirm = () => {},
    onCancel = () => {},
  }: Props = $props();

  const estudiante = $derived(
    inscripcion?.estudiante?.usuario
      ? `${inscripcion.estudiante.usuario.nombre1} ${inscripcion.estudiante.usuario.apellido1}`
      : null,
  );

  const meta = $derived(
    inscripcion
      ? [
          inscripcion.estudiante?.usuario?.username ?? '',
          inscripcion.curso?.asignatura_nombre ?? inscripcion.curso?.nombre ?? '',
          inscripcion.num_intento ? `Intento ${inscripcion.num_intento}` : '',
        ]
      : [],
  );
</script>

<ConfirmationModal
  bind:isOpen
  tone="danger"
  title="Eliminar inscripción"
  recordName={estudiante}
  recordMeta={meta}
  message="La inscripción y su historial de notas se eliminan de forma definitiva."
  confirmLabel="Eliminar inscripción"
  {onConfirm}
  {onCancel}
  {isLoading}
>
  <div class="rounded-lg p-3 text-[13px] leading-relaxed bg-[var(--state-info-soft)] text-[var(--state-info)]">
    Para sacar a un estudiante del curso conservando su historial, cambia el estado de la
    inscripción a <strong>Retirado</strong> o <strong>Anulado</strong> en lugar de eliminarla.
  </div>
</ConfirmationModal>
