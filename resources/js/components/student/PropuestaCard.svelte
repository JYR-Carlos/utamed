<script lang="ts">
  /**
   * Cascarón de una sección "Propuesta": el diseño ya existe, pero el dato que
   * mostraría todavía no viaja del backend (notas recientes, próximas entregas,
   * novedades de actividad). Se muestra siempre en su estado vacío honesto en
   * vez de inventar contenido — el borde punteado y la pastilla "PROPUESTA"
   * la distinguen del resto.
   */
  interface Props {
    icon: any;
    title: string;
    emptyTitle: string;
    emptyDescription: string;
  }

  let { icon: Icon, title, emptyTitle, emptyDescription }: Props = $props();
</script>

<section
  class="flex flex-col gap-3 rounded-xl border border-dashed border-slate-300 bg-white p-4 shadow-sm"
>
  <div class="flex flex-row-reverse justify-between items-center gap-2">
    <Icon class="h-4 w-4 text-slate-500" />
    <p class="text-xl font-semibold text-slate-900">{title}</p>
    
  </div>

  <div
    class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center"
  >
    <span class="text-[13px] font-semibold text-slate-900">{emptyTitle}</span>
    <p class="text-[12px] text-slate-500">{emptyDescription}</p>
  </div>
</section>
