<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica?: Rubrica | null;
    onRubricaClick: () => void;
  }

  let { rubrica, onRubricaClick }: Props = $props();
</script>

<div class="space-y-3">
  <button
    class="group flex w-full items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50"
    onclick={onRubricaClick}
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
      >
        <path
          d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"
        />
        <rect x="9" y="3" width="6" height="4" rx="2" />
        <line x1="9" y1="12" x2="15" y2="12" />
        <line x1="9" y1="16" x2="12" y2="16" />
      </svg>
    </div>
    <div class="flex-1">
      <div class="text-sm font-bold text-slate-900">Ver Rúbrica de Evaluación</div>
      <div class="text-xs text-slate-500">
        {rubrica ? 'Criterios y niveles de desempeño' : 'Aún no hay rúbrica asignada'}
      </div>
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
