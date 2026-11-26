<script lang="ts">
  /**
   * Restablecer la contraseña desde el enlace del correo (guía dm, dirección
   * A · Noche; paso 3 de 3).
   *
   * Antes de enviar se revisa que la contraseña cumpla la política y que la
   * confirmación coincida; el servidor vuelve a validar. Si el enlace venció
   * o ya se usó, Fortify responde con un error en `email` y la pantalla pasa
   * a «Este enlace ya no sirve». Al guardar, Fortify lleva al login, que
   * muestra el aviso de contraseña restablecida.
   */
  import NewPasswordController from '@/actions/Laravel/Fortify/Http/Controllers/NewPasswordController';
  import CampoClave from '@/components/ingreso/CampoClave.svelte';
  import IngresoNoche from '@/components/ingreso/IngresoNoche.svelte';
  import PasosRecuperacion from '@/components/ingreso/PasosRecuperacion.svelte';
  import RequisitosClave from '@/components/ingreso/RequisitosClave.svelte';
  import type { Escena } from '@/components/ingreso/ReticulaViva.svelte';
  import { btnIngreso, enlace } from '@/lib/dm';
  import { passwordCumplePolitica } from '@/lib/password';
  import { login } from '@/routes';
  import { request } from '@/routes/password';
  import { Link, useForm } from '@inertiajs/svelte';
  import { ArrowLeft, LoaderCircle, LockKeyhole, Send } from 'lucide-svelte';
  import { onMount } from 'svelte';

  interface Props {
    /** Token del enlace. */
    token: string;
    /** Correo de la cuenta, del enlace. */
    email: string;
    /** Minutos que vale el enlace. */
    vigenciaMinutos: number;
  }

  let { token, email, vigenciaMinutos }: Props = $props();

  const form = useForm({ password: '', password_confirmation: '' });

  let errorLocal = $state<'faltan' | 'noCoinciden' | null>(null);

  // El enlace venció, ya se usó o no corresponde al correo. Esos errores
  // vienen de campos que el formulario no tiene (van en transform).
  const vencido = $derived.by(() => {
    const errores: Partial<Record<string, string>> = $form.errors;
    return !!errores.email || !!errores.token;
  });

  // Lo que dice el servidor sobre la clave se traduce a los dos casos de la
  // pantalla: Laravel cuelga del campo `password` también el de la confirmación.
  const problema = $derived.by(() => {
    if (errorLocal) return errorLocal;
    if ($form.errors.password || $form.errors.password_confirmation) {
      return passwordCumplePolitica($form.password) ? 'noCoinciden' : 'faltan';
    }
    return null;
  });

  const escena: Escena = $derived($form.processing ? 'trabajando' : 'reposo');

  function enfocar(id: string) {
    document.getElementById(id)?.focus();
  }

  function guardar(evento: SubmitEvent) {
    evento.preventDefault();
    $form.clearErrors();
    if (!passwordCumplePolitica($form.password)) {
      errorLocal = 'faltan';
      enfocar('nueva');
      return;
    }
    if ($form.password_confirmation !== $form.password) {
      errorLocal = 'noCoinciden';
      enfocar('confirma');
      return;
    }
    errorLocal = null;
    $form
      .transform((datos) => ({ ...datos, token, email }))
      .post(NewPasswordController.store.url(), {
        preserveScroll: true,
        onError: (errores) => {
          if (errores.password || errores.password_confirmation) enfocar('nueva');
        },
      });
  }

  onMount(() => enfocar('nueva'));
</script>

<svelte:head>
  <title>Restablecer contraseña | UTAMED</title>
</svelte:head>

{#snippet volver(clase: string)}
  <Link href={login()} class="{enlace} gap-1.5! font-medium! {clase}">
    <ArrowLeft size={16} aria-hidden="true" />
    Volver a iniciar sesión
  </Link>
{/snippet}

<IngresoNoche
  titular={vencido ? ['Pide un', 'enlace nuevo'] : ['Un último', 'paso']}
  bajada={vencido
    ? `Por seguridad, cada enlace vale ${vigenciaMinutos} minutos y se usa una sola vez.`
    : 'Elige una contraseña que sólo tú conozcas. Con ella y tu RUT entras a UTAMED desde el computador o el celular.'}
  {escena}
  ayuda="¿El enlace no abre o ya no usas ese correo?"
>
  {#snippet barra()}
    {@render volver('')}
  {/snippet}

  <PasosRecuperacion actual={vencido ? 0 : 2} />

  {#if vencido}
    <div class="flex flex-col gap-6">
      <div role="alert" class="flex animate-dm-aparece flex-col gap-1.5 motion-reduce:animate-none lg:gap-2">
        <h1 class="m-0 text-[28px] leading-[34px] font-medium">Este enlace ya no sirve</h1>
        <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
          Los enlaces para cambiar la contraseña valen {vigenciaMinutos} minutos y se usan una sola vez. Pide uno
          nuevo y te llegará otro a tu correo institucional.
        </p>
      </div>
      <Link
        href={request()}
        class="{btnIngreso} animate-dm-aparece [animation-delay:0.1s] motion-reduce:animate-none"
      >
        Pedir un enlace nuevo
        <Send size={18} aria-hidden="true" />
      </Link>
    </div>
  {:else}
    <div class="flex animate-dm-aparece flex-col gap-5 motion-reduce:animate-none lg:gap-6">
      <div class="flex flex-col gap-1.5 lg:gap-2">
        <h1 class="m-0 text-[28px] leading-[34px] font-medium">Restablece tu contraseña</h1>
        <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
          Es para la cuenta de <strong class="font-semibold [overflow-wrap:anywhere] text-dm-ink">{email}</strong>.
          Después entras con tu RUT y esta contraseña.
        </p>
      </div>

      <form aria-label="Restablecer contraseña" novalidate onsubmit={guardar} class="flex flex-col gap-5">
        <CampoClave
          id="nueva"
          name="password"
          etiqueta="Nueva contraseña"
          nombreLector="nueva contraseña"
          autocomplete="new-password"
          bind:value={$form.password}
          error={problema === 'faltan' ? 'A la contraseña le falta lo que sigue sin marcar en la lista de abajo.' : null}
          describe="requisitos"
          disabled={$form.processing}
        />
        <CampoClave
          id="confirma"
          name="password_confirmation"
          etiqueta="Confirma la nueva contraseña"
          nombreLector="confirmación"
          autocomplete="new-password"
          bind:value={$form.password_confirmation}
          error={problema === 'noCoinciden' ? 'No es igual a la contraseña nueva. Escríbela otra vez.' : null}
          describe="requisitos"
          disabled={$form.processing}
        />

        <RequisitosClave password={$form.password} confirmacion={$form.password_confirmation} />

        <button type="submit" disabled={$form.processing} class={btnIngreso}>
          {#if $form.processing}
            <LoaderCircle size={18} aria-hidden="true" class="animate-spin motion-reduce:animate-none" />
            <span aria-live="polite">Guardando contraseña…</span>
          {:else}
            <LockKeyhole size={18} aria-hidden="true" />
            Guardar contraseña
          {/if}
        </button>
      </form>
    </div>
  {/if}

  <div class="flex justify-center lg:hidden">{@render volver('text-[15px]!')}</div>
</IngresoNoche>
