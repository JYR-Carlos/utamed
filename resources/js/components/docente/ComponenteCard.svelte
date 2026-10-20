<script lang="ts">
  /** Tarjeta de un curso donde el docente imparte un componente (no es titular del curso). */
  import { Link } from '@inertiajs/svelte';
  import { Presentation, Wrench, FlaskConical, Layers, ChevronRight } from 'lucide-svelte';
  import { tonoGrupo } from '@/utils/grupoColor';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    tipo_componente: string;
    titular_nombre?: string | null;
  }

  let { id_curso, nombre, cod_curso, letra_grupo, tipo_componente, titular_nombre }: Props = $props();

  const grupo = $derived(tonoGrupo(letra_grupo));

  const tono = $derived.by(() => {
    const tipo = tipo_componente.toLowerCase();
    if (tipo.includes('laborator')) {
      return { bg: 'bg-[#F3F0FA]', border: 'border-[#DED5F0]', color: 'text-[#4B2E83]', Icon: FlaskConical };
    }
    if (tipo.includes('taller')) {
      return { bg: 'bg-[#FFF4EC]', border: 'border-[#FBD3B4]', color: 'text-[#9A4B12]', Icon: Wrench };
    }
    if (tipo.includes('cátedra') || tipo.includes('catedra')) {
      return { bg: 'bg-[#E8EDF5]', border: 'border-[#C9D6E6]', color: 'text-[#002F6C]', Icon: Presentation };
    }
    return { bg: 'bg-[#F1F5F9]', border: 'border-[#E2E8F0]', color: 'text-[#475569]', Icon: Layers };
  });
</script>

<Link
  href={`/docente/cursos/${id_curso}`}
  class="group flex flex-col gap-2.5 rounded-xl border border-l-4 p-4 no-underline transition-[border-color,box-shadow] hover:shadow-md {grupo.tarjeta}"
>
  <div class="flex items-center gap-2 text-[13px]">
    <span class="font-mono text-[#4A4E5C]">{cod_curso}</span>
    {#if letra_grupo}
      <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {grupo.chip}">Grupo {letra_grupo}</span>
    {/if}
  </div>
  <span class="text-base font-semibold leading-snug text-[#171A26]">{nombre}</span>
  <span class="inline-flex w-fit items-center gap-1.5 rounded-full {tono.bg} border {tono.border} {tono.color} px-2 py-0.5 text-[11px] font-semibold">
    <tono.Icon class="h-3 w-3" />
    {tipo_componente}
  </span>
  <div class="flex items-center gap-2 border-t border-black/10 pt-2.5">
    <span class="text-[13px] text-[#5A5E6E]">Titular: {titular_nombre ?? '—'}</span>
    <ChevronRight class="ml-auto h-5 w-5 text-[#5A5E6E] transition-transform group-hover:translate-x-0.5" />
  </div>
</Link>
