<script lang="ts">
  /**
   * Puente a jefatura de carrera: es un panel aparte del trabajo de cursos
   * del docente, no un curso más — por eso vive fuera del grid de cursos y
   * se marca "Otro rol" en vez de otra paleta.
   */
  import { Link } from '@inertiajs/svelte';
  import { ClipboardList, ArrowUpRight } from 'lucide-svelte';

  interface Props {
    carreraNombre: string;
    pendientesRevision: number;
  }

  let { carreraNombre, pendientesRevision }: Props = $props();
</script>

<section
  class="flex items-center gap-4 rounded-xl border border-[#C9D6E6] border-l-[3px] border-l-[#002F6C] bg-white p-4 shadow-sm"
>
  <div class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[9px] bg-[#E8EDF5]">
    <ClipboardList class="h-[18px] w-[18px] text-[#002F6C]" />
  </div>
  <div class="flex min-w-0 flex-1 flex-col gap-0.5">
    <span class="text-[10.5px] font-semibold uppercase tracking-wide text-[#002F6C]">Otro rol</span>
    <span class="text-base font-semibold leading-snug text-[#1A1A24]">Jefatura de Carrera — {carreraNombre}</span>
    <span class="text-[13px] text-[#5A5E6E]">
      {#if pendientesRevision > 0}
        {pendientesRevision} syllabus {pendientesRevision === 1 ? 'espera' : 'esperan'} tu revisión como jefe de carrera.
      {:else}
        Sin syllabus pendientes de revisión hoy.
      {/if}
      Es un panel aparte del trabajo de tus cursos.
    </span>
  </div>
  <Link
    href="/docente/jefe-carrera/dashboard"
    class="flex shrink-0 items-center gap-1.5 rounded-lg bg-[#002F6C] px-3.5 py-2.5 text-[13.5px] font-semibold text-white no-underline transition-colors hover:bg-[#00214d]"
  >
    Ir al dashboard de jefatura
    <ArrowUpRight class="h-[15px] w-[15px]" />
  </Link>
</section>
