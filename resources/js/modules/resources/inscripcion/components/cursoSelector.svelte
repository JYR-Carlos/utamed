<script lang="ts">
  /**
   * cursoSelector — Paso 1 del flujo de inscripciones: elegir el curso cuya
   * nómina se va a gestionar.
   * Utiliza DataTable estandarizado con paginación en servidor y recarga parcial ultra rápida.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { CursoItem } from '../types/inscripcion.types';
  import { cursoDisplayName } from '../types/inscripcion.types';
  import { router, page } from '@inertiajs/svelte';
  import type { PaginatedResponse } from '@/types/admin.types';
  import { tonoGrupo, limpiarNombreGrupo } from '@/utils/grupoColor';

  interface Props {
    /** Cursos paginados (del servidor) o array */
    cursos: PaginatedResponse<CursoItem> | CursoItem[];
    carreras?: string[];
    /** Selección de curso: el padre muestra su roster. */
    onSelect: (id: number) => void;
  }

  let { cursos, carreras = [], onSelect }: Props = $props();

  let carreraFiltro = $derived(
    new URL($page.url, window.location.origin).searchParams.get('carrera') ?? '',
  );

  function handleCarreraChange(e: Event) {
    const val = (e.target as HTMLSelectElement).value;
    const url = new URL($page.url, window.location.origin);
    if (val) {
      url.searchParams.set('carrera', val);
    } else {
      url.searchParams.delete('carrera');
    }
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
      only: ['cursos'],
    });
  }

  const columns = [
    { key: 'curso', label: 'Curso' },
    { key: 'carrera_nombre', label: 'Carrera' },
    { key: 'periodo', label: 'Periodo' },
    { key: 'inscritos_count', label: 'Inscritos', class: 'text-right' },
    { key: 'acciones', label: '', class: 'text-right w-1 whitespace-nowrap' },
  ];
</script>

<div class="space-y-4">
  <header>
    <h1 class="page-title">Inscripciones</h1>
    <p class="page-subtitle">Elige un curso para gestionar su nómina de estudiantes.</p>
  </header>

  <DataTable
    data={cursos}
    {columns}
    only={['cursos']}
    searchPlaceholder="Buscar por código, nombre o carrera…"
    onRowClick={(c) => onSelect(c.id_curso)}
  >
    {#snippet filterSnippet()}
      <select
        value={carreraFiltro}
        onchange={handleCarreraChange}
        class="px-2.5 py-1.5 text-sm border border-gray-300 rounded-md bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400"
      >
        <option value="">Todas las carreras</option>
        {#each carreras as carrera}
          <option value={carrera}>{carrera}</option>
        {/each}
      </select>
    {/snippet}

    {#snippet cellSnippet({ item, column })}
      {#if column.key === 'curso'}
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <p class="font-medium text-gray-900 leading-snug">
              {limpiarNombreGrupo(cursoDisplayName(item), item.letra_grupo)}
            </p>
            {#if item.letra_grupo}
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {tonoGrupo(item.letra_grupo).chip}"
              >
                Grupo {item.letra_grupo}
              </span>
            {/if}
          </div>
          <p class="text-xs text-gray-400 font-mono mt-0.5">{item.cod_curso}</p>
        </div>
      {:else if column.key === 'carrera_nombre'}
        <span class="text-gray-600 text-sm">{item.carrera_nombre ?? '—'}</span>
      {:else if column.key === 'periodo'}
        {#if item.agno_real}
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-medium text-gray-800">
              {item.agno_real}
            </span>
            {#if item.semestre_real}
              <span class="text-xs text-gray-500">
                Semestre {item.semestre_real}
              </span>
            {/if}
          </div>
        {:else if item.semestre_real}
          <span class="text-sm text-gray-500">Semestre {item.semestre_real}</span>
        {:else}
          <span class="text-gray-400">—</span>
        {/if}
      {:else if column.key === 'inscritos_count'}
        <span class="text-sm tabular-nums">
          {#if (item.inscritos_count ?? 0) > 0}
            <span class="font-semibold text-gray-900">{item.inscritos_count}</span>
          {:else}
            <span class="text-gray-400">0</span>
          {/if}
        </span>
      {:else if column.key === 'acciones'}
        <button
          type="button"
          class="btn btn-neutral btn-sm"
          onclick={(e) => {
            e.stopPropagation();
            onSelect(item.id_curso);
          }}
        >
          Ver nómina
        </button>
      {/if}
    {/snippet}
  </DataTable>
</div>
