<script lang="ts">
  /** Estado vacío de un bloque del dashboard: caja punteada, texto alineado a la izquierda. */
  interface Props {
    title: string;
    description: string;
  }

  let { title, description }: Props = $props();
</script>

<div class="flex flex-col gap-1 rounded-[10px] border border-dashed border-[#E5E7EB] bg-[#F8FAFC] p-4">
  <span class="text-[13px] font-semibold text-[#171A26]">{title}</span>
  <p class="text-[13px] leading-[18px] text-[#5A5E6E]">{description}</p>
</div>
