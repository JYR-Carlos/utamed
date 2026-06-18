<script lang="ts">
  interface Props {
    disponible: boolean;
    onSubirClick: () => void;
  }

  let { disponible, onSubirClick }: Props = $props();

  const labelEstado = $derived(
    disponible ? "Adjunta aquí tu archivo" : "El plazo de entrega ha vencido."
  );
</script>

<div
  class="mb-[18px] flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center sm:flex-row sm:text-left"
>
  <div
    class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
  >
    <svg
      width="22"
      height="22"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <polyline points="17 8 12 3 7 8" />
      <line x1="12" y1="3" x2="12" y2="15" />
    </svg>
  </div>

  <div class="flex-1">
    <h3 class="text-base font-bold text-slate-900">Subir trabajo</h3>
    <p class="text-xs text-slate-500">{labelEstado}</p>
  </div>

  <button
    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors
           bg-blue-600 hover:bg-blue-700
           disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 disabled:shadow-none disabled:hover:bg-slate-300"
    disabled={!disponible}
    onclick={onSubirClick}
  >
    <svg
      width="15"
      height="15"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linecap="round"
      stroke-linejoin="round"
    >
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <polyline points="17 8 12 3 7 8" />
      <line x1="12" y1="3" x2="12" y2="15" />
    </svg>

    Subir archivo
  </button>
</div>