<script lang="ts">
  /**
   * Cambio de contraseña obligatorio: primer ingreso o clave vencida.
   *
   * Mientras la clave no cumpla la política, el middleware ForcePasswordChange
   * trae aquí al usuario desde cualquier otra ruta. Por eso la página no usa
   * AppLayout: la barra lateral y el menú sólo ofrecerían enlaces que vuelven
   * a esta misma pantalla. Lo único que se puede hacer es cambiar la clave o
   * cerrar sesión, y eso es todo lo que se muestra.
   *
   * Visualmente es la continuación del Login: misma foto de fondo, misma
   * tarjeta de cristal, mismos campos y botón. Lo que cambia es la composición:
   * aquí la tarjeta va sola y centrada, sin el panel de marca del Login. El
   * usuario ya entró; lo único que tiene delante es el formulario.
   */
  import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
  import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
  import { Button } from '@/components/ui/button';
  import { Spinner } from '@/components/ui/spinner';
  import { logout } from '@/routes';
  import type { BaseFormSnippetProps } from '@/types/forms';
  import { Form, page, router } from '@inertiajs/svelte';
  import { Eye, EyeOff } from 'lucide-svelte';
  import { onMount } from 'svelte';

  interface Props {
    /**
     * Por qué el sistema exige el cambio. Lo decide el backend
     * (`Usuario::motivoCambioPasswordObligatorio`), que es el mismo criterio
     * con el que el middleware bloquea la navegación: si la vista lo
     * recalculara por su cuenta, el aviso y el bloqueo podrían discrepar.
     */
    motivo: 'primer_ingreso' | 'vencida';
    /** Meses de vigencia de la política, para no repetir el "6" en el texto. */
    vigenciaMeses: number;
  }

  let { motivo, vigenciaMeses }: Props = $props();

  type Campo = 'current_password' | 'password' | 'password_confirmation';

  /** Qué campos muestran la clave en texto plano (ojo abierto). */
  let visibles = $state<Record<Campo, boolean>>({
    current_password: false,
    password: false,
    password_confirmation: false,
  });

  let usuario = $derived($page.props.auth.user);

  let descripcion = $derived(
    motivo === 'primer_ingreso'
      ? 'Estás usando la contraseña con la que se creó tu cuenta. Elige una nueva para entrar al portal.'
      : `Han pasado más de ${vigenciaMeses} meses desde tu último cambio. Elige una nueva para entrar al portal.`,
  );

  function enfocar(id: Campo) {
    document.getElementById(id)?.focus();
  }

  function cerrarSesion() {
    router.post(logout().url);
  }

  onMount(() => enfocar('current_password'));
</script>

<svelte:head>
  <title>Cambiar contraseña | UTAmed</title>
</svelte:head>

{#snippet campo(
  nombre: Campo,
  etiqueta: string,
  autocomplete: 'current-password' | 'new-password',
  error: string | undefined,
  disabled: boolean,
  ayuda?: string,
)}
  <div class="space-y-1.5">
    <label for={nombre} class="text-[13px] font-medium text-[#C4BFE0]">{etiqueta}</label>
    <div class="relative">
      <input
        id={nombre}
        name={nombre}
        type={visibles[nombre] ? 'text' : 'password'}
        required
        {autocomplete}
        {disabled}
        placeholder="••••••••"
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${nombre}-error` : ayuda ? `${nombre}-ayuda` : undefined}
        class="w-full rounded-[14px] border border-[rgba(255,255,255,0.15)] bg-[rgba(255,255,255,0.05)] px-4 py-3.5 pr-12 text-[#F5F3FF] transition-all duration-150 placeholder:text-[#C4BFE0]/60 focus:border-[#5B9BD5] focus:shadow-[0_0_0_3px_rgba(91,155,213,0.35)] focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-red-400/70"
      />
      <button
        type="button"
        tabindex={-1}
        {disabled}
        aria-label={visibles[nombre] ? 'Ocultar contraseña' : 'Mostrar contraseña'}
        class="absolute top-1/2 right-4 -translate-y-1/2 text-[#C4BFE0] transition-colors hover:text-[#F5F3FF] disabled:cursor-not-allowed disabled:opacity-50"
        onclick={() => (visibles[nombre] = !visibles[nombre])}
      >
        {#if visibles[nombre]}
          <EyeOff size={20} aria-hidden="true" />
        {:else}
          <Eye size={20} aria-hidden="true" />
        {/if}
      </button>
    </div>
    {#if error}
      <p id="{nombre}-error" class="text-xs text-red-300">{error}</p>
    {:else if ayuda}
      <p id="{nombre}-ayuda" class="text-xs text-[#C4BFE0]/70">{ayuda}</p>
    {/if}
  </div>
{/snippet}

<div class="relative min-h-screen w-full overflow-hidden bg-[#0d1522]">
  <!-- Fondo fotográfico full-bleed (el mismo del Login) -->
  <div
    class="absolute inset-0 scale-105 bg-cover bg-center blur-[2px]"
    style="background-image: url('/img/bardesign.jpg');"
    aria-hidden="true"
  ></div>

  <!-- Overlay nocturno violeta para legibilidad y atmósfera -->
  <div
    class="absolute inset-0 bg-gradient-to-br from-[#10233a]/80 via-[#0d1626]/75 to-[#070c15]/90"
    aria-hidden="true"
  ></div>

  <!-- Resplandor ambiental detrás de la card -->
  <div
    class="pointer-events-none absolute top-1/2 left-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#5B9BD5]/10 blur-[120px]"
    aria-hidden="true"
  ></div>

  <!-- Card centrada -->
  <div class="relative z-10 flex min-h-screen items-center justify-center px-2 py-4 sm:px-4">
    <div
      class="w-full max-w-180 rounded-3xl border border-[rgba(255,255,255,0.12)] bg-[rgba(28,44,64,0.45)] p-8 shadow-[0_24px_64px_rgba(0,0,0,0.35)] backdrop-blur-2xl sm:p-12"
    >
      <!-- Icono circular decorativo -->
      <div
        class="mx-auto mb-6 flex size-12 items-center justify-center rounded-full border border-dashed border-[rgba(255,255,255,0.25)]"
      >
        <AppLogoIcon class="size-6" />
      </div>

      <!-- Título -->
      <h1
        class="text-center text-[28px] leading-[1.2] font-semibold text-[#F5F3FF] sm:text-[34px]"
      >
        {motivo === 'primer_ingreso' ? 'Crea tu nueva' : 'Renueva tu'}
        <span class="bg-gradient-to-r from-white to-[#BFD9F2] bg-clip-text text-transparent"
          >contraseña</span
        >
      </h1>
      <p
        class="mx-auto mt-3 max-w-[360px] text-center text-[15px] leading-[1.5] text-[#C4BFE0]"
      >
        {descripcion}
      </p>

      <Form
        {...PasswordController.update.form()}
        options={{ preserveScroll: true }}
        onError={(errors) => {
          if (errors.current_password) {
            enfocar('current_password');
          } else if (errors.password) {
            enfocar('password');
          }
        }}
        resetOnError={['password', 'password_confirmation', 'current_password']}
        class="mt-8 flex flex-col gap-5"
      >
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
          {@render campo(
            'current_password',
            'Contraseña actual',
            'current-password',
            errors.current_password,
            processing,
            'La contraseña con la que acabas de ingresar.',
          )}

          {@render campo(
            'password',
            'Nueva contraseña',
            'new-password',
            errors.password,
            processing,
            'Mínimo 8 caracteres, con letras y números.',
          )}

          {@render campo(
            'password_confirmation',
            'Confirmar nueva contraseña',
            'new-password',
            errors.password_confirmation,
            processing,
          )}

          <!-- Submit Button -->
          <Button
            type="submit"
            class="mt-1 flex w-full cursor-pointer items-center justify-center gap-2 rounded-[14px] bg-[#F5F4F0] py-3.5 text-[15px] font-semibold text-[#1A1625] shadow-none transition-all duration-150 hover:font-semibold hover:text-white hover:brightness-95 active:scale-[0.98] disabled:opacity-50"
            disabled={processing}
          >
            {#if processing}
              <Spinner class="h-4 w-4" />
            {/if}
            Guardar y entrar al Portal
          </Button>

          <!-- Info box -->
          <div
            class="space-y-2.5 rounded-2xl border border-[rgba(255,255,255,0.1)] bg-[rgba(255,255,255,0.04)] p-5 text-start"
          >
            <p class="text-sm font-medium text-[#F5F3FF]">Cambio obligatorio</p>
            <p class="text-xs leading-relaxed text-[#C4BFE0]">
              Mientras no cambies la contraseña, el resto del sistema no está disponible.
            </p>
            <p class="text-xs font-medium text-[#C4BFE0]">
              {#if usuario}
                Sesión iniciada como {usuario.nombre1} {usuario.apellido1}. ¿No eres tú?
              {:else}
                ¿No eres tú?
              {/if}
              <button
                type="button"
                class="cursor-pointer text-xs font-medium text-[#5B9BD5] transition-colors hover:text-[#2A66AC] hover:underline"
                onclick={cerrarSesion}
              >
                Cerrar sesión
              </button>
            </p>
          </div>
        {/snippet}
      </Form>
    </div>
  </div>
</div>
