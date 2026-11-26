<script lang="ts">
  /**
   * «Mi perfil» del alumno (guía dm). Los datos institucionales (nombre, RUT,
   * correo, carrera…) vienen de la Intranet y son de sólo lectura; lo único
   * que se edita aquí es el contacto personal (T10). La foto es la de la
   * credencial: también viene de la Intranet y aquí no se cambia.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import FotoPerfil from '@/components/student/dm/FotoPerfil.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Check, ChevronRight, CircleAlert, CircleCheck, Copy, Info, KeyRound, Lock } from 'lucide-svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { btnPrimario, campo, etiqueta, tarjeta, tituloCond } from '@/lib/dm';
  import { titleCase } from '@/lib/utils';

  type Red = 'youtube' | 'instagram' | 'linkedin';

  interface Props {
    perfil: {
      nombre_completo: string;
      rut: string;
      email: string;
      username: string;
      carrera_nombre: string;
      agno_ingreso: number;
      total_cursos: number;
    };
    urlImagenPerfil: string;
    /** Contacto personal que el alumno mantiene (T10). */
    contacto: {
      correo_personal: string | null;
      celular: string | null;
      redes_sociales: Record<Red, string | null>;
    };
  }

  let { perfil, urlImagenPerfil, contacto }: Props = $props();

  // Basta el usuario: el servidor arma el enlace (OBS-19). También se puede pegar el enlace.
  const REDES: { id: Red; label: string; placeholder: string; prefijo: string; ejemplo: string; dominios: string[] }[] = [
    { id: 'youtube', label: 'YouTube', placeholder: '@tu-canal', prefijo: 'youtube.com/@', ejemplo: 'tu-canal', dominios: ['youtube.com', 'youtu.be'] },
    { id: 'instagram', label: 'Instagram', placeholder: '@tu-usuario', prefijo: 'instagram.com/', ejemplo: 'tu-usuario', dominios: ['instagram.com'] },
    { id: 'linkedin', label: 'LinkedIn', placeholder: 'tu-perfil', prefijo: 'linkedin.com/in/', ejemplo: 'tu-perfil', dominios: ['linkedin.com'] },
  ];

  const PATRON_USUARIO: Record<Red, RegExp> = {
    youtube: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/(@[A-Za-z0-9._-]+)\/?$/i,
    instagram: /^(?:https?:\/\/)?(?:www\.|m\.)?instagram\.com\/([A-Za-z0-9._-]+)\/?$/i,
    linkedin: /^(?:https?:\/\/)?(?:www\.|m\.)?linkedin\.com\/in\/([A-Za-z0-9._-]+)\/?$/i,
  };

  function extraerUsuario(red: Red, valor: string | null | undefined): string {
    const limpio = (valor ?? '').trim();
    if (!limpio) return '';
    return limpio.match(PATRON_USUARIO[red])?.[1] ?? limpio;
  }

  function vistaPreviaEnlace(red: (typeof REDES)[number], valor: string | null | undefined): string {
    const limpio = (valor ?? '').trim();
    if (!limpio) return `${red.prefijo}${red.ejemplo}`;

    const usuario = limpio.replace(/^@+/, '');
    const sinSubdominio = usuario.toLowerCase().replace(/^(www\.|m\.)/, '');
    if (!red.dominios.includes(sinSubdominio) && /^[A-Za-z0-9._-]{1,100}$/.test(usuario)) {
      return `${red.prefijo}${usuario}`;
    }

    return limpio.replace(/^https?:\/\/(www\.)?/i, '');
  }

  // svelte-ignore state_referenced_locally
  const form = useForm({
    correo_personal: contacto?.correo_personal ?? '',
    celular: contacto?.celular ?? '',
    redes_sociales: {
      youtube: extraerUsuario('youtube', contacto?.redes_sociales?.youtube),
      instagram: extraerUsuario('instagram', contacto?.redes_sociales?.instagram),
      linkedin: extraerUsuario('linkedin', contacto?.redes_sociales?.linkedin),
    } as Record<Red, string>,
  });

  let guardado = $state(false);
  let copiado = $state<Red | null>(null);
  let temporizadorCopiado: ReturnType<typeof setTimeout> | undefined;

  async function copiarEnlace(red: (typeof REDES)[number], valor: string | null | undefined) {
    const vista = vistaPreviaEnlace(red, valor);
    const url = /^https?:\/\//i.test(vista) ? vista : `https://${vista}`;
    try {
      await navigator.clipboard.writeText(url);
      copiado = red.id;
      clearTimeout(temporizadorCopiado);
      temporizadorCopiado = setTimeout(() => {
        if (copiado === red.id) copiado = null;
      }, 2000);
    } catch {
      // Sin acceso al portapapeles en contextos no seguros.
    }
  }

  function guardarContacto(e: SubmitEvent) {
    e.preventDefault();
    guardado = false;
    $form.patch('/estudiante/perfil/contacto', {
      preserveScroll: true,
      onSuccess: (pagina) => {
        guardado = true;
        // Se mantiene sólo el usuario en el input; el enlace completo sigue visible abajo.
        const redes = (pagina.props.contacto as Props['contacto'] | undefined)?.redes_sociales;
        for (const red of REDES) $form.redes_sociales[red.id] = extraerUsuario(red.id, redes?.[red.id]);
      },
    });
  }

  const errores = $derived($form.errors as Record<string, string | undefined>);
  const conErrores = $derived(Object.values(errores).some(Boolean));

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mi perfil', href: '/estudiante/perfil' },
  ];

  const nombre = $derived(titleCase(perfil.nombre_completo));
  const filaCuenta = 'flex min-h-14 items-center gap-3 px-4 py-2.5 text-dm-ink no-underline hover:bg-dm-sutil hover:text-dm-ink lg:px-5';

  const intranet = $derived([
    ['RUT', perfil.rut],
    ['Correo institucional', perfil.email],
    ['Carrera', perfil.carrera_nombre],
    ['Año de ingreso', perfil.agno_ingreso],
    ['Cursos inscritos', perfil.total_cursos],
  ] as const);

  const ETIQUETA_CAMPO = 'text-sm leading-5 font-semibold';
</script>

<svelte:head>
  <title>Mi perfil | UTAMED</title>
</svelte:head>

{#snippet error(id: string, mensaje: string | undefined)}
  {#if mensaje}
    <span {id} class="flex items-start gap-1.5 text-sm leading-5 text-dm-error">
      <CircleAlert size={16} aria-hidden="true" class="mt-0.5 shrink-0" />{mensaje}
    </span>
  {/if}
{/snippet}

<StudentLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-280 flex-col gap-4 pb-10 lg:gap-6 lg:px-10 lg:pt-8 lg:pb-12">
    <section aria-labelledby="t-perfil" class="sobre-noche relative overflow-hidden bg-dm-noche lg:rounded-xl lg:border lg:border-dm-noche-borde">
      <Reticula class="left-[45%] lg:left-1/2" />
      <div class="relative flex items-center gap-4 px-4 py-6 lg:gap-7 lg:px-10 lg:py-8">
        <FotoPerfil src={urlImagenPerfil} class="aspect-[3/4] w-18 rounded-xl border-3 border-white/25 bg-dm-noche-avatar lg:w-26" />
        <div class="flex min-w-0 flex-col gap-1.5 lg:gap-2">
          <span class="{etiqueta} text-dm-amarillo">Mi perfil</span>
          <h1 id="t-perfil" class="{tituloCond} text-[32px] leading-8 break-words text-dm-noche-texto lg:text-5xl lg:leading-12">{nombre}</h1>
          <span class="text-sm leading-5 text-dm-noche-texto-2 lg:text-[15px] lg:leading-[22px]">
            {perfil.carrera_nombre} · ingreso {perfil.agno_ingreso} · {perfil.total_cursos}
            {perfil.total_cursos === 1 ? 'curso inscrito' : 'cursos inscritos'}
          </span>
        </div>
      </div>
    </section>

    <div class="flex flex-col gap-4 px-4 lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:grid-rows-[auto_1fr] lg:items-start lg:gap-6 lg:px-0">
      <section aria-labelledby="t-cuenta" class="{tarjeta} overflow-hidden lg:col-start-2 lg:row-start-1">
        <h2 id="t-cuenta" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5 lg:py-4">Cuenta</h2>
        <Link href="/settings/password" class={filaCuenta}>
          <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-dm-accion-suave text-dm-accion-texto">
            <KeyRound size={18} aria-hidden="true" />
          </span>
          <span class="min-w-0 flex-1 text-[15px] leading-5 font-semibold text-dm-enlace">Cambiar contraseña</span>
          <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-dm-ink-3" />
        </Link>
      </section>

      <section aria-labelledby="t-contacto" class="{tarjeta} flex min-w-0 flex-col lg:col-start-1 lg:row-span-2 lg:row-start-1">
        <div class="flex flex-col gap-1 border-b border-dm-linea px-4 py-4 lg:px-6 lg:py-5">
          <h2 id="t-contacto" class="text-lg leading-6 font-bold">Datos de contacto</h2>
        </div>
        <form aria-labelledby="t-contacto" class="flex flex-col gap-6 p-4 lg:p-6" onsubmit={guardarContacto} novalidate>
          <p class="flex items-start gap-2.5 rounded-lg bg-dm-info-s px-3.5 py-3 text-sm leading-5">
            <Info size={18} aria-hidden="true" class="mt-px shrink-0 text-dm-info" />
            <span>Sólo tus docentes ven estos datos; no tus compañeros.</span>
          </p>

          <div class="grid gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
              <label for="correo_personal" class={ETIQUETA_CAMPO}>Correo personal</label>
              <input
                id="correo_personal"
                type="email"
                autocomplete="email"
                placeholder="tu.correo@ejemplo.com"
                bind:value={$form.correo_personal}
                aria-invalid={errores.correo_personal ? 'true' : undefined}
                aria-describedby={errores.correo_personal ? 'correo_personal-error' : undefined}
                class={campo}
              />
              {@render error('correo_personal-error', errores.correo_personal)}
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="celular" class={ETIQUETA_CAMPO}>Celular</label>
              <input
                id="celular"
                type="tel"
                autocomplete="tel"
                placeholder="+56 9 1234 5678"
                bind:value={$form.celular}
                aria-invalid={errores.celular ? 'true' : undefined}
                aria-describedby={errores.celular ? 'celular-error' : undefined}
                class={campo}
              />
              {@render error('celular-error', errores.celular)}
            </div>
          </div>

          <fieldset class="min-w-0">
            <legend class="{etiqueta} mb-1 text-dm-ink-3">Redes sociales</legend>
            <p class="mb-4 text-sm leading-5 text-dm-ink-2">Escribe tu usuario o pega el enlace a tu perfil.</p>
            <div class="grid gap-5 sm:grid-cols-2">
              {#each REDES as red (red.id)}
                {@const mensaje = errores[`redes_sociales.${red.id}`]}
                <div class="flex flex-col gap-1.5">
                  <label for="red_{red.id}" class={ETIQUETA_CAMPO}>{red.label}</label>
                  <input
                    id="red_{red.id}"
                    type="text"
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                    placeholder={red.placeholder}
                    bind:value={$form.redes_sociales[red.id]}
                    aria-invalid={mensaje ? 'true' : undefined}
                    aria-describedby="red_{red.id}-ayuda{mensaje ? ` red_${red.id}-error` : ''}"
                    class={campo}
                  />
                  <div class="flex items-center justify-between gap-2">
                    <span id="red_{red.id}-ayuda" class="min-w-0 text-[13px] leading-[18px] text-dm-ink-2 [overflow-wrap:anywhere]">
                      {vistaPreviaEnlace(red, $form.redes_sociales[red.id])}
                    </span>
                    {#if ($form.redes_sociales[red.id] ?? '').trim()}
                      <button
                        type="button"
                        onclick={() => copiarEnlace(red, $form.redes_sociales[red.id])}
                        aria-label="Copiar enlace de {red.label}"
                        title="Copiar enlace"
                        class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold transition-colors {copiado === red.id ? 'bg-dm-ok/10 text-dm-ok' : 'text-dm-ink-2 hover:bg-dm-sutil hover:text-dm-ink'}"
                      >
                        {#if copiado === red.id}
                          <Check size={14} aria-hidden="true" />Copiado
                        {:else}
                          <Copy size={14} aria-hidden="true" />Copiar
                        {/if}
                      </button>
                    {/if}
                  </div>
                  {@render error(`red_${red.id}-error`, mensaje)}
                </div>
              {/each}
            </div>
            {#if errores.redes_sociales}<div class="mt-3">{@render error('redes-error', errores.redes_sociales)}</div>{/if}
          </fieldset>

          <div class="flex flex-wrap items-center gap-4 border-t border-dm-linea pt-5">
            <button type="submit" class="{btnPrimario} max-sm:w-full" disabled={$form.processing}>Guardar contacto</button>
            {#if conErrores}
              <p role="alert" class="inline-flex items-center gap-1.5 text-sm leading-5 font-semibold text-dm-error">
                <CircleAlert size={16} aria-hidden="true" />No se guardó: revisa el campo marcado.
              </p>
            {:else if guardado}
              <p role="status" class="inline-flex items-center gap-1.5 text-sm leading-5 font-semibold text-dm-ok">
                <CircleCheck size={16} aria-hidden="true" />Tus datos de contacto se guardaron.
              </p>
            {/if}
          </div>
        </form>
      </section>

      <section aria-labelledby="t-intranet" class="{tarjeta} overflow-hidden lg:col-start-2 lg:row-start-2">
        <h2 id="t-intranet" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5 lg:py-4">Datos de la Intranet</h2>
        <dl class="flex flex-col gap-3 px-4 py-4 lg:px-5">
          {#each intranet as [dato, valor] (dato)}
            <div class="flex flex-col gap-0.5">
              <dt class="text-[13px] leading-[18px] text-dm-ink-3">{dato}</dt>
              <dd class="text-[15px] leading-5 font-semibold [overflow-wrap:anywhere]">{valor}</dd>
            </div>
          {/each}
        </dl>
        <p class="flex items-start gap-2 border-t border-dm-linea bg-dm-sutil px-4 py-3 text-[13px] leading-[18px] text-dm-ink-2 lg:px-5">
          <Lock size={16} aria-hidden="true" class="mt-px shrink-0" />Si algun dato está mal, avisa a jefatura de carrera.
        </p>
      </section>
    </div>
  </div>
</StudentLayout>
