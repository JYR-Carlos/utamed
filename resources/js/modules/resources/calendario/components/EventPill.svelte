<script lang="ts">
  /**
   * Píldora compacta que representa una actividad dentro de una celda de día.
   * Solo visualización: muestra el color del curso y el título de la actividad.
   */
  import type { CalendarEvento, CursoAccent } from '../types';
  import { Users } from 'lucide-svelte';

  interface Props {
    evento: CalendarEvento;
    accent: CursoAccent;
    onClick?: (evento: CalendarEvento) => void;
  }

  let { evento, accent, onClick }: Props = $props();
</script>

<button
  type="button"
  onclick={(e) => {
    e.stopPropagation();
    onClick?.(evento);
  }}
  class="group flex w-full items-center gap-1.5 rounded-md px-1.5 py-[3px] text-left text-[11px] font-medium leading-tight transition-all hover:brightness-95"
  style="background:{accent.soft};color:{accent.text};{evento.visible ? '' : 'opacity:.6;'}"
  title={evento.titulo}
>
  <span class="h-2 w-2 shrink-0 rounded-full" style="background:{accent.base}"></span>
  <span class="flex-1 truncate">{evento.titulo}</span>
  {#if evento.es_grupal}
    <Users size={11} class="shrink-0 opacity-70" />
  {/if}
</button>
