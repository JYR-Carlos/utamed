<script lang="ts">
  interface Props {
    percentage: number;
    tipo: string;
    showLabel?: boolean;
  }

  let { percentage, tipo, showLabel = true }: Props = $props();

  const requiredSections = $derived(tipo === 'BASICO' ? { count: 5, label: 'I, II, VI, VII, VIII' } : { count: 9, label: 'I-IX' });

  const isComplete = $derived(percentage >= 100);
  const progressColor = $derived.by(() => {
    if (percentage >= 100) return 'bg-green-500';
    if (percentage >= 75) return 'bg-blue-500';
    if (percentage >= 50) return 'bg-yellow-500';
    return 'bg-orange-500';
  });
</script>

<div class="space-y-2">
  {#if showLabel}
    <div class="flex justify-between items-center text-sm">
      <span class="font-semibold text-slate-700">
        Completitud ({requiredSections.label})
      </span>
      <span class={`font-bold ${isComplete ? 'text-green-600' : 'text-slate-600'}`}>
        {percentage}%
      </span>
    </div>
  {/if}

  <!-- Progress Bar -->
  <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
    <div class={`h-full transition-all duration-300 ease-out ${progressColor}`} style="width: {percentage}%"></div>
  </div>

  <!-- Status Text -->
  {#if showLabel}
    <p class="text-xs text-slate-600">
      {#if percentage === 0}
        Sin contenido
      {:else if percentage < 50}
        Incompleto - Faltan {100 - percentage}%
      {:else if percentage < 100}
        Casi completo - Faltan {100 - percentage}%
      {:else}
        ✓ Listo para aprobación
      {/if}
    </p>
  {/if}
</div>
