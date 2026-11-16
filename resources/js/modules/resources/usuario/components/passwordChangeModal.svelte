<script lang="ts">
  /**
   * passwordChangeModal — Modal para que el admin cambie la contraseña de
   * un usuario (mínimo 8 caracteres con una mayúscula, números y algún signo, con
   * confirmación; la lista de requisitos se actualiza mientras se escribe).
   *
   * Componente controlado: el formulario vive en el padre (bindeable) y el
   * POST lo hace el padre vía usuarioApi.changePassword.
   */
  import { Eye, EyeOff } from 'lucide-svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import RequisitosPassword from '@/components/custom/common/RequisitosPassword.svelte';
  import { PASSWORD_HINT, PASSWORD_MIN_LENGTH } from '@/constants/admin';

  interface Props {
    isOpen: boolean;
    /** Estado del formulario; vive en el padre y se bindea aquí. */
    passwordFormData: {
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

  let showPassword = $state(false);
  let showPasswordConfirmation = $state(false);

  function handleClose() {
    showPassword = false;
    showPasswordConfirmation = false;
    onClose();
  }
</script>

<FormModal {isOpen} title="Cambiar Contraseña" onClose={handleClose} {onSubmit} {isLoading}>
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
    <div class="relative">
      <input
        id="new_password"
        type={showPassword ? 'text' : 'password'}
        autocomplete="new-password"
        bind:value={passwordFormData.password}
        class="field-input pr-10"
        placeholder={PASSWORD_HINT}
        minlength={PASSWORD_MIN_LENGTH}
        required
      />
      <button
        type="button"
        aria-label={showPassword ? 'Ocultar contraseña' : 'Ver contraseña'}
        aria-pressed={showPassword}
        onclick={() => (showPassword = !showPassword)}
        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none cursor-pointer"
      >
        {#if showPassword}
          <EyeOff size={18} aria-hidden="true" />
        {:else}
          <Eye size={18} aria-hidden="true" />
        {/if}
      </button>
    </div>
  </div>

  <div class="mb-4">
    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2"
      >Confirmar Contraseña *</label
    >
    <div class="relative">
      <input
        id="password_confirmation"
        type={showPasswordConfirmation ? 'text' : 'password'}
        autocomplete="new-password"
        bind:value={passwordFormData.password_confirmation}
        class="field-input pr-10"
        placeholder="Repita la contraseña"
        minlength={PASSWORD_MIN_LENGTH}
        required
      />
      <button
        type="button"
        aria-label={showPasswordConfirmation ? 'Ocultar confirmación de contraseña' : 'Ver confirmación de contraseña'}
        aria-pressed={showPasswordConfirmation}
        onclick={() => (showPasswordConfirmation = !showPasswordConfirmation)}
        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none cursor-pointer"
      >
        {#if showPasswordConfirmation}
          <EyeOff size={18} aria-hidden="true" />
        {:else}
          <Eye size={18} aria-hidden="true" />
        {/if}
      </button>
    </div>
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
