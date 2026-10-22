<script lang="ts">
  /**
   * «Mi perfil» del alumno (guía dm). Los datos institucionales (nombre, RUT,
   * correo, carrera…) vienen de la Intranet y son de sólo lectura; lo único
   * que se edita aquí es el contacto personal (T10). La foto también viene de
   * la Intranet y se cambia allá.
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import Reticula from '@/components/student/dm/Reticula.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { ChevronRight, CircleAlert, CircleCheck, ExternalLink, Info, KeyRound, Lock, UserRound } from 'lucide-svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { btnPrimario, campo, enlace, etiqueta, tarjeta, tituloCond } from '@/lib/dm';
  import { titleCase } from '@/lib/utils';
  import { initials } from '@/utils/formatters';

  type Red = 'youtube' | 'x' | 'instagram' | 'linkedin';

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

  const REDES: { id: Red; label: string; placeholder: string }[] = [
    { id: 'youtube', label: 'YouTube', placeholder: 'https://www.youtube.com/@tu-canal' },
    { id: 'x', label: 'X (Twitter)', placeholder: 'https://x.com/tu-usuario' },
    { id: 'instagram', label: 'Instagram', placeholder: 'https://www.instagram.com/tu-usuario' },
    { id: 'linkedin', label: 'LinkedIn', placeholder: 'https://www.linkedin.com/in/tu-perfil' },
  ];

  // svelte-ignore state_referenced_locally
  const form = useForm({
    correo_personal: contacto?.correo_personal ?? '',
    celular: contacto?.celular ?? '',
    redes_sociales: {
      youtube: contacto?.redes_sociales?.youtube ?? '',
      x: contacto?.redes_sociales?.x ?? '',
      instagram: contacto?.redes_sociales?.instagram ?? '',
      linkedin: contacto?.redes_sociales?.linkedin ?? '',
    } as Record<Red, string>,
  });

  let guardado = $state(false);

  function guardarContacto(e: SubmitEvent) {
    e.preventDefault();
    guardado = false;
    $form.patch('/estudiante/perfil/contacto', {
      preserveScroll: true,
      onSuccess: () => (guardado = true),
    });
  }

  const errores = $derived($form.errors as Record<string, string | undefined>);
  const conErrores = $derived(Object.values(errores).some(Boolean));

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mi perfil', href: '/estudiante/perfil' },
  ];

  const nombre = $derived(titleCase(perfil.nombre_completo));
  let imagenError = $state(false);

  const intranet = $derived([
    ['RUT', perfil.rut],
    ['Correo institucional', perfil.email],
    ['Usuario', perfil.username],
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
        <span
          class="flex size-18 shrink-0 items-center justify-center overflow-hidden rounded-full border-3 border-white/25 bg-[#2c3170] font-dm-cond text-[30px] leading-none font-black text-white lg:size-26 lg:text-[44px]"
        >
          {#if urlImagenPerfil && !imagenError}
            <img src={urlImagenPerfil} alt="Tu foto de perfil" class="size-full object-cover" onerror={() => (imagenError = true)} />
          {:else}
            <span role="img" aria-label="Sin foto de perfil">{initials(nombre)}</span>
          {/if}
        </span>
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

    <div class="flex flex-col gap-4 px-4 lg:grid lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start lg:gap-6 lg:px-0">
      <section aria-labelledby="t-cuenta" class="{tarjeta} overflow-hidden lg:col-start-2 lg:row-start-1">
        <h2 id="t-cuenta" class="border-b border-dm-linea px-4 py-3.5 text-base leading-[22px] font-bold lg:px-5 lg:py-4">Cuenta</h2>
        <Link
          href="/settings/password"
          class="flex min-h-15 items-center gap-3 border-b border-dm-linea px-4 py-2.5 text-dm-ink no-underline hover:bg-dm-sutil hover:text-dm-ink lg:min-h-16 lg:px-5 lg:py-3"
        >
          <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-dm-accion-suave text-dm-accion-texto">
            <KeyRound size={18} aria-hidden="true" />
          </span>
          <span class="flex min-w-0 flex-1 flex-col">
            <span class="text-[15px] leading-5 font-semibold text-dm-enlace">Cambiar contraseña</span>
            <span class="text-[13px] leading-[18px] text-dm-ink-2">La que usas para entrar a UTAMED</span>
          </span>
          <ChevronRight size={18} aria-hidden="true" class="shrink-0 text-dm-ink-3" />
        </Link>
        <div class="flex items-start gap-3 px-4 py-3.5 lg:px-5">
          <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-dm-sutil text-dm-ink-2">
            <UserRound size={18} aria-hidden="true" />
          </span>
          <span class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[15px] leading-5 font-semibold">Foto de perfil</span>
            <span class="text-[13px] leading-[18px] text-dm-ink-2">Viene de la Intranet y se cambia allá.</span>
            <a
              href="https://portal.uta.cl/"
              target="_blank"
              rel="noopener noreferrer"
              class="{enlace} self-start"
            >
              Ir a la Intranet<ExternalLink size={14} aria-hidden="true" /><span class="sr-only">{' '}(se abre en otra pestaña)</span>
            </a>
          </span>
        </div>
      </section>

      <section aria-labelledby="t-contacto" class="{tarjeta} flex min-w-0 flex-col lg:col-start-1 lg:row-span-2 lg:row-start-1">
        <div class="flex flex-col gap-1 border-b border-dm-linea px-4 py-4 lg:px-6 lg:py-5">
          <h2 id="t-contacto" class="text-lg leading-6 font-bold">Datos de contacto</h2>
          <span class="text-sm leading-5 text-dm-ink-2">Es lo único del perfil que editas aquí. Todos los campos son opcionales.</span>
        </div>
        <form aria-labelledby="t-contacto" class="flex flex-col gap-6 p-4 lg:p-6" onsubmit={guardarContacto} novalidate>
          <p class="flex items-start gap-2.5 rounded-lg bg-dm-info-s px-3.5 py-3 text-sm leading-5">
            <Info size={18} aria-hidden="true" class="mt-px shrink-0 text-dm-info" />
            <span>Sólo tus docentes ven estos datos; tus compañeros no.</span>
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
            <legend class="{etiqueta} mb-3 text-dm-ink-3">Redes sociales</legend>
            <div class="grid gap-5 sm:grid-cols-2">
              {#each REDES as red (red.id)}
                {@const mensaje = errores[`redes_sociales.${red.id}`]}
                <div class="flex flex-col gap-1.5">
                  <label for="red_{red.id}" class={ETIQUETA_CAMPO}>{red.label}</label>
                  <input
                    id="red_{red.id}"
                    type="url"
                    placeholder={red.placeholder}
                    bind:value={$form.redes_sociales[red.id]}
                    aria-invalid={mensaje ? 'true' : undefined}
                    aria-describedby={mensaje ? `red_${red.id}-error` : undefined}
                    class={campo}
                  />
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
          <Lock size={16} aria-hidden="true" class="mt-px shrink-0" />No se editan aquí. Si algo está mal, avisa a tu jefatura de carrera.
        </p>
      </section>
    </div>
  </div>
</StudentLayout>
