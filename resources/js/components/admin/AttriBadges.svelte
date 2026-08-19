<script lang="ts">
  /**
   * AttriBadges — Jornada, sede y modalidad de una carrera.
   *
   * Los tres son dimensiones distintas de la misma carrera, no estados, así
   * que comparten tratamiento: antes cada uno tenía su propio color
   * (violeta, celeste, verde) sin que la diferencia significara nada.
   *
   * Cuando faltan los tres, se dice qué falta en vez de pintar un guion:
   * son campos que se rellenan al editar la carrera.
   */
  interface Props {
    sede?: string;
    jornada?: string;
    modalidad?: string;
  }

  let { sede, jornada, modalidad }: Props = $props();

  const attributes = $derived([jornada, sede, modalidad].filter(Boolean) as string[]);
</script>

<div class="flex flex-wrap gap-1">
  {#each attributes as attr}
    <span
      class="inline-block px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px] font-medium leading-tight"
    >
      {attr}
    </span>
  {/each}
  {#if attributes.length === 0}
    <span class="text-gray-400 text-[11px]">Sin definir</span>
  {/if}
</div>
