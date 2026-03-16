<script lang="ts">
  import { Link } from '@inertiajs/svelte';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    estudiantes: number;
    proximaSesion: string;
    color: string;
    iconColor?: string;
  }

  let {
    id_curso,
    nombre,
    cod_curso,
    estudiantes,
    proximaSesion,
    color,
    iconColor = 'text-white',
  }: Props = $props();
</script>

<Link
  href={`/ayudante/cursos/${id_curso}`}
  class="group relative overflow-hidden rounded-3xl bg-gradient-to-br {color} p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 cursor-pointer h-full flex flex-col"
>
  <!-- Overlay gradient -->
  <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent" />

  <!-- Content -->
  <div class="relative z-10 flex flex-col h-full">
    <!-- Header -->
    <div class="flex justify-between items-start mb-4">
      <div class="flex-1">
        <span
          class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white/20 text-white backdrop-blur-sm inline-block"
        >
          {cod_curso}
        </span>
      </div>
      <button
        onclick={(e) => e.preventDefault()}
        class="text-white/60 hover:text-white transition-colors p-1 ml-2"
        title="Más opciones"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2m0 7a1 1 0 110-2 1 1 0 010 2m0 7a1 1 0 110-2 1 1 0 010 2"
          />
        </svg>
      </button>
    </div>

    <!-- Title -->
    <h3 class="text-xl font-bold text-white mb-1 line-clamp-2 leading-tight">
      {nombre}
    </h3>

    <!-- Stats -->
    <div class="space-y-3 mt-6 flex-grow">
      <div class="flex items-center gap-2 text-sm text-white/80">
        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
          <path
            d="M9 9a3 3 0 000 6h6a3 3 0 000-6H9zm0 0V7a1 1 0 012 0v2h2V7a1 1 0 012 0v2h2V7a1 1 0 012 0v2a3 3 0 01-3 3H9a3 3 0 01-3-3V7a1 1 0 012 0v2z"
          />
        </svg>
        <span>{estudiantes} estudiantes</span>
      </div>

      <div class="flex items-start gap-2 text-sm text-white/80">
        <svg
          class="w-4 h-4 flex-shrink-0 mt-0.5"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
        <span class="line-clamp-2">{proximaSesion}</span>
      </div>
    </div>

    <!-- Footer action -->
    <div class="mt-4 pt-4 border-t border-white/20">
      <span class="text-xs font-semibold text-white/80">Haz clic para gestionar →</span>
    </div>
  </div>
</Link>
