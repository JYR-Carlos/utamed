<script lang="ts">
  interface Props {
    ultima_nota?: number | null;
    es_sumativa: boolean;
  }

  let { ultima_nota, es_sumativa }: Props = $props();

  const gradeLabel = $derived.by(() => {
    if (ultima_nota === null || ultima_nota === undefined) {
      return 'Sin nota';
    }
    return `${ultima_nota.toFixed(1)}`;
  });

  const gradeClass = $derived.by(() => {
    const baseClass =
      'flex flex-col items-start justify-between gap-4 rounded-xl p-5 shadow-sm sm:flex-row sm:items-center border';
    const gradeColors =
      ultima_nota === null || ultima_nota === undefined
        ? 'bg-gray-50 border-gray-100 text-gray-900'
        : ultima_nota >= 4
          ? 'bg-emerald-50/60 border-emerald-100 text-emerald-900'
          : 'bg-rose-50/60 border-rose-100 text-rose-900';
    return `${baseClass} ${gradeColors}`;
  });
</script>

<div class={gradeClass}>
  <div class="flex flex-col items-center gap-3.5">
    <p class="truncate text-sm font-semibold">
      {es_sumativa ? 'Nota Sumativa' : 'Nota Formativa'}
    </p>
    <div
      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg shadow-sm bg-white text-black"
    >
      <span class="text-lg font-bold">{gradeLabel}</span>
    </div>
  </div>
</div>
