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
  class="flex items-center gap-4 rounded-xl border border-slate-300 border-l-[3px] border-l-navy bg-white p-4 shadow-sm"
>
  <div class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-[9px] bg-uta-blue-light">
    <ClipboardList class="h-[18px] w-[18px] text-navy" />
  </div>
  <div class="flex min-w-0 flex-1 flex-col gap-0.5">
    <span class="text-[10.5px] font-semibold uppercase tracking-wide text-navy">Otro rol</span>
    <span class="text-base font-semibold leading-snug text-ink">Jefatura de Carrera — {carreraNombre}</span>
    <span class="text-[13px] text-ink-2">
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
    class="flex shrink-0 items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2.5 text-[13.5px] font-semibold text-white no-underline transition-colors hover:bg-navy-hover"
  >
    Ir al dashboard de jefatura
    <ArrowUpRight class="h-[15px] w-[15px]" />
  </Link>
</section>
