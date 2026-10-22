<script lang="ts">
  /**
   * Cascarón de una sección "Propuesta": el diseño ya existe, pero el dato que
   * mostraría (grupos por evaluar transversal a todos los cursos, con permiso
   * delegado incluido) todavía no tiene una consulta que lo arme. No se
   * inventa contenido: una sola línea dice qué será y lleva a donde hoy se
   * hace ese trabajo. Como bloque vacío a todo el ancho ocupaba el sitio de
   * algo que no existe.
   */
  import { Link } from '@inertiajs/svelte';
  import { ArrowRight } from 'lucide-svelte';

  interface Props {
    icon: any;
    title: string;
    description: string;
    href: string;
    linkLabel: string;
  }

  let { icon: Icon, title, description, href, linkLabel }: Props = $props();
</script>

<section
  class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-gray-200 bg-white px-4 py-3 sm:px-5"
>
  <Icon class="h-5 w-5 shrink-0 text-uta-blue" aria-hidden="true" />
  <div class="flex min-w-0 flex-1 flex-col">
    <h3 class="text-sm font-semibold text-ink">{title}</h3>
    <p class="text-[13px] leading-[18px] text-ink-2">{description}</p>
  </div>
  <Link
    {href}
    class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-semibold text-navy no-underline transition-colors hover:bg-slate-50 sm:min-h-9"
  >
    {linkLabel}
    <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
  </Link>
</section>
