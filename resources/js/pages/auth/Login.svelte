<script lang="ts">
  /**
   * Página de login del sistema UtaMed.
   *
   * Formulario de autenticación que permite a usuarios ingresar sus
   * credenciales (RUT/contraseña).
   *
   * Características:
   * - Campo de RUT para flexibilidad en login
   * - Opción de "Recuérdame" para mantener sesión
   * - Enlace a recuperación de contraseña
   * - Validación de errores con display diferenciado (RUT no encontrado, contraseña incorrecta, etc)
   * - Spinner de carga durante autenticación
   * - Soporte para login con Fortify (Laravel)
   * - Timer visual para rate limiting
   */
  import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
  import ErrorAlert from '@/components/custom/common/ErrorAlert.svelte';
  import TextLink from '@/components/custom/common/TextLink.svelte';
  import { Button } from '@/components/ui/button';
  import { Checkbox } from '@/components/ui/checkbox';
  import { Label } from '@/components/ui/label';
  import { Spinner } from '@/components/ui/spinner';
  import { login } from '@/routes';
  import { request } from '@/routes/password';
  import type { BaseFormSnippetProps } from '@/types/forms';
  import { Form, useForm } from '@inertiajs/svelte';
  import { Eye, EyeOff } from 'lucide-svelte';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Mensaje de estado (ej: "Email verificado") */
    status?: string;
    /** Si la contraseña puede ser recuperada */
    canResetPassword: boolean;
    /** Si está habilitado el registro de usuarios */
    canRegister: boolean;
    /** Error de login desde el backend (cuando hay POST redirect) */
    loginError?: {
      code: string;
      message: string;
      retry_after?: number;
    };
  }

  type ErrorCode =
    | 'RUT_NOT_FOUND'
    | 'PASSWORD_INCORRECT'
    | 'USER_INACTIVE'
    | 'EMAIL_NOT_VERIFIED'
    | 'RATE_LIMIT_EXCEEDED'
    | null;

  let { status, canResetPassword, canRegister, loginError }: Props = $props();
  let showPassword = $state(false);
  let errorCode: ErrorCode = $state(null);
  let rateLimitRetryAfter: number | undefined = $state(undefined);
  let rateLimitTimer: ReturnType<typeof setInterval> | null = null;

  const form = useForm({
    email: '',
    password: '',
    remember: false,
  }) as any;

  // Inicializar errorCode desde loginError si existe
  $effect(() => {
    if (loginError?.code) {
      const code = loginError.code as ErrorCode;
      if (
        code &&
        [
          'RUT_NOT_FOUND',
          'PASSWORD_INCORRECT',
          'USER_INACTIVE',
          'EMAIL_NOT_VERIFIED',
          'RATE_LIMIT_EXCEEDED',
        ].includes(code)
      ) {
        errorCode = code;
        if (code === 'RATE_LIMIT_EXCEEDED' && loginError.retry_after) {
          startRateLimitTimer(loginError.retry_after);
        }
      }
    }
  });

  // Función para extraer error code de la respuesta del servidor
  function extractErrorCode(response: any): ErrorCode {
    // Primero intentar obtener el error_code directo (desde nuestro exception handler)
    if (response?.error_code) {
      const code = String(response.error_code);
      if (
        code === 'RUT_NOT_FOUND' ||
        code === 'PASSWORD_INCORRECT' ||
        code === 'USER_INACTIVE' ||
        code === 'EMAIL_NOT_VERIFIED' ||
        code === 'RATE_LIMIT_EXCEEDED'
      ) {
        return code as ErrorCode;
      }
    }

    // Fallback: intentar inferir del mensaje de error
    if (response?.email) {
      const errorMsg = String(response.email).toLowerCase();
      if (
        errorMsg.includes('no existe') ||
        errorMsg.includes('not found') ||
        errorMsg.includes('rut')
      ) {
        return 'RUT_NOT_FOUND';
      }
    }

    if (response?.password) {
      const errorMsg = String(response.password).toLowerCase();
      if (errorMsg.includes('incorrecta') || errorMsg.includes('incorrect')) {
        return 'PASSWORD_INCORRECT';
      }
      if (errorMsg.includes('inactivo') || errorMsg.includes('inactive')) {
        return 'USER_INACTIVE';
      }
    }

    return null;
  }

  // Monitorear cambios en los errores del formulario
  $effect(() => {
    if ($form.errors && Object.keys($form.errors).length > 0) {
      const extracted = extractErrorCode($form.errors);
      if (extracted) {
        errorCode = extracted;

        // Si hay rate limit, extraer el retry_after
        if (extracted === 'RATE_LIMIT_EXCEEDED' && !rateLimitTimer) {
          const retryAfter = parseInt(String(($form.errors as any)?.retry_after ?? 60));
          startRateLimitTimer(retryAfter);
        }
      }
    } else {
      errorCode = null;
      // Limpiar timer si se borran los errores
      if (rateLimitTimer) {
        clearInterval(rateLimitTimer);
        rateLimitTimer = null;
      }
      rateLimitRetryAfter = undefined;
    }
  });

  // Iniciar countdown para rate limit
  function startRateLimitTimer(seconds: number) {
    rateLimitRetryAfter = seconds;

    if (rateLimitTimer) {
      clearInterval(rateLimitTimer);
    }

    rateLimitTimer = setInterval(() => {
      rateLimitRetryAfter = (rateLimitRetryAfter ?? 0) - 1;

      if ((rateLimitRetryAfter ?? 0) <= 0) {
        clearInterval(rateLimitTimer!);
        rateLimitTimer = null;
        rateLimitRetryAfter = undefined;
      }
    }, 1000);
  }

  // Monitorear checkbox de remember
  function handleRememberChange(e: Event) {
    const target = e.target as HTMLInputElement;
    $form.data.remember = target.checked;
  }

  // Limpiar timer al desmontar componente
  $effect(() => {
    return () => {
      if (rateLimitTimer) {
        clearInterval(rateLimitTimer);
      }
    };
  });

  // Deshabilitar inputs si hay rate limit
  const isRateLimited = $derived(
    errorCode === 'RATE_LIMIT_EXCEEDED' && (rateLimitRetryAfter ?? 0) > 0,
  );

  function formatRut(rut: string): string {
    // 1. Limpiar absolutamente todo lo que no sea número o K
    const cleanRut = rut.replace(/[^0-9kK]/gi, '');

    // 2. Si no hay suficientes caracteres para un RUT (mínimo cuerpo + DV), no formatear
    if (cleanRut.length < 2) {
      return cleanRut;
    }

    // 3. Limitar el largo máximo para evitar que peguen textos gigantes (8 cuerpo + 1 DV = 9)
    const truncatedRut = cleanRut.slice(0, 9);

    // 4. Separar limpiamente el cuerpo del dígito verificador
    const body = truncatedRut.slice(0, -1);
    const dv = truncatedRut.slice(-1).toUpperCase();

    // 5. Retornar el formato limpio que Svelte espera
    return `${body}-${dv}`;
  }

  // Manejar cambios en el input de RUT
  function handleRutInput(e: Event) {
    const target = e.target as HTMLInputElement;

    // Formateamos el valor que viene del input
    const formatted = formatRut(target.value);

    // Actualizamos el estado de Inertia (esto actualizará el input automáticamente)
    $form.data.email = formatted;
  }

  // Valida que el RUT tenga el formato correcto
  function isValidRutFormat(rut: string): boolean {
    // Elimina puntos, guiones y espacios en blanco al principio/final
    const cleanRut = rut.replace(/[.-]/g, '').trim();

    // \d{7,8} permite que el cuerpo del RUT tenga 7 u 8 dígitos
    return /^\d{7,8}[0-9kK]$/.test(cleanRut);
  }

  let rutValid = $derived(isValidRutFormat($form.data.email));
</script>

<svelte:head>
  <title>Iniciar Sesión | UTAmed</title>
</svelte:head>

<div class="relative min-h-screen w-full overflow-hidden bg-[#0d1522]">
  <!-- Fondo fotográfico full-bleed -->
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
    class="pointer-events-none absolute left-1/2 top-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#5B9BD5]/10 blur-[120px]"
    aria-hidden="true"
  ></div>

  <!-- Card centrada -->
  <div class="relative z-10 flex min-h-screen items-center justify-start px-2 py-4 sm:px-4">
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
      <h1 class="text-center text-[28px] font-semibold leading-[1.2] text-[#F5F3FF] sm:text-[40px]">
        Bienvenido de
        <span class="bg-gradient-to-r from-white to-[#BFD9F2] bg-clip-text text-transparent"
          >vuelta</span
        >
      </h1>
      <p class="mx-auto mt-3 max-w-[320px] text-center text-[15px] leading-[1.5] text-[#C4BFE0]">
        Ingresa tus credenciales institucionales para acceder a tu portal académico UTAmed.
      </p>

      {#if status}
        <div class="mt-6 text-center text-sm font-medium text-emerald-300">
          {status}
        </div>
      {/if}

      {#if errorCode}
        <div class="mt-6">
          <ErrorAlert {errorCode} retryAfter={rateLimitRetryAfter} />
        </div>
      {/if}

      <Form form={$form} method="post" action={login().url} class="mt-8 flex flex-col gap-6">
        {#snippet children({ errors, processing }: BaseFormSnippetProps)}
          <div class="flex flex-col gap-6">
            <!-- RUT Field -->
            <div class="space-y-1.5">
              <label for="email" class="text-[13px] font-medium text-[#C4BFE0]"> RUT </label>
              <div class="relative">
                <input
                  id="email"
                  name="email"
                  type="text"
                  required
                  maxlength="13"
                  autocomplete="off"
                  placeholder="11111111-1"
                  aria-describedby="email-hint"
                  aria-invalid={$form.data.email && !rutValid ? true : undefined}
                  disabled={isRateLimited || processing}
                  bind:value={$form.data.email}
                  oninput={handleRutInput}
                  class="w-full rounded-[14px] border border-[rgba(255,255,255,0.15)] bg-[rgba(255,255,255,0.05)] px-4 py-3.5 text-[#F5F3FF] transition-all duration-150 placeholder:text-[#C4BFE0]/60 focus:border-[#5B9BD5] focus:shadow-[0_0_0_3px_rgba(91,155,213,0.35)] focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
              </div>
              {#if $form.data.email && !rutValid}
                <p id="email-hint" class="text-xs text-red-300">
                  RUT debe tener 8 dígitos + dígito verificador (formato: 12345678-K)
                </p>
              {:else if $form.data.email && rutValid}
                <p id="email-hint" class="text-xs text-emerald-300">Formato válido</p>
              {:else}
                <p id="email-hint" class="text-xs text-[#C4BFE0]/70">
                  Ingresa sin puntos y con guion
                </p>
              {/if}
            </div>

            <!-- Password Field -->
            <div class="space-y-1.5">
              <label for="password" class="text-[13px] font-medium text-[#C4BFE0]"> Contraseña </label>
              <div class="relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                  disabled={isRateLimited || processing}
                  bind:value={$form.data.password}
                  class="w-full rounded-[14px] border border-[rgba(255,255,255,0.15)] bg-[rgba(255,255,255,0.05)] px-4 py-3.5 pr-12 text-[#F5F3FF] transition-all duration-150 placeholder:text-[#C4BFE0]/60 focus:border-[#5B9BD5] focus:shadow-[0_0_0_3px_rgba(91,155,213,0.35)] focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                <button
                  type="button"
                  tabindex={-1}
                  disabled={isRateLimited || processing}
                  aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-[#C4BFE0] transition-colors hover:text-[#F5F3FF] disabled:cursor-not-allowed disabled:opacity-50"
                  onclick={() => (showPassword = !showPassword)}
                >
                  {#if showPassword}
                    <EyeOff size={20} aria-hidden="true" />
                  {:else}
                    <Eye size={20} aria-hidden="true" />
                  {/if}
                </button>
              </div>
            </div>

            <!-- Remember me + Forgot password -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <input type="hidden" name="remember" value="off" />
                <Checkbox
                  id="remember"
                  name="remember"
                  checked={Boolean((form?.data as any)?.remember)}
                  disabled={isRateLimited || processing}
                  onchange={handleRememberChange}
                  class="border-[rgba(255,255,255,0.25)] bg-[rgba(255,255,255,0.05)] data-[state=checked]:border-[#5B9BD5] data-[state=checked]:bg-[#5B9BD5] data-[state=checked]:text-[#1A1625] disabled:opacity-50"
                />
                <Label
                  for="remember"
                  class="cursor-pointer text-[13px] text-[#C4BFE0] hover:text-[#F5F3FF] {isRateLimited ||
                  processing
                    ? 'opacity-50'
                    : ''}">Recuérdame</Label
                >
              </div>
              {#if canResetPassword}
                <div class={isRateLimited ? 'pointer-events-none opacity-50' : ''}>
                  <TextLink
                    href={request().url}
                    class="text-xs text-white transition-colors hover:text-[#5B9BD5] decoration-[#C4BFE0]/40! hover:!decoration-[#5B9BD5]"
                  >
                    ¿Olvidaste tu contraseña?
                  </TextLink>
                </div>
              {/if}
            </div>

            <!-- Submit Button -->
            <Button
              type="submit"
              class="mt-1 flex w-full items-center justify-center gap-2 rounded-[14px] bg-[#F5F4F0] py-3.5 text-[15px] font-semibold text-[#1A1625] shadow-none transition-all duration-150 hover:brightness-95 active:scale-[0.98] disabled:opacity-50"
              disabled={isRateLimited || $form.processing}
            >
              {#if $form.processing}
                <Spinner class="h-4 w-4" />
              {/if}
              {isRateLimited ? `Bloqueado por ${rateLimitRetryAfter}s` : 'Entrar al Portal'}
            </Button>

            <!-- Info box -->
            <div
              class="rounded-2xl border border-[rgba(255,255,255,0.1)] bg-[rgba(255,255,255,0.04)] p-5 text-start space-y-2.5"
            >
              <p class="text-sm font-medium text-[#F5F3FF]">Información de Acceso</p>
              <p class="text-xs leading-relaxed text-[#C4BFE0]">
                Como parte de nuestra comunidad académica, tus credenciales han sido enviadas
                previamente a tu correo institucional.
              </p>
              <p class="text-xs font-medium text-[#C4BFE0]">
                ¿Problemas para entrar? <br />
                <a
                  href="mailto:cite@gestion.uta.cl"
                  class="text-[#5B9BD5] transition-colors hover:text-[#2A66AC] hover:underline"
                  >Contactar a soporte: cite@gestion.uta.cl</a
                >
              </p>
            </div>
          </div>
        {/snippet}
      </Form>
    </div>
  </div>
</div>
