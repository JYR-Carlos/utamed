<script lang="ts">
  /** Estado vacío de un bloque del dashboard: caja punteada, texto alineado a la izquierda. */
  interface Props {
    title: string;
    description: string;
  }

  let { title, description }: Props = $props();
</script>

<div class="flex flex-col gap-1 rounded-[10px] border border-dashed border-gray-200 bg-slate-50 p-4">
  <span class="text-[13px] font-semibold text-ink">{title}</span>
  <p class="text-[13px] leading-[18px] text-ink-2">{description}</p>
</div>
