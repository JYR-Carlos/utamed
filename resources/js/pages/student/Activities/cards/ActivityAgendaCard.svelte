<script lang="ts">
  import { MessageSquare, ArrowUpRight } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    listado_interacciones: Array<{
      id_interaccion: number;
      fecha_emision: string;
      tipo_interaccion: string;
      emisor: string;
      mensaje: string;
      es_de_docente: boolean;
      es_retroalimentacion: boolean;
      adjunta_rubrica: boolean;
      rubrica?: Rubrica | null;
      puntaje_obtenido?: number | null;
    }>;
    onAgendaClick: () => void;
  }

  let { listado_interacciones, onAgendaClick }: Props = $props();

  const ultimo = $derived(
    listado_interacciones.length > 0 ? listado_interacciones[listado_interacciones.length - 1] : null,
  );
</script>

<section class="flex flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
  <div class="flex items-center gap-2.5">
    <MessageSquare class="h-4 w-4 text-[#5A5E6E]" />
    <h3 class="text-[15px] font-semibold text-[#1A1A24]">Agenda de la actividad</h3>
    <button
      class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-transparent px-2 py-1.5 text-xs font-semibold text-[#22213F] transition-colors hover:bg-[#F8FAFC]"
      onclick={onAgendaClick}
    >
      Ir al hilo
      <ArrowUpRight class="h-3.5 w-3.5" />
    </button>
  </div>

  {#if ultimo}
    <div class="flex gap-3 rounded-lg border border-[#E5E7EB] bg-[#FCFBF9] p-3">
      <div
        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold
        {ultimo.es_de_docente ? 'bg-[#22213F]/10 text-[#22213F]' : 'bg-emerald-50 text-emerald-700'}"
      >
        {ultimo.es_de_docente ? 'D' : 'T'}
      </div>
      <div class="flex min-w-0 flex-col gap-0.5">
        <span class="text-[12.5px]">
          <span class="font-semibold text-[#1A1A24]">{ultimo.emisor}</span>
          <span class="text-[#5A5E6E]"> · {ultimo.tipo_interaccion}</span>
        </span>
        <p class="line-clamp-2 text-[13px] text-[#1A1A24]">{ultimo.mensaje}</p>
      </div>
    </div>
  {:else}
    <p class="text-center text-xs text-[#5A5E6E] py-3">
      No hay mensajes aún. Abre la agenda para enviar una consulta.
    </p>
  {/if}
</section>
