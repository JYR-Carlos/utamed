<script lang="ts">
  /**
   * Cascarón de una sección "Propuesta": el diseño ya existe, pero el dato que
   * mostraría (grupos por evaluar transversal a todos los cursos, con permiso
   * delegado incluido) todavía no tiene una consulta que lo arme. Se muestra
   * siempre en su estado vacío honesto en vez de inventar contenido.
   */
  interface Props {
    icon: any;
    title: string;
    emptyTitle: string;
    emptyDescription: string;
  }

  let { icon: Icon, title, emptyTitle, emptyDescription }: Props = $props();
</script>

<section class="flex flex-col gap-3 rounded-xl border border-dashed border-[#C9D6E6] bg-white p-4 shadow-sm">
  <div class="flex items-center gap-2">
    <Icon class="h-4 w-4 text-[#5A5E6E]" />
    <h3 class="text-[15px] font-semibold text-[#1A1A24]">{title}</h3>
    <span
      class="ml-auto inline-flex shrink-0 items-center rounded-full border border-[#C9D6E6] bg-[#E8EDF5] px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-[#002F6C]"
    >
      PROPUESTA
    </span>
  </div>

  <div class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center">
    <span class="text-[13px] font-semibold text-[#1A1A24]">{emptyTitle}</span>
    <p class="text-[12px] text-[#5A5E6E]">{emptyDescription}</p>
  </div>
</section>
