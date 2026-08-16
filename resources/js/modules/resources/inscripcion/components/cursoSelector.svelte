<script lang="ts">
  /**
   * cursoSelector — Paso 1 del flujo de inscripciones: elegir el curso cuya
   * nómina se va a gestionar.
   *
   * Era la única vista en tarjetas de todo el panel: 219 cursos en una
   * rejilla sin orden ni filtros, con el centro de cada tarjeta vacío y sin
   * el único dato por el que se elige un curso aquí —cuántos estudiantes
   * tiene inscritos—. Ahora es una tabla como las demás, con ese dato y con
   * filtro por carrera.
   */
  import type { CursoItem } from '../types/inscripcion.types';
  import { cursoDisplayName } from '../types/inscripcion.types';

  interface Props {
    /** Todos los cursos administrables (sin paginar; se filtra en cliente). */
    cursos: CursoItem[];
    /** Selección de curso: el padre muestra su roster. */
    onSelect: (id: number) => void;
  }

  let { cursos, onSelect }: Props = $props();

  let courseSearch = $state('');
  let carreraFiltro = $state('');

  const carreras = $derived(
    [...new Set(cursos.map((c) => c.carrera_nombre).filter(Boolean))].sort() as string[],
  );

  const filteredCursos = $derived(
    cursos.filter((c) => {
      if (carreraFiltro && c.carrera_nombre !== carreraFiltro) return false;
      const term = courseSearch.trim().toLowerCase();
      if (!term) return true;
      return (
        c.cod_curso.toLowerCase().includes(term) ||
        (c.nombre ?? '').toLowerCase().includes(term) ||
        (c.asignatura_nombre ?? '').toLowerCase().includes(term) ||
        (c.carrera_nombre ?? '').toLowerCase().includes(term)
      );
    }),
  );
</script>

<div class="space-y-6">
  <header>
    <h1 class="page-title">Inscripciones</h1>
    <p class="page-subtitle">Elige un curso para gestionar su nómina de estudiantes.</p>
  </header>

  <!-- Filtros -->
  <div class="flex flex-wrap items-center gap-3">
    <div class="relative flex-1 min-w-[240px] max-w-md">
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
        aria-hidden="true"
      >
        <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
      <input
        type="search"
        bind:value={courseSearch}
        placeholder="Buscar por código, nombre o carrera…"
        class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50 transition"
      />
    </div>

    <select
      bind:value={carreraFiltro}
      class="px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:border-blue-400"
    >
      <option value="">Todas las carreras</option>
      {#each carreras as carrera}
        <option value={carrera}>{carrera}</option>
      {/each}
    </select>

    <span class="text-sm text-gray-500 shrink-0 tabular-nums ml-auto">
      {filteredCursos.length}
      curso{filteredCursos.length !== 1 ? 's' : ''}
    </span>
  </div>

  <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
              >Curso</th
            >
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
              >Carrera</th
            >
            <th
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 whitespace-nowrap"
              >Periodo</th
            >
            <th
              class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 whitespace-nowrap"
              >Inscritos</th
            >
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          {#if filteredCursos.length === 0}
            <tr>
              <td colspan="5" class="px-4 py-14 text-center text-gray-400 text-sm">
                {courseSearch || carreraFiltro
                  ? 'Ningún curso coincide con el filtro.'
                  : 'No hay cursos disponibles.'}
              </td>
            </tr>
          {:else}
            {#each filteredCursos as c (c.id_curso)}
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-900 leading-snug">{cursoDisplayName(c)}</p>
                  <p class="text-xs text-gray-400 font-mono mt-0.5">{c.cod_curso}</p>
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {c.carrera_nombre ?? '—'}
                </td>
                <td class="px-4 py-3 text-gray-600 whitespace-nowrap tabular-nums">
                  {#if c.agno_real}
                    {c.agno_real} · Semestre {c.semestre_real}
                  {:else}
                    <span class="text-gray-400">Sin periodo</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right tabular-nums">
                  {#if (c.inscritos_count ?? 0) > 0}
                    <span class="font-semibold text-gray-900">{c.inscritos_count}</span>
                  {:else}
                    <span class="text-gray-400">0</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    type="button"
                    class="btn btn-neutral btn-sm"
                    onclick={() => onSelect(c.id_curso)}
                  >
                    Ver nómina
                  </button>
                </td>
              </tr>
            {/each}
          {/if}
        </tbody>
      </table>
    </div>
  </div>
</div>
