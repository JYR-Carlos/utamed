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
    class="fixed inset-0 z-50 flex items-center justify-center bg-[#1A1A24]/40 p-4"
    role="presentation"
    onclick={(e) => {
      if (e.target === e.currentTarget) cerrar();
    }}
  >
    <div
      class="flex w-full max-w-[660px] flex-col overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      role="dialog"
      aria-modal="true"
      aria-labelledby="rechazo-titulo"
    >
      <div class="flex items-center gap-2.5 border-b border-[#E5E7EB] bg-[#FCFBF9] px-5 py-3.5">
        <XCircle size={16} class="text-[#B91C1C]" aria-hidden="true" />
        <h2 id="rechazo-titulo" class="m-0 text-[13.5px] font-semibold text-[#1A1A24]">
          {titulo} — {curso}
        </h2>
      </div>

      <div class="flex flex-col gap-2.5 px-5 py-[18px]">
        <label for="razon-rechazo" class="text-[13px] font-semibold text-[#1A1A24]">
          Razón {revocando ? 'de la revocación' : 'del rechazo'}
          <span class="text-[#DC2626]" aria-hidden="true">*</span>
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
          class="w-full resize-y rounded-lg border px-3.5 py-2.5 text-[14px] text-[#1A1A24] outline-none transition-colors {tocado &&
          !valida
            ? 'border-[#DC2626]'
            : 'border-[#D6D9E0] focus:border-[#002F6C]'}"
        ></textarea>
        <div class="flex items-center gap-2.5">
          <span
            id="razon-ayuda"
            class="text-[12px] {tocado && !valida ? 'text-[#DC2626]' : 'text-[#5A5E6E]'}"
          >
            {tocado && !valida
              ? 'La razón es obligatoria.'
              : 'Se envía al docente y queda en el historial.'}
          </span>
          <span class="ml-auto font-mono text-[11.5px] tabular-nums text-[#5A5E6E]">
            {razon.length} / {MAX}
          </span>
        </div>
        <p
          class="m-0 flex gap-2.5 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3.5 py-2.5 text-[12.5px] leading-[1.5] text-[#7A5A16]"
        >
          <Eye size={15} class="mt-px shrink-0 text-[#B45309]" aria-hidden="true" />
          El docente titular verá esta razón como el primer contenido de su vista de syllabus.
        </p>
      </div>

      <div class="flex items-center gap-3 border-t border-[#E5E7EB] px-5 py-3.5">
        <button
          type="button"
          onclick={cerrar}
          disabled={enviando}
          class="rounded-lg border border-transparent px-3 py-2.5 text-[14px] font-medium text-[#002F6C] transition-colors hover:bg-[#F5F1EA] disabled:opacity-50"
        >
          Cancelar
        </button>
        <button
          type="button"
          onclick={confirmar}
          disabled={enviando || !valida}
          class="ml-auto inline-flex items-center gap-[7px] rounded-lg border border-[#DC2626] bg-[#DC2626] px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#B91C1C] disabled:cursor-not-allowed disabled:border-[#F3B4B4] disabled:bg-[#F3B4B4]"
        >
          <XCircle size={15} aria-hidden="true" />
          {enviando ? 'Enviando…' : titulo}
        </button>
      </div>
    </div>
  </div>
{/if}
