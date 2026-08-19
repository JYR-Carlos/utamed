<script lang="ts">
  /**
   * facultadForm — Modal de creación/edición de facultades (un solo campo:
   * nombre).
   *
   * Componente controlado: no hace HTTP; delega en onSubmit({nombre}) y el
   * padre llama a facultadApi.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import type { Facultad, FacultadFormData } from '@/types/admin.types';

  interface Props {
    isOpen?: boolean;
    /** Modo edición; el padre lo pasa junto con la facultad. */
    isEditing?: boolean;
    /** Facultad en edición; null/undefined para modo creación. */
    facultad?: Facultad | null;
    /** Enviando; lo controla el padre porque es quien hace la petición. */
    isLoading?: boolean;
    onSubmit?: (formData: FacultadFormData) => void;
    onClose?: () => void;
  }

  let {
    isOpen = $bindable(false),
    isEditing = false,
    facultad,
    isLoading = false,
    onSubmit = () => {},
    onClose = () => {},
  }: Props = $props();

  // $derived con override (Svelte 5.25+): parte del nombre de la facultad en
  // edición, el input lo sobrescribe al escribir, y cuando cambia la prop
  // facultad el derived se recalcula descartando el override.
  let nombre = $derived(facultad?.nombre || '');

  function handleSubmit() {
    onSubmit?.({ nombre });
  }

  function handleClose() {
    // Limpia el override manualmente: si facultad no cambia entre aperturas,
    // el derived no se recalcula solo.
    nombre = '';
    onClose?.();
  }
</script>

<FormModal
  bind:isOpen
  title={isEditing ? 'Editar Facultad' : 'Nueva Facultad'}
  onClose={handleClose}
  onSubmit={handleSubmit}
  {isLoading}
>
  <div class="mb-4">
    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
    <input
      id="nombre"
      type="text"
      bind:value={nombre}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      placeholder="Ej: Facultad de Medicina"
      required
    />
  </div>
</FormModal>
