<script lang="ts" module>
  export type FiltroActividad = 'todas' | 'pendientes' | 'entregadas' | 'individuales' | 'grupales';

  export const FILTROS: ReadonlyArray<{ id: FiltroActividad; etiqueta: string }> = [
    { id: 'todas', etiqueta: 'Todas' },
    { id: 'pendientes', etiqueta: 'Pendientes' },
    { id: 'entregadas', etiqueta: 'Entregadas' },
    { id: 'individuales', etiqueta: 'Individuales' },
    { id: 'grupales', etiqueta: 'Grupales' },
  ];

  interface Filtrable {
    con_entrega: boolean;
    es_grupal: boolean;
    ya_entregada?: boolean;
  }

  /** Un solo filtro a la vez: «Pendientes» son las que tienen entrega y aún no la tienen. */
  export function coincideConFiltro(actividad: Filtrable, filtro: FiltroActividad): boolean {
    switch (filtro) {
      case 'pendientes':
        return actividad.con_entrega && !actividad.ya_entregada;
      case 'entregadas':
        return !!actividad.ya_entregada;
      case 'individuales':
        return !actividad.es_grupal;
      case 'grupales':
        return actividad.es_grupal;
      default:
        return true;
    }
  }
</script>

<script lang="ts">
  /**
   * Lista de actividades del curso para el alumno.
   *
   * Va después de la ficha del curso en student/Courses/Show.svelte, que es
   * quien pone el encabezado de la sección y el conteo; aquí viven los filtros
   * y las tarjetas.
   */
  import { Link } from '@inertiajs/svelte';
  import { Check, ClipboardList, Search } from 'lucide-svelte';
  import ActividadCard from '@/modules/resources/actividad/components/actividadCard.svelte';

  interface Actividad {
    id_actividad: number;
    nombre: string;
    es_sumativa: boolean;
    con_entrega: boolean;
    es_grupal: boolean;
    max_integrantes: number;
    fecha_limite: string;
    visible: boolean;
  }

  interface Props {
    id_curso: number;
    filtro: FiltroActividad;
    filtered: Actividad[];
    onSelectFiltro: (filtro: FiltroActividad) => void;
  }

  let { id_curso, filtro, filtered, onSelectFiltro }: Props = $props();

  const hayFiltro = $derived(filtro !== 'todas');

  const SIN_RESULTADOS: Record<Exclude<FiltroActividad, 'todas'>, string> = {
    pendientes: 'No tienes actividades pendientes de entrega en este curso.',
    entregadas: 'Todavía no has entregado actividades en este curso.',
    individuales: 'No hay actividades individuales en este curso.',
    grupales: 'No hay actividades grupales en este curso.',
  };
</script>

<div class="flex flex-col gap-4">
  <!-- Filtros: uno a la vez; «Todas» los quita. -->
  <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrar actividades">
    {#each FILTROS as f (f.id)}
      {@const activo = filtro === f.id}
      <button
        type="button"
        onclick={() => onSelectFiltro(f.id)}
        aria-pressed={activo}
        class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-4 text-sm transition-colors {activo
          ? 'bg-uta-blue font-semibold text-white'
          : 'border border-slate-300 bg-white font-medium text-slate-700 hover:bg-gray-50'}"
      >
        {#if activo}
          <Check class="h-4 w-4" aria-hidden="true" />
        {/if}
        {f.etiqueta}
      </button>
    {/each}
  </div>

  <!-- Listado -->
  {#if filtered.length === 0}
    <div
      class="flex flex-col items-center gap-3 rounded-xl border border-slate-200 bg-white px-6 py-8 text-center"
    >
      {#if hayFiltro}
        <div class="flex size-14 items-center justify-center rounded-full bg-gray-100">
          <Search class="h-6 w-6 text-slate-600" aria-hidden="true" />
        </div>
        <h3 class="text-lg font-bold text-slate-900">Ninguna actividad coincide</h3>
        <p class="text-sm text-slate-600">
          {SIN_RESULTADOS[filtro as Exclude<FiltroActividad, 'todas'>]} Prueba con otro filtro.
        </p>
        <!-- Único «Quitar filtros»: la fila de arriba ya no lo repite. -->
        <button
          type="button"
          onclick={() => onSelectFiltro('todas')}
          class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-uta-blue transition-colors hover:bg-gray-50"
        >
          Quitar filtros
        </button>
      {:else}
        <div class="flex size-14 items-center justify-center rounded-full bg-gray-100">
          <ClipboardList class="h-6 w-6 text-slate-600" aria-hidden="true" />
        </div>
        <h3 class="text-lg font-bold text-slate-900">Todavía no hay actividades</h3>
        <p class="text-sm text-slate-600">Tu docente aún no publica actividades para este curso.</p>
      {/if}
    </div>
  {:else}
    <ul class="flex flex-col gap-3">
      {#each filtered as act (act.id_actividad)}
        <li>
          <Link
            href={`/estudiante/cursos/${id_curso}/actividad/${act.id_actividad}`}
            class="block rounded-xl no-underline"
          >
            <ActividadCard actividad={act} />
          </Link>
        </li>
      {/each}
    </ul>
  {/if}
</div>
