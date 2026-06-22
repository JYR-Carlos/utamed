<script lang="ts">
  import type { CursoItem } from '../types/inscripcion.types';
  import { cursoDisplayName } from '../types/inscripcion.types';

  interface Props {
    cursos: CursoItem[];
    onSelect: (id: number) => void;
  }

  let { cursos, onSelect }: Props = $props();

  let courseSearch = $state('');

  const filteredCursos = $derived(
    courseSearch.trim().length === 0
      ? cursos
      : cursos.filter((c) => {
          const term = courseSearch.toLowerCase();
          return (
            c.cod_curso.toLowerCase().includes(term) ||
            (c.nombre ?? '').toLowerCase().includes(term) ||
            (c.asignatura_nombre ?? '').toLowerCase().includes(term) ||
            (c.carrera_nombre ?? '').toLowerCase().includes(term)
          );
        }),
  );
</script>

<div class="max-w-[1100px] space-y-7">
  <!-- Header -->
  <div>
    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Inscripciones de Cursos</h1>
    <p class="text-sm text-gray-500 mt-1">
      Selecciona un curso para gestionar su nómina de estudiantes.
    </p>
  </div>

  <!-- Search + count -->
  <div class="flex items-center gap-4">
    <div class="relative flex-1 max-w-md">
      <svg
        class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
        xmlns="http://www.w3.org/2000/svg"
        width="15"
        height="15"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      <input
        type="search"
        bind:value={courseSearch}
        placeholder="Buscar por código, nombre o carrera…"
        class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50 transition-shadow"
      />
    </div>
    <span class="text-sm text-gray-400 shrink-0 tabular-nums">
      {filteredCursos.length}
      curso{filteredCursos.length !== 1 ? 's' : ''}
    </span>
  </div>

  <!-- Empty state -->
  {#if filteredCursos.length === 0}
    <div class="flex flex-col items-center gap-3 py-20 text-center">
      <div
        class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-300"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="22"
          height="22"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M22 10v6M2 10l10-5 10 5-10 5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" />
        </svg>
      </div>
      <p class="text-gray-500 text-sm">
        {courseSearch ? `Sin resultados para «${courseSearch}».` : 'No hay cursos disponibles.'}
      </p>
    </div>
  {:else}
    <!-- Course grid -->
    <div class="grid grid-cols-[repeat(auto-fill,minmax(256px,1fr))] gap-4">
      {#each filteredCursos as c (c.id_curso)}
        <button
          type="button"
          class="group relative flex flex-col text-left bg-white border border-gray-200 rounded-2xl p-5 cursor-pointer overflow-hidden hover:border-blue-200 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
          onclick={() => onSelect(c.id_curso)}
        >
          <!-- Left accent bar -->
          <div
            class="absolute left-0 inset-y-0 w-[3px] bg-gradient-to-b from-blue-400 to-indigo-500 rounded-l-2xl"
          ></div>

          <div class="pl-3 flex flex-col h-full">
            <!-- Badges -->
            <div class="flex items-center gap-2 mb-3">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 text-blue-600 text-[0.6875rem] font-mono font-bold ring-1 ring-inset ring-blue-100"
              >
                {c.cod_curso}
              </span>
              {#if c.agno_real}
                <span class="text-[0.6875rem] text-gray-400 font-medium tabular-nums">
                  {c.agno_real} · S{c.semestre_real}
                </span>
              {/if}
            </div>

            <!-- Course name -->
            <p class="font-semibold text-sm text-gray-900 leading-snug flex-1 mb-1.5">
              {cursoDisplayName(c)}
            </p>

            <!-- Career -->
            {#if c.carrera_nombre}
              <p class="text-xs text-gray-400 leading-snug mb-3 line-clamp-2">{c.carrera_nombre}</p>
            {:else}
              <div class="mb-3"></div>
            {/if}

            <!-- Footer -->
            <div
              class="pt-3 border-t border-gray-100 flex items-center gap-1 text-xs font-semibold text-blue-500 group-hover:text-blue-600 transition-colors"
            >
              Ver Nómina
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="13"
                height="13"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="group-hover:translate-x-0.5 transition-transform duration-150"
              >
                <path d="m9 18 6-6-6-6" />
              </svg>
            </div>
          </div>
        </button>
      {/each}
    </div>
  {/if}
</div>
