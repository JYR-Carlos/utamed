<script lang="ts">
  import { ChevronDown, ChevronRight } from 'lucide-svelte';

  interface Module {
    id: string;
    title: string;
    duration: string;
  }

  interface Unit {
    id: string;
    title: string;
    modules: Module[];
  }

  interface Props {
    units: Unit[];
    activeModuleId: string;
    onModuleClick: (moduleId: string) => void;
    courseName?: string;
    progress?: number;
  }

  let {
    units,
    activeModuleId,
    onModuleClick,
    courseName = 'Diseño de Interfaces Digitales',
    progress = 65,
  }: Props = $props();

  let expandedUnits = $state<Record<string, boolean>>(
    units.reduce((acc, unit) => ({ ...acc, [unit.id]: true }), {}),
  );

  function toggleUnit(unitId: string) {
    expandedUnits[unitId] = !expandedUnits[unitId];
  }

  function getUnitIndex(unitId: string): number {
    return units.findIndex((u) => u.id === unitId) + 1;
  }
</script>

<div class="h-screen w-80 border-r border-gray-200 flex flex-col">
  <!-- Header -->
  <div class="px-6 py-5 border-b border-gray-200">
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
      Programa del Curso
    </p>
    <h2 class="text-sm font-bold text-gray-900 leading-snug">{courseName}</h2>
  </div>

  <!-- Scrollable Content -->
  <div class="flex-1 overflow-y-auto">
    <div class="px-3 py-4 space-y-1">
      {#each units as unit (unit.id)}
        <!-- Unit Header -->
        <button
          onclick={() => toggleUnit(unit.id)}
          class="w-full flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 transition-colors text-left"
        >
          <div
            class="w-8 h-8 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0"
          >
            <span class="text-xs font-bold text-indigo-700">U{getUnitIndex(unit.id)}</span>
          </div>
          <span class="text-sm font-semibold text-gray-900 flex-1 leading-snug">{unit.title}</span>
          <ChevronDown
            class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0"
            style="transform: rotate({expandedUnits[unit.id] ? 0 : -90}deg)"
          />
        </button>

        <!-- Modules -->
        {#if expandedUnits[unit.id]}
          <div class="mb-2">
            {#each unit.modules as module (module.id)}
              {@const isActive = activeModuleId === module.id}
              <button
                onclick={() => onModuleClick(module.id)}
                class={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors text-left ${
                  isActive ? 'bg-indigo-50' : 'hover:bg-gray-50'
                }`}
              >
                <!-- Bullet indicator -->
                <div class="flex-shrink-0 w-5 flex items-center justify-center mt-0.5">
                  {#if isActive}
                    <div class="w-2.5 h-2.5 rounded-full bg-indigo-600"></div>
                  {:else}
                    <div class="w-2.5 h-2.5 rounded-full border-2 border-gray-300"></div>
                  {/if}
                </div>

                <!-- Title + Duration stacked -->
                <div class="flex-1 min-w-0">
                  <p
                    class={`text-xs font-semibold leading-snug ${isActive ? 'text-indigo-700' : 'text-gray-700'}`}
                  >
                    {module.title}
                  </p>
                  <p class="text-xs text-gray-400 mt-0.5">{module.duration}</p>
                </div>

                <!-- Chevron for active -->
                {#if isActive}
                  <ChevronRight class="w-4 h-4 text-indigo-400 flex-shrink-0" />
                {/if}
              </button>
            {/each}
          </div>
        {/if}
      {/each}
    </div>
  </div>

  <!-- Footer: Progress -->
  <div class="px-6 py-4 border-t border-gray-200">
    <div class="flex items-center justify-between mb-2">
      <span class="text-xs font-semibold text-gray-500">Progreso Total</span>
      <span class="text-xs font-bold text-indigo-600">{progress}%</span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-2">
      <div class="bg-indigo-600 h-2 rounded-full transition-all" style="width: {progress}%"></div>
    </div>
  </div>
</div>
