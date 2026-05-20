<script lang="ts">
  import ChevronLeft from 'lucide-svelte/icons/chevron-left';
  import {
    ChevronDown,
    Menu,
    X,
    BookOpen,
    ClipboardList
  } from 'lucide-svelte';
  import { slide } from 'svelte/transition';

  interface Unit {
    id: string;
    title: string;
  }

  interface Props {
    units: Unit[];
    activeModuleId: string;
    onModuleClick: (moduleId: string) => void;
    onBibliografiaClick?: () => void;
    courseName?: string;
  }

  let {
    units,
    activeModuleId,
    onModuleClick,
    onBibliografiaClick,
    courseName = 'Diseño de Interfaces Digitales',
  }: Props = $props();

  let isMobileMenuOpen = $state(false);
  let showActivities = $state(true);

  function toggleMenu() {
    isMobileMenuOpen = !isMobileMenuOpen;
  }

  function toggleActivities() {
    showActivities = !showActivities;
  }

  function handleUnitClick(unitId: string) {
    onModuleClick(unitId);

    if (window.innerWidth < 1024) {
      isMobileMenuOpen = false;
    }
  }
</script>

<div class="w-full lg:w-80 lg:h-screen bg-white border-b lg:border-b-0 lg:border-r border-gray-200 flex flex-col z-40 relative sm:fixed">

  <!-- Volver -->
  <button
    class="hidden lg:flex items-center px-6 py-4 bg-primary text-secondary hover:text-primary hover:bg-secondary transition-colors border-b border-gray-200"
    onclick={() => window.history.back()}
  >
    <ChevronLeft class="w-4 h-4 mr-2" />
    <span class="text-sm font-medium">Volver a Cursos</span>
  </button>

  <!-- Header -->
  <div class="p-4 lg:p-6 border-b border-gray-200 flex items-center justify-between bg-white">
    <div class="overflow-hidden">
      <p class="text-sm lg:text-base font-bold text-gray-900 truncate">
        {courseName}
      </p>
    </div>

    <button
      class="lg:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg"
      onclick={toggleMenu}
      aria-label="Menu"
    >
      {#if isMobileMenuOpen}
        <X size={24} />
      {:else}
        <Menu size={24} />
      {/if}
    </button>
  </div>

  <!-- Contenido -->
  <div class="{isMobileMenuOpen ? 'block' : 'hidden'} lg:block flex-1 overflow-y-auto bg-white transition-all">

    <!-- Dropdown Actividades -->
    <div class="p-4 border-b border-gray-100">
      <button
        class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-primary text-secondary hover:bg-secondary hover:text-primary transition-all font-medium"
        onclick={toggleActivities}
      >
        <div class="flex items-center gap-2">
          <ClipboardList class="w-4 h-4" />
          <span>Ver Actividades</span>
        </div>

        <ChevronDown
          class="w-4 h-4 transition-transform duration-300 {showActivities ? 'rotate-180' : ''}"
        />
      </button>
    </div>

    <!-- Lista desplegable -->
    {#if showActivities}
      <nav
        class="p-4"
        transition:slide={{ duration: 200 }}
      >
        <ul class="space-y-1">
          {#each units as unit}
            <li>
              <button
                class="w-full flex items-center justify-between px-3 py-2.5 text-left text-sm font-medium rounded-xl transition-all border-2
                {activeModuleId.includes(unit.id)
                  ? 'bg-blue-50 text-primary border-primary'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-transparent'}"
                onclick={() => handleUnitClick(unit.id)}
              >
                <span class="truncate">{unit.title}</span>
              </button>
            </li>
          {/each}
        </ul>
      </nav>
    {/if}

    <!-- Bibliografía -->
<div class="p-4 border-t border-gray-100">
  <button
    class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-primary text-secondary hover:bg-secondary hover:text-primary transition-all font-medium"
    onclick={onBibliografiaClick}
  >
    <div class="flex items-center gap-2">
      <BookOpen class="w-4 h-4" />
      <span>Ver Bibliografía</span>
    </div>

    <ChevronDown class="w-4 h-4 opacity-0" />
  </button>
</div>
  </div>
</div>