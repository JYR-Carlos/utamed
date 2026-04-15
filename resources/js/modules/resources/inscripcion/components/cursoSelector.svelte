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

<div class="max-w-[1100px]">
  <div class="mb-5">
    <h1 class="text-[1.625rem] font-bold text-gray-900 mb-0.5 mt-0">Inscripciones de Cursos</h1>
    <p class="text-[0.8125rem] text-gray-500 m-0">
      Selecciona un Curso Ofertado para gestionar su lista de alumnos.
    </p>
  </div>

  <div class="relative mb-5">
    <svg
      class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
      xmlns="http://www.w3.org/2000/svg"
      width="16"
      height="16"
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
      placeholder="Buscar curso por nombre, asignatura o carrera…"
      class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
    />
  </div>

  {#if filteredCursos.length === 0}
    <p class="text-gray-500 text-[0.9375rem]">
      {courseSearch ? `Sin resultados para «${courseSearch}».` : 'No hay cursos disponibles.'}
    </p>
  {:else}
    <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-3">
      {#each filteredCursos as c (c.id_curso)}
        <button
          type="button"
          class="text-left bg-white border border-gray-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 hover:shadow-md transition-all"
          onclick={() => onSelect(c.id_curso)}
        >
          <div class="flex justify-between items-center mb-1.5">
            <span class="text-xs font-mono font-semibold text-blue-600">{c.cod_curso}</span>
            {#if c.agno_real}
              <span class="text-xs text-gray-400">{c.agno_real} S{c.semestre_real}</span>
            {/if}
          </div>
          <h3 class="font-semibold text-gray-900 text-sm mb-1">{cursoDisplayName(c)}</h3>
          {#if c.carrera_nombre}
            <p class="text-xs text-gray-500 mb-2.5">{c.carrera_nombre}</p>
          {/if}
          <div class="flex items-center gap-1 text-xs font-medium text-blue-500 mt-2">
            Ver Nómina
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="13"
              height="13"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg
            >
          </div>
        </button>
      {/each}
    </div>
  {/if}
</div>
