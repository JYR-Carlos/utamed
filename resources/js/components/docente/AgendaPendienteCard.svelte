<script lang="ts">
  /**
   * Interacciones de agenda.agenda (consultas de estudiantes sobre una
   * actividad) que esperan respuesta del docente. Canal distinto de la
   * mensajería de curso — ver Docente\DashboardController::agendaPendiente().
   *
   * Cada ítem se lee sin abrir nada: quién, en qué actividad y grupo del curso,
   * y la primera línea de lo que escribió. El clic abre la agenda de ese grupo
   * dentro de la actividad, igual que la bandeja de mensajes.
   */
  import { Link } from '@inertiajs/svelte';
  import { MessageCircle, ArrowUpRight } from 'lucide-svelte';
  import BloqueVacio from '@/components/docente/BloqueVacio.svelte';
  import { tonoGrupo } from '@/utils/grupoColor';

  interface Item {
    quien: string;
    actividad_nombre: string;
    mensaje: string;
    id_curso: number;
    id_actividad: number;
    id_grupo: number;
    cod_curso: string;
    letra_grupo: string | null;
    fecha_envio: string | null;
  }

  interface Props {
    items?: Item[];
    /** Todos los que esperan respuesta; `items` trae sólo los más antiguos. */
    total?: number;
  }

  let { items = [], total = items.length }: Props = $props();

  function hace(fecha: string | null): string {
    if (!fecha) return '';
    const diffMs = Date.now() - new Date(fecha).getTime();
    const horas = Math.floor(diffMs / 3_600_000);
    if (horas < 1) return 'recién';
    if (horas < 24) return `hace ${horas} h`;
    return `hace ${Math.floor(horas / 24)} d`;
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
      {#each items as item, i (item.id_grupo)}
        <Link
          href="/docente/cursos/{item.id_curso}/actividades/{item.id_actividad}/evaluacion?grupo={item.id_grupo}"
          class="-mx-2 flex flex-col gap-1 rounded-lg px-2 py-2.5 no-underline transition-colors hover:bg-slate-50 {i <
          items.length - 1
            ? 'border-b border-slate-100'
            : ''}"
        >
          <span class="flex items-baseline gap-2">
            <span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">{item.quien}</span>
            {#if item.fecha_envio}
              <span class="shrink-0 text-xs text-ink-2">{hace(item.fecha_envio)}</span>
            {/if}
          </span>
          <span class="flex min-w-0 items-center gap-1.5 text-xs text-ink-2">
            {#if item.letra_grupo}
              <span class="shrink-0 rounded-full px-1.5 py-px font-semibold {tonoGrupo(item.letra_grupo).chip}">
                Grupo {item.letra_grupo}
              </span>
            {/if}
            <span class="truncate">{item.actividad_nombre}</span>
          </span>
          <span class="line-clamp-1 text-[13px] leading-snug text-slate-700">{item.mensaje}</span>
        </Link>
      {/each}
    </div>
    <div class="flex items-center gap-2 border-t border-gray-200 pt-2.5">
      <span class="text-[12px] text-ink-2">{total} esperando respuesta</span>
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
