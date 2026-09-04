<script lang="ts">
  /**
   * Próximas fechas límite de actividades en todos los cursos del docente
   * (titular o componente), desde agenda.actividad.fecha_limite — mismo
   * criterio que el calendario académico (/docente/calendario).
   */
  import { Link } from '@inertiajs/svelte';
  import { CalendarClock } from 'lucide-svelte';

  interface Item {
    id_actividad: number;
    nombre: string;
    id_curso: number | null;
    cod_curso: string | null;
    fecha_limite: string | null;
  }

  interface Props {
    items?: Item[];
  }

  let { items = [] }: Props = $props();
</script>

<section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
  <div class="flex items-center gap-2">
    <CalendarClock class="h-4 w-4 text-[#5A5E6E]" />
    <h3 class="text-base font-semibold text-[#1A1A24]">Próximas fechas límite</h3>
  </div>

  {#if items.length > 0}
    <div class="flex flex-col">
      {#each items as item, i (item.id_actividad)}
        <Link
          href={item.id_curso ? `/docente/cursos/${item.id_curso}/actividades` : '/docente/calendario'}
          class="flex items-start gap-2.5 py-[9px] no-underline {i < items.length - 1 ? 'border-b border-[#F1F5F9]' : ''}"
        >
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="text-[13.5px] font-medium leading-snug text-[#1A1A24]">{item.nombre}</span>
            {#if item.cod_curso}
              <span class="font-mono text-[11px] text-[#5A5E6E]">{item.cod_curso}</span>
            {/if}
          </div>
          <span class="shrink-0 text-[12.5px] font-semibold text-[#4A4E5C]">{item.fecha_limite}</span>
        </Link>
      {/each}
    </div>
  {:else}
    <div class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center">
      <span class="text-[13px] font-semibold text-[#1A1A24]">Sin fechas próximas</span>
      <p class="text-[12px] text-[#5A5E6E]">Aparecerán cuando tus cursos tengan actividades con fecha límite.</p>
    </div>
  {/if}
</section>
