<script lang="ts" module>
  import { porEntregar, type ActividadAlumno } from '@/lib/actividadAlumno';

  export type FiltroActividad = 'todas' | 'pendientes' | 'entregadas' | 'individuales' | 'grupales';

  export const FILTROS: ReadonlyArray<{ id: FiltroActividad; etiqueta: string }> = [
    { id: 'todas', etiqueta: 'Todas' },
    { id: 'pendientes', etiqueta: 'Por entregar' },
    { id: 'entregadas', etiqueta: 'Entregadas' },
    { id: 'individuales', etiqueta: 'Individuales' },
    { id: 'grupales', etiqueta: 'Grupales' },
  ];

  /** Un solo filtro a la vez: «Por entregar» son las que aún se pueden entregar. */
  export function coincideConFiltro(actividad: ActividadAlumno, filtro: FiltroActividad): boolean {
    switch (filtro) {
      case 'pendientes':
        return porEntregar(actividad);
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
   * La pone student/Courses/Show.svelte, que lleva el título y el conteo;
   * aquí viven los filtros y la lista. En escritorio es una tabla (actividad,
   * fecha límite, estado); en móvil cada actividad es una tarjeta con la fecha
   * y el estado en la última línea.
   */
  import { Link } from '@inertiajs/svelte';
  import { Check, ChevronRight, CircleAlert, CircleCheck, ClipboardList, Clock, Search, Upload } from 'lucide-svelte';
  import EstadoVacio from '@/components/student/dm/EstadoVacio.svelte';
  import { estadoDe, fechaLimiteTexto } from '@/lib/actividadAlumno';
  import { btnSecundario, etiqueta, pildora, tono } from '@/lib/dm';
  import { claveDia } from '@/utils/formatters';

  interface Props {
    id_curso: number;
    filtro: FiltroActividad;
    filtered: ActividadAlumno[];
    /** El curso tiene actividades de más de un componente: cada fila dice de cuál. */
    conComponente?: boolean;
    onSelectFiltro: (filtro: FiltroActividad) => void;
  }

  let { id_curso, filtro, filtered, conComponente = false, onSelectFiltro }: Props = $props();

  const hoy = claveDia();

  const SIN_RESULTADOS: Record<FiltroActividad, string> = {
    todas: '',
    pendientes: 'No tienes actividades por entregar en este curso.',
    entregadas: 'Todavía no has entregado actividades en este curso.',
    individuales: 'No hay actividades individuales en este curso.',
    grupales: 'No hay actividades grupales en este curso.',
  };

  const ICONOS = { ok: CircleCheck, info: Upload, reloj: Clock, alerta: CircleAlert } as const;

  // Columnas de la tabla de escritorio; la cabecera y las filas las comparten.
  const COLUMNAS = 'md:grid md:grid-cols-[minmax(0,1fr)_150px_200px_36px] md:items-center md:gap-4';

  function detalle(a: ActividadAlumno): string {
    return [conComponente ? a.componente_tipo : null, a.es_sumativa ? 'Sumativa' : 'Formativa', a.es_grupal ? 'Grupal' : 'Individual']
      .filter(Boolean)
      .join(' · ');
  }
</script>

<div class="flex flex-col gap-4">
  <!-- Filtros: uno a la vez; «Todas» los quita. En móvil la fila se desliza. -->
  <div
    role="group"
    aria-label="Filtrar actividades"
    class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] md:mx-0 md:flex-wrap md:overflow-visible md:px-0 md:pb-0 [&::-webkit-scrollbar]:hidden"
  >
    {#each FILTROS as f (f.id)}
      {@const activo = filtro === f.id}
      <button
        type="button"
        onclick={() => onSelectFiltro(f.id)}
        aria-pressed={activo}
        class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-semibold whitespace-nowrap transition-colors md:min-h-9 {activo
          ? 'border-dm-amarillo bg-dm-activo-suave text-dm-activo-texto'
          : 'border-dm-control bg-dm-tarjeta text-dm-ink hover:bg-dm-sutil'}"
      >
        {#if activo}<Check size={16} aria-hidden="true" />{/if}
        {f.etiqueta}
      </button>
    {/each}
  </div>

  {#if filtered.length === 0}
    {#if filtro !== 'todas'}
      <EstadoVacio
        icono={Search}
        titulo="Ninguna actividad coincide"
        texto="{SIN_RESULTADOS[filtro]} Prueba con otro filtro."
      >
        {#snippet accion()}
          <button type="button" class={btnSecundario} onclick={() => onSelectFiltro('todas')}>Quitar filtros</button>
        {/snippet}
      </EstadoVacio>
    {:else}
      <EstadoVacio
        icono={ClipboardList}
        titulo="Todavía no hay actividades"
        texto="Tu docente aún no publica actividades para este curso."
      />
    {/if}
  {:else}
    <div class="md:overflow-hidden md:rounded-xl md:border md:border-dm-linea md:bg-dm-tarjeta">
      <div aria-hidden="true" class="{COLUMNAS} {etiqueta} hidden border-b border-dm-linea bg-dm-sutil px-5 py-2.5 text-dm-ink-3">
        <span>Actividad</span><span>Fecha límite</span><span>Estado</span><span></span>
      </div>
      <ul class="flex flex-col gap-3 md:gap-0">
        {#each filtered as act (act.id_actividad)}
          {@const estado = estadoDe(act, hoy)}
          {@const Icono = estado.icono ? ICONOS[estado.icono] : null}
          {@const venceHoy = estado.tono === 'warn' && act.fecha_limite?.slice(0, 10) === hoy}
          <li class="md:border-b md:border-dm-linea md:last:border-b-0">
            <Link
              href={`/estudiante/cursos/${id_curso}/actividad/${act.id_actividad}`}
              class="{COLUMNAS} flex flex-col gap-2 rounded-xl border border-dm-linea bg-dm-tarjeta p-4 text-dm-ink no-underline hover:bg-dm-sutil md:min-h-18 md:rounded-none md:border-0 md:bg-transparent md:px-5 md:py-3"
            >
              <span class="flex min-w-0 flex-col gap-1">
                <span class="text-[13px] leading-[18px] text-dm-ink-3">{detalle(act)}</span>
                <span class="text-[17px] leading-[22px] font-semibold md:text-base">{act.nombre}</span>
              </span>
              <!-- En móvil fecha y estado comparten la última línea; en la tabla, cada uno su columna. -->
              <span class="flex flex-wrap items-center justify-between gap-2 md:contents">
                <span
                  class="inline-flex items-center gap-1.5 text-[13px] leading-[18px] tabular-nums md:text-sm {venceHoy
                    ? 'font-bold text-dm-warn'
                    : 'text-dm-ink-2'}"
                >
                  <Clock size={14} aria-hidden="true" class="md:hidden" />
                  {act.fecha_limite ? fechaLimiteTexto(act.fecha_limite, hoy) : 'Sin fecha'}
                </span>
                <span class="{pildora} {tono[estado.tono]} md:justify-self-start">
                  {#if Icono}<Icono size={14} aria-hidden="true" />{/if}{estado.texto}
                </span>
              </span>
              <ChevronRight size={18} aria-hidden="true" class="hidden text-dm-ink-3 md:block" />
            </Link>
          </li>
        {/each}
      </ul>
    </div>
  {/if}
</div>
