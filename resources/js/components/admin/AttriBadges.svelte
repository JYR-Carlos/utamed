<script lang="ts">
  /**
   * Componente para mostrar badges de atributos
   * (Sede, Jornada, Modalidad, etc.)
   */
  import { CARRERA_ATTRIBUTE_COLORS } from '@/constants/admin';

  interface Props {
    sede?: string;
    jornada?: string;
    modalidad?: string;
  }

  let { sede, jornada, modalidad }: Props = $props();

  const attributes = [
    { label: sede, color: 'violet' },
    { label: jornada, color: 'sky' },
    { label: modalidad, color: 'emerald' },
  ].filter((attr) => attr.label);

  const colorClasses: Record<string, { bg: string; text: string; border: string }> = {
    violet: { bg: 'bg-violet-50', text: 'text-violet-700', border: 'border-violet-100' },
    sky: { bg: 'bg-sky-50', text: 'text-sky-700', border: 'border-sky-100' },
    emerald: { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-100' },
  };
</script>

<div class="flex flex-wrap gap-1">
  {#each attributes as attr}
    <span
      class="inline-block px-1.5 py-0.5 rounded {colorClasses[attr.color].bg} {colorClasses[attr.color]
        .text} text-[11px] font-medium border {colorClasses[attr.color].border} leading-tight"
    >
      {attr.label}
    </span>
  {/each}
  {#if attributes.length === 0}
    <span class="text-gray-300 text-[11px]">—</span>
  {/if}
</div>
