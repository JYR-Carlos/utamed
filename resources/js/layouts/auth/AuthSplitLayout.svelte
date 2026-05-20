<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import * as THREE from 'three';
  import NET from 'vanta/dist/vanta.net.min';
  import AppLogoIcon from '@/components/custom/layout/AppLogoIcon.svelte';
  import { home } from '@/routes';
  import { Link } from '@inertiajs/svelte';
  import type { Snippet } from 'svelte';
  import { fade, fly } from 'svelte/transition';

  interface Props {
    title: string;
    description: string;
    children?: Snippet;
  }

  let { title, description, children }: Props = $props();

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

<div class="flex min-h-screen font-[Inter,sans-serif]">
  <!-- Left Column: Form Panel (40% desktop, 100% mobile) -->
  <div
    class="flex w-full md:w-[40%] flex-col justify-center bg-white px-6 py-8 sm:px-12 lg:px-16 z-10"
  >
    <div class="mx-auto w-full max-w-md" in:fly={{ y: 20, duration: 600, delay: 200 }}>
      <!-- Logo -->
      <div class="mb-10 flex items-center gap-3">
        <Link href={home()} class="flex items-center gap-3 hover:opacity-80 transition-opacity">
          <AppLogoIcon class="size-8 fill-current text-[#2A66AC]" />
          <span class="text-2xl font-bold tracking-tight text-gray-900">UTAMed</span>
        </Link>
      </div>

      <!-- Title -->
      <div class="space-y-2 mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">{title}</h1>
        <p class="text-gray-500 text-sm">{description}</p>
      </div>

      <!-- Form slot -->
      <div class="mt-6">
        {@render children?.()}
      </div>

      <!-- Footer -->
      <div class="mt-10 flex items-center justify-between text-xs text-gray-400">
        <p>&copy; 2026 Universidad de Tarapacá</p>
        <div class="flex gap-4">
          <a href="#privacy" class="hover:text-[#2A66AC] transition-colors">Privacidad</a>
          <a href="#support" class="hover:text-[#2A66AC] transition-colors">Soporte</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Generative Art Panel (60% desktop, hidden mobile) -->
  <div
    bind:this={vantaContainer}
    class="hidden md:relative md:flex md:w-[60%] md:items-center md:justify-center bg-gradient-to-br from-[#1E4E8C] to-[#2A66AC] overflow-hidden"
  >
    <!-- Vanta canvas renders here as direct child (z-index auto / below z-10 wrapper) -->
    <div class="absolute inset-0 z-10 flex items-center justify-center">
      <!-- ══════════════════════════════════════════════════════════
         LAYER 5 — Central text (z-20, above everything)
         ══════════════════════════════════════════════════════════ -->
      <div
        class="relative z-20 flex flex-col items-center text-center px-8 max-w-xl"
        in:fade={{ duration: 1000 }}
      >
        <h2
          class="text-4xl md:text-5xl font-extrabold text-white tracking-tight drop-shadow-2xl text-center leading-tight"
        >
          Bienvenido al<br />portal académico
        </h2>
        <p class="mt-5 text-white/50 text-lg font-light max-w-sm leading-relaxed">
          Plataforma de gestión para estudiantes de Diseño Multimedia
        </p>

        <!-- Decorative amber accent bar -->
        <div class="mt-6 flex items-center gap-2">
          <div class="h-1 w-8 rounded-full bg-[#F0AD4E]/80"></div>
          <div class="h-1.5 w-14 rounded-full bg-[#F0AD4E]"></div>
          <div class="h-1 w-8 rounded-full bg-[#F0AD4E]/80"></div>
        </div>
      </div>

      <!-- Bottom vignette -->
      <div
        class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#1E4E8C]/40 to-transparent pointer-events-none z-[6]"
      ></div>
      <!-- Top vignette -->
      <div
        class="absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-[#1E4E8C]/25 to-transparent pointer-events-none z-[6]"
      ></div>
    </div>
  </div>
</div>

<style>
  :global(body) {
    background-color: #ffffff;
  }

  :global(::-webkit-scrollbar) {
    width: 8px;
  }
  :global(::-webkit-scrollbar-track) {
    background: #ffffff;
  }
  :global(::-webkit-scrollbar-thumb) {
    background: #d1d5db;
    border-radius: 4px;
  }
  :global(::-webkit-scrollbar-thumb:hover) {
    background: #9ca3af;
  }

  /* Floating keyframes for micro-dot drift */
  @keyframes float-up {
    0%,
    100% {
      transform: translateY(0) scale(1);
      opacity: 0.5;
    }
    50% {
      transform: translateY(-18px) scale(1.3);
      opacity: 1;
    }
  }
  @keyframes float-down {
    0%,
    100% {
      transform: translateY(0) scale(1);
      opacity: 0.5;
    }
    50% {
      transform: translateY(14px) scale(1.25);
      opacity: 1;
    }
  }
</style>
