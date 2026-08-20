<script lang="ts">
  /**
   * ReutilizarGruposModal — Modal para copiar grupos de una actividad grupal
   * anterior del mismo curso hacia la actividad actual.
   *
   * Flujo en dos pasos:
   * 1. Elegir la actividad origen (estado del padre, vía props/callbacks).
   * 2. Elegir qué grupos concretos copiar: se listan con sus integrantes
   *    (fetch propio del modal, igual que EntregasModal) porque no todos los
   *    grupos siguen intactos — algunos integrantes pueden haberse
   *    desinscrito o el grupo puede haberse desintegrado — y el docente debe
   *    decidir grupo por grupo, no de forma automática.
   *
   * Al copiar, sólo se traen los integrantes que siguen inscritos en el curso;
   * si un grupo no tiene ninguno vigente queda deshabilitado.
   */
  import { X, Users } from 'lucide-svelte';

  type ActividadOrigen = {
    id_actividad: number;
    nombre: string;
    fecha_limite: string | null;
    cantidad_grupos: number;
  };

  type IntegranteOrigen = {
    id_estudiante: number;
    nombre_completo: string;
    inscrito: boolean;
  };

  type GrupoOrigen = {
    grupo: number;
    integrantes: IntegranteOrigen[];
    cantidad_integrantes: number;
    cantidad_vigentes: number;
  };

  interface Props {
    idCurso: number;
    idActividad: number;
    /** Actividades grupales del curso con grupos ya conformados. */
    actividades: ActividadOrigen[];
    /** Id de la actividad origen seleccionada (estado del padre). */
    seleccionada: number | null;
    loading: boolean;
    error: string | null;
    onSeleccionar: (id: number) => void;
    onCopiar: (grupos: number[]) => void;
    onCerrar: () => void;
  }

  let {
    idCurso,
    idActividad,
    actividades,
    seleccionada,
    loading,
    error,
    onSeleccionar,
    onCopiar,
    onCerrar,
  }: Props = $props();

  let gruposOrigen = $state<GrupoOrigen[]>([]);
  let loadingGrupos = $state(false);
  let errorGrupos = $state<string | null>(null);
  let gruposSeleccionados = $state<Set<number>>(new Set());

  async function cargarGrupos(idOrigen: number) {
    gruposOrigen = [];
    gruposSeleccionados = new Set();
    errorGrupos = null;
    loadingGrupos = true;
    try {
      const res = await fetch(
        `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos-origen/${idOrigen}`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error(`Error ${res.status}`);
      gruposOrigen = await res.json();
    } catch (e: any) {
      errorGrupos = e?.message ?? 'No se pudieron cargar los grupos de esta actividad.';
    } finally {
      loadingGrupos = false;
    }
  }

  $effect(() => {
    if (seleccionada !== null) {
      cargarGrupos(seleccionada);
    } else {
      gruposOrigen = [];
      gruposSeleccionados = new Set();
    }
  });

  function toggleGrupo(id: number, habilitado: boolean) {
    if (!habilitado) return;
    const next = new Set(gruposSeleccionados);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    gruposSeleccionados = next;
  }
</script>

<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg flex flex-col max-h-[90vh]">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b">
      <h3 class="text-base font-bold text-gray-900">Reutilizar grupos anteriores</h3>
      <button onclick={onCerrar} class="p-1.5 rounded-full hover:bg-gray-100 transition">
        <X class="w-4 h-4 text-gray-500" />
      </button>
    </div>

    <!-- Body -->
    <div class="flex-1 overflow-y-auto px-6 py-4">
      {#if actividades.length === 0}
        <p class="text-sm text-gray-500 italic text-center py-6">
          No hay otras actividades grupales de este curso con grupos ya formados.
        </p>
      {:else}
        <!-- Paso 1: actividad origen -->
        <p class="text-xs text-gray-500 mb-3">
          1. Elige la actividad de la que se copiarán los grupos.
        </p>
        <div class="flex flex-col gap-2 mb-4">
          {#each actividades as a (a.id_actividad)}
            <label
              class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer hover:bg-gray-50 transition {seleccionada ===
              a.id_actividad
                ? 'border-uta-blue bg-uta-blue/5'
                : 'border-gray-200'}"
            >
              <input
                type="radio"
                name="actividad_origen"
                checked={seleccionada === a.id_actividad}
                onchange={() => onSeleccionar(a.id_actividad)}
                class="accent-[#002855] w-4 h-4 shrink-0"
              />
              <span class="flex-1 text-sm text-gray-800">{a.nombre}</span>
              <span class="text-xs text-gray-400 shrink-0">
                {a.cantidad_grupos} grupo{a.cantidad_grupos !== 1 ? 's' : ''}
              </span>
            </label>
          {/each}
        </div>

        <!-- Paso 2: grupos concretos a copiar -->
        {#if seleccionada !== null}
          <p class="text-xs text-gray-500 mb-3">
            2. Elige los grupos a copiar. Sólo se traen los integrantes que siguen inscritos en
            el curso.
          </p>

          {#if loadingGrupos}
            <div class="flex flex-col gap-2 animate-pulse">
              {#each [1, 2, 3] as _}
                <div class="h-14 rounded-xl bg-gray-100"></div>
              {/each}
            </div>
          {:else if errorGrupos}
            <p class="text-xs text-red-600 py-3">{errorGrupos}</p>
          {:else if gruposOrigen.length === 0}
            <p class="text-sm text-gray-500 italic text-center py-4">
              Esta actividad no tiene grupos.
            </p>
          {:else}
            <div class="flex flex-col gap-2">
              {#each gruposOrigen as g (g.grupo)}
                {@const habilitado = g.cantidad_vigentes > 0}
                {@const completo = g.cantidad_vigentes === g.cantidad_integrantes}
                <label
                  class="flex items-start gap-3 p-2.5 rounded-xl border transition {habilitado
                    ? 'cursor-pointer hover:bg-gray-50'
                    : 'opacity-50 cursor-not-allowed'} {gruposSeleccionados.has(g.grupo)
                    ? 'border-uta-blue bg-uta-blue/5'
                    : 'border-gray-200'}"
                >
                  <input
                    type="checkbox"
                    checked={gruposSeleccionados.has(g.grupo)}
                    disabled={!habilitado}
                    onchange={() => toggleGrupo(g.grupo, habilitado)}
                    class="accent-[#002855] w-4 h-4 shrink-0 mt-0.5"
                  />
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="text-sm font-medium text-gray-800 flex items-center gap-1">
                        <Users class="w-3.5 h-3.5 text-gray-400" />
                        Grupo #{g.grupo}
                      </span>
                      <span
                        class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {completo
                          ? 'bg-green-100 text-green-700'
                          : habilitado
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-red-100 text-red-700'}"
                      >
                        {g.cantidad_vigentes}/{g.cantidad_integrantes} vigente{g.cantidad_vigentes !==
                        1
                          ? 's'
                          : ''}
                      </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                      {#each g.integrantes as m, i}<span
                          class={m.inscrito ? '' : 'line-through text-gray-400'}
                          >{m.nombre_completo}</span
                        >{i < g.integrantes.length - 1 ? ', ' : ''}{/each}
                    </p>
                    {#if !habilitado}
                      <p class="text-[11px] text-red-500 mt-1">
                        Ningún integrante sigue inscrito en el curso.
                      </p>
                    {/if}
                  </div>
                </label>
              {/each}
            </div>
          {/if}
        {/if}
      {/if}

      {#if error}
        <p class="mt-3 text-xs text-red-600">{error}</p>
      {/if}
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between gap-3 px-6 py-4 border-t">
      <span class="text-xs text-gray-500">
        {gruposSeleccionados.size} grupo{gruposSeleccionados.size !== 1 ? 's' : ''} seleccionado{gruposSeleccionados.size !==
        1
          ? 's'
          : ''}
      </span>
      <div class="flex gap-2">
        <button
          onclick={onCerrar}
          class="px-4 py-2 text-sm border rounded-xl text-gray-600 hover:bg-gray-50 transition"
        >
          Cancelar
        </button>
        <button
          onclick={() => onCopiar([...gruposSeleccionados])}
          disabled={gruposSeleccionados.size === 0 || loading}
          class="px-4 py-2 text-sm font-semibold bg-uta-blue text-white rounded-xl hover:bg-uta-blue-hover transition-colors disabled:opacity-50"
        >
          {loading ? 'Copiando…' : 'Copiar grupos'}
        </button>
      </div>
    </div>
  </div>
</div>
