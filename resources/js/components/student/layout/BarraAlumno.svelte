<script lang="ts">
  /**
   * Barra superior del alumno (guía dm).
   *
   * En escritorio: la ruta de navegación sobre fondo de tarjeta. En móvil: barra
   * noche con el botón del menú y la marca; ahí la ruta no cabe y cada página
   * trae su «‹ volver» en la cabecera.
   */
  import { Link } from '@inertiajs/svelte';
  import { Menu } from 'lucide-svelte';
  import IssueReport from '@/components/custom/IssueReport.svelte';
  import { OPCIONES_APARIENCIA, useAppearance } from '@/hooks/useAppearance.svelte';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    breadcrumbs?: BreadcrumbItem[];
    onAbrirMenu: () => void;
  }

  let { breadcrumbs = [], onAbrirMenu }: Props = $props();

  // Un solo botón de tema: cada clic pasa a la siguiente opción
  // (Claro → Oscuro → Sistema → Claro) y el ícono muestra la actual.
  const apariencia = useAppearance();
  const temaIndice = $derived(Math.max(0, OPCIONES_APARIENCIA.findIndex((o) => o.value === apariencia.appearance)));
  const temaActual = $derived(OPCIONES_APARIENCIA[temaIndice]);
  const temaSiguiente = $derived(OPCIONES_APARIENCIA[(temaIndice + 1) % OPCIONES_APARIENCIA.length]);
  const temaEtiqueta = $derived(`Tema: ${temaActual.label}. Cambiar a ${temaSiguiente.label.toLowerCase()}`);

  // Sobre noche (móvil) el foco va en amarillo; sobre la tarjeta (escritorio), en azul.
  const foco = 'focus-visible:outline-dm-amarillo lg:focus-visible:outline-dm-enlace';
</script>

<header
  class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-dm-noche-linea bg-dm-noche px-1.5 lg:gap-4 lg:border-dm-linea lg:bg-dm-tarjeta lg:px-10"
>
  <button
    type="button"
    onclick={onAbrirMenu}
    aria-label="Abrir el menú"
    class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-dm-noche-texto hover:bg-dm-noche-activo lg:hidden {foco}"
  >
    <Menu size={22} aria-hidden="true" />
  </button>

  <Link href="/estudiante/dashboard" class="flex min-h-11 min-w-0 flex-1 items-center gap-2 no-underline lg:hidden {foco}">
    <img src="/icon.png" alt="" width="28" height="28" class="size-7 shrink-0 object-contain" />
    <span class="font-dm-cond text-[22px] leading-[26px] font-extrabold tracking-[0.02em] text-dm-noche-texto">UTAMED</span>
  </Link>

  <nav aria-label="Ruta de navegación" class="hidden min-w-0 flex-1 lg:block">
    <ol class="flex min-w-0 items-center gap-2 text-sm font-medium text-dm-ink-2">
      {#each breadcrumbs as miga, i (i)}
        {#if i === breadcrumbs.length - 1}
          <li class="min-w-0 truncate" aria-current="page" title={miga.title}>{miga.title}</li>
        {:else}
          <li class="flex shrink-0 items-center gap-2">
            <Link
              href={miga.href}
              class="block max-w-64 truncate py-2 text-dm-enlace no-underline hover:text-dm-enlace-hover hover:underline"
              title={miga.title}>{miga.title}</Link
            >
            <span aria-hidden="true" class="text-dm-ink-3">/</span>
          </li>
        {/if}
      {/each}
    </ol>
  </nav>

  <div class="flex shrink-0 items-center gap-1 lg:gap-3">
    <IssueReport
      class="border-dm-control bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil max-lg:border-transparent max-lg:bg-transparent max-lg:text-dm-noche-texto max-lg:hover:bg-dm-noche-activo {foco}"
    />
    <button
      type="button"
      onclick={() => apariencia.updateAppearance(temaSiguiente.value)}
      aria-label={temaEtiqueta}
      title={temaEtiqueta}
      class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-dm-noche-texto hover:bg-dm-noche-activo lg:size-10 lg:rounded-[10px] lg:border lg:border-dm-linea lg:bg-dm-tarjeta lg:text-dm-ink lg:hover:bg-dm-sutil {foco}"
    >
      <temaActual.Icon size={18} aria-hidden="true" />
    </button>
  </div>
</header>
