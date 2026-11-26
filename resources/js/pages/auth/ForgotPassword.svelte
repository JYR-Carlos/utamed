<script lang="ts">
  /**
   * Recuperar la contraseña (guía dm, dirección A · Noche; paso 1 de 3).
   *
   * Se escribe el RUT, como en el login, y el enlace sale al correo
   * institucional guardado en la cuenta: no se escribe ningún correo
   * (Auth\PasswordResetLinkController).
   *
   * Estados: inicial, RUT incompleto (se revisa al enviar), enviando, RUT sin
   * cuenta, cuenta sin correo institucional, pedido muy seguido (pausa con
   * cuenta regresiva) y enlace enviado. Los rechazos del servidor llegan en
   * `aviso`; el envío, en `enviadoA`, con el correo en parte oculto.
   */
  import PasswordResetLinkController from '@/actions/App/Http/Controllers/Auth/PasswordResetLinkController';
  import IngresoNoche from '@/components/ingreso/IngresoNoche.svelte';
  import PasosRecuperacion from '@/components/ingreso/PasosRecuperacion.svelte';
  import type { Escena } from '@/components/ingreso/ReticulaViva.svelte';
  import { btnIngreso, btnIngresoEnPausa, campo, enlace } from '@/lib/dm';
  import { rutDeIngreso, rutDeIngresoCompleto } from '@/lib/validators';
  import { login } from '@/routes';
  import { Link, useForm } from '@inertiajs/svelte';
  import { ArrowLeft, CircleX, Clock, Inbox, KeyRound, LoaderCircle, RotateCcw, Send } from 'lucide-svelte';
  import { onDestroy, onMount, tick } from 'svelte';

  interface Props {
    /** Correo institucional al que salió el enlace, en parte oculto («ma•••@alumnos.uta.cl»). */
    enviadoA?: string | null;
    /** Por qué no salió el enlace. */
    aviso?: { codigo: 'no_registrado' } | { codigo: 'sin_correo' } | { codigo: 'pausa'; segundos: number } | null;
    /** Segundos que el broker exige entre un enlace y otro. */
    esperaReenvio: number;
    /** Minutos que vale el enlace. */
    vigenciaMinutos: number;
  }

  let { aviso, esperaReenvio, vigenciaMinutos }: Props = $props();

  const form = useForm({ rut: '' });

  let rutIncompleto = $state(false);
  /** Correo al que salió el último enlace; null mientras no sale ninguno. */
  let correoDelEnvio = $state<string | null>(null);
  const enviado = $derived(correoDelEnvio !== null);

  // Una sola cuenta regresiva: la pausa que exige el servidor o la espera
  // para reenviar después de un envío.
  let restante = $state(0);
  let reloj: ReturnType<typeof setInterval> | undefined;
  function esperar(segundos: number) {
    clearInterval(reloj);
    restante = segundos;
    reloj = setInterval(() => {
      restante -= 1;
      if (restante <= 0) clearInterval(reloj);
    }, 1000);
  }
  onDestroy(() => clearInterval(reloj));

  // Cada respuesta trae un objeto nuevo, así que una segunda pausa también
  // reinicia la cuenta.
  $effect(() => {
    if (aviso?.codigo === 'pausa') esperar(aviso.segundos);
  });

  const cuenta = $derived(`${Math.floor(restante / 60)}:${String(restante % 60).padStart(2, '0')}`);
  const enPausa = $derived(aviso?.codigo === 'pausa' && restante > 0);
  const noRegistrado = $derived(aviso?.codigo === 'no_registrado');
  const sinCorreo = $derived(aviso?.codigo === 'sin_correo');
  // Un error del campo sin `aviso` es la validación del servidor (formato).
  const errorFormato = $derived(rutIncompleto || (!!$form.errors.rut && !aviso));
  const inactivo = $derived(enPausa || $form.processing);

  const escena: Escena = $derived($form.processing ? 'trabajando' : enviado ? 'listo' : 'reposo');

  function enfocarRut() {
    document.getElementById('rut')?.focus();
  }

  function enviar(evento?: SubmitEvent) {
    evento?.preventDefault();
    // El formato se revisa al enviar, no mientras se escribe.
    if (!rutDeIngresoCompleto($form.rut)) {
      rutIncompleto = true;
      correoDelEnvio = null;
      enfocarRut();
      return;
    }
    rutIncompleto = false;
    $form.post(PasswordResetLinkController.store.url(), {
      preserveScroll: true,
      onSuccess: (pagina) => {
        correoDelEnvio = (pagina.props.enviadoA as string | null) ?? 'tu correo institucional';
        esperar(esperaReenvio);
      },
      onError: async () => {
        correoDelEnvio = null;
        await tick();
        enfocarRut();
      },
    });
  }

  async function usarOtroRut() {
    clearInterval(reloj);
    restante = 0;
    correoDelEnvio = null;
    $form.reset();
    $form.clearErrors();
    await tick();
    enfocarRut();
  }

  onMount(enfocarRut);
</script>

<svelte:head>
  <title>Recuperar contraseña | UTAMED</title>
</svelte:head>

{#snippet volver(clase: string)}
  <Link href={login()} class="{enlace} gap-1.5! font-medium! {clase}">
    <ArrowLeft size={16} aria-hidden="true" />
    Volver a iniciar sesión
  </Link>
{/snippet}

<IngresoNoche
  titular={enviado ? ['El enlace', 'va en camino'] : ['Recupera', 'tu acceso']}
  bajada="Te llega un enlace al correo institucional y desde ahí eliges una contraseña nueva."
  {escena}
  figura="sobre"
  ayuda="¿Ya no usas ese correo o no te llega nada?"
>
  {#snippet barra()}
    {@render volver('')}
  {/snippet}

  <PasosRecuperacion actual={enviado ? 1 : 0} />

  {#if !enviado}
    <div class="flex animate-dm-aparece flex-col gap-5 motion-reduce:animate-none lg:gap-6">
      <div class="flex flex-col gap-1.5 lg:gap-2">
        <h1 class="m-0 text-[28px] leading-[34px] font-medium">¿Olvidaste tu contraseña?</h1>
        <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
          Escribe tu RUT y te enviaremos un enlace a tu correo institucional para elegir una contraseña nueva.
        </p>
      </div>

      {#if noRegistrado || sinCorreo || enPausa}
        <div
          role="alert"
          class="flex animate-dm-aparece items-start gap-3 rounded-[10px] border px-3.5 py-3.5 motion-reduce:animate-none lg:px-4
            {enPausa ? 'border-dm-warn bg-dm-warn-s' : 'border-dm-error bg-dm-error-s'}"
        >
          {#if enPausa}
            <Clock size={20} aria-hidden="true" class="mt-px shrink-0 text-dm-warn" />
          {:else}
            <CircleX size={20} aria-hidden="true" class="mt-px shrink-0 text-dm-error" />
          {/if}
          <div class="flex min-w-0 flex-col gap-0.5">
            {#if noRegistrado}
              <span class="text-[15px] leading-[22px] font-semibold text-dm-ink"
                >No encontramos una cuenta con ese RUT</span
              >
              <span class="text-sm leading-5 text-dm-ink-2">
                Revisa que esté bien escrito. Si lo está, escribe a cite@gestion.uta.cl e indica tu RUT y tu nombre
                completo.
              </span>
            {:else if sinCorreo}
              <span class="text-[15px] leading-[22px] font-semibold text-dm-ink"
                >Tu cuenta no tiene un correo institucional registrado</span
              >
              <span class="text-sm leading-5 text-dm-ink-2">
                Sin él no podemos enviarte el enlace. Escribe a cite@gestion.uta.cl e indica tu RUT y tu nombre
                completo.
              </span>
            {:else}
              <span class="text-[15px] leading-[22px] font-semibold text-dm-ink"
                >Ya te enviamos un enlace hace un momento</span
              >
              <span class="text-sm leading-5 text-dm-ink-2">
                Revisa tu correo, también la carpeta de spam. Si no llegó, podrás pedir otro en unos segundos.
              </span>
            {/if}
          </div>
        </div>
      {/if}

      <form aria-label="Recuperar contraseña" novalidate onsubmit={enviar} class="flex flex-col gap-5">
        <div class="flex flex-col gap-1.5">
          <label for="rut" class="text-sm leading-5 font-medium">RUT</label>
          <input
            id="rut"
            name="rut"
            type="text"
            required
            maxlength="12"
            autocomplete="username"
            disabled={inactivo}
            aria-invalid={errorFormato || noRegistrado || sinCorreo ? true : undefined}
            aria-describedby="rut-ayuda"
            value={$form.rut}
            oninput={(e) => {
              // Se reescribe también el campo: si el valor formateado no cambia
              // (p. ej. se tecleó un punto), Svelte no lo volvería a pintar.
              $form.rut = e.currentTarget.value = rutDeIngreso(e.currentTarget.value);
            }}
            class="{campo} min-h-12! text-base! aria-invalid:shadow-[0_0_0_1px_var(--color-dm-error)]"
          />
          {#if errorFormato}
            <span id="rut-ayuda" class="flex items-start gap-1.5 text-sm leading-5 text-dm-error">
              <CircleX size={16} aria-hidden="true" class="mt-0.5 shrink-0" />
              {rutIncompleto || !$form.errors.rut
                ? 'El RUT está incompleto. Escribe los números y el dígito verificador, por ejemplo 12345678-5.'
                : $form.errors.rut}
            </span>
          {:else}
            <span id="rut-ayuda" class="text-[13px] leading-[18px] text-dm-ink-2">
              El mismo con el que entras. Ej.: 12345678-5, sin puntos; el guion se agrega solo.
            </span>
          {/if}
        </div>

        {#if enPausa}
          <button type="submit" disabled class={btnIngresoEnPausa}>
            <Clock size={18} aria-hidden="true" />
            Podrás pedir otro en {cuenta}
          </button>
        {:else}
          <button type="submit" disabled={$form.processing} class="{btnIngreso} group">
            {#if $form.processing}
              <LoaderCircle size={18} aria-hidden="true" class="animate-spin motion-reduce:animate-none" />
              <span aria-live="polite">Enviando enlace…</span>
            {:else}
              Enviar enlace
              <Send
                size={18}
                aria-hidden="true"
                class="transition-transform duration-[250ms] group-hover:translate-x-[3px] group-hover:-translate-y-[3px] motion-reduce:transition-none"
              />
            {/if}
          </button>
        {/if}
      </form>

      <div class="flex justify-center lg:hidden">{@render volver('text-[15px]!')}</div>
    </div>
  {:else}
    <div class="flex flex-col gap-6">
      <div role="status" class="flex animate-dm-aparece flex-col gap-1.5 motion-reduce:animate-none lg:gap-2">
        <h1 class="m-0 text-[28px] leading-[34px] font-medium">Revisa tu correo</h1>
        <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
          Te enviamos un enlace a tu correo institucional, <strong
            class="font-semibold [overflow-wrap:anywhere] text-dm-ink">{correoDelEnvio}</strong
          >.
        </p>
      </div>

      <ul
        class="m-0 flex animate-dm-aparece list-none flex-col gap-3.5 rounded-[10px] border border-dm-linea bg-dm-tarjeta p-4 [animation-delay:0.1s] motion-reduce:animate-none"
      >
        <li class="flex items-start gap-3">
          <Clock size={20} aria-hidden="true" class="shrink-0 text-dm-ink-2" />
          <span>El enlace vale <strong class="font-semibold">{vigenciaMinutos} minutos</strong>. Después tendrás que pedir otro.</span>
        </li>
        <li class="flex items-start gap-3">
          <Inbox size={20} aria-hidden="true" class="shrink-0 text-dm-ink-2" />
          <span>Si no aparece en la bandeja de entrada, revisa spam o correo no deseado.</span>
        </li>
        <li class="flex items-start gap-3">
          <KeyRound size={20} aria-hidden="true" class="shrink-0 text-dm-ink-2" />
          <span>Al abrirlo eliges la contraseña nueva y vuelves a entrar con tu RUT.</span>
        </li>
      </ul>

      <div class="flex animate-dm-aparece flex-col gap-2 [animation-delay:0.2s] motion-reduce:animate-none">
        <Link href={login()} class={btnIngreso}>Volver a iniciar sesión</Link>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
          {#if restante > 0}
            <span class="inline-flex min-h-11 items-center gap-1.5 text-sm leading-5 text-dm-ink-2">
              <Clock size={16} aria-hidden="true" />
              Podrás pedir otro en {cuenta}
            </span>
          {:else}
            <button
              type="button"
              onclick={() => enviar()}
              disabled={$form.processing}
              class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-[10px] border border-dm-control bg-dm-tarjeta px-3.5 text-sm font-medium text-dm-ink hover:bg-dm-sutil disabled:cursor-progress"
            >
              {#if $form.processing}
                <LoaderCircle size={16} aria-hidden="true" class="animate-spin motion-reduce:animate-none" />
                <span aria-live="polite">Reenviando…</span>
              {:else}
                <RotateCcw size={16} aria-hidden="true" />
                Reenviar enlace
              {/if}
            </button>
          {/if}
          <button
            type="button"
            onclick={usarOtroRut}
            class="inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-dm-enlace hover:text-dm-enlace-hover hover:underline"
          >
            Usar otro RUT
          </button>
        </div>
      </div>
    </div>
  {/if}
</IngresoNoche>
