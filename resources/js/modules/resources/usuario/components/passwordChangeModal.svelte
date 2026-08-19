<script lang="ts">
  /**
   * passwordChangeModal — Modal para que el admin cambie la contraseña de
   * un usuario (mínimo 8 caracteres con letras y números, con confirmación).
   *
   * Cambiar la clave de una cuenta ajena es una toma de control, así que el
   * backend exige además que el administrador reautentique con su propia
   * contraseña.
   *
   * Componente controlado: el formulario vive en el padre (bindeable) y el
   * POST lo hace el padre vía usuarioApi.changePassword.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import { PASSWORD_HINT, PASSWORD_MIN_LENGTH } from '@/constants/admin';

  interface Props {
    isOpen: boolean;
    /** Estado del formulario; vive en el padre y se bindea aquí. */
    passwordFormData: {
      current_password: string;
      password: string;
      password_confirmation: string;
    };
    isLoading: boolean;
    onClose: () => void;
    onSubmit: () => void;
  }

  let { isOpen, passwordFormData = $bindable(), isLoading, onClose, onSubmit }: Props = $props();
</script>

<FormModal {isOpen} title="Cambiar Contraseña" {onClose} {onSubmit} {isLoading}>
  <div class="mb-4">
    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2"
      >Tu contraseña *</label
    >
    <input
      id="current_password"
      type="password"
      autocomplete="current-password"
      bind:value={passwordFormData.current_password}
      class="field-input"
      placeholder="Confirma tu identidad"
      required
    />
    <p class="mt-1 text-xs text-gray-500">
      Se pide para confirmar que eres tú quien realiza el cambio.
    </p>
  </div>

  <div class="mb-4">
    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2"
      >Nueva Contraseña *</label
    >
    <input
      id="new_password"
      type="password"
      autocomplete="new-password"
      bind:value={passwordFormData.password}
      class="field-input"
      placeholder={PASSWORD_HINT}
      minlength={PASSWORD_MIN_LENGTH}
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
      autocomplete="new-password"
      bind:value={passwordFormData.password_confirmation}
      class="field-input"
      placeholder="Repita la contraseña"
      minlength={PASSWORD_MIN_LENGTH}
      required
    />
  </div>

  <p class="text-xs text-gray-500">
    Al cambiarla se cerrarán todas las sesiones activas de ese usuario.
  </p>
</FormModal>
