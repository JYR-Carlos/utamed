<script lang="ts">
  /**
   * Tarjeta de un curso donde el docente es titular. El grupo va como texto
   * junto al código y la fila inferior muestra la próxima entrega; el estado
   * del syllabus sólo aparece si requiere atención (el aprobado no pide nada).
   */
  import { Link } from '@inertiajs/svelte';
  import { XCircle, FilePenLine, Clock, ChevronRight, CalendarDays } from 'lucide-svelte';
  import PlazoBadge from '@/components/docente/PlazoBadge.svelte';
  import { fechaCorta } from '@/utils/plazo';
  import { tonoGrupo } from '@/utils/grupoColor';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    letra_grupo?: string | null;
    estado_syllabus: 'NO_INICIADO' | 'BORRADOR' | 'RECHAZADO' | 'EN_REVISION' | 'APROBADO';
    proxima_entrega?: string | null;
  }

  let { id_curso, nombre, cod_curso, letra_grupo, estado_syllabus, proxima_entrega = null }: Props = $props();

  const tono = $derived(tonoGrupo(letra_grupo));

  const syllabusPendiente = $derived(
    {
      RECHAZADO: { bg: 'bg-[#FEF2F2]', border: 'border-[#FECACA]', color: 'text-[#B91C1C]', Icon: XCircle, label: 'Syllabus rechazado' },
      BORRADOR: { bg: 'bg-[#FFFBEB]', border: 'border-[#FDE68A]', color: 'text-[#B45309]', Icon: FilePenLine, label: 'Syllabus en borrador' },
      EN_REVISION: { bg: 'bg-[#F1F5F9]', border: 'border-[#E2E8F0]', color: 'text-[#475569]', Icon: Clock, label: 'Syllabus en revisión' },
      NO_INICIADO: { bg: 'bg-[#F8FAFC]', border: 'border-[#E5E7EB]', color: 'text-[#5A5E6E]', Icon: FilePenLine, label: 'Sin syllabus' },
      APROBADO: null,
    }[estado_syllabus],
  );
</script>

<Link
  href={`/docente/cursos/${id_curso}`}
  class="group flex flex-col gap-2.5 rounded-xl border border-l-4 p-4 no-underline transition-[border-color,box-shadow] hover:shadow-md {tono.tarjeta}"
>
  <div class="flex items-center justify-between gap-2">
    <div class="flex min-w-0 items-center gap-2 text-[13px]">
      <span class="font-mono text-[#4A4E5C]">{cod_curso}</span>
      {#if letra_grupo}
        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {tono.chip}">Grupo {letra_grupo}</span>
      {/if}
    </div>
    <ChevronRight class="h-5 w-5 shrink-0 text-[#5A5E6E] transition-transform group-hover:translate-x-0.5" />
  </div>

  <span class="text-base font-semibold leading-snug text-[#171A26]">{nombre}</span>

  <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 border-t border-black/10 pt-2.5 text-[13px]">
    <CalendarDays class="h-4 w-4 shrink-0 text-[#5A5E6E]" />
    {#if proxima_entrega}
      <span class="text-[#5A5E6E]">Próxima entrega</span>
      <span class="font-semibold text-[#4A4E5C]">{fechaCorta(proxima_entrega)}</span>
      <PlazoBadge fecha={proxima_entrega} />
    {:else}
      <span class="text-[#5A5E6E]">Sin entregas próximas</span>
    {/if}

    {#if syllabusPendiente}
      <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-semibold {syllabusPendiente.bg} {syllabusPendiente.border} {syllabusPendiente.color}"
      >
        <syllabusPendiente.Icon class="h-3 w-3" />
        {syllabusPendiente.label}
      </span>
    {/if}
  </div>
</Link>
