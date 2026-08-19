<script lang="ts">
  /**
   * ComponenteTitularModal
   * Permite al Docente Titular del Curso cambiar cuál docente es el titular
   * de un componente colegiado (es_titular=true).
   *
   * Llama a PUT /docente/cursos/{cursoId}/componentes/{componenteId}/titular
   * con { id_docente_componente: number }.
   * Al confirmar hace router.reload() para reflejar el cambio.
   */
  import { router } from '@inertiajs/svelte';
  import { Crown, Loader2, Users } from 'lucide-svelte';
  import { initials } from '@/utils/formatters';

  interface DocenteComponente {
    id_docente_componente: number;
    id_docente: number;
    id_usuario: number | null;
    nombre: string;
    es_titular: boolean;
  }

  interface ComponenteConDocentes {
    id_componente: number;
    tipo_componente: string;
    docentes: DocenteComponente[];
  }

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    cursoId: number;
    idDocenteTitularCurso: number;
    componente: ComponenteConDocentes | null;
  }

  let {
    isOpen = $bindable(),
    onClose,
    cursoId,
    idDocenteTitularCurso,
    componente,
  }: Props = $props();

  // ─── Estado ─────────────────────────────────────────────────────────────
  let selectedDcId = $state<number | null>(null);
  let saving = $state(false);
  let error = $state<string | null>(null);

  // Inicializar selección cuando se abre el modal o cambia el componente
  $effect(() => {
    if (isOpen && componente) {
      const titular = componente.docentes.find((d) => d.es_titular);
      selectedDcId = titular?.id_docente_componente ?? null;
      error = null;
    }
  });

  function getRolLabel(doc: DocenteComponente): string {
    if (doc.id_docente === idDocenteTitularCurso) return 'DT del Curso';
    if (doc.es_titular) return 'Titular del Componente';
    return 'Colegiado';
  }

  function getRolColor(doc: DocenteComponente): string {
    if (doc.id_docente === idDocenteTitularCurso) return 'text-blue-700 bg-blue-50 border-blue-200';
    if (doc.es_titular) return 'text-amber-700 bg-amber-50 border-amber-200';
    return 'text-gray-600 bg-gray-50 border-gray-200';
  }

  // ─── Confirmar cambio ────────────────────────────────────────────────────
  async function confirm() {
    if (!selectedDcId || !componente) return;

    saving = true;
    error = null;

    try {
      const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
        ?.content ?? '';

      const res = await fetch(
        `/docente/cursos/${cursoId}/componentes/${componente.id_componente}/titular`,
        {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ id_docente_componente: selectedDcId }),
        },
      );

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error ?? data.message ?? 'Error al actualizar el titular');
      }

      onClose();
      router.reload({ only: ['todos_componentes'] });
    } catch (e: any) {
      error = e.message ?? 'Error inesperado';
    } finally {
      saving = false;
    }
  }
</script>

{#if isOpen && componente}
  <!-- Backdrop -->
  <button
    class="fixed inset-0 bg-black/40 z-40 cursor-default"
    onclick={onClose}
    aria-label="Cerrar"
  ></button>

  <!-- Panel -->
  <div
    class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-50
           bg-white rounded-2xl shadow-2xl w-full max-w-md
           flex flex-col overflow-hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cambiar-titular-title"
  >
    <!-- Header -->
    <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
      <div class="flex items-center gap-2.5">
        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50">
          <Crown size={16} class="text-amber-600" />
        </div>
        <div>
          <h2 id="cambiar-titular-title" class="text-base font-bold text-gray-900">
            Titular del {componente.tipo_componente}
          </h2>
          <p class="text-xs text-gray-400">Selecciona quién será el responsable principal</p>
        </div>
      </div>
      <button
        onclick={onClose}
        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
        aria-label="Cerrar"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg
        >
      </button>
    </div>

    <!-- Body -->
    <div class="p-5 space-y-2">
      {#if error}
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm mb-3">
          {error}
        </div>
      {/if}

      <fieldset class="space-y-2 border-0 m-0 p-0">
        <legend class="sr-only">Seleccionar titular del {componente.tipo_componente}</legend>
        {#each componente.docentes as doc}
        {@const esDtCurso = doc.id_docente === idDocenteTitularCurso}
        <label
          class="flex items-center gap-3 p-3 rounded-xl border transition-colors cursor-pointer
                 {selectedDcId === doc.id_docente_componente
                   ? 'border-[#2A66AC] bg-blue-50/50'
                   : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'}"
        >
          <input
            type="radio"
            name="titular"
            class="accent-[#2A66AC] w-4 h-4 shrink-0"
            value={doc.id_docente_componente}
            bind:group={selectedDcId}
            disabled={esDtCurso}
          />

          <!-- Avatar -->
          <div
            class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold text-white shrink-0"
            style="background:#2A66AC;"
          >
            {initials(doc.nombre)}
          </div>

          <!-- Info -->
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900 truncate">{doc.nombre}</p>
            <p class="text-[11px] text-gray-400">{esDtCurso ? 'Siempre titular del curso' : 'Seleccionable como titular'}</p>
          </div>

          <!-- Badge -->
          <span
            class="text-[10px] font-semibold px-2 py-0.5 rounded-full border shrink-0 {getRolColor(doc)}"
          >
            {getRolLabel(doc)}
          </span>
        </label>
      {/each}
      </fieldset>

      {#if componente.docentes.length === 0}
        <div class="flex flex-col items-center py-10 text-gray-400">
          <Users size={28} class="mb-2 text-gray-300" />
          <p class="text-sm">No hay docentes asignados a este componente.</p>
        </div>
      {/if}
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100">
      <button
        onclick={onClose}
        class="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition-colors"
        disabled={saving}
      >
        Cancelar
      </button>
      <button
        onclick={confirm}
        disabled={saving || !selectedDcId}
        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold
               bg-[#2A66AC] text-white hover:bg-[#1e4d8a] disabled:opacity-50
               disabled:cursor-not-allowed transition-colors"
      >
        {#if saving}
          <Loader2 size={14} class="animate-spin" />
          Guardando...
        {:else}
          <Crown size={14} />
          Confirmar titular
        {/if}
      </button>
    </div>
  </div>
{/if}
