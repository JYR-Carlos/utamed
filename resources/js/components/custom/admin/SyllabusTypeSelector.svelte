<script lang="ts">
  import { X, BookOpen, BookMarked, BookPlus } from 'lucide-svelte';
  import { fade, scale } from 'svelte/transition';

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    onSelect: (type: 'simplified' | 'combined' | 'complete') => void;
    existingSyllabusType?: 'BASICO' | 'COMPLETO' | null;
  }

  let { isOpen = $bindable(), onClose, onSelect, existingSyllabusType = null }: Props = $props();

  function handleSelect(type: 'simplified' | 'combined' | 'complete') {
    console.log('📤 SyllabusTypeSelector.handleSelect called with:', type);
    onSelect(type);
    console.log('📤 onSelect callback ejecutado, cerrando selector');
    onClose();
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') onClose();
  }

  // Determinar qué opciones mostrar
  const showOptions = $derived.by(() => {
    if (existingSyllabusType === 'BASICO') {
      // Si ya existe BASICO, solo mostrar "Continuar"
      return ['combined'];
    }
    // Si no existe programa, mostrar "Simplificado" y "Completo"
    return ['simplified', 'complete'];
  });

  const headerText = $derived.by(() => {
    if (existingSyllabusType === 'BASICO') {
      return {
        title: 'Continuar desarrollo del programa',
        subtitle: 'Amplía el programa básico actual con secciones adicionales',
      };
    }
    return {
      title: 'Crear Programa de Cátedra',
      subtitle: 'Selecciona el nivel de detalle deseado',
    };
  });
</script>

<svelte:window onkeydown={handleKeydown} />

{#if isOpen}
  <div
    class="fixed inset-0 bg-black/30 backdrop-blur-sm flex items-center justify-center z-50 p-4"
    transition:fade={{ duration: 150 }}
  >
    <div
      class="bg-white rounded-xl shadow-2xl max-w-2xl w-full"
      role="dialog"
      tabindex="0"
      onclick={(e) => e.stopPropagation()}
      onkeydown={(e) => {
        if (e.key === 'Escape') onClose();
        e.stopPropagation();
      }}
      transition:scale={{ duration: 200, opacity: 0.5 }}
    >
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-slate-200">
        <div>
          <h2 class="text-2xl font-bold text-slate-900">{headerText.title}</h2>
          <p class="text-sm text-slate-600 mt-1">{headerText.subtitle}</p>
        </div>
        <button onclick={onClose} class="text-slate-400 hover:text-slate-600 transition-colors">
          <X size={24} />
        </button>
      </div>

      <!-- Options -->
      <div class="p-6 space-y-4">
        {#if showOptions.includes('simplified')}
          <!-- Opción 1: Simplificada -->
          <button
            onclick={() => handleSelect('simplified')}
            class="w-full text-left p-6 rounded-lg border-2 border-slate-200 hover:border-blue-500 hover:bg-blue-50 transition-all group"
          >
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 mt-1">
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors"
                >
                  <BookMarked size={24} class="text-blue-600" />
                </div>
              </div>
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-slate-900 group-hover:text-blue-900">
                  1. Edición Simplificada
                </h3>
                <p class="text-sm text-slate-600 mt-2 group-hover:text-slate-700">
                  Completa solo los campos básicos y esenciales del programa. Ideal para una
                  creación rápida inicial.
                </p>
                <ul class="text-xs text-slate-500 mt-3 space-y-1 pl-4">
                  <li>• Información básica (asignatura, código, créditos)</li>
                  <li>• Presentación y estándares</li>
                  <li>• Competencias principales</li>
                </ul>
              </div>
              <div class="flex-shrink-0 text-slate-400 group-hover:text-blue-600">⟶</div>
            </div>
          </button>
        {/if}

        {#if showOptions.includes('combined')}
          <!-- Opción 2: Combinada (o "Continuar") -->
          <button
            onclick={() => handleSelect('combined')}
            class="w-full text-left p-6 rounded-lg border-2 border-slate-200 hover:border-amber-500 hover:bg-amber-50 transition-all group"
          >
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 mt-1">
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-lg bg-amber-100 group-hover:bg-amber-200 transition-colors"
                >
                  <BookOpen size={24} class="text-amber-600" />
                </div>
              </div>
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-slate-900 group-hover:text-amber-900">
                  {existingSyllabusType === 'BASICO'
                    ? '2. Continuar y completar'
                    : '2. Del Simplificado al Completo'}
                </h3>
                <p class="text-sm text-slate-600 mt-2 group-hover:text-slate-700">
                  {existingSyllabusType === 'BASICO'
                    ? 'Amplía el programa básico existente con secciones adicionales (III, IV, V, IX).'
                    : 'Continúa desde el programa simplificado y completalo con todos los detalles y aspectos. Mejor para desarrollo iterativo.'}
                </p>
                <ul class="text-xs text-slate-500 mt-3 space-y-1 pl-4">
                  {#if existingSyllabusType === 'BASICO'}
                    <li>• Reutiliza lo creado en el programa básico</li>
                    <li>• Agrega estándares, competencias, evaluaciones</li>
                    <li>• Transforma a programa completo</li>
                  {:else}
                    <li>• Reutiliza lo creado en la edición simplificada</li>
                    <li>• Agrega unidades, evaluaciones y recursos</li>
                    <li>• Refina competencias y capacidades</li>
                  {/if}
                </ul>
              </div>
              <div class="flex-shrink-0 text-slate-400 group-hover:text-amber-600">⟶</div>
            </div>
          </button>
        {/if}

        {#if showOptions.includes('complete')}
          <!-- Opción 3: Completa -->
          <button
            onclick={() => handleSelect('complete')}
            class="w-full text-left p-6 rounded-lg border-2 border-slate-200 hover:border-green-500 hover:bg-green-50 transition-all group"
          >
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 mt-1">
                <div
                  class="flex items-center justify-center h-12 w-12 rounded-lg bg-green-100 group-hover:bg-green-200 transition-colors"
                >
                  <BookPlus size={24} class="text-green-600" />
                </div>
              </div>
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-slate-900 group-hover:text-green-900">
                  3. Syllabus Completo
                </h3>
                <p class="text-sm text-slate-600 mt-2 group-hover:text-slate-700">
                  Crea el programa completo de una vez con todas las 9 secciones y detalles. Para
                  usuarios con experiencia.
                </p>
                <ul class="text-xs text-slate-500 mt-3 space-y-1 pl-4">
                  <li>• Todas las 9 secciones del programa</li>
                  <li>• Información completa y detallada</li>
                  <li>• Listo para aprobación</li>
                </ul>
              </div>
              <div class="flex-shrink-0 text-slate-400 group-hover:text-green-600">⟶</div>
            </div>
          </button>
        {/if}
      </div>

      <!-- Footer hint -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 rounded-b-xl">
        <p class="text-xs text-slate-600 text-center">
          Presiona <span class="font-medium">Esc</span> para cancelar
        </p>
      </div>
    </div>
  </div>
{/if}
