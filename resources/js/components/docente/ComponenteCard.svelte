<script lang="ts">
  /** Tarjeta de un curso donde el docente imparte un componente (no es titular del curso). */
  import { Link } from '@inertiajs/svelte';
  import { Presentation, Wrench, FlaskConical, Layers, ChevronRight } from 'lucide-svelte';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    tipo_componente: string;
    titular_nombre?: string | null;
  }

  let { id_curso, nombre, cod_curso, letra_grupo, tipo_componente, titular_nombre }: Props = $props();

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
  class="flex flex-col gap-2.5 rounded-[10px] border border-[#E5E7EB] p-3.5 no-underline transition-colors hover:border-[#C9D6E6] hover:bg-[#FAFBFC]"
>
  <div class="flex items-start gap-2.5">
    <div class="flex min-w-0 flex-col gap-0.5">
      <span class="font-mono text-[11.5px] text-[#5A5E6E]">{cod_curso}</span>
      <span class="text-[15px] font-semibold leading-snug text-[#1A1A24]">{nombre}</span>
    </div>
    {#if letra_grupo}
      <span
        class="ml-auto flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-lg bg-[#E8EDF5] text-[13px] font-bold text-[#002F6C]"
      >
        {letra_grupo}
      </span>
    {/if}
  </div>
  <span class="inline-flex w-fit items-center gap-1.5 rounded-full {tono.bg} border {tono.border} {tono.color} px-2 py-0.5 text-[11px] font-semibold">
    <tono.Icon class="h-3 w-3" />
    {tipo_componente}
  </span>
  <div class="flex items-center gap-2 border-t border-[#F1F5F9] pt-2.5">
    <span class="text-[12.5px] text-[#5A5E6E]">Titular: {titular_nombre ?? '—'}</span>
    <ChevronRight class="ml-auto h-[15px] w-[15px] text-[#5A5E6E]" />
  </div>
</Link>
