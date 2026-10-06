<script lang="ts">
  import { MessageSquare, ArrowUpRight } from 'lucide-svelte';
  import type { InteraccionItem } from '@/types/agenda';

  interface Props {
    listado_interacciones: InteraccionItem[];
    onAgendaClick: () => void;
  }

  let { listado_interacciones, onAgendaClick }: Props = $props();

  const ultimo = $derived(
    listado_interacciones.length > 0 ? listado_interacciones[listado_interacciones.length - 1] : null,
  );

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      onAgendaClick();
    }
  }
</script>

<div
  role="button"
  tabindex="0"
  onclick={onAgendaClick}
  onkeydown={handleKeydown}
  class="group flex cursor-pointer flex-col gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-5 shadow-sm transition-all duration-150 hover:border-[#C3CAD4] hover:shadow-md active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-[#22213F] focus:ring-offset-2"
>
  <div class="flex items-center gap-2.5">
    <MessageSquare class="h-4 w-4 text-[#5A5E6E] transition-colors group-hover:text-[#22213F]" />
    <h3 class="text-[15px] font-semibold text-[#1A1A24]">Agenda de la actividad</h3>
    <button
      type="button"
      class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-2.5 py-1 text-xs font-semibold text-[#1A1A24] transition-colors group-hover:border-[#22213F] group-hover:text-[#22213F]"
      onclick={(e) => {
        e.stopPropagation();
        onAgendaClick();
      }}
    >
      Abrir agenda
      <ArrowUpRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
    </button>
  </div>

  {#if ultimo}
    <div class="flex gap-3 rounded-lg border border-[#E5E7EB] bg-[#FCFBF9] p-3 transition-colors group-hover:bg-[#F9F7F4]">
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
    <p class="py-3 text-center text-xs text-[#5A5E6E]">
      No hay mensajes aún. Haz clic para abrir la agenda y enviar una consulta.
    </p>
  {/if}
</div>
