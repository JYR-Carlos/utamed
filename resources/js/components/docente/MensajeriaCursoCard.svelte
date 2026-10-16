<script lang="ts">
  /**
   * Mensajería de nivel curso (curso.mensaje) sin leer, agrupada por curso.
   * El docente ve todo el canal del componente (esStaff=true), no sólo lo
   * suyo. Canal distinto de la agenda de actividad (ver AgendaPendienteCard).
   */
  import { Link } from '@inertiajs/svelte';
  import { MessageSquare, Inbox } from 'lucide-svelte';

  interface CursoConMensajes {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    no_leidos: number;
  }

  interface Props {
    total?: number;
    cursos?: CursoConMensajes[];
  }

  let { total = 0, cursos = [] }: Props = $props();
</script>

<section class="flex flex-col gap-4 rounded-xl border border-[#E5E7EB] bg-white p-4 sm:p-5">
  <div class="flex items-center gap-2">
    <MessageSquare class="h-5 w-5 text-[#002855]" />
    <h3 class="text-base font-semibold text-[#171A26]">Mensajería de curso</h3>
  </div>
  <div class="flex items-baseline gap-2">
    <span class="text-[28px] font-semibold leading-none tracking-tight text-[#1A1A24]">{total}</span>
    <span class="text-[13px] text-[#5A5E6E]">mensajes sin leer</span>
  </div>

  {#if cursos.length > 0}
    <div class="flex flex-col">
      {#each cursos as curso, i (curso.id_curso)}
        <Link
          href={`/docente/cursos/${curso.id_curso}/mensajeria`}
          class="flex items-center gap-2.5 py-[7px] no-underline {i < cursos.length - 1 ? 'border-b border-[#F1F5F9]' : ''}"
        >
          <span class="font-mono text-[11.5px] text-[#5A5E6E]">{curso.cod_curso}</span>
          <span class="min-w-0 flex-1 truncate text-[13px] text-[#1A1A24]">{curso.nombre}</span>
          <span class="text-[12.5px] font-semibold text-[#1A1A24]">{curso.no_leidos}</span>
        </Link>
      {/each}
    </div>
  {/if}

  <Link
    href="/docente/cursos"
    class="flex min-h-11 w-fit items-center gap-2 rounded-[10px] border border-[#D5D9E0] bg-white px-4 text-sm font-medium text-[#171A26] no-underline transition-colors hover:bg-[#F8FAFC]"
  >
    <Inbox class="h-4 w-4 text-[#5A5E6E]" />
    Abrir bandeja
  </Link>
</section>
