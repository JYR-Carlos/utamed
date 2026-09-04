<script lang="ts">
  /**
   * Interacciones de agenda.agenda (consultas de estudiantes sobre una
   * actividad) que esperan respuesta del docente. Canal distinto de la
   * mensajería de curso — ver Docente\DashboardController::agendaPendiente().
   */
  import { Link } from '@inertiajs/svelte';
  import { MessageCircleQuestion, ArrowUpRight } from 'lucide-svelte';

  interface Item {
    quien: string;
    actividad_nombre: string;
    id_curso: number | null;
    cod_curso: string | null;
    fecha_envio: string | null;
  }

  interface Props {
    items?: Item[];
  }

  let { items = [] }: Props = $props();

  function hace(fecha: string | null): string {
    if (!fecha) return '';
    const diffMs = Date.now() - new Date(fecha).getTime();
    const horas = Math.floor(diffMs / 3_600_000);
    if (horas < 1) return 'recién';
    if (horas < 24) return `hace ${horas} ${horas === 1 ? 'hora' : 'horas'}`;
    const dias = Math.floor(horas / 24);
    return `hace ${dias} ${dias === 1 ? 'día' : 'días'}`;
  }
</script>

<section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
  <div class="flex items-center gap-2">
    <MessageCircleQuestion class="h-4 w-4 text-[#5A5E6E]" />
    <h3 class="text-base font-semibold text-[#1A1A24]">Agenda pendiente</h3>
  </div>
  <p class="text-[12.5px] text-[#5A5E6E]">
    Interacciones dentro de actividades que esperan tu respuesta. Canal distinto de la mensajería de curso.
  </p>

  {#if items.length > 0}
    <div class="flex flex-col">
      {#each items as item, i}
        <Link
          href={item.id_curso ? `/docente/cursos/${item.id_curso}/mensajes` : '/docente/mensajes'}
          class="flex flex-col gap-0.5 py-[9px] no-underline {i < items.length - 1 ? 'border-b border-[#F1F5F9]' : ''}"
        >
          <span class="text-[13px] leading-snug text-[#1A1A24]">{item.quien} · {item.actividad_nombre}</span>
          <span class="font-mono text-[11px] text-[#5A5E6E]">
            {item.cod_curso}{#if item.fecha_envio} · {hace(item.fecha_envio)}{/if}
          </span>
        </Link>
      {/each}
    </div>
    <div class="flex items-center gap-2 border-t border-[#E5E7EB] pt-2.5">
      <span class="text-[12px] text-[#5A5E6E]">{items.length} esperando respuesta</span>
      <Link
        href="/docente/mensajes"
        class="ml-auto flex items-center gap-1.5 rounded-lg px-2.5 py-[6px] text-[13px] font-semibold text-[#002F6C] no-underline transition-colors hover:bg-[#F8FAFC]"
      >
        Ver agenda
        <ArrowUpRight class="h-3.5 w-3.5" />
      </Link>
    </div>
  {:else}
    <div class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center">
      <span class="text-[13px] font-semibold text-[#1A1A24]">Sin consultas pendientes</span>
      <p class="text-[12px] text-[#5A5E6E]">Aparecerán cuando un estudiante escriba en una de tus actividades.</p>
    </div>
  {/if}
</section>
