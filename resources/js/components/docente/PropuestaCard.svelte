<script lang="ts">
  /**
   * Cascarón de una sección "Propuesta": el diseño ya existe, pero el dato que
   * mostraría (grupos por evaluar transversal a todos los cursos, con permiso
   * delegado incluido) todavía no tiene una consulta que lo arme. Se muestra
   * siempre en su estado vacío honesto en vez de inventar contenido.
   */
  import BloqueVacio from '@/components/docente/BloqueVacio.svelte';

  interface Props {
    icon: any;
    title: string;
    emptyTitle: string;
    emptyDescription: string;
  }

  let { icon: Icon, title, emptyTitle, emptyDescription }: Props = $props();
</script>

<section class="flex flex-col gap-4 rounded-xl border border-[#E5E7EB] bg-white p-4 sm:p-5">
  <div class="flex items-center gap-2">
    <Icon class="h-5 w-5 text-[#002855]" />
    <h3 class="text-base font-semibold text-[#171A26]">{title}</h3>
  </div>

  <BloqueVacio title={emptyTitle} description={emptyDescription} />
</section>
