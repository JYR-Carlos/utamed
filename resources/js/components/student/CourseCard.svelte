<script lang="ts">
  import { useFormatName, useInitials } from '@/hooks';
  import { Link } from '@inertiajs/svelte';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    profesor: string;
    semestre_real: number;
    agno_real: number;
  }

  let { id_curso, nombre, cod_curso, profesor, semestre_real, agno_real }: Props = $props();

  const { formatName } = useFormatName();
  const { getInitials } = useInitials();

  let sinDocente = $derived(profesor === '(sin docente asignado)');
  let periodoLabel = $derived(
    `${semestre_real === 1 ? 'Primer' : 'Segundo'} semestre ${agno_real}`,
  );
</script>

<Link
  href={`/estudiante/cursos/${id_curso}`}
  class="group flex h-full flex-col gap-2.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
>
  <div class="flex items-start gap-2.5">
    <div
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#22213F]/10 text-[13px] font-bold text-[#22213F]"
    >
      {getInitials(nombre)}
    </div>
    <div class="flex min-w-0 flex-col gap-0.5">
      <span class="font-mono text-[11px] text-slate-500">{cod_curso}</span>
      <span
        class="line-clamp-2 text-[15px] font-semibold leading-tight text-slate-900 group-hover:text-[#22213F]"
      >
        {formatName(nombre)}
      </span>
    </div>
  </div>

  <div class="mt-auto flex flex-col gap-1 border-t border-slate-100 pt-2.5">
    {#if sinDocente}
      <span class="text-[12.5px] italic text-slate-500">{profesor}</span>
    {:else}
      <span class="text-[12.5px] text-slate-800">{formatName(profesor)}</span>
    {/if}
    <span class="text-[11.5px] text-slate-500">{periodoLabel}</span>
  </div>
</Link>
