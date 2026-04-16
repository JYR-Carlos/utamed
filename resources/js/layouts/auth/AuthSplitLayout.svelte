<script lang="ts">
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
    class="hidden md:relative md:flex md:w-[60%] md:items-center md:justify-center bg-gradient-to-br from-[#1E4E8C] to-[#2A66AC] overflow-hidden"
  >
    <!-- ══════════════════════════════════════════════════════════
         LAYER 0 — Radial glow base (subtle depth)
         ══════════════════════════════════════════════════════════ -->
    <div
      class="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_40%,rgba(42,102,172,0.45),transparent)] z-0"
    ></div>

    <!-- ══════════════════════════════════════════════════════════
         LAYER 1 — Glassmorphism Spheres (4 orbs, slow ambient)
         ══════════════════════════════════════════════════════════ -->
    <!-- Orb A — top-left, giant, very slow spin -->
    <div
      class="absolute -top-28 -left-28 w-[420px] h-[420px] rounded-full bg-white/5 border border-white/10 backdrop-blur-lg animate-[spin_55s_linear_infinite] z-[1]"
    ></div>
    <!-- Orb B — bottom-right, largest, counter-spin -->
    <div
      class="absolute -bottom-36 -right-36 w-[520px] h-[520px] rounded-full bg-white/[0.07] border border-white/[0.08] backdrop-blur-md animate-[spin_70s_linear_infinite_reverse] z-[1]"
    ></div>
    <!-- Orb C — center-right, medium, gentle pulse -->
    <div
      class="absolute top-[18%] right-[12%] w-72 h-72 rounded-full bg-white/5 border border-white/10 backdrop-blur-lg animate-[pulse_10s_ease-in-out_infinite] z-[2]"
    ></div>
    <!-- Orb D — lower-left, small, slow float -->
    <div
      class="absolute bottom-[22%] left-[15%] w-48 h-48 rounded-full bg-white/10 border border-white/[0.12] backdrop-blur-md animate-[pulse_14s_ease-in-out_infinite] z-[2]"
    ></div>

    <!-- ══════════════════════════════════════════════════════════
         LAYER 2 — Ring geometry (concentric outlines)
         ══════════════════════════════════════════════════════════ -->
    <!-- Ring 1 — large, slow counter-spin -->
    <div
      class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full border border-white/[0.08] animate-[spin_90s_linear_infinite_reverse] z-[3]"
    ></div>
    <!-- Ring 2 — medium -->
    <div
      class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[420px] h-[420px] rounded-full border border-white/[0.12] animate-[spin_60s_linear_infinite] z-[3]"
    ></div>
    <!-- Ring 3 — small, dashed feel via double border -->
    <div
      class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full border border-dashed border-white/[0.10] animate-[spin_45s_linear_infinite_reverse] z-[3]"
    ></div>

    <!-- ══════════════════════════════════════════════════════════
         LAYER 3 — Animated Node network (SVG native animations)
         ══════════════════════════════════════════════════════════ -->
    <svg
      class="absolute inset-0 w-full h-full z-[4]"
      xmlns="http://www.w3.org/2000/svg"
      preserveAspectRatio="none"
    >
      <!-- Primary connections — breathing opacity -->
      <line x1="8%" y1="12%" x2="28%" y2="28%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.08;0.20;0.08"
          dur="6s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="28%" y1="28%" x2="52%" y2="16%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.06;0.16;0.06"
          dur="7s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="52%" y1="16%" x2="78%" y2="24%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.08;0.22;0.08"
          dur="5.5s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="78%" y1="24%" x2="88%" y2="48%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.06;0.18;0.06"
          dur="8s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="88%" y1="48%" x2="72%" y2="68%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.08;0.20;0.08"
          dur="6.5s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="72%" y1="68%" x2="48%" y2="62%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.06;0.16;0.06"
          dur="7.5s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="48%" y1="62%" x2="22%" y2="72%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.08;0.20;0.08"
          dur="5s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="22%" y1="72%" x2="12%" y2="88%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.06;0.15;0.06"
          dur="9s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="12%" y1="88%" x2="42%" y2="90%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.05;0.14;0.05"
          dur="7s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="42%" y1="90%" x2="72%" y2="82%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.06;0.16;0.06"
          dur="6s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="72%" y1="82%" x2="92%" y2="88%" stroke="white" stroke-width="0.8">
        <animate
          attributeName="stroke-opacity"
          values="0.05;0.14;0.05"
          dur="8.5s"
          repeatCount="indefinite"
        />
      </line>

      <!-- Cross links — slower breathing -->
      <line x1="28%" y1="28%" x2="48%" y2="62%" stroke="white" stroke-width="0.6">
        <animate
          attributeName="stroke-opacity"
          values="0.03;0.10;0.03"
          dur="10s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="52%" y1="16%" x2="72%" y2="68%" stroke="white" stroke-width="0.6">
        <animate
          attributeName="stroke-opacity"
          values="0.03;0.10;0.03"
          dur="11s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="8%" y1="12%" x2="22%" y2="72%" stroke="white" stroke-width="0.6">
        <animate
          attributeName="stroke-opacity"
          values="0.02;0.08;0.02"
          dur="12s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="78%" y1="24%" x2="72%" y2="82%" stroke="white" stroke-width="0.6">
        <animate
          attributeName="stroke-opacity"
          values="0.02;0.08;0.02"
          dur="13s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="48%" y1="62%" x2="42%" y2="90%" stroke="white" stroke-width="0.6">
        <animate
          attributeName="stroke-opacity"
          values="0.03;0.09;0.03"
          dur="9s"
          repeatCount="indefinite"
        />
      </line>
      <line x1="88%" y1="48%" x2="92%" y2="88%" stroke="white" stroke-width="0.6">
        <animate
          attributeName="stroke-opacity"
          values="0.03;0.09;0.03"
          dur="10s"
          repeatCount="indefinite"
        />
      </line>

      <!-- Amber primary nodes — pulsing radius + opacity -->
      <circle cx="8%" cy="12%" fill="#F0AD4E">
        <animate attributeName="r" values="3;5;3" dur="4s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.7;1;0.7"
          dur="4s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="28%" cy="28%" fill="#F0AD4E">
        <animate attributeName="r" values="4;6.5;4" dur="5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.65;0.95;0.65"
          dur="5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="52%" cy="16%" fill="#F0AD4E">
        <animate attributeName="r" values="2.5;4.5;2.5" dur="4.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.7;1;0.7"
          dur="4.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="78%" cy="24%" fill="#F0AD4E">
        <animate attributeName="r" values="3.5;5.5;3.5" dur="6s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.6;0.9;0.6"
          dur="6s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="88%" cy="48%" fill="#F0AD4E">
        <animate attributeName="r" values="3;5;3" dur="5.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.65;0.95;0.65"
          dur="5.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="72%" cy="68%" fill="#F0AD4E">
        <animate attributeName="r" values="4;6;4" dur="7s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.55;0.85;0.55"
          dur="7s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="48%" cy="62%" fill="#F0AD4E">
        <animate attributeName="r" values="2.5;4.5;2.5" dur="4s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.7;1;0.7"
          dur="4s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="22%" cy="72%" fill="#F0AD4E">
        <animate attributeName="r" values="3;5;3" dur="6.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.6;0.9;0.6"
          dur="6.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="12%" cy="88%" fill="#F0AD4E">
        <animate attributeName="r" values="2.5;4.5;2.5" dur="5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.65;0.95;0.65"
          dur="5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="42%" cy="90%" fill="#F0AD4E">
        <animate attributeName="r" values="3;5;3" dur="5.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.55;0.85;0.55"
          dur="5.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="72%" cy="82%" fill="#F0AD4E">
        <animate attributeName="r" values="2.5;4.5;2.5" dur="6s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.6;0.9;0.6"
          dur="6s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="92%" cy="88%" fill="#F0AD4E">
        <animate attributeName="r" values="3;5;3" dur="7s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.5;0.8;0.5"
          dur="7s"
          repeatCount="indefinite"
        />
      </circle>

      <!-- White secondary nodes — twinkling -->
      <circle cx="18%" cy="20%" fill="white">
        <animate attributeName="r" values="1;2.5;1" dur="5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.10;0.35;0.10"
          dur="5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="40%" cy="22%" fill="white">
        <animate attributeName="r" values="1;2;1" dur="6s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.08;0.28;0.08"
          dur="6s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="65%" cy="20%" fill="white">
        <animate attributeName="r" values="1;2.5;1" dur="4.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.10;0.32;0.10"
          dur="4.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="83%" cy="36%" fill="white">
        <animate attributeName="r" values="1;2;1" dur="7s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.08;0.28;0.08"
          dur="7s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="80%" cy="58%" fill="white">
        <animate attributeName="r" values="1;2.5;1" dur="5.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.10;0.35;0.10"
          dur="5.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="60%" cy="65%" fill="white">
        <animate attributeName="r" values="1;2;1" dur="6.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.08;0.28;0.08"
          dur="6.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="35%" cy="67%" fill="white">
        <animate attributeName="r" values="1;2.5;1" dur="5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.10;0.32;0.10"
          dur="5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="17%" cy="80%" fill="white">
        <animate attributeName="r" values="1;2;1" dur="8s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.06;0.25;0.06"
          dur="8s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="57%" cy="86%" fill="white">
        <animate attributeName="r" values="1;2;1" dur="6s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.08;0.28;0.08"
          dur="6s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="82%" cy="85%" fill="white">
        <animate attributeName="r" values="1;2.5;1" dur="7s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.10;0.32;0.10"
          dur="7s"
          repeatCount="indefinite"
        />
      </circle>

      <!-- Amber glow halos — expanding/contracting -->
      <circle cx="28%" cy="28%" fill="#F0AD4E">
        <animate attributeName="r" values="8;18;8" dur="5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.02;0.08;0.02"
          dur="5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="72%" cy="68%" fill="#F0AD4E">
        <animate attributeName="r" values="10;20;10" dur="7s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.01;0.07;0.01"
          dur="7s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="48%" cy="62%" fill="#F0AD4E">
        <animate attributeName="r" values="6;15;6" dur="6s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.02;0.08;0.02"
          dur="6s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="42%" cy="90%" fill="#F0AD4E">
        <animate attributeName="r" values="7;16;7" dur="5.5s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.01;0.07;0.01"
          dur="5.5s"
          repeatCount="indefinite"
        />
      </circle>
      <circle cx="88%" cy="48%" fill="#F0AD4E">
        <animate attributeName="r" values="8;17;8" dur="8s" repeatCount="indefinite" />
        <animate
          attributeName="fill-opacity"
          values="0.02;0.06;0.02"
          dur="8s"
          repeatCount="indefinite"
        />
      </circle>
    </svg>

    <!-- ══════════════════════════════════════════════════════════
         LAYER 4 — Floating decorative dots (amber micro-nodes)
         ══════════════════════════════════════════════════════════ -->
    <div
      class="absolute top-[8%] left-[42%] w-2 h-2 rounded-full bg-[#F0AD4E]/60 animate-[float-up_8s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[35%] left-[8%] w-1.5 h-1.5 rounded-full bg-[#F0AD4E]/50 animate-[float-down_12s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[55%] left-[55%] w-2 h-2 rounded-full bg-[#F0AD4E]/40 animate-[float-up_9s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[75%] left-[82%] w-1.5 h-1.5 rounded-full bg-[#F0AD4E]/55 animate-[float-down_11s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[42%] left-[75%] w-2 h-2 rounded-full bg-[#F0AD4E]/45 animate-[float-up_13s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[88%] left-[30%] w-1.5 h-1.5 rounded-full bg-[#F0AD4E]/50 animate-[float-down_10s_ease-in-out_infinite] z-[5]"
    ></div>

    <!-- Small white floating dots -->
    <div
      class="absolute top-[20%] left-[62%] w-1 h-1 rounded-full bg-white/20 animate-[float-up_15s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[65%] left-[25%] w-1 h-1 rounded-full bg-white/15 animate-[float-down_18s_ease-in-out_infinite] z-[5]"
    ></div>
    <div
      class="absolute top-[45%] left-[92%] w-1 h-1 rounded-full bg-white/20 animate-[float-up_16s_ease-in-out_infinite] z-[5]"
    ></div>

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
