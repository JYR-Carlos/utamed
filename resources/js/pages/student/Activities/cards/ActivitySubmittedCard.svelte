<script lang="ts">
</script>

<div
  class="flex flex-col items-center gap-4 rounded-2xl border border-slate-200 bg-slate-100/60 p-5 sm:flex-row"
>
  <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-200 text-slate-500">
    <svg
      width="22"
      height="22"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
    >
  </div>

  <div class="flex-1 text-center sm:text-left">
    <h3 class="text-sm font-bold text-slate-900">Tu entrega</h3>
    <p class="text-xs text-slate-500">
      Tu trabajo ha sido entregado. No es posible volver a subir un archivo porque ya fue
      calificada.
    </p>
  </div>
  <button
    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 disabled:opacity-50 disabled:hover:bg-white"
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
      <polyline points="7 10 12 15 17 10" />
      <line x1="12" y1="15" x2="12" y2="3" />
    </svg>
    Descargar entrega
  </button>
</div>
