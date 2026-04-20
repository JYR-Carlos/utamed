<script lang="ts">
  import { Link } from '@inertiajs/svelte';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    profesor: string;
    progreso: number;
    color: string;
    iconColor?: string;
  }

  let {
    id_curso,
    nombre,
    cod_curso,
    profesor,
    progreso,
    color,
    iconColor = 'text-white',
  }: Props = $props();
</script>

<Link
  href={`/estudiante/cursos/${id_curso}`}
  class="group relative overflow-hidden rounded-3xl bg-gradient-to-br {color} p-6 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 cursor-pointer h-full flex flex-col"
>
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

    <!-- Profesor -->
    <p class="text-sm text-white/80 mb-6 flex-grow leading-snug">
      {profesor}
    </p>

    <!-- Progress bar -->
    <div class="mt-auto">
      <div class="flex justify-between items-baseline mb-2">
        <span class="text-xs font-semibold text-white/80">Progreso</span>
        <span class="text-sm font-bold text-white">{progreso}%</span>
      </div>
      <div class="h-2 w-full bg-white/20 rounded-full overflow-hidden backdrop-blur-sm">
        <div
          class="h-full bg-white/90 rounded-full transition-all duration-500"
          style="width: {progreso}%"
        ></div>
      </div>
    </div>
  </div>
</Link>
