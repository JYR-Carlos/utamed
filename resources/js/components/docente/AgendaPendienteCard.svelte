<script lang="ts">
  /**
   * Interacciones de agenda.agenda (consultas de estudiantes sobre una
   * actividad) que esperan respuesta del docente. Canal distinto de la
   * mensajería de curso — ver Docente\DashboardController::agendaPendiente().
   */
  import { Link } from '@inertiajs/svelte';
  import { MessageCircle, ArrowUpRight } from 'lucide-svelte';
  import BloqueVacio from '@/components/docente/BloqueVacio.svelte';

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

<section class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
  <div class="flex items-center gap-2">
    <MessageCircle class="h-5 w-5 text-uta-blue" />
    <h3 class="text-base font-semibold text-ink">Agenda pendiente</h3>
  </div>
  <p class="text-[13px] leading-[18px] text-ink-2">Interacciones dentro de actividades que esperan tu respuesta</p>

  {#if items.length > 0}
    <div class="flex flex-col">
      {#each items as item, i}
        <Link
          href={item.id_curso ? `/docente/cursos/${item.id_curso}/mensajes` : '/docente/mensajes'}
          class="flex flex-col gap-0.5 py-[9px] no-underline {i < items.length - 1 ? 'border-b border-slate-100' : ''}"
        >
          <span class="text-[13px] leading-snug text-ink">{item.quien} · {item.actividad_nombre}</span>
          <span class="font-mono text-[11px] text-ink-2">
            {item.cod_curso}{#if item.fecha_envio} · {hace(item.fecha_envio)}{/if}
          </span>
        </Link>
      {/each}
    </div>
    <div class="flex items-center gap-2 border-t border-gray-200 pt-2.5">
      <span class="text-[12px] text-ink-2">{items.length} esperando respuesta</span>
      <Link
        href="/docente/mensajes"
        class="ml-auto flex items-center gap-1.5 rounded-lg px-2.5 py-[6px] text-[13px] font-semibold text-navy no-underline transition-colors hover:bg-slate-50"
      >
        Ver agenda
        <ArrowUpRight class="h-3.5 w-3.5" />
      </Link>
    </div>
  {:else}
    <BloqueVacio title="Sin consultas pendientes" description="Aparecerán cuando un estudiante escriba en una de tus actividades." />
  {/if}
</section>
