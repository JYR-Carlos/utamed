<script lang="ts">
  /**
   * Página de login del sistema UtaMed.
   *
   * Formulario de autenticación que permite a usuarios ingresar sus
   * credenciales (email/username y contraseña).
   *
   * Características:
   * - Campo de email o username para flexibilidad en login
   * - Opción de "Recuérdame" para mantener sesión
   * - Enlace a recuperación de contraseña
   * - Validación de errores con display diferenciado (RUT no encontrado, contraseña incorrecta, etc)
   * - Spinner de carga durante autenticación
   * - Soporte para login con Fortify (Laravel)
   * - Timer visual para rate limiting
   */
  import { onMount, onDestroy } from 'svelte';
  import * as THREE from 'three';
  import NET from 'vanta/dist/vanta.net.min';
  import ErrorAlert from '@/components/custom/common/ErrorAlert.svelte';
  import TextLink from '@/components/custom/common/TextLink.svelte';
  import { Button } from '@/components/ui/button';
  import { Checkbox } from '@/components/ui/checkbox';
  import { Label } from '@/components/ui/label';
  import { Spinner } from '@/components/ui/spinner';
  import AuthBase from '@/layouts/auth/AuthSplitLayout.svelte';
  import { register, login } from '@/routes';
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

  // Formatea el RUT al formato 00000000-0 (sin puntos)
  function formatRut(rut: string): string {
    // Eliminar puntos y guiones
    const cleanRut = rut.replace(/[.\-]/g, '');

    // Validar formato básico (7-8 dígitos + dígito verificador o K)
    if (!/^\d{7,8}[0-9kK]?$/.test(cleanRut)) {
      return rut; // Retornar sin formatear si no es válido
    }

    // Si tiene menos de 8 dígitos antes del verificador, no formatear aún
    if (cleanRut.length < 8) {
      return cleanRut;
    }

    // Extraer cuerpo y dígito verificador
    const body = cleanRut.slice(0, -1);
    const dv = cleanRut.slice(-1).toUpperCase();

    // Formatear como 00000000-0 (sin puntos)
    return `${body}-${dv}`;
  }

  // Manejar cambios en el input de RUT
  function handleRutInput(e: Event) {
    const target = e.target as HTMLInputElement;
    const formatted = formatRut(target.value);
    if (formatted !== target.value) {
      target.value = formatted;
      $form.data.email = formatted;
    }
  }

  // Valida que el RUT tenga el formato correcto
  function isValidRutFormat(rut: string): boolean {
    const cleanRut = rut.replace(/[.\-]/g, '');
    return /^\d{8}[0-9kK]$/.test(cleanRut);
  }

  let rutValid = $derived(isValidRutFormat($form.data.email));

  let vantaContainer: HTMLElement | undefined = $state(undefined);
  let vantaEffect: any = null;

  onMount(() => {
    if (vantaContainer) {
      vantaEffect = NET({
        el: vantaContainer,
        THREE,
        color: 0xf59e0b,
        backgroundColor: 0x1e40af,
        points: 12,
        maxDistance: 20,
        spacing: 17,
      });
    }
  });

  onDestroy(() => {
    vantaEffect?.destroy();
  });
</script>

<svelte:head>
  <title>Iniciar Sesión | UTAMed</title>
</svelte:head>

<div bind:this={vantaContainer} class="relative min-h-screen overflow-hidden">
  <div class="relative z-10">
    <AuthBase title="Iniciar Sesión" description="Ingresa los detalles de tu cuenta">
  {#if status}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
      {status}
    </div>
  {/if}

  {#if errorCode}
    <div class="mb-6">
      <ErrorAlert {errorCode} retryAfter={rateLimitRetryAfter} />
    </div>
  {/if}

  <Form form={$form} method="post" action={login().url} class="flex flex-col gap-6">
    {#snippet children({ errors, processing }: BaseFormSnippetProps)}
      <div class="grid gap-5">
        <!-- RUT Field -->
        <div class="space-y-1.5">
          <label for="email" class="text-sm font-medium text-gray-700 ml-0.5"> RUT </label>
          <div class="relative">
            <input
              id="email"
              name="email"
              type="text"
              required
              maxlength="10"
              autocomplete="off"
              placeholder="11111111-1"
              aria-describedby="email-hint"
              aria-invalid={$form.data.email && !rutValid ? true : undefined}
              disabled={isRateLimited || processing}
              bind:value={$form.data.email}
              oninput={handleRutInput}
              class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3.5 text-gray-900 focus-visible:ring-2 focus-visible:ring-[#2A66AC] focus:border-[#2A66AC] focus:outline-none transition-all placeholder:text-gray-400 disabled:opacity-50 disabled:cursor-not-allowed"
            />
          </div>
          {#if $form.data.email && !rutValid}
            <p id="email-hint" class="text-xs text-red-600 ml-0.5">
              RUT debe tener 8 dígitos + dígito verificador (formato: 12345678-K)
            </p>
          {:else if $form.data.email && rutValid}
            <p id="email-hint" class="text-xs text-green-600 ml-0.5">Formato válido</p>
          {:else}
            <p id="email-hint" class="text-xs text-gray-500 ml-0.5">Ingresa sin puntos y con guion</p>
          {/if}
        </div>

        <!-- Password Field -->
        <div class="space-y-1.5">
          <div class="flex justify-between items-center px-0.5">
            <label for="password" class="text-sm font-medium text-gray-700"> Contraseña </label>
            {#if canResetPassword}
              <div class={isRateLimited ? 'opacity-50 pointer-events-none' : ''}>
                <TextLink
                  href={request().url}
                  class="text-xs text-gray-400 hover:text-[#2A66AC] transition-colors !decoration-gray-300 hover:!decoration-[#2A66AC]"
                >
                  ¿Olvidaste tu contraseña?
                </TextLink>
              </div>
            {/if}
          </div>
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
              class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3.5 pr-12 text-gray-900 focus-visible:ring-2 focus-visible:ring-[#2A66AC] focus:border-[#2A66AC] focus:outline-none transition-all placeholder:text-gray-400 disabled:opacity-50 disabled:cursor-not-allowed"
            />
            <button
              type="button"
              tabindex={-1}
              disabled={isRateLimited || processing}
              aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
              class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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

        <!-- Remember me -->
        <div class="flex items-center space-x-2 px-0.5">
          <input type="hidden" name="remember" value="off" />
          <Checkbox
            id="remember"
            name="remember"
            checked={Boolean((form?.data as any)?.remember)}
            disabled={isRateLimited || processing}
            onchange={handleRememberChange}
            class="border-gray-300 bg-transparent data-[state=checked]:bg-[#2A66AC] data-[state=checked]:border-[#2A66AC] disabled:opacity-50"
          />
          <div
            class="text-xs text-gray-500 cursor-pointer hover:text-gray-700"
            class:opacity-50={isRateLimited || processing}
          >
            <Label for="remember" class="text-xs text-gray-500 cursor-pointer hover:text-gray-700"
              >Mantenerme conectado</Label
            >
          </div>
        </div>

        <!-- Submit Button -->
        <Button
          type="submit"
          class="mt-1 w-full bg-[#2A66AC] hover:bg-[#234f8a] active:scale-[0.98] text-white font-bold py-7 rounded-xl shadow-lg shadow-[#2A66AC]/20 transition-all flex items-center justify-center gap-2 text-base cursor-pointer"
          disabled={isRateLimited || $form.processing}
        >
          {#if $form.processing}
            <Spinner class="h-4 w-4" />
          {/if}
          {isRateLimited ? `Bloqueado por ${rateLimitRetryAfter}s` : 'Entrar al Portal'}
        </Button>

        <!-- Info box -->
        <div class="mt-4 p-5 rounded-lg bg-gray-50 border border-gray-100 text-center space-y-2.5">
          <p class="text-sm text-gray-800 font-medium">Información de Acceso</p>
          <p class="text-xs text-gray-500 leading-relaxed">
            Como parte de nuestra comunidad académica, tus credenciales han sido enviadas
            previamente a tu correo institucional.
          </p>
          <p class="text-xs font-medium text-gray-500">
            ¿Problemas para entrar? <br />
            <a
              href="mailto:cite@gestion.uta.cl"
              class="text-[#2A66AC] hover:underline transition-all"
              >Contactar a soporte: cite@gestion.uta.cl</a
            >
          </p>
        </div>
      </div>
    {/snippet}
  </Form>
</AuthBase>
  </div>
</div>
