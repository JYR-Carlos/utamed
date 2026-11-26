<script lang="ts">
  /**
   * Armazón de las pantallas de contraseña (guía dm, dirección A · Noche):
   * recuperar, restablecer y cambio obligatorio.
   *
   * A la izquierda (arriba en móvil) va el panel noche con la retícula viva,
   * el titular condensado y los logos; a la derecha, la barra superior, la
   * columna del formulario, la ayuda con el correo de soporte y el pie. Es el
   * mismo esquema del login, con la tinta y los pesos más suaves que pidió el
   * usuario para estas pantallas (.tinta-suave).
   */
  import BotonTema from '@/components/ingreso/BotonTema.svelte';
  import LogosInstitucionales from '@/components/ingreso/LogosInstitucionales.svelte';
  import ReticulaViva, { type Escena, type Figura } from '@/components/ingreso/ReticulaViva.svelte';
  import { home } from '@/routes';
  import { Link } from '@inertiajs/svelte';
  import type { Snippet } from 'svelte';

  interface Props {
    /** Titular del panel en dos líneas (en móvil va en una). */
    titular: [string, string];
    /** Bajada del panel; sólo en escritorio. */
    bajada: string;
    escena: Escena;
    figura?: Figura;
    /** La marca lleva a la portada; no en el cambio obligatorio, que no deja salir. */
    marcaEnlace?: boolean;
    /** Pregunta que abre la ayuda: «… Escribe a cite@gestion.uta.cl …». */
    ayuda: string;
    /** Lo que va a la izquierda de la barra superior en escritorio. */
    barra: Snippet;
    children: Snippet;
  }

  let { titular, bajada, escena, figura = 'centro', marcaEnlace = true, ayuda, barra, children }: Props = $props();

  const fondoNoche =
    'bg-[linear-gradient(160deg,#161949_0%,#161949_45%,#660959_100%)] lg:bg-[linear-gradient(150deg,#161949_0%,#161949_45%,#660959_100%)]';
</script>

{#snippet marca()}
  <img src="/icon.png" alt="" width="32" height="32" class="size-7 shrink-0 object-contain lg:size-8" />
  <span class="font-dm-cond text-[22px] leading-[26px] font-extrabold tracking-[0.02em] lg:text-2xl lg:leading-7"
    >UTAMED</span
  >
{/snippet}

<div class="vista-ingreso tinta-suave flex min-h-screen flex-col bg-dm-pagina font-dm text-sm leading-5 text-dm-ink">
  <div class="flex flex-1 flex-col lg:flex-row">
    <aside
      aria-label="UTAMED · Diseño Multimedia"
      class="sobre-noche relative flex flex-col overflow-hidden pb-7 text-dm-noche-texto lg:w-[42%] lg:shrink-0 lg:gap-10 lg:px-14 lg:pt-8 lg:pb-12 {fondoNoche}"
    >
      <ReticulaViva {escena} {figura} class="absolute inset-x-0 top-0 h-[620px] max-lg:hidden" />

      <div class="relative flex h-16 items-center gap-2 ps-4 pe-2 lg:h-auto lg:p-0">
        {#if marcaEnlace}
          <Link
            href={home()}
            class="flex min-h-11 flex-1 items-center gap-2 text-dm-noche-texto no-underline lg:flex-none lg:gap-2.5"
          >
            {@render marca()}
          </Link>
        {:else}
          <div class="flex min-h-11 flex-1 items-center gap-2 lg:flex-none lg:gap-2.5">
            {@render marca()}
          </div>
        {/if}
        <BotonTema class="text-dm-noche-texto hover:bg-dm-noche-activo lg:hidden" />
      </div>

      <ReticulaViva {escena} {figura} movil class="relative h-36 lg:hidden" />

      <div class="relative flex flex-col gap-3 px-4 pt-3 lg:mt-auto lg:max-w-[460px] lg:gap-4 lg:p-0">
        <span class="text-xs leading-4 font-semibold tracking-[0.06em] text-dm-amarillo uppercase"
          >Diseño Multimedia · Universidad de Tarapacá</span
        >
        {#key titular.join(' ')}
          <p class="m-0 font-dm-cond text-4xl leading-[34px] font-semibold uppercase lg:text-[56px] lg:leading-[52px]">
            <!-- Lo responsivo va en envoltorios: el estilo de .linea, fuera de
                 las capas de Tailwind, le ganaría a `hidden`. -->
            <span class="hidden lg:block">
              <span class="linea"><span>{titular[0]}</span></span>
              <span class="linea"><span class="segunda">{titular[1]}</span></span>
            </span>
            <span class="block lg:hidden">
              <span class="linea"><span>{titular[0]} {titular[1]}</span></span>
            </span>
          </p>
        {/key}
        <p class="m-0 text-base leading-6 text-dm-noche-texto-2 max-lg:hidden">{bajada}</p>
      </div>

      <LogosInstitucionales class="relative mt-6 px-4 lg:mt-0 lg:px-0" />
    </aside>

    <main class="flex min-w-0 flex-1 flex-col gap-6 px-4 pt-7 pb-8 lg:gap-5 lg:px-10 lg:pt-6 lg:pb-7">
      <div class="flex items-center justify-between gap-4 max-lg:hidden">
        {@render barra()}
        <BotonTema class="h-10 border border-dm-linea bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil" />
      </div>

      <div class="flex flex-1 justify-center lg:items-center lg:py-8">
        <div class="flex w-full max-w-[420px] flex-col gap-6 lg:gap-7">
          {@render children()}

          <p class="m-0 border-t border-dm-linea pt-4 text-sm leading-5 text-dm-ink-2 lg:pt-5">
            {ayuda} Escribe a
            <a href="mailto:cite@gestion.uta.cl" class="text-sm font-medium text-dm-enlace hover:text-dm-enlace-hover"
              >cite@gestion.uta.cl</a
            >
            e indica tu RUT y tu nombre completo.
          </p>
        </div>
      </div>

      <p class="m-0 text-[13px] leading-[18px] text-dm-ink-3">
        © {new Date().getFullYear()} Universidad de Tarapacá<span class="max-lg:hidden"
          >{' '}· Escuela de Diseño e Innovación Tecnológica</span
        >
      </p>
    </main>
  </div>
</div>

<style>
  /* Cada línea del titular sube desde abajo de su propia caja. */
  .linea {
    display: block;
    overflow: hidden;
    padding-top: 6px;
    margin-top: -6px;
  }

  .linea > span {
    display: block;
    animation: sube 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) both;
  }

  .linea > .segunda {
    animation-delay: 0.08s;
  }

  @keyframes sube {
    from {
      transform: translateY(110%);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .linea > span {
      animation: none;
    }
  }
</style>
