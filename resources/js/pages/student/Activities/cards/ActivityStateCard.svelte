<script lang="ts">
  interface Props {
    ultimo_estado?: string | null;
    ultima_nota?: number | null;
    es_sumativa: boolean;
    onRubricaClick: () => void;
  }

  let { ultimo_estado, ultima_nota, es_sumativa, onRubricaClick }: Props = $props();

  const stateLabel = $derived.by(() => {
    return ultimo_estado?.toUpperCase();
  });

  const stateClass = $derived.by(() => {
    const baseClass = 'flex flex-col items-start justify-between gap-4 rounded-xl p-5 shadow-sm sm:flex-row sm:items-center border';
    const stateColors = {
      pass: 'bg-emerald-50/60 border-emerald-100 text-emerald-900',
      fail: 'bg-rose-50/60 border-rose-100 text-rose-900',
      submitted: 'bg-blue-50/60 border-blue-100 text-blue-900',
      pending: 'bg-amber-50/60 border-amber-100 text-amber-900',
    };
    return `${baseClass} ${stateColors[ultimo_estado as keyof typeof stateColors] || ''}`;
  });

  const iconClass = $derived.by(() => {
    const baseClass = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg shadow-sm';
    const colors = {
      pass: 'bg-emerald-600 text-white',
      fail: 'bg-rose-600 text-white',
      submitted: 'bg-blue-600 text-white',
      pending: 'bg-amber-600 text-white',
    };
    return `${baseClass} ${colors[ultimo_estado as keyof typeof colors] || ''}`;
  });
</script>

<div class={stateClass}>
  <div class="flex items-center gap-3.5">
    <div class={iconClass}>
      {#if ultimo_estado === 'pass'}
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
        >
      {:else if ultimo_estado === 'fail'}
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
        >
      {:else if ultimo_estado === 'submitted'}
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          ><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline
            points="17 8 12 3 7 8"
          /><line x1="12" y1="3" x2="12" y2="15" /></svg
        >
      {:else}
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line
            x1="12"
            y1="16"
            x2="12.01"
            y2="16"
          /></svg
        >
      {/if}
    </div>
    <div>
      <span class="block text-xs font-medium opacity-70">Estado de tu trabajo</span>
      <span class="block text-base font-bold">{stateLabel}</span>
    </div>
  </div>

  <div class="flex flex-1 flex-wrap items-center gap-4 sm:justify-end">
    {#if es_sumativa && (ultimo_estado === 'pass' || ultimo_estado === 'fail')}
      <div class="hidden h-8 w-px bg-current opacity-20 sm:block"></div>
      <div class="text-sm">
        <span class="opacity-60 block text-xs">Evaluación</span>
        <button
          class="font-bold underline transition-opacity hover:opacity-80"
          onclick={onRubricaClick}>Ver rúbrica →</button
        >
      </div>
    {/if}
  </div>

  <div class="flex items-baseline gap-0.5 border-t border-current/10 pt-3 text-right sm:border-0 sm:pt-0">
    {#if ultima_nota !== null && ultima_nota !== undefined}
      <span class="text-3xl font-black tracking-tight">{ultima_nota}</span>
    {:else}
      <span class="text-2xl font-bold opacity-40">–</span>
    {/if}
  </div>
</div>
