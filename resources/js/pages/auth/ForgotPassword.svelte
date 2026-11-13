<script lang="ts">
  /**
   * Recuperar la contraseña: se pide el enlace al correo institucional
   * (guía dm, dirección A · Noche; paso 1 de 3).
   *
   * Estados: inicial, correo incompleto (se revisa al enviar), enviando,
   * correo sin cuenta, pedido muy seguido (pausa con cuenta regresiva) y
   * enlace enviado. Los dos rechazos del servidor llegan en `aviso`
   * (FailedPasswordResetLinkRequestResponse); el envío, en `status`.
   */
  import PasswordResetLinkController from '@/actions/Laravel/Fortify/Http/Controllers/PasswordResetLinkController';
  import IngresoNoche from '@/components/ingreso/IngresoNoche.svelte';
  import PasosRecuperacion from '@/components/ingreso/PasosRecuperacion.svelte';
  import type { Escena } from '@/components/ingreso/ReticulaViva.svelte';
  import { btnIngreso, btnIngresoEnPausa, campo, enlace } from '@/lib/dm';
  import { login } from '@/routes';
  import { Link, useForm } from '@inertiajs/svelte';
  import { ArrowLeft, CircleX, Clock, Inbox, KeyRound, LoaderCircle, RotateCcw, Send } from 'lucide-svelte';
  import { onDestroy, onMount, tick } from 'svelte';

  interface Props {
    /** Fortify: llega cuando el enlace salió. */
    status?: string;
    /** Por qué no salió el enlace. */
    aviso?: { codigo: 'no_registrado' } | { codigo: 'pausa'; segundos: number } | null;
    /** Segundos que el broker exige entre un enlace y otro. */
    esperaReenvio: number;
    /** Minutos que vale el enlace. */
    vigenciaMinutos: number;
  }

  let { aviso, esperaReenvio, vigenciaMinutos }: Props = $props();

  const form = useForm({ email: '' });

  let correoIncompleto = $state(false);
  /** Correo al que salió el último enlace; null mientras no sale ninguno. */
  let enviadoA = $state<string | null>(null);
  const enviado = $derived(enviadoA !== null);

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
  // Un error del campo sin `aviso` es la validación del servidor (formato).
  const errorFormato = $derived(correoIncompleto || (!!$form.errors.email && !aviso));
  const inactivo = $derived(enPausa || $form.processing);

  const escena: Escena = $derived($form.processing ? 'trabajando' : enviado ? 'listo' : 'reposo');

  function enfocarCorreo() {
    document.getElementById('correo')?.focus();
  }

  function enviar(evento?: SubmitEvent) {
    evento?.preventDefault();
    const correo = $form.email.trim();
    // El formato se revisa al enviar, no mientras se escribe.
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(correo)) {
      correoIncompleto = true;
      enviadoA = null;
      enfocarCorreo();
      return;
    }
    correoIncompleto = false;
    $form.email = correo;
    $form.post(PasswordResetLinkController.store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        enviadoA = correo;
        esperar(esperaReenvio);
      },
      onError: async () => {
        enviadoA = null;
        await tick();
        enfocarCorreo();
      },
    });
  }

  async function usarOtroCorreo() {
    clearInterval(reloj);
    restante = 0;
    enviadoA = null;
    $form.reset();
    $form.clearErrors();
    await tick();
    enfocarCorreo();
  }

  onMount(enfocarCorreo);
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
          Escribe tu correo institucional y te enviaremos un enlace para elegir una contraseña nueva.
        </p>
      </div>

      {#if noRegistrado || enPausa}
        <div
          role="alert"
          class="flex animate-dm-aparece items-start gap-3 rounded-[10px] border px-3.5 py-3.5 motion-reduce:animate-none lg:px-4
            {noRegistrado ? 'border-dm-error bg-dm-error-s' : 'border-dm-warn bg-dm-warn-s'}"
        >
          {#if noRegistrado}
            <CircleX size={20} aria-hidden="true" class="mt-px shrink-0 text-dm-error" />
          {:else}
            <Clock size={20} aria-hidden="true" class="mt-px shrink-0 text-dm-warn" />
          {/if}
          <div class="flex min-w-0 flex-col gap-0.5">
            {#if noRegistrado}
              <span class="text-[15px] leading-[22px] font-semibold text-dm-ink"
                >No encontramos una cuenta con ese correo</span
              >
              <span class="text-sm leading-5 text-dm-ink-2">
                Revisa que sea tu correo institucional y no uno personal. Si está bien escrito, escribe a
                cite@gestion.uta.cl e indica tu RUT.
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
          <label for="correo" class="text-sm leading-5 font-medium">Correo institucional</label>
          <input
            id="correo"
            name="email"
            type="email"
            required
            autocomplete="email"
            placeholder="nombre.apellido@…"
            disabled={inactivo}
            aria-invalid={errorFormato || noRegistrado ? true : undefined}
            aria-describedby="correo-ayuda"
            bind:value={$form.email}
            class="{campo} min-h-12! text-base! aria-invalid:shadow-[0_0_0_1px_var(--color-dm-error)]"
          />
          {#if errorFormato}
            <span id="correo-ayuda" class="flex items-start gap-1.5 text-sm leading-5 text-dm-error">
              <CircleX size={16} aria-hidden="true" class="mt-0.5 shrink-0" />
              Al correo le falta una parte. Escríbelo completo, con la @ y el dominio de la universidad.
            </span>
          {:else}
            <span id="correo-ayuda" class="text-[13px] leading-[18px] text-dm-ink-2">
              Es el mismo correo al que te llegó tu contraseña.
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
          Te enviamos un enlace a <strong class="font-semibold [overflow-wrap:anywhere] text-dm-ink"
            >{enviadoA}</strong
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
            onclick={usarOtroCorreo}
            class="inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-dm-enlace hover:text-dm-enlace-hover hover:underline"
          >
            Usar otro correo
          </button>
        </div>
      </div>
    </div>
  {/if}
</IngresoNoche>
