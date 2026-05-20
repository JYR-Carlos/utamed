<script lang="ts">
  import BibliographyCard from './BibliographyCard.svelte';

  interface Bib {
    id_bib: number;
    titulo: string;
    autor: string;
    editorial: string;
    año: number;
    url?: string;
    tipo: 'utamed' | 'uta' | 'otro';
    unidad: string;
  }

  interface Props {
    filtered: Bib[];
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
