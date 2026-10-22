<script lang="ts">
  /**
   * Componente: Lista de Cursos (Admin)
   *
   * Utiliza DataTable estandarizado con:
   * - Buscador integrado con debounce
   * - Paginador deslizante integrado
   * - Segmented control de estado mediante filterSnippet
   * - Badges y formato enriquecido mediante cellSnippet
   * - Slide-over en fila (onRowClick) y menú kebab (onEdit, onDelete)
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { Curso, PaginatedResponse } from '../types/curso.types';
  import { router, page } from '@inertiajs/svelte';
  import { tonoGrupo, limpiarNombreGrupo } from '@/utils/grupoColor';

  interface Props {
    cursos?: PaginatedResponse<Curso>;
    status?: string;
    onStatusChange?: (status: string) => void;
    onManage?: (curso: Curso) => void;
    onEdit?: (curso: Curso) => void;
    onDelete?: (curso: Curso) => void;
    // Props opcionales para compatibilidad con Cursos.svelte
    searchTerm?: string;
    perPage?: number;
    paginationOptions?: readonly number[];
    onSearchChange?: (term: string) => void;
    onPerPageChange?: (value: number) => void;
    onPageChange?: (page: number) => void;
    onCreateNew?: () => void;
  }

  let {
    cursos = {
      data: [],
      current_page: 1,
      last_page: 1,
      per_page: 15,
      from: 0,
      to: 0,
      total: 0,
      links: [],
    },
    status = 'active',
    onStatusChange,
    onManage = () => {},
    onEdit = () => {},
    onDelete = () => {},
  }: Props = $props();

  const STATUS_SEGMENTS = [
    { label: 'Activos', value: 'active' },
    { label: 'Históricos', value: 'inactive' },
    { label: 'Todos', value: 'all' },
  ] as const;

  const columns = [
    { key: 'nombre', label: 'Asignatura' },
    { key: 'carrera_nombre', label: 'Carrera' },
    { key: 'periodo', label: 'Período' },
    { key: 'secciones', label: 'Secciones' },
    { key: 'programa_estado', label: 'Programa' },
  ];

  let activeStatus = $derived(
    new URL($page.url, window.location.origin).searchParams.get('status') ?? status,
  );

  function handleStatus(newStatus: string) {
    if (onStatusChange) {
      onStatusChange(newStatus);
    }
    const url = new URL($page.url, window.location.origin);
    if (newStatus === 'all') url.searchParams.delete('status');
    else url.searchParams.set('status', newStatus);
    url.searchParams.delete('page');
    router.get(url.pathname, Object.fromEntries(url.searchParams), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const PROGRAMA_BADGES: Record<string, { label: string; cls: string }> = {
    BORRADOR: { label: 'Borrador', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    BASICO_COMPLETO: { label: 'Básico', cls: 'bg-blue-50 text-blue-700 border-blue-200' },
    COMPLETO: { label: 'Completo', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    APROBADO: { label: 'Aprobado', cls: 'bg-green-50 text-green-700 border-green-200' },
    ENVIADO: { label: 'Enviado', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
  };
</script>

<DataTable
  data={cursos}
  {columns}
  rowClass={(item) => (item.es_plantilla ? 'bg-slate-100/75 hover:bg-slate-100 text-slate-800' : '')}
  onRowClick={onManage}
  onCustomAction={onManage}
  customActionLabel="Gestionar"
  {onEdit}
  {onDelete}
  only={['cursos']}
  searchPlaceholder="Buscar por nombre o código..."
>
  {#snippet filterSnippet()}
    <div class="flex items-center bg-gray-100 rounded-lg p-0.5 gap-px">
      {#each STATUS_SEGMENTS as seg}
        <button
          onclick={() => handleStatus(seg.value)}
          class="px-3.5 py-1.5 text-sm font-medium rounded-md transition-all cursor-pointer {activeStatus === seg.value
            ? 'bg-white text-gray-900 shadow-sm'
            : 'text-gray-500 hover:text-gray-700'}"
        >
          {seg.label}
        </button>
      {/each}
    </div>
  {/snippet}

  {#snippet cellSnippet({ item, column })}
    {#if column.key === 'nombre'}
      {@const nombreLimpio = limpiarNombreGrupo(item.nombre || item.asignatura_nombre || '(sin nombre)', item.letra_grupo)}
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <p class="text-gray-900 leading-snug font-normal">
            {nombreLimpio}
          </p>
          {#if item.letra_grupo}
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {tonoGrupo(item.letra_grupo).chip}"
            >
              Grupo {item.letra_grupo}
            </span>
          {/if}
        </div>
        <p class="text-xs text-gray-500 font-mono mt-0.5">{item.cod_curso}</p>
      </div>
    {:else if column.key === 'carrera_nombre'}
      <span class="text-gray-600 text-sm line-clamp-2">{item.carrera_nombre || '—'}</span>
    {:else if column.key === 'periodo'}
      {@const agno = item.agno_real ?? (item.fecha_inicio ? new Date(item.fecha_inicio).getFullYear() : (item.asignacionPlan?.agno_planificado && item.asignacionPlan.agno_planificado > 1900 ? item.asignacionPlan.agno_planificado : null))}
      {@const semestre = item.semestre_real ?? item.numero_semestre ?? item.asignacionPlan?.semestre_planificado}
      {#if agno}
        <div class="flex flex-col gap-0.5">
          <span class="text-sm font-medium text-gray-800">
            {agno}
          </span>
          {#if semestre}
            <span class="text-xs text-gray-500">
              Semestre {semestre}
            </span>
          {/if}
        </div>
      {:else if semestre}
        <div class="flex flex-col gap-0.5">
          {#if item.asignacionPlan?.agno_planificado}
            <span class="text-sm font-medium text-gray-800">
              Año {item.asignacionPlan.agno_planificado}
            </span>
          {/if}
          <span class="text-xs text-gray-500">
            Semestre {semestre}
          </span>
        </div>
      {:else}
        <span class="text-gray-400">—</span>
      {/if}
    {:else if column.key === 'secciones'}
      {@const count = item.componentes?.length ?? 0}
      {#if count > 0}
        <span
          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200"
        >
          {count === 1 ? '1 Sección' : `${count} Secciones`}
        </span>
      {:else}
        <span class="text-xs text-gray-400">Sin secciones</span>
      {/if}
    {:else if column.key === 'programa_estado'}
      {@const badge = PROGRAMA_BADGES[item.programa_estado]}
      {#if badge}
        <span
          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {badge.cls}"
        >
          {badge.label}
        </span>
      {:else}
        <span class="text-xs text-gray-400">—</span>
      {/if}
    {:else}
      {item[column.key] ?? '—'}
    {/if}
  {/snippet}
</DataTable>
