<script lang="ts">
  interface Props {
    trae_archivo: boolean;
  }

  let { trae_archivo }: Props = $props();
</script>

<div
  class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-slate-200 bg-white p-6 text-center sm:flex-row sm:text-left mb-[18px]"
>
  <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
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
    <p class="text-xs text-slate-500">
      Adjunta tu archivo de entrega para esta actividad
    </p>
  </div>
  <button
    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
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

{#if trae_archivo}
  <div class="space-y-3 mb-1">
    <button
      class="group flex w-full items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
    >
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
        <svg
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
          ><path
            d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
          /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line
            x1="16"
            y1="17"
            x2="8"
            y2="17"
          /></svg
        >
      </div>
      <div class="flex-1">
        <div class="text-sm font-bold text-slate-900">Ver Enunciado</div>
        <div class="text-xs text-slate-500">Instrucciones de la actividad</div>
      </div>
      <svg
        class="text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-400"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg
      >
    </button>
  </div>
{/if}
