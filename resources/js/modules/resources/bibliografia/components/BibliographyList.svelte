<script lang="ts">
  /**
   * BibliographyList — Lista de bibliografías ya filtradas por el padre
   * (los filtros de tipo/unidad viven en la página).
   */
  import BibliographyCard from './BibliographyCard.svelte';
  import type { Bib } from '../types';

  interface Props {
    /** Bibliografías tras aplicar los filtros del padre. */
    filtered: Bib[];
    /** Etiqueta legible por tipo ('utamed' | 'uta' | 'otro'). */
    getTipoLabel: (tipo: string) => string;
  }

  let { filtered, getTipoLabel }: Props = $props();
</script>

<!-- Bibliographies List -->
<div class="flex flex-col gap-4">
  {#each filtered as bib (bib.id_bib)}
    <BibliographyCard {bib} {getTipoLabel} />
  {/each}

  {#if filtered.length === 0}
    <div class="text-center py-8">
      <p class="text-gray-500">No hay bibliografías que coincidan con los filtros</p>
    </div>
  {/if}
</div>
