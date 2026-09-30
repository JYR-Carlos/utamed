<script lang="ts">
  /**
   * «Próximas entregas» del curso: las actividades con entrega que todavía no
   * vencen, de la más cercana a la más lejana. Antes era una franja dentro del
   * encabezado con sólo la primera; ahora es una sección propia (T26).
   */
  import { Link } from '@inertiajs/svelte';
  import { CalendarClock, ChevronRight } from 'lucide-svelte';
  import { formatFechaCorta, parseFechaSoloDia } from '@/utils/formatters';

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

  function diasRestantes(fecha: string): number {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    return Math.round((parseFechaSoloDia(fecha).getTime() - hoy.getTime()) / 86_400_000);
  }

  function plazoLabel(dias: number): string {
    if (dias === 0) return 'Vence hoy';
    if (dias === 1) return 'Vence mañana';
    return `Vence en ${dias} días`;
  }
</script>

{#if entregas.length > 0}
  <ul class="flex flex-col gap-3">
    {#each entregas as entrega (entrega.id_actividad)}
      {@const dias = diasRestantes(entrega.fecha_limite)}
      {@const urgente = dias <= 3}
      <li>
        <Link
          href={`/estudiante/cursos/${idCurso}/actividad/${entrega.id_actividad}`}
          class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl border p-4 no-underline transition-colors {urgente
            ? 'border-uta-red/20 bg-uta-red-light hover:border-uta-red/40'
            : 'border-gray-200 bg-white hover:border-uta-blue/30 hover:bg-uta-blue-light/40'}"
        >
          <CalendarClock class="w-4 h-4 shrink-0 {urgente ? 'text-uta-red' : 'text-gray-500'}" />
          <span class="min-w-0 flex-1 text-sm font-semibold text-gray-900">{entrega.nombre}</span>
          <span class="text-sm text-gray-500">{formatFechaCorta(entrega.fecha_limite)}</span>
          <span class="text-sm font-semibold {urgente ? 'text-uta-red' : 'text-gray-700'}">
            {plazoLabel(dias)}
          </span>
          <ChevronRight class="w-4 h-4 shrink-0 text-gray-400" />
        </Link>
      </li>
    {/each}
  </ul>
{:else}
  <div class="rounded-3xl border border-dashed border-gray-200 p-6 text-center">
    <p class="text-sm text-gray-600">No tienes entregas pendientes en este curso.</p>
  </div>
{/if}
