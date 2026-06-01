<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';
  import { router } from '@inertiajs/svelte';
  import RubricaView from '../../student/Activities/Agenda/Rubrica.svelte';
  import { X, Plus, Trash2, Eye, Pencil } from 'lucide-svelte';

  interface Props {
    rubrica?: Rubrica | null;
    idCurso: number;
    idActividad: number;
    onClose: () => void;
  }

  let { rubrica = null, idCurso, idActividad, onClose }: Props = $props();

  // ── Draft types ────────────────────────────────────────────────────────────
  type EscalaDraft = { _id: string; puntos: number | string; criterio: string };
  type NivelDraft = { _id: string; nombre: string; descripcion: string; escalas: EscalaDraft[] };
  type EscalaCalif = { _id: string; puntaje_minimo: number | string; evaluacion: string };

  function uid() {
    return Math.random().toString(36).slice(2, 10);
  }

  function makeEscalas(n: number): EscalaDraft[] {
    return Array.from({ length: n }, () => ({ _id: uid(), puntos: '', criterio: '' }));
  }

  function makeNivel(cols: number): NivelDraft {
    return { _id: uid(), nombre: '', descripcion: '', escalas: makeEscalas(cols) };
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  const initCols = rubrica?.niveles?.[0]?.escalas?.length ?? 3;

  function initNiveles(): NivelDraft[] {
    if (!rubrica?.niveles?.length) return [makeNivel(initCols)];
    return rubrica.niveles.map((n) => ({
      _id: uid(),
      nombre: n.nombre,
      descripcion: n.descripcion,
      escalas: n.escalas.map((e) => ({ _id: uid(), puntos: e.puntos, criterio: e.criterio })),
    }));
  }

  function initEscalaCalif(): EscalaCalif[] {
    const src = rubrica?.detalles_evaluacion?.escala_evaluacion;
    if (!src?.length) {
      return [
        { _id: uid(), puntaje_minimo: 60, evaluacion: 'Aprobado' },
        { _id: uid(), puntaje_minimo: 0, evaluacion: 'Reprobado' },
      ];
    }
    return src.map((e) => ({
      _id: uid(),
      puntaje_minimo: e.puntaje_minimo,
      evaluacion: e.evaluacion,
    }));
  }

  let numCols = $state(initCols);
  let niveles = $state<NivelDraft[]>(initNiveles());
  let escalaCal = $state<EscalaCalif[]>(initEscalaCalif());
  let tab = $state<'editor' | 'preview'>('editor');
  let saving = $state(false);
  let error = $state<string | null>(null);

  // ── Computed ──────────────────────────────────────────────────────────────
  const puntajeTotal = $derived(
    niveles.reduce((sum, n) => {
      const max = n.escalas.reduce((m, e) => Math.max(m, Number(e.puntos) || 0), 0);
      return sum + max;
    }, 0),
  );

  const rubricaPreview = $derived<Rubrica>({
    niveles: niveles.map((n) => ({
      id: n._id,
      nombre: n.nombre || '(criterio)',
      descripcion: n.descripcion || '',
      nro_escalas: n.escalas.length,
      puntaje_minimo: 0,
      puntaje_total: n.escalas.reduce((m, e) => Math.max(m, Number(e.puntos) || 0), 0),
      escalas: n.escalas.map((e) => ({
        id: e._id,
        puntos: Number(e.puntos) || 0,
        criterio: e.criterio || '',
      })),
    })),
    detalles_evaluacion: {
      puntaje_total: puntajeTotal,
      escala_evaluacion: escalaCal.map((e) => ({
        puntaje_minimo: Number(e.puntaje_minimo) || 0,
        evaluacion: e.evaluacion || '',
      })),
    },
  });

  // ── Mutations ─────────────────────────────────────────────────────────────
  function addNivel() {
    niveles = [...niveles, makeNivel(numCols)];
  }

  function removeNivel(id: string) {
    if (niveles.length <= 1) return;
    niveles = niveles.filter((n) => n._id !== id);
  }

  function addColumna() {
    numCols++;
    niveles = niveles.map((n) => ({
      ...n,
      escalas: [...n.escalas, { _id: uid(), puntos: '', criterio: '' }],
    }));
  }

  function removeColumna(idx: number) {
    if (numCols <= 1) return;
    numCols--;
    niveles = niveles.map((n) => ({ ...n, escalas: n.escalas.filter((_, i) => i !== idx) }));
  }

  function addEscalaCalif() {
    escalaCal = [...escalaCal, { _id: uid(), puntaje_minimo: '', evaluacion: '' }];
  }

  function removeEscalaCalif(id: string) {
    escalaCal = escalaCal.filter((e) => e._id !== id);
  }

  // ── Save ──────────────────────────────────────────────────────────────────
  function validate(): boolean {
    if (niveles.some((n) => !n.nombre.trim())) {
      error = 'Todos los criterios deben tener un nombre.';
      return false;
    }
    if (niveles.some((n) => n.escalas.some((e) => !e.criterio.trim()))) {
      error = 'Todas las celdas de la rúbrica deben tener una descripción.';
      return false;
    }
    error = null;
    return true;
  }

  function guardar() {
    if (!validate()) return;
    saving = true;
    router.post(
      `/docente/cursos/${idCurso}/rubrica`,
      { rubrica: rubricaPreview, id_actividad: idActividad },
      {
        onSuccess: () => {
          saving = false;
          onClose();
        },
        onError: () => {
          saving = false;
          error = 'Error al guardar la rúbrica.';
        },
      },
    );
  }
</script>

<!-- Full-screen overlay -->
<div class="fixed inset-0 z-[70] flex flex-col bg-white overflow-hidden">
  <!-- ── Header ── -->
  <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b bg-white shrink-0 gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <button
        onclick={onClose}
        class="p-1.5 rounded-full hover:bg-gray-100 transition shrink-0"
        title="Cerrar"
      >
        <X class="w-5 h-5 text-gray-500" />
      </button>
      <h2 class="text-sm sm:text-base font-bold text-gray-900 truncate">Crear Rúbrica</h2>

      <!-- Tabs -->
      <div class="flex gap-1 ml-2 bg-gray-100 rounded-lg p-1 shrink-0">
        <button
          onclick={() => (tab = 'editor')}
          class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-md transition {tab ===
          'editor'
            ? 'bg-white shadow text-primary'
            : 'text-gray-500 hover:text-gray-700'}"
        >
          <Pencil class="w-3 h-3" />
          Editor
        </button>
        <button
          onclick={() => (tab = 'preview')}
          class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-md transition {tab ===
          'preview'
            ? 'bg-white shadow text-primary'
            : 'text-gray-500 hover:text-gray-700'}"
        >
          <Eye class="w-3 h-3" />
          Vista Previa
        </button>
      </div>
    </div>

    <button
      onclick={guardar}
      disabled={saving}
      class="shrink-0 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:opacity-90 transition disabled:opacity-50"
    >
      {saving ? 'Guardando…' : 'Guardar Rúbrica'}
    </button>
  </div>

  {#if error}
    <div class="px-6 py-2 bg-red-50 border-b border-red-200 text-sm text-red-700 shrink-0">
      {error}
    </div>
  {/if}

  <!-- ── Body ── -->
  <div class="flex-1 overflow-auto">
    {#if tab === 'editor'}
      <div class="px-4 sm:px-8 py-6 max-w-[1400px] mx-auto">
        <!-- Resumen de puntaje -->
        <div
          class="mb-6 flex flex-wrap items-center gap-6 bg-primary/5 rounded-2xl px-6 py-4 border border-primary/10"
        >
          <div>
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Total</p>
            <p class="text-3xl font-black text-primary">{puntajeTotal} pts</p>
          </div>
          <p class="text-xs text-gray-500 max-w-xs">
            Suma automática del puntaje máximo de cada criterio. Ajusta los puntos en cada celda.
          </p>
        </div>

        <!-- Tabla editor -->
        <div class="overflow-x-auto rounded-3xl border-2 border-gray-100 mb-8">
          <table class="w-full border-collapse" style="min-width: {200 + numCols * 220}px">
            <thead>
              <tr class="bg-gray-50 border-b-2 border-gray-100">
                <th
                  class="px-5 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider border-r border-gray-100"
                  style="width:220px; min-width:200px"
                >
                  Criterio de Evaluación
                </th>
                {#each Array(numCols) as _, ci}
                  <th
                    class="px-5 py-4 text-center text-xs font-black text-gray-500 uppercase tracking-wider border-r border-gray-100 last:border-r-0"
                    style="min-width:200px"
                  >
                    <div class="flex items-center justify-between gap-2">
                      <span>Nivel {ci + 1}</span>
                      {#if numCols > 1}
                        <button
                          onclick={() => removeColumna(ci)}
                          class="p-0.5 text-gray-300 hover:text-red-400 transition rounded"
                          title="Eliminar nivel"
                        >
                          <Trash2 class="w-3 h-3" />
                        </button>
                      {/if}
                    </div>
                  </th>
                {/each}
                <th class="px-3 py-4 border-l border-gray-100" style="width:56px">
                  <button
                    onclick={addColumna}
                    title="Agregar nivel de desempeño"
                    class="inline-flex flex-col items-center gap-0.5 text-primary/50 hover:text-primary transition"
                  >
                    <Plus class="w-4 h-4" />
                    <span class="text-[9px] font-bold uppercase tracking-wide">Nivel</span>
                  </button>
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
              {#each niveles as nivel (nivel._id)}
                <tr class="hover:bg-gray-50/30 transition-colors group">
                  <!-- Criterio column -->
                  <td class="px-5 py-4 border-r border-gray-100 align-top bg-gray-50/50">
                    <div class="flex flex-col gap-2">
                      <input
                        type="text"
                        bind:value={nivel.nombre}
                        placeholder="Nombre del criterio"
                        class="w-full text-sm font-bold text-gray-800 bg-white border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20"
                      />
                      <textarea
                        bind:value={nivel.descripcion}
                        placeholder="Descripción (opcional)"
                        rows="2"
                        class="w-full text-xs text-gray-500 italic bg-white border border-gray-200 rounded-xl px-3 py-2 resize-none focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20"
                      ></textarea>
                      {#if niveles.length > 1}
                        <button
                          onclick={() => removeNivel(nivel._id)}
                          class="inline-flex items-center gap-1 text-[11px] text-red-400 hover:text-red-600 transition self-start opacity-0 group-hover:opacity-100"
                        >
                          <Trash2 class="w-3 h-3" />
                          Eliminar fila
                        </button>
                      {/if}
                    </div>
                  </td>

                  <!-- Escala columns -->
                  {#each nivel.escalas as escala (escala._id)}
                    <td class="px-5 py-4 border-r border-gray-100 last:border-r-0 align-top">
                      <div class="flex flex-col gap-2">
                        <input
                          type="number"
                          bind:value={escala.puntos}
                          placeholder="0"
                          min="0"
                          class="w-24 text-sm font-black text-primary bg-primary/5 border border-primary/20 rounded-xl px-3 py-2 focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20"
                        />
                        <textarea
                          bind:value={escala.criterio}
                          placeholder="Descripción del nivel de desempeño…"
                          rows="4"
                          class="w-full text-sm text-gray-600 bg-white border border-gray-200 rounded-xl px-3 py-2 resize-none focus:outline-none focus:border-primary/40 focus:ring-1 focus:ring-primary/20 leading-snug"
                        ></textarea>
                      </div>
                    </td>
                  {/each}

                  <td class="px-3 py-4 align-middle border-l border-gray-100"></td>
                </tr>
              {/each}

              <!-- Add row -->
              <tr>
                <td colspan={numCols + 2} class="px-5 py-3">
                  <button
                    onclick={addNivel}
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary/60 hover:text-primary transition"
                  >
                    <Plus class="w-4 h-4" />
                    Agregar criterio
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Escala de calificación -->
        <div class="bg-gray-50/50 rounded-3xl border border-gray-100 p-6">
          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">
            Escala de Calificación
          </p>
          <div class="flex flex-col gap-2 max-w-lg">
            {#each escalaCal as esc (esc._id)}
              <div class="flex items-center gap-3">
                <div
                  class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 py-2 w-36 shrink-0"
                >
                  <span class="text-xs text-gray-400 font-medium">≥</span>
                  <input
                    type="number"
                    bind:value={esc.puntaje_minimo}
                    placeholder="0"
                    min="0"
                    class="w-full text-sm font-bold text-primary bg-transparent focus:outline-none"
                  />
                  <span class="text-xs text-gray-400">pts</span>
                </div>
                <span class="text-gray-400">→</span>
                <input
                  type="text"
                  bind:value={esc.evaluacion}
                  placeholder="ej: Aprobado"
                  class="flex-1 text-sm bg-white border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:border-primary/40"
                />
                <button
                  onclick={() => removeEscalaCalif(esc._id)}
                  class="p-1.5 text-gray-300 hover:text-red-400 transition shrink-0"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            {/each}
            <button
              onclick={addEscalaCalif}
              class="inline-flex items-center gap-1.5 text-sm font-medium text-primary/60 hover:text-primary transition mt-1"
            >
              <Plus class="w-4 h-4" />
              Agregar nivel de calificación
            </button>
          </div>
        </div>
      </div>
    {:else}
      <!-- Preview -->
      <div class="px-4 sm:px-8 py-6 max-w-5xl mx-auto">
        <div
          class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 bg-primary/5 p-6 rounded-3xl border-2 border-primary/10"
        >
          <div class="text-center md:text-left">
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Puntaje Máximo</p>
            <p class="text-3xl font-black text-primary">{puntajeTotal} pts</p>
          </div>
          <div
            class="text-center border-y md:border-y-0 md:border-x border-primary/10 py-4 md:py-0"
          >
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">Criterios</p>
            <p class="text-3xl font-black text-primary">{niveles.length}</p>
          </div>
          <div class="text-center md:text-right">
            <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
              Niveles de Desempeño
            </p>
            <p class="text-3xl font-black text-primary">{numCols}</p>
          </div>
        </div>

        <RubricaView rubrica={rubricaPreview} />

        <div class="mt-8 bg-gray-50/50 p-6 rounded-3xl border border-gray-100">
          <p class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">
            Escala de calificación aplicada:
          </p>
          <div class="flex flex-wrap gap-3">
            {#each rubricaPreview.detalles_evaluacion.escala_evaluacion as esc}
              <div
                class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-200 shadow-sm"
              >
                <span class="w-2.5 h-2.5 rounded-full bg-primary shrink-0"></span>
                <span class="text-xs font-bold text-gray-700">{esc.evaluacion || '—'}</span>
                <span class="text-xs text-gray-400 ml-1">≥ {esc.puntaje_minimo} pts</span>
              </div>
            {/each}
          </div>
        </div>
      </div>
    {/if}
  </div>
</div>
