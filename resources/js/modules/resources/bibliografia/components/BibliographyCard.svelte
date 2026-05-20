<script lang="ts">
  import { ExternalLink, BookOpen, Users, Calendar } from 'lucide-svelte';

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
    bib: Bib;
    getTipoLabel: (tipo: string) => string;
  }

  let { bib, getTipoLabel }: Props = $props();
</script>

<div class="p-10 border border-gray-200 rounded-lg hover:shadow-md transition-shadow bg-white">
  <div class="flex flex-col gap-3">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1">
        <h3 class="text-lg font-semibold text-gray-900">{bib.titulo}</h3>
        <p class="text-sm text-gray-600">{bib.autor}</p>
      </div>
      <span
        class="inline-block px-3 py-1 rounded-full text-xs font-medium bg-primary text-secondary whitespace-nowrap"
      >
        {getTipoLabel(bib.tipo)}
      </span>
    </div>

    <!-- Metadata -->
    <div class="flex flex-wrap gap-4 text-sm text-gray-600">
      <div class="flex items-center gap-2">
        <BookOpen size={16} class="text-gray-400" />
        <span>{bib.editorial}</span>
      </div>
      <div class="flex items-center gap-2">
        <Calendar size={16} class="text-gray-400" />
        <span>{bib.año}</span>
      </div>
      <div class="flex items-center gap-2">
        <Users size={16} class="text-gray-400" />
        <span class="text-xs">{bib.unidad}</span>
      </div>
    </div>

    <!-- URL Link -->
    {#if bib.url}
      <div>
        <a
          href={bib.url}
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 text-sm text-primary hover:text-blue-800 font-medium"
        >
          Ver recurso
          <ExternalLink size={14} />
        </a>
      </div>
    {/if}
  </div>
</div>
