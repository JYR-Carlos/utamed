<script lang="ts" module>
  export interface ActividadPorVencer {
    id_actividad: number;
    id_curso: number;
    curso: string;
    nombre: string;
    es_sumativa: boolean;
    fecha_limite: string;
    plazo_hasta: string | null;
  }
</script>

<script lang="ts">
  /**
   * Actividades cuyo plazo termina pronto. La ventana la decide el servidor
   * y no se menciona aquí. Cuando la actividad tiene plazo adicional, el
   * servidor manda la fecha ya calculada en `plazo_hasta` y es la que se
   * muestra.
   */
  import { Link } from '@inertiajs/svelte';
  import { CalendarClock, ChevronRight } from 'lucide-svelte';
  import { formatDate } from '@/utils/formatters';

  interface Props {
    items?: ActividadPorVencer[];
  }

  let { items = [] }: Props = $props();
</script>

<section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  <div class="flex items-center gap-2">
    <CalendarClock class="h-4 w-4 text-slate-500" />
    <h3 class="text-[15px] font-semibold text-slate-900">Próximas a vencer</h3>
    {#if items.length > 0}
      <span
        class="ml-auto shrink-0 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
      >
        {items.length}
      </span>
    {/if}
  </div>

  {#if items.length > 0}
    <div class="flex flex-col gap-2">
      {#each items as item (item.id_actividad)}
        <Link
          href={`/estudiante/cursos/${item.id_curso}/actividad/${item.id_actividad}`}
          class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 transition-colors hover:border-slate-300 hover:bg-slate-50"
        >
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="font-mono text-[10.5px] text-slate-500">
              {item.curso} · {item.es_sumativa ? 'Sumativa' : 'Formativa'}
            </span>
            <span class="truncate text-[13px] font-semibold text-slate-900">{item.nombre}</span>
            <span class="text-[12px] text-slate-600">
              {#if item.plazo_hasta}
                Plazo hasta {formatDate(item.plazo_hasta)}
              {:else}
                Vence el {formatDate(item.fecha_limite)}
              {/if}
            </span>
          </div>
          <ChevronRight class="h-4 w-4 shrink-0 text-slate-400" />
        </Link>
      {/each}
    </div>
  {:else}
    <div
      class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center"
    >
      <span class="text-[13px] font-semibold text-slate-900">Nada por vencer</span>
      <p class="text-[12px] text-slate-500">
        Aquí verás las actividades de tus cursos cuyo plazo está por terminar.
      </p>
    </div>
  {/if}
</section>
