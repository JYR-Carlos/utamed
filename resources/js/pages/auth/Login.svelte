<script lang="ts">
  /**
   * Ingreso a UTAMED con RUT y contraseña (guía dm, dirección A · Noche).
   *
   * Panel de marca noche con el degradado y la retícula (en móvil, arriba) y el
   * formulario sobre la página. El campo se llama `email` por compatibilidad
   * con Fortify, pero lleva el RUT.
   *
   * Errores (loginError, ver bootstrap/app.php): RUT inexistente y contraseña
   * errónea dan el mismo «Credenciales incorrectas», para no revelar qué RUT
   * tienen cuenta; la cuenta desactivada sólo llega con la contraseña correcta,
   * y el exceso de intentos trae los segundos de pausa.
   */
  import BotonTema from '@/components/ingreso/BotonTema.svelte';
  import LogosInstitucionales from '@/components/ingreso/LogosInstitucionales.svelte';
  import UtaMed from '@/components/ingreso/UtaMed.svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import { campo, enlace, etiqueta } from '@/lib/dm';
  import { home, login } from '@/routes';
  import { request } from '@/routes/password';
  import { Link, useForm } from '@inertiajs/svelte';
  import { ArrowLeft, CircleAlert, CircleCheck, Clock, Eye, EyeOff, LoaderCircle } from 'lucide-svelte';

  interface Props {
    /** Aviso de Fortify; al login sólo llega el de contraseña restablecida. */
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    loginError?: {
      code: string;
      message: string;
      retry_after?: number | null;
    } | null;
  }

  let { status, canResetPassword, loginError }: Props = $props();

  const form = useForm({ email: '', password: '', remember: false });

  let verClave = $state(false);
  let rutError = $state('');

  // Pausa por exceso de intentos: cuenta regresiva desde los segundos que
  // manda el servidor; mientras dura, el formulario queda deshabilitado.
  let restante = $state(0);
  $effect(() => {
    if (loginError?.code !== 'RATE_LIMIT_EXCEEDED') return;
    restante = loginError.retry_after ?? 60;
    const reloj = setInterval(() => {
      restante -= 1;
      if (restante <= 0) clearInterval(reloj);
    }, 1000);
    return () => clearInterval(reloj);
  });
  const bloqueado = $derived(loginError?.code === 'RATE_LIMIT_EXCEEDED' && restante > 0);
  const deshabilitado = $derived(bloqueado || $form.processing);
  const cuenta = $derived(`${Math.floor(restante / 60)}:${String(restante % 60).padStart(2, '0')}`);

  type Aviso = { tono: 'error' | 'warn' | 'ok'; titulo: string; texto?: string };

  const aviso = $derived.by((): Aviso | null => {
    if (!loginError) {
      return status ? { tono: 'ok', titulo: 'Tu contraseña se restableció', texto: 'Ya puedes entrar con la nueva.' } : null;
    }
    switch (loginError.code) {
      case 'INVALID_CREDENTIALS':
        return { tono: 'error', titulo: 'Credenciales incorrectas' };
      case 'USER_INACTIVE':
        return {
          tono: 'error',
          titulo: 'Tu cuenta está desactivada',
          texto: 'Mientras siga así no puedes entrar. Para reactivarla, escribe a cite@gestion.uta.cl desde tu correo institucional.',
        };
      case 'RATE_LIMIT_EXCEEDED':
        return bloqueado
          ? {
              tono: 'warn',
              titulo: 'Hubo varios intentos fallidos seguidos',
              texto: 'Por seguridad, el ingreso queda en pausa unos segundos. Si no recuerdas la contraseña, recupérala mientras tanto.',
            }
          : null;
      default:
        return { tono: 'error', titulo: loginError.message };
    }
  });

  const avisoEstilo = {
    error: { caja: 'border-dm-error bg-dm-error-s', icono: 'text-dm-error', Icono: CircleAlert },
    warn: { caja: 'border-dm-warn bg-dm-warn-s', icono: 'text-dm-warn', Icono: Clock },
    ok: { caja: 'border-dm-ok bg-dm-ok-s', icono: 'text-dm-ok', Icono: CircleCheck },
  } as const;

  // Sin btnPrimario: aquí el botón no se aclara al enviar y bloqueado va en gris.
  const botonEntrar =
    'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-[10px] px-5 py-2.5 text-[15px] font-bold';

  /** Deja sólo números y K, y pone el guion antes del dígito verificador. */
  function formatearRut(valor: string): string {
    const limpio = valor.replace(/[^0-9kK]/g, '').slice(0, 9);
    if (limpio.length < 2) return limpio;
    return `${limpio.slice(0, -1)}-${limpio.slice(-1).toUpperCase()}`;
  }

  function entrar(evento: SubmitEvent) {
    evento.preventDefault();
    // El formato se revisa al enviar, no mientras se escribe.
    if (!/^\d{7,8}-[0-9K]$/.test($form.email)) {
      rutError = 'El RUT está incompleto. Escribe los números y el dígito verificador, por ejemplo 12345678-5.';
      document.getElementById('rut')?.focus();
      return;
    }
    rutError = '';
    $form.post(login().url, { onFinish: () => $form.reset('password') });
  }
</script>

<svelte:head>
  <title>Ingresar | UTAMED</title>
</svelte:head>

<div class="vista-ingreso flex min-h-screen flex-col bg-dm-pagina font-dm text-sm leading-5 text-dm-ink">
  <div class="flex flex-1 flex-col lg:flex-row">
    <aside
      aria-label="UTAMED · Diseño Multimedia"
      class="sobre-noche relative flex flex-col gap-3 overflow-hidden bg-[linear-gradient(160deg,#161949_0%,#161949_45%,#660959_100%)] pb-7 text-dm-noche-texto lg:w-[42%] lg:shrink-0 lg:justify-between lg:gap-10 lg:bg-[linear-gradient(150deg,#161949_0%,#161949_45%,#660959_100%)] lg:px-14 lg:pt-8 lg:pb-12"
    >
      <Reticula class="left-[30%] lg:left-[25%]" />

      <div class="relative flex h-16 items-center gap-2 ps-4 pe-2 lg:h-auto lg:p-0">
        <Link href={home()} class="flex min-h-11 flex-1 items-center gap-2 text-dm-noche-texto no-underline lg:flex-none lg:gap-2.5">
          <img src="/icon.png" alt="" width="32" height="32" class="size-7 shrink-0 object-contain lg:size-8" />
          <span class="font-dm-cond text-[22px] leading-[26px] font-extrabold tracking-[0.02em] lg:text-2xl lg:leading-7">UTAMED</span>
        </Link>
        <BotonTema class="text-dm-noche-texto hover:bg-dm-noche-activo lg:hidden" />
      </div>

      <div class="relative flex max-w-[460px] flex-col gap-3 px-4 lg:gap-4 lg:px-0">
        <span class="{etiqueta} text-dm-amarillo">Diseño Multimedia · Universidad de Tarapacá</span>
        <p class="m-0 font-dm-cond text-4xl leading-[34px] font-black uppercase lg:text-[56px] lg:leading-[52px]">
          Cursos, entregas y notas en un solo lugar
        </p>
        <p class="m-0 text-base leading-6 text-dm-noche-texto-2 max-lg:hidden">
          El aula virtual de la carrera, para quienes estudian y enseñan en ella.
        </p>
      </div>

      <LogosInstitucionales class="relative mt-3 px-4 lg:mt-0 lg:px-0" />
    </aside>

    <main class="flex min-w-0 flex-1 flex-col gap-5 px-4 pt-7 pb-8 lg:px-10 lg:pt-6 lg:pb-7">
      <div class="flex items-center justify-between gap-4 max-lg:hidden">
        <Link href={home()} class="{enlace} gap-1.5!">
          <ArrowLeft size={16} aria-hidden="true" />
          Volver a la portada
        </Link>
        <BotonTema class="size-10! border border-dm-linea bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil" />
      </div>

      <div class="flex flex-1 items-center justify-center lg:py-8">
        <div class="flex w-full max-w-[420px] flex-col gap-5 lg:gap-6">
          <div class="flex flex-col gap-1.5 lg:gap-2">
            <h1 class="m-0 text-[28px] leading-[34px] font-bold">Bienvenido a mi <UtaMed /></h1>
            <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
              Usa tu RUT y la contraseña que te llegó al correo institucional.
            </p>
          </div>

          {#if aviso}
            {@const estilo = avisoEstilo[aviso.tono]}
            <div
              role={aviso.tono === 'ok' ? 'status' : 'alert'}
              class="flex items-start gap-3 rounded-[10px] border px-3.5 py-3.5 lg:px-4 {estilo.caja}"
            >
              <estilo.Icono size={20} aria-hidden="true" class="mt-px shrink-0 {estilo.icono}" />
              <div class="flex min-w-0 flex-col gap-0.5">
                <span class="text-[15px] leading-[22px] font-bold text-dm-ink">{aviso.titulo}</span>
                {#if aviso.texto}
                  <span class="text-sm leading-5 text-dm-ink-2">{aviso.texto}</span>
                {/if}
              </div>
            </div>
          {/if}

          <form aria-label="Ingreso" novalidate onsubmit={entrar} class="flex flex-col gap-5">
            <div class="flex flex-col gap-1.5">
              <label for="rut" class="text-sm leading-5 font-semibold">RUT</label>
              <input
                id="rut"
                name="email"
                type="text"
                required
                maxlength="10"
                autocomplete="username"
                placeholder="12345678-5"
                aria-invalid={rutError ? true : undefined}
                aria-describedby="rut-ayuda"
                disabled={deshabilitado}
                value={$form.email}
                oninput={(e) => {
                  // Se reescribe también el campo: si el valor formateado no cambia
                  // (p. ej. se tecleó un punto), Svelte no lo volvería a pintar.
                  $form.email = e.currentTarget.value = formatearRut(e.currentTarget.value);
                }}
                class="{campo} min-h-12! text-base!"
              />
              {#if rutError}
                <span id="rut-ayuda" class="flex items-start gap-1.5 text-sm leading-5 text-dm-error">
                  <CircleAlert size={16} aria-hidden="true" class="mt-0.5 shrink-0" />
                  {rutError}
                </span>
              {:else}
                <span id="rut-ayuda" class="text-[13px] leading-[18px] text-dm-ink-2">
                  Sin puntos; el guion antes del dígito verificador se agrega solo.
                </span>
              {/if}
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="clave" class="text-sm leading-5 font-semibold">Contraseña</label>
              <div class="relative">
                <input
                  id="clave"
                  name="password"
                  type={verClave ? 'text' : 'password'}
                  required
                  autocomplete="current-password"
                  disabled={deshabilitado}
                  bind:value={$form.password}
                  class="{campo} min-h-12! pe-[52px]! text-base!"
                />
                <button
                  type="button"
                  aria-label={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                  aria-pressed={verClave}
                  disabled={deshabilitado}
                  onclick={() => (verClave = !verClave)}
                  class="absolute inset-y-0 end-0 flex w-12 cursor-pointer items-center justify-center rounded-e-[10px] text-dm-ink-2 hover:text-dm-ink disabled:cursor-not-allowed"
                >
                  {#if verClave}
                    <EyeOff size={20} aria-hidden="true" />
                  {:else}
                    <Eye size={20} aria-hidden="true" />
                  {/if}
                </button>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
              <label for="recordar" class="inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-sm leading-5">
                <input
                  id="recordar"
                  name="remember"
                  type="checkbox"
                  disabled={deshabilitado}
                  bind:checked={$form.remember}
                  class="size-5 shrink-0 accent-dm-accion"
                />
                Mantener la sesión iniciada
              </label>
              {#if canResetPassword}
                <Link href={request()} class="{enlace} lg:min-h-11!">¿Olvidaste tu contraseña?</Link>
              {/if}
            </div>

            {#if bloqueado}
              <button type="submit" disabled class="{botonEntrar} cursor-not-allowed bg-dm-linea text-dm-ink-2">
                <Clock size={18} aria-hidden="true" />
                Podrás intentar de nuevo en {cuenta}
              </button>
            {:else}
              <button
                type="submit"
                disabled={$form.processing}
                class="{botonEntrar} cursor-pointer bg-dm-accion text-dm-sobre-accion hover:bg-dm-accion-hover disabled:cursor-wait"
              >
                {#if $form.processing}
                  <LoaderCircle size={18} aria-hidden="true" class="animate-spin motion-reduce:animate-none" />
                  <span aria-live="polite">Entrando…</span>
                {:else}
                  Entrar a UTAMED
                {/if}
              </button>
            {/if}
          </form>

          <p class="m-0 border-t border-dm-linea pt-5 text-sm leading-5 text-dm-ink-2">
            ¿No puedes entrar? Escribe a
            <a href="mailto:cite@gestion.uta.cl" class="text-sm font-semibold text-dm-enlace hover:text-dm-enlace-hover">cite@gestion.uta.cl</a>
            desde tu correo institucional e indica tu RUT.
          </p>
        </div>
      </div>

      <p class="m-0 text-[13px] leading-[18px] text-dm-ink-3">
        © {new Date().getFullYear()} Universidad de Tarapacá · Escuela de Diseño e Innovación Tecnológica
      </p>
    </main>
  </div>

  <div aria-hidden="true" class="grid h-2 grid-cols-3">
    <span class="bg-[#DEB01F]"></span><span class="bg-[#22213F]"></span><span class="bg-[#678FCA]"></span>
  </div>
</div>
