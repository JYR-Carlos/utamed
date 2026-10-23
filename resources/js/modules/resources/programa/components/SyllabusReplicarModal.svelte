<script lang="ts">
  /**
   * SyllabusReplicarModal — Modal administrativo para replicar atómicamente el
   * syllabus vigente (y sus actividades) de un curso a sus cursos hermanos de la misma asignatura.
   */
  import { X, Copy, CheckSquare, Square, AlertCircle, Sparkles, BookOpen, Layers } from 'lucide-svelte';

  interface CursoDestino {
    id_curso: number;
    cod_curso: string;
    nombre: string;
    agno_real: number;
    semestre_real: number;
    periodo: string;
    tiene_programa: boolean;
    programa: {
      id_programa: number;
      version: number;
      estado: string;
      tipo_syllabus: string | null;
    } | null;
    total_actividades: number;
  }

  interface OrigenData {
    id_curso: number;
    cod_curso: string;
    nombre: string;
    asignatura: string;
    cod_asignatura: string;
    agno_real: number;
    semestre_real: number;
    programa: {
      id_programa: number;
      version: number;
      estado: string;
      tipo_syllabus: string | null;
    } | null;
    total_actividades: number;
  }

  interface Props {
    isOpen?: boolean;
    cursoId?: number | null;
    onClose?: () => void;
    onSuccess?: () => void;
  }

  let {
    isOpen = $bindable(false),
    cursoId = null,
    onClose = () => {},
    onSuccess = () => {},
  }: Props = $props();

  let loading = $state(false);
  let errorMsg = $state('');
  let replicando = $state(false);
  let replicarError = $state('');

  let origen = $state<OrigenData | null>(null);
  let destinos = $state<CursoDestino[]>([]);
  let selectedIds = $state<number[]>([]);
  let conActividades = $state(true);

  $effect(() => {
    if (isOpen && cursoId) {
      cargarDestinos(cursoId);
    } else {
      origen = null;
      destinos = [];
      selectedIds = [];
      errorMsg = '';
      replicarError = '';
    }
  });

  async function cargarDestinos(id: number) {
    loading = true;
    errorMsg = '';
    try {
      const res = await fetch(`/admin/cursos/${id}/syllabus/destinos-replicacion`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      origen = data.origen ?? null;
      destinos = data.destinos ?? [];
      // Por defecto pre-seleccionar todos los destinos que no tienen syllabus o todos
      selectedIds = destinos.map((d) => d.id_curso);
    } catch (e: any) {
      errorMsg = 'No se pudieron cargar los cursos hermanos de la asignatura.';
    } finally {
      loading = false;
    }
  }

  function toggleSelectAll() {
    if (selectedIds.length === destinos.length) {
      selectedIds = [];
    } else {
      selectedIds = destinos.map((d) => d.id_curso);
    }
  }

  function toggleDestino(id: number) {
    if (selectedIds.includes(id)) {
      selectedIds = selectedIds.filter((item) => item !== id);
    } else {
      selectedIds = [...selectedIds, id];
    }
  }

  async function ejecutarReplicacion() {
    if (!cursoId || selectedIds.length === 0) return;
    replicando = true;
    replicarError = '';

    try {
      const res = await fetch(`/admin/cursos/${cursoId}/syllabus/replicar`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        body: JSON.stringify({
          destinos: selectedIds,
          con_actividades: conActividades,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || data.error || 'Ocurrió un error al replicar el syllabus.');
      }

      isOpen = false;
      onSuccess();
    } catch (e: any) {
      replicarError = e.message || 'Error al replicar el syllabus.';
    } finally {
      replicando = false;
    }
  }

  function handleClose() {
    if (replicando) return;
    isOpen = false;
    onClose();
  }
</script>

{#if isOpen}
  <!-- Backdrop -->
  <!-- svelte-ignore a11y_interactive_supports_focus -->
  <div
    class="fixed inset-0 z-50 bg-gray-900/50 backdrop-blur-[1px]"
    role="button"
    aria-label="Cerrar modal"
    onclick={handleClose}
    onkeydown={(e) => {
      if (e.key === 'Escape') handleClose();
    }}
  ></div>

  <!-- Diálogo modal -->
  <div
    class="fixed inset-0 z-[60] flex items-center justify-center p-4 pointer-events-none"
    role="dialog"
    aria-modal="true"
    aria-labelledby="replicar-syllabus-title"
  >
    <div
      class="pointer-events-auto w-full max-w-xl max-h-[90dvh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-100"
    >
      <!-- Cabecera -->
      <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-gray-100 shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
            <Copy size={18} />
          </div>
          <div>
            <p class="text-[11px] font-semibold text-indigo-600 uppercase tracking-widest">
              Replicación de Syllabus
            </p>
            <h2 id="replicar-syllabus-title" class="text-base font-bold text-gray-900 leading-snug">
              {origen?.asignatura ?? 'Propagar a cursos hermanos'}
            </h2>
          </div>
        </div>
        <button
          onclick={handleClose}
          disabled={replicando}
          class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
          aria-label="Cerrar"
        >
          <X size={18} />
        </button>
      </div>

      <!-- Cuerpo con scroll -->
      <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
        {#if loading}
          <div class="flex flex-col items-center justify-center py-12 gap-3">
            <div class="w-8 h-8 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
            <span class="text-xs text-gray-500">Buscando cursos de la misma asignatura…</span>
          </div>
        {:else if errorMsg}
          <div class="flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
            <AlertCircle size={16} class="shrink-0 mt-0.5" />
            <div>
              <p class="font-semibold">Error al cargar cursos</p>
              <p>{errorMsg}</p>
            </div>
          </div>
        {:else if origen}
          <!-- Resumen curso fuente -->
          <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-gray-500 uppercase tracking-wider text-[10px]">Curso origen (fuente)</span>
              {#if origen.programa}
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Syllabus v{origen.programa.version} · {origen.programa.estado}
                </span>
              {/if}
            </div>
            <p class="font-bold text-gray-900 text-sm mt-1">{origen.nombre}</p>
            <div class="flex items-center gap-3 mt-1 text-gray-500">
              <span class="font-mono bg-white px-1.5 py-0.2 rounded border border-gray-200">{origen.cod_asignatura}</span>
              <span>{origen.agno_real} · Semestre {origen.semestre_real}</span>
              <span>· {origen.total_actividades} actividad{origen.total_actividades !== 1 ? 'es' : ''}</span>
            </div>
          </div>

          <!-- Selección de cursos destino -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-semibold text-gray-700">
                Cursos hermanos receptores ({destinos.length})
              </span>
              {#if destinos.length > 0}
                <button
                  type="button"
                  onclick={toggleSelectAll}
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition"
                >
                  {selectedIds.length === destinos.length ? 'Desmarcar todos' : 'Seleccionar todos'}
                </button>
              {/if}
            </div>

            {#if destinos.length === 0}
              <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl text-center text-xs text-gray-500 italic">
                No existen otros cursos registrados para la asignatura {origen.asignatura}.
              </div>
            {:else}
              <div class="space-y-2 max-h-52 overflow-y-auto pr-1">
                {#each destinos as d (d.id_curso)}
                  {@const isSelected = selectedIds.includes(d.id_curso)}
                  <button
                    type="button"
                    onclick={() => toggleDestino(d.id_curso)}
                    class="w-full text-left p-3 rounded-xl border transition flex items-center justify-between gap-3 text-xs {isSelected
                      ? 'border-indigo-400 bg-indigo-50/50 text-indigo-950'
                      : 'border-gray-200 bg-white hover:bg-gray-50 text-gray-700'}"
                  >
                    <div class="flex items-center gap-3">
                      <div class="text-indigo-600 shrink-0">
                        {#if isSelected}
                          <CheckSquare size={16} />
                        {:else}
                          <Square size={16} class="text-gray-400" />
                        {/if}
                      </div>
                      <div>
                        <p class="font-semibold text-gray-900">{d.nombre}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                          {d.periodo} · {d.total_actividades} actividad{d.total_actividades !== 1 ? 'es' : ''} existentes
                        </p>
                      </div>
                    </div>

                    <div class="shrink-0 text-right">
                      {#if d.tiene_programa}
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                          Reemplazará borrador (v{d.programa?.version ?? 1})
                        </span>
                      {:else}
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600 border border-gray-200">
                          Sin syllabus
                        </span>
                      {/if}
                    </div>
                  </button>
                {/each}
              </div>
            {/if}
          </div>

          <!-- Toggle de actividades -->
          <div class="p-3.5 rounded-xl border border-indigo-200 bg-indigo-50/30">
            <label class="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                bind:checked={conActividades}
                class="mt-1 rounded text-indigo-600 focus:ring-indigo-500 border-gray-300"
              />
              <div class="text-xs">
                <span class="font-semibold text-gray-900 block">
                  Replicar también las actividades evaluativas y rúbricas analíticas ({origen.total_actividades})
                </span>
                <span class="text-gray-600 text-[11px] leading-relaxed block mt-0.5">
                  Las actividades se asociarán a los componentes y unidades temáticas homólogas con fechas límite vacías.
                  Si el curso destino ya cuenta con alguna actividad con el mismo nombre, se conservará la existente.
                </span>
              </div>
            </label>
          </div>

          {#if replicarError}
            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
              {replicarError}
            </div>
          {/if}
        {/if}
      </div>

      <!-- Pie de acciones -->
      <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
        <button
          type="button"
          onclick={handleClose}
          disabled={replicando}
          class="px-4 py-2 text-xs font-semibold text-gray-600 hover:text-gray-800 transition"
        >
          Cancelar
        </button>

        <button
          type="button"
          onclick={ejecutarReplicacion}
          disabled={replicando || loading || selectedIds.length === 0}
          class="flex items-center gap-2 px-5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-xs"
        >
          {#if replicando}
            <div class="w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
            Replicando…
          {:else}
            <Copy size={13} />
            Replicar a {selectedIds.length} curso{selectedIds.length !== 1 ? 's' : ''}
          {/if}
        </button>
      </div>
    </div>
  </div>
{/if}
