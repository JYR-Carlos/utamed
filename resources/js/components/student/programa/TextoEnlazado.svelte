<script lang="ts">
  /** Texto del programa con sus URLs y archivos convertidos en enlaces (guía dm). */
  import { segmentarTexto } from '@/modules/resources/programa/utils/syllabusTexto';

  let { texto }: { texto: string | null | undefined } = $props();
</script>

{#each segmentarTexto(texto) as seg, i (i)}
  {#if seg.href}
    <a
      href={seg.href}
      target="_blank"
      rel="noopener noreferrer"
      class="font-medium text-dm-enlace underline hover:text-dm-enlace-hover {seg.tipo === 'url' ? 'break-all' : ''}"
      >{seg.texto}</a
    >
  {:else}{seg.texto}{/if}
{/each}
