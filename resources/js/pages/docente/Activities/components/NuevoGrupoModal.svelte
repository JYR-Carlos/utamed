<script lang="ts">
  /**
   * NuevoGrupoModal — Modal para crear un grupo nuevo seleccionando estudiantes
   * libres (sin grupo asignado en la actividad). El estado de selección y la
   * llamada al backend viven en el padre (Index.svelte).
   */
  import { X } from 'lucide-svelte';

  type EstudianteInscrito = {
    id_estudiante: number;
    nombre_completo: string;
  };

  interface Props {
    /** Estudiantes sin grupo asignado, candidatos a integrar el nuevo grupo. */
    estudiantesLibres: EstudianteInscrito[];
    /** Máximo de integrantes permitido por grupo (opcional). */
    maxIntegrantes?: number;
    /** Conjunto de ids seleccionados (estado del padre). */
    seleccion: Set<number>;
    loading: boolean;
    error: string | null;
    onToggleSeleccion: (id: number) => void;
    onCrear: () => void;
    onCerrar: () => void;
  }

  let {
    estudiantesLibres,
    maxIntegrantes,
    seleccion,
    loading,
    error,
    onToggleSeleccion,
    onCrear,
    onCerrar,
  }: Props = $props();
</script>

<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md flex flex-col max-h-[90vh]">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 class="text-base font-bold text-gray-900">Crear nuevo grupo</h3>
      <button
        onclick={onCerrar}
        class="p-1.5 rounded-full hover:bg-gray-100 transition"
      >
        <X class="w-4 h-4 text-gray-500" />
      </button>
    </div>

    <!-- Body: lista de estudiantes libres -->
    <div class="flex-1 overflow-y-auto px-6 py-4">
      {#if maxIntegrantes}
        <p class="text-xs text-gray-500 mb-3">
          Máximo {maxIntegrantes} integrante{maxIntegrantes !== 1 ? 's' : ''} por grupo.
        </p>
      {/if}

      {#if estudiantesLibres.length === 0}
        <p class="text-sm text-gray-500 italic text-center py-6">
          Todos los estudiantes ya están asignados a un grupo.
        </p>
      {:else}
        <p class="text-xs text-gray-500 mb-3">
          Selecciona los estudiantes que conformarán este grupo:
        </p>
        <div class="flex flex-col gap-2">
          {#each estudiantesLibres as e}
            <label
              class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer hover:bg-gray-50 transition {seleccion.has(
                e.id_estudiante,
              )
                ? 'border-uta-blue bg-uta-blue/5'
                : 'border-gray-200'}"
            >
              <input
                type="checkbox"
                checked={seleccion.has(e.id_estudiante)}
                onchange={() => onToggleSeleccion(e.id_estudiante)}
                class="accent-[#002855] w-4 h-4 shrink-0"
              />
              <span class="text-sm text-gray-800">{e.nombre_completo}</span>
            </label>
          {/each}
        </div>
      {/if}

      {#if error}
        <p class="mt-3 text-xs text-red-600">{error}</p>
      {/if}
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between gap-3 px-6 py-4 border-t">
      <span class="text-xs text-gray-500"
        >{seleccion.size} seleccionado{seleccion.size !== 1 ? 's' : ''}</span
      >
      <div class="flex gap-2">
        <button
          onclick={onCerrar}
          class="px-4 py-2 text-sm border rounded-xl text-gray-600 hover:bg-gray-50 transition"
        >
          Cancelar
        </button>
        <button
          onclick={onCrear}
          disabled={seleccion.size === 0 || loading}
          class="px-4 py-2 text-sm font-semibold bg-uta-blue text-white rounded-xl hover:bg-uta-blue-hover transition-colors disabled:opacity-50"
        >
          {loading ? 'Creando…' : 'Crear Grupo'}
        </button>
      </div>
    </div>
  </div>
</div>
