<script lang="ts">
  /**
   * Diálogo de rechazo del syllabus — la razón es obligatoria.
   *
   * Sobre un documento APROBADO el mismo diálogo cambia de título a «Revocar
   * aprobación» y mantiene la exigencia: retirar una firma pide explicación
   * igual que negarla. El backend valida `required|string|max:500`
   * (`Admin\ProgramaController::reject`), así que el contador es el mismo 500.
   */
  import { XCircle, Eye } from 'lucide-svelte';

  interface Props {
    abierto: boolean;
    /** Rótulo del curso, para que el revisor sepa qué está devolviendo. */
    curso: string;
    /** true si el documento estaba APROBADO: se está revocando una firma. */
    revocando?: boolean;
    enviando?: boolean;
    onCancelar: () => void;
    onConfirmar: (razon: string) => void;
  }

  let {
    abierto,
    curso,
    revocando = false,
    enviando = false,
    onCancelar,
    onConfirmar,
  }: Props = $props();

  const MAX = 500;

  let razon = $state('');
  let tocado = $state(false);

  const limpia = $derived(razon.trim());
  const valida = $derived(limpia.length > 0 && razon.length <= MAX);
  const titulo = $derived(revocando ? 'Revocar aprobación' : 'Rechazar syllabus');

  function cerrar() {
    razon = '';
    tocado = false;
    onCancelar();
  }

  function confirmar() {
    tocado = true;
    if (!valida) return;
    onConfirmar(limpia);
  }

  function alTeclear(event: KeyboardEvent) {
    if (event.key === 'Escape') cerrar();
  }
</script>

<svelte:window onkeydown={abierto ? alTeclear : undefined} />

{#if abierto}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 p-4"
    role="presentation"
    onclick={(e) => {
      if (e.target === e.currentTarget) cerrar();
    }}
  >
    <div
      class="flex w-full max-w-[660px] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      role="dialog"
      aria-modal="true"
      aria-labelledby="rechazo-titulo"
    >
      <div class="flex items-center gap-2.5 border-b border-gray-200 bg-stone-50 px-5 py-3.5">
        <XCircle size={16} class="text-red-700" aria-hidden="true" />
        <h2 id="rechazo-titulo" class="m-0 text-[13.5px] font-semibold text-ink">
          {titulo} — {curso}
        </h2>
      </div>

      <div class="flex flex-col gap-2.5 px-5 py-[18px]">
        <label for="razon-rechazo" class="text-[13px] font-semibold text-ink">
          Razón {revocando ? 'de la revocación' : 'del rechazo'}
          <span class="text-red-600" aria-hidden="true">*</span>
        </label>
        <textarea
          id="razon-rechazo"
          rows="4"
          maxlength={MAX}
          bind:value={razon}
          onblur={() => (tocado = true)}
          placeholder="Indica qué debe corregir el docente antes de reenviar"
          aria-invalid={tocado && !valida}
          aria-describedby="razon-ayuda"
          class="w-full resize-y rounded-lg border px-3.5 py-2.5 text-[14px] text-ink outline-none transition-colors {tocado &&
          !valida
            ? 'border-red-600'
            : 'border-line focus:border-navy'}"
        ></textarea>
        <div class="flex items-center gap-2.5">
          <span
            id="razon-ayuda"
            class="text-[12px] {tocado && !valida ? 'text-red-600' : 'text-ink-2'}"
          >
            {tocado && !valida
              ? 'La razón es obligatoria.'
              : 'Se envía al docente y queda en el historial.'}
          </span>
          <span class="ml-auto font-mono text-[11.5px] tabular-nums text-ink-2">
            {razon.length} / {MAX}
          </span>
        </div>
        <p
          class="m-0 flex gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-[12.5px] leading-[1.5] text-ocre"
        >
          <Eye size={15} class="mt-px shrink-0 text-amber-700" aria-hidden="true" />
          El docente titular verá esta razón como el primer contenido de su vista de syllabus.
        </p>
      </div>

      <div class="flex items-center gap-3 border-t border-gray-200 px-5 py-3.5">
        <button
          type="button"
          onclick={cerrar}
          disabled={enviando}
          class="rounded-lg border border-transparent px-3 py-2.5 text-[14px] font-medium text-navy transition-colors hover:bg-sand disabled:opacity-50"
        >
          Cancelar
        </button>
        <button
          type="button"
          onclick={confirmar}
          disabled={enviando || !valida}
          class="ml-auto inline-flex items-center gap-[7px] rounded-lg border border-red-600 bg-red-600 px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:border-red-300 disabled:bg-red-300"
        >
          <XCircle size={15} aria-hidden="true" />
          {enviando ? 'Enviando…' : titulo}
        </button>
      </div>
    </div>
  </div>
{/if}
