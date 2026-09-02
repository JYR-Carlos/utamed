<script lang="ts">
  /**
   * Mensajes de nivel curso (avisos del componente + canal con el equipo
   * docente) pendientes de leer, agrupados por curso.
   *
   * El alumno no tiene bandeja global: la mensajería se entra desde cada
   * curso, así que sin este aviso un mensaje puede quedar días sin abrirse.
   * Sólo cuenta mensajes de nivel curso — las consultas de una entrega viven
   * en la actividad (agenda), no aquí.
   */
  import { Link } from '@inertiajs/svelte';
  import { Mail, ChevronRight } from 'lucide-svelte';

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

<section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  <div class="flex items-center gap-2">
    <Mail class="h-4 w-4 text-slate-500" />
    <h3 class="text-[15px] font-semibold text-slate-900">Mensajes de tus cursos</h3>
    <span
      class="ml-auto shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-semibold {total > 0
        ? 'border-red-200 bg-red-50 text-red-700'
        : 'border-slate-200 bg-slate-100 text-slate-600'}"
    >
      {total}
    </span>
  </div>

  {#if cursos.length > 0}
    <div class="flex flex-col gap-2">
      {#each cursos as curso (curso.id_curso)}
        <Link
          href={`/estudiante/cursos/${curso.id_curso}/mensajeria`}
          class="flex items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 transition-colors hover:border-slate-300 hover:bg-slate-50"
        >
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="font-mono text-[10.5px] text-slate-500">{curso.cod_curso}</span>
            <span class="truncate text-[13px] font-semibold text-slate-900">{curso.nombre}</span>
          </div>
          <span
            class="ml-auto flex shrink-0 items-center gap-2 rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700"
          >
            {curso.no_leidos}
          </span>
          <ChevronRight class="h-4 w-4 shrink-0 text-slate-400" />
        </Link>
      {/each}
    </div>
  {:else}
    <div
      class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center"
    >
      <span class="text-[13px] font-semibold text-slate-900">Sin mensajes todavía</span>
      <p class="text-[12px] text-slate-500">
        Aparecerán cuando el equipo docente escriba en alguno de tus cursos.
      </p>
    </div>
  {/if}
</section>
