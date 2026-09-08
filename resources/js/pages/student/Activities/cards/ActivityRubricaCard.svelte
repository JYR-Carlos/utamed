<script lang="ts">
  import { ClipboardList, Eye } from 'lucide-svelte';
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica?: Rubrica | null;
    onRubricaClick: () => void;
  }

  let { rubrica, onRubricaClick }: Props = $props();

  const criterios = $derived(rubrica?.niveles?.length ?? 0);
  const puntos = $derived(rubrica?.detalles_evaluacion?.puntaje_total ?? null);
</script>

<section class="flex items-center gap-3.5 rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
  <ClipboardList class="h-4 w-4 shrink-0 text-[#5A5E6E]" />
  <div class="flex min-w-0 flex-col">
    <h3 class="text-sm font-semibold text-[#1A1A24]">Rúbrica de evaluación</h3>
    <span class="text-xs text-[#5A5E6E]">
      {#if rubrica}
        {criterios} {criterios === 1 ? 'criterio' : 'criterios'}{puntos !== null ? ` · ${puntos} puntos` : ''} · sólo lectura
      {:else}
        Aún no hay rúbrica asignada
      {/if}
    </span>
  </div>
  <button
    class="ml-auto flex shrink-0 items-center gap-1.5 rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-sm font-medium text-[#1A1A24] transition-colors hover:bg-[#F8FAFC]"
    onclick={onRubricaClick}
  >
    <Eye class="h-[15px] w-[15px] text-[#5A5E6E]" />
    Ver rúbrica
  </button>
</section>
