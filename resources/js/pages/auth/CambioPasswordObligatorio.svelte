<script lang="ts">
  /**
   * Cambio de contraseña obligatorio: primer ingreso o clave vencida (guía
   * dm, dirección A · Noche).
   *
   * Mientras la clave no cumpla la política, el middleware ForcePasswordChange
   * trae aquí al usuario desde cualquier otra ruta. Por eso la página no usa
   * AppLayout ni deja salir por la marca: lo único que se puede hacer es
   * cambiar la clave o cerrar sesión.
   *
   * Antes de enviar se revisa que esté la contraseña actual, que la nueva
   * cumpla la política y que la confirmación coincida; el servidor vuelve a
   * validar. Al guardar, el servidor trae de vuelta a esta pantalla con
   * `cambiada` y se muestra el aviso de éxito con el botón para entrar.
   */
  import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
  import CampoClave from '@/components/ingreso/CampoClave.svelte';
  import IngresoNoche from '@/components/ingreso/IngresoNoche.svelte';
  import RequisitosClave from '@/components/ingreso/RequisitosClave.svelte';
  import type { Escena } from '@/components/ingreso/ReticulaViva.svelte';
  import { btnIngreso } from '@/lib/dm';
  import { passwordCumplePolitica } from '@/lib/password';
  import { dashboard, logout } from '@/routes';
  import { Link, page, router, useForm } from '@inertiajs/svelte';
  import { ArrowRight, CircleCheck, LoaderCircle, LockKeyhole } from 'lucide-svelte';
  import { onMount, tick } from 'svelte';

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
    /** La contraseña se acaba de cambiar: se muestra el aviso de éxito. */
    cambiada?: boolean;
  }

  let { motivo, vigenciaMeses, cambiada = false }: Props = $props();

  const form = useForm({ current_password: '', password: '', password_confirmation: '' });

  let errorLocal = $state<'actualVacia' | 'faltan' | 'noCoinciden' | null>(null);

  const usuario = $derived($page.props.auth.user);
  const vencida = $derived(motivo === 'vencida');

  const errorActual = $derived(
    errorLocal === 'actualVacia'
      ? 'Escribe la contraseña con la que acabas de ingresar.'
      : $form.errors.current_password
        ? 'La contraseña actual no es correcta. Escríbela de nuevo.'
        : null,
  );

  // Lo que dice el servidor sobre la clave nueva se traduce a los dos casos de
  // la pantalla: Laravel cuelga del campo `password` también el de la confirmación.
  const problema = $derived.by(() => {
    if (errorLocal === 'faltan' || errorLocal === 'noCoinciden') return errorLocal;
    if ($form.errors.password || $form.errors.password_confirmation) {
      return passwordCumplePolitica($form.password) ? 'noCoinciden' : 'faltan';
    }
    return null;
  });

  const escena: Escena = $derived($form.processing ? 'trabajando' : cambiada ? 'listo' : 'reposo');

  function enfocar(id: string) {
    document.getElementById(id)?.focus();
  }

  function guardar(evento: SubmitEvent) {
    evento.preventDefault();
    $form.clearErrors();
    if ($form.current_password.trim() === '') {
      errorLocal = 'actualVacia';
      enfocar('actual');
      return;
    }
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
    $form.put(PasswordController.update.url(), {
      preserveScroll: true,
      onError: (errores) => {
        // La actual equivocada se borra para escribirla de nuevo; la nueva se
        // conserva, porque ya cumplía.
        if (errores.current_password) {
          $form.reset('current_password');
          enfocar('actual');
        } else if (errores.password || errores.password_confirmation) {
          enfocar('nueva');
        }
      },
      onSuccess: () => $form.reset(),
    });
  }

  function cerrarSesion() {
    router.post(logout().url);
  }

  onMount(() => {
    if (!cambiada) enfocar('actual');
  });

  // Tras guardar, el foco pasa al botón para entrar: el formulario ya no está.
  $effect(() => {
    if (cambiada) tick().then(() => enfocar('entrar'));
  });
</script>

<svelte:head>
  <title>{cambiada ? 'Contraseña cambiada' : 'Cambiar contraseña'} | UTAMED</title>
</svelte:head>

{#snippet sesion()}
  <p class="m-0 flex flex-wrap items-center gap-x-2 text-sm leading-5 text-dm-ink-2">
    <span>
      {#if usuario}
        Sesión de <strong class="font-semibold text-dm-ink">{usuario.nombre1} {usuario.apellido1}</strong>.
      {/if}
      ¿No eres tú?
    </span>
    <button
      type="button"
      onclick={cerrarSesion}
      class="inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-dm-enlace hover:text-dm-enlace-hover hover:underline"
    >
      Cerrar sesión
    </button>
  </p>
{/snippet}

<IngresoNoche
  titular={cambiada ? ['Acceso', 'listo'] : vencida ? ['Es hora de', 'renovarla'] : ['Tu primer', 'ingreso']}
  bajada={cambiada
    ? 'Desde ahora entras con tu RUT y la contraseña nueva.'
    : vencida
      ? `Por seguridad, la contraseña se renueva cada ${vigenciaMeses} meses.`
      : 'Antes de entrar, cambia la contraseña con la que se creó tu cuenta por una que sólo tú conozcas.'}
  {escena}
  marcaEnlace={false}
  ayuda="¿No recuerdas la contraseña con la que entraste?"
>
  {#snippet barra()}
    {@render sesion()}
  {/snippet}

  {#if cambiada}
    <div class="flex flex-col gap-6">
      <div
        role="status"
        class="flex animate-dm-aparece items-start gap-4 rounded-xl border border-dm-ok bg-dm-ok-s p-5 motion-reduce:animate-none"
      >
        <CircleCheck
          size={28}
          aria-hidden="true"
          class="shrink-0 animate-dm-pop text-dm-ok [animation-delay:0.2s] motion-reduce:animate-none"
        />
        <div class="flex min-w-0 flex-col gap-1.5">
          <h1 class="m-0 text-2xl leading-[30px] font-medium">Tu contraseña se cambió</h1>
          <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
            Desde ahora entras a UTAMED con tu RUT y la contraseña nueva. La anterior ya no sirve.
          </p>
        </div>
      </div>
      <Link
        id="entrar"
        href={dashboard()}
        class="{btnIngreso} animate-dm-aparece [animation-delay:0.15s] motion-reduce:animate-none"
      >
        Entrar a UTAMED
        <ArrowRight size={18} aria-hidden="true" />
      </Link>
    </div>
  {:else}
    <div class="flex animate-dm-aparece flex-col gap-5 motion-reduce:animate-none lg:gap-6">
      <div class="flex flex-col gap-1.5 lg:gap-2">
        <h1 class="m-0 text-[28px] leading-[34px] font-medium">
          {vencida ? 'Renueva tu contraseña' : 'Crea tu nueva contraseña'}
        </h1>
        <p class="m-0 text-[15px] leading-[22px] text-dm-ink-2">
          {vencida
            ? `Han pasado más de ${vigenciaMeses} meses desde tu último cambio.`
            : 'Estás usando la contraseña con la que se creó tu cuenta.'}
          Mientras no elijas una nueva, el resto de UTAMED no está disponible.
        </p>
      </div>

      <form
        aria-label={vencida ? 'Renueva tu contraseña' : 'Crea tu nueva contraseña'}
        novalidate
        onsubmit={guardar}
        class="flex flex-col gap-5"
      >
        <CampoClave
          id="actual"
          name="current_password"
          etiqueta="Contraseña actual"
          nombreLector="contraseña actual"
          autocomplete="current-password"
          bind:value={$form.current_password}
          error={errorActual}
          ayuda="La contraseña con la que acabas de ingresar."
          disabled={$form.processing}
        />
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

  <div class="lg:hidden">{@render sesion()}</div>
</IngresoNoche>
