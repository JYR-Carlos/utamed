<script lang="ts">
  interface Props {
    ultima_nota?: number | null;
    es_sumativa: boolean;
  }

  let { ultima_nota, es_sumativa }: Props = $props();

  const gradeLabel = $derived.by(() => {
    if (ultima_nota === null || ultima_nota === undefined) {
      return '-.-';
    }
    return `${ultima_nota.toFixed(1)}`;
  });

  const containerClass = $derived.by(() => {
    const base = 'flex w-full items-center gap-4 rounded-xl border p-4 shadow-sm';
    if (ultima_nota === null || ultima_nota === undefined) {
      return `${base} border-slate-200 bg-slate-50/50 text-slate-900`;
    }
    return ultima_nota >= 4
      ? `${base} border-emerald-100 bg-emerald-50/30 text-emerald-950`
      : `${base} border-rose-100 bg-rose-50/30 text-rose-950`;
  });

  const badgeClass = $derived.by(() => {
    const base = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg font-bold text-sm shadow-sm border border-black/5';
    if (ultima_nota === null || ultima_nota === undefined) {
      return `${base} bg-slate-100 text-slate-500`;
    }
    return ultima_nota >= 4
      ? `${base} bg-emerald-500 text-white`
      : `${base} bg-rose-500 text-white`;
  });
</script>

<div class={containerClass}>
  <div class={badgeClass}>
    {gradeLabel}
  </div>
  
  <div class="flex-1 min-w-0">
    <div class="text-sm font-bold truncate">
      {es_sumativa ? 'Nota Sumativa' : 'Nota Formativa'}
    </div>
    <div class="text-xs opacity-70 truncate">
      {ultima_nota === null || ultima_nota === undefined ? 'Sin nota registrada' : 'Última calificación obtenida'}
    </div>
  </div>
</div>