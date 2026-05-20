<script lang="ts">
  import BibliographyList from "@/modules/resources/bibliografia/components/BibliographyList.svelte";

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
    id_curso: number;
    bibliografias?: Bib[];
  }

  let { id_curso, bibliografias = [] }: Props = $props();

  // Datos de ejemplo si no se proporcionan
  const defaultBibliografias: Bib[] = [
    {
      id_bib: 1,
      titulo: 'The Design of Everyday Things',
      autor: 'Don Norman',
      editorial: 'MIT Press',
      año: 2013,
      tipo: 'utamed',
      unidad: 'Unidad 1: Fundamentos de UX',
      url: 'https://example.com',
    },
    {
      id_bib: 2,
      titulo: 'Rocket Surgery Made Easy',
      autor: 'Steve Krug',
      editorial: 'New Riders',
      año: 2010,
      tipo: 'utamed',
      unidad: 'Unidad 2: Research y Análisis',
      url: 'https://example.com',
    },
    {
      id_bib: 3,
      titulo: 'Dont Make Me Think',
      autor: 'Steve Krug',
      editorial: 'New Riders',
      año: 2014,
      tipo: 'utamed',
      unidad: 'Unidad 1: Fundamentos de UX',
    },
    {
      id_bib: 4,
      titulo: 'Remote Research: Real Users, Real Contexts, Real Research',
      autor: 'Nate Bolt, Tony Tulathimutte',
      editorial: 'Rosenfeld Media',
      año: 2010,
      tipo: 'uta',
      unidad: 'Unidad 2: Research y Análisis',
      url: 'https://example.com',
    },
    {
      id_bib: 5,
      titulo: 'Prototyping: A Practitioner\'s Guide',
      autor: 'Todd Zaki Warfel',
      editorial: 'Rosenfeld Media',
      año: 2009,
      tipo: 'utamed',
      unidad: 'Unidad 3: Wireframing y Prototipos',
    },
  ];

  let bibilista = $state<Bib[]>(bibliografias.length > 0 ? bibliografias : defaultBibliografias);
  let filterTipo: string | null = $state(null);

  function toggleFilter(tipo: string) {
    filterTipo = filterTipo === tipo ? null : tipo;
  }

  function clearFilters() {
    filterTipo = null;
  }

  let filtered = $derived(
    bibilista.filter((b) => {
      if (filterTipo !== null && b.tipo !== filterTipo) return false;
      return true;
    }),
  );

  function getTipoLabel(tipo: string): string {
    const labels: Record<string, string> = {
      utamed: 'Recurso UTAMED',
      uta: 'Recurso UTA',
    
    };
    return labels[tipo] || tipo;
  }
</script>

<!-- Main Content -->
<div class="w-full md:flex-1">
  <p class="text-2xl font-bold text-gray-900 my-4 mx-6">Bibliografía</p>
  <div class="flex flex-col gap-4 mx-6">
    <!-- Filter Buttons -->
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
      <div
        class="flex gap-2 items-center overflow-x-auto pb-2 sm:pb-0 w-full sm:w-auto no-scrollbar"
      >
        <button
          class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterTipo ===
          'utamed'
            ? 'bg-blue-500 text-white border-blue-500'
            : 'bg-white text-gray-900 border-gray-300'}"
          onclick={() => toggleFilter('utamed')}
        >
          {'Ver Recursos UTAMED'}
        </button>

        <button
          class="whitespace-nowrap px-4 py-2 rounded-lg border transition-all min-w-40 {filterTipo ===
          'uta'
            ? 'bg-blue-500 text-white border-blue-500'
            : 'bg-white text-gray-900 border-gray-300'}"
          onclick={() => toggleFilter('uta')}
        >
          {'Ver Recursos UTA'}
        </button>
      </div>

      <button
        class="w-full sm:w-auto px-4 py-2 rounded-lg border border-gray-300 sm:ml-auto hover:bg-gray-100 text-sm"
        onclick={clearFilters}
      >
        Limpiar filtros
      </button>
    </div>

    <BibliographyList {filtered} {getTipoLabel} />
  </div>
</div>
