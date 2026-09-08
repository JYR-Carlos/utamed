<script lang="ts">
  /** Tarjeta de un curso donde el docente es titular, con su estado de syllabus real. */
  import { Link } from '@inertiajs/svelte';
  import { XCircle, FilePenLine, Clock, CheckCircle2, ChevronRight } from 'lucide-svelte';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: 'NO_INICIADO' | 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION' | 'APROBADO';
  }

  let { id_curso, nombre, cod_curso, letra_grupo, estado_syllabus }: Props = $props();

  const badge = $derived(
    {
      RECHAZADO: { bg: 'bg-[#FEF2F2]', border: 'border-[#FECACA]', color: 'text-[#B91C1C]', Icon: XCircle, label: 'Syllabus rechazado' },
      BORRADOR: { bg: 'bg-[#FFFBEB]', border: 'border-[#FDE68A]', color: 'text-[#B45309]', Icon: FilePenLine, label: 'Syllabus en borrador' },
      EN_REVISION: { bg: 'bg-[#F1F5F9]', border: 'border-[#E2E8F0]', color: 'text-[#475569]', Icon: Clock, label: 'Syllabus en revisión' },
      APROBADO: { bg: 'bg-[#ECFDF5]', border: 'border-[#A7F3D0]', color: 'text-[#047857]', Icon: CheckCircle2, label: 'Syllabus aprobado' },
      NO_INICIADO: { bg: 'bg-[#F8FAFC]', border: 'border-[#E5E7EB]', color: 'text-[#5A5E6E]', Icon: FilePenLine, label: 'Sin syllabus' },
    }[estado_syllabus],
  );
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
  <div class="flex items-center gap-2 border-t border-[#F1F5F9] pt-2.5">
    <span class="inline-flex items-center gap-1.5 rounded-full {badge.bg} border {badge.border} {badge.color} px-2 py-0.5 text-[11px] font-semibold">
      <badge.Icon class="h-3 w-3" />
      {badge.label}
    </span>
    <ChevronRight class="ml-auto h-[15px] w-[15px] text-[#5A5E6E]" />
  </div>
</Link>
