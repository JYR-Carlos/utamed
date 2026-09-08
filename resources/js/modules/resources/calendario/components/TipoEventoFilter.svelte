<script lang="ts">
  /**
   * Filtro por familia de marca. Complementa al filtro por curso: el color
   * responde "de qué curso es", esta casilla responde "qué clase de cosa es".
   */
  import { Check, ClipboardCheck, ClipboardList, Clock } from 'lucide-svelte';
  import type { FamiliaItem } from '../types';
  import { ETIQUETA } from '../utils/estilos';

  interface Props {
    /** Familias visibles ahora mismo. */
    familias: Set<FamiliaItem>;
    /** Total de marcas de cada familia en el período mostrado. */
    conteos: Record<FamiliaItem, number>;
    onToggle?: (familia: FamiliaItem) => void;
  }

  let { familias, conteos, onToggle }: Props = $props();

  const OPCIONES: { familia: FamiliaItem; etiqueta: string }[] = [
    { familia: 'ENTREGA', etiqueta: 'Fechas límite' },
    { familia: 'SESION', etiqueta: 'Clases (asistencia)' },
    { familia: 'HITO', etiqueta: 'Hitos de syllabus' },
  ];
</script>

<div class="flex flex-col gap-2.5 border-t border-[#E5E7EB] px-3.5 py-3.5">
  <span class={ETIQUETA}>Tipo de evento</span>

  {#each OPCIONES as opcion (opcion.familia)}
    {@const activo = familias.has(opcion.familia)}
    <button
      type="button"
      onclick={() => onToggle?.(opcion.familia)}
      class="flex items-center gap-2 text-left"
      aria-pressed={activo}
    >
      <span
        class="flex h-[15px] w-[15px] shrink-0 items-center justify-center rounded-[4px] border transition-colors"
        style={activo
          ? 'background:#002F6C;border-color:#002F6C'
          : 'background:#FFFFFF;border-color:#C9CDD6;border-width:1.5px'}
      >
        {#if activo}<Check size={11} color="#FFFFFF" />{/if}
      </span>

      {#if opcion.familia === 'ENTREGA'}
        <ClipboardCheck size={14} class="shrink-0" color="#5A5E6E" />
      {:else if opcion.familia === 'SESION'}
        <Clock size={14} class="shrink-0" color="#5A5E6E" />
      {:else}
        <ClipboardList size={14} class="shrink-0" color="#5A5E6E" />
      {/if}

      <span class="text-[12.5px] font-medium {activo ? 'text-[#1A1A24]' : 'text-[#8A8E9C]'}">
        {opcion.etiqueta}
      </span>
      <span class="ml-auto font-mono text-[11px] tabular-nums text-[#5A5E6E]">
        {conteos[opcion.familia]}
      </span>
    </button>
  {/each}
</div>
