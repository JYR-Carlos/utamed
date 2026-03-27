<script lang="ts">
  /**
   * Componente modal para cambiar contraseña de usuario.
   *
   * Formulario de cambio de contraseña con validación de campos requeridos.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';

  interface Props {
    isOpen: boolean;
    passwordFormData: { password: string; password_confirmation: string };
    isLoading: boolean;
    onClose: () => void;
    onSubmit: () => void;
  }

  let { isOpen, passwordFormData = $bindable(), isLoading, onClose, onSubmit }: Props = $props();
</script>

<FormModal {isOpen} title="Cambiar Contraseña" {onClose} {onSubmit} {isLoading}>
  <div class="mb-4">
    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2"
      >Nueva Contraseña *</label
    >
    <input
      id="new_password"
      type="password"
      bind:value={passwordFormData.password}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      placeholder="Mín. 6 caracteres"
      minlength="6"
      required
    />
  </div>

  <div class="mb-4">
    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2"
      >Confirmar Contraseña *</label
    >
    <input
      id="password_confirmation"
      type="password"
      bind:value={passwordFormData.password_confirmation}
      class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      placeholder="Repita la contraseña"
      minlength="6"
      required
    />
  </div>
</FormModal>
