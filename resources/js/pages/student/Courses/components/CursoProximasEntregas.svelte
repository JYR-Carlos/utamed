<script lang="ts">
  /**
   * «Próximas entregas» del curso: las actividades con entrega que todavía no
   * vencen, de la más cercana a la más lejana. Antes era una franja dentro del
   * encabezado con sólo la primera; ahora es una sección propia (T26).
   *
   * Cada entrega es una tarjeta: etiqueta de tipo, nombre y, debajo, la fecha
   * con el plazo. En móvil las tarjetas se apilan y el nombre ocupa todo el
   * ancho; en escritorio se reparten en tres columnas.
   */
  import { Link } from '@inertiajs/svelte';
  import { CalendarClock } from 'lucide-svelte';
  import ChipActividad from '@/components/student/ChipActividad.svelte';
  import { diasHasta, etiquetaPlazo, formatFechaCorta } from '@/utils/formatters';

  interface Entrega {
    id_actividad: number;
    nombre: string;
    fecha_limite: string;
    es_sumativa: boolean;
  }

  interface Props {
    idCurso: number;
    entregas?: Entrega[];
  }

  let { idCurso, entregas = [] }: Props = $props();
</script>

{#if entregas.length > 0}
  <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
    {#each entregas.filter((e) => diasHasta(e.fecha_limite) >= 0) as entrega (entrega.id_actividad)}
      {@const dias = diasHasta(entrega.fecha_limite)}
      {@const urgente = dias <= 3}

      <li class="flex">
        <Link
          href={`/estudiante/cursos/${idCurso}/actividad/${entrega.id_actividad}`}
          class="flex w-full flex-col items-start gap-2 rounded-xl border border-slate-200 bg-white p-4 no-underline transition-colors hover:border-uta-blue/30 hover:bg-uta-blue-light/40"
        >
          <ChipActividad tipo={entrega.es_sumativa ? 'sumativa' : 'formativa'} />

          <span class="min-w-0 text-[15px] font-semibold leading-snug text-slate-900">
            {entrega.nombre}
          </span>

          <span class="mt-auto flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
            <CalendarClock
              class="h-4 w-4 shrink-0 {urgente ? 'text-amber-700' : 'text-slate-600'}"
              aria-hidden="true"
            />
            <span class="font-semibold text-slate-900">{formatFechaCorta(entrega.fecha_limite)}</span>
            <span class="text-slate-600" aria-hidden="true">·</span>
            <span class="font-bold {urgente ? 'text-amber-800' : 'text-uta-blue'}">
              {etiquetaPlazo(dias)}
            </span>
          </span>
        </Link>
      </li>
    {/each}
  </ul>
{:else}
  <div class="rounded-3xl border border-dashed border-gray-200 p-6 text-center">
    <p class="text-sm text-gray-600">No tienes entregas pendientes en este curso.</p>
  </div>
{/if}
