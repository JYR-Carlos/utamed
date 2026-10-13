<script lang="ts">
  /**
   * passwordChangeModal — Modal para que el admin cambie la contraseña de
   * un usuario (mínimo 8 caracteres con letras, números y algún signo, con
   * confirmación; la lista de requisitos se actualiza mientras se escribe).
   *
   * Cambiar la clave de una cuenta ajena es una toma de control, así que el
   * backend exige además que el administrador reautentique con su propia
   * contraseña.
   *
   * Componente controlado: el formulario vive en el padre (bindeable) y el
   * POST lo hace el padre vía usuarioApi.changePassword.
   */
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import RequisitosPassword from '@/components/custom/common/RequisitosPassword.svelte';
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
    onResetDefault?: () => void;
  }

  let {
    isOpen,
    passwordFormData = $bindable(),
    isLoading,
    onClose,
    onSubmit,
    onResetDefault,
  }: Props = $props();
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
  </div>

  {#if onResetDefault}
    
    <div class="space-y-1.5 my-4">
      <button
        type="button"
        onclick={onResetDefault}
        disabled={isLoading}
        class="cursor-pointer w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-lg text-amber-800 bg-amber-50 border border-amber-300 hover:bg-amber-100 hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        Reiniciar a Contraseña por Defecto 
      </button>
    </div>
  {/if}

  <hr class="my-4">
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

  <div class="mb-4">
    <RequisitosPassword
      password={passwordFormData.password}
      confirmacion={passwordFormData.password_confirmation}
    />
  </div>

  <p class="text-xs text-gray-500">
    Al cambiar la contraseña, se cerrarán todas las sesiones activas de ese usuario.
  </p>

  
</FormModal>
