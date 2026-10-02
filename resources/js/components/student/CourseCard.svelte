<script lang="ts">
  import { useFormatName, useInitials } from '@/hooks';
  import { Link } from '@inertiajs/svelte';
  import { MessageSquare } from 'lucide-svelte';

  interface Props {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    /** Código de la asignatura (p. ej. DM095); puede faltar si el curso no tiene asignación. */
    cod_asignatura?: string | null;
    profesor: string;
    /** Mensajes de nivel curso sin leer (curso.mensaje), si los hay. */
    no_leidos?: number;
  }

  let { id_curso, nombre, cod_curso, cod_asignatura, profesor, no_leidos = 0 }: Props = $props();

  const { formatName } = useFormatName();
  const { getInitials } = useInitials();

  // El alumno reconoce el curso por el código de la asignatura; el del curso
  // queda sólo como respaldo cuando no hay asignatura asociada.
  let codigo = $derived(cod_asignatura || cod_curso || '');
  let sinDocente = $derived(profesor === '(sin docente asignado)');
</script>

<!-- La tarjeta ya no es un único <Link>: lleva dos destinos (curso y
     mensajería) y un enlace no puede ir dentro de otro. -->
<article
  class="group flex h-full flex-col gap-2.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
>
  <Link href={`/estudiante/cursos/${id_curso}`} class="flex items-start gap-2.5">
    <div
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light text-[13px] font-bold text-uta-blue"
    >
      {getInitials(nombre)}
    </div>
    <div class="flex min-w-0 flex-col gap-0.5">
      {#if codigo}
        <span class="font-mono text-[11px] text-slate-500">{codigo}</span>
      {/if}
      <span
        class="line-clamp-2 text-[15px] font-semibold leading-tight text-slate-900 group-hover:text-uta-blue"
      >
        {formatName(nombre)}
      </span>
    </div>
  </Link>

  <div class="mt-auto flex items-center gap-2 border-t border-slate-100 pt-2.5">
    {#if sinDocente}
      <span class="min-w-0 flex-1 text-[12.5px] text-slate-500">
        Aún no hay docente asignado a este curso.
      </span>
    {:else}
      <span class="min-w-0 flex-1 truncate text-[12.5px] text-slate-800">{formatName(profesor)}</span>
      <Link
        href={`/estudiante/cursos/${id_curso}/mensajeria`}
        class="relative inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-[12px] font-medium text-slate-700 transition-colors hover:border-uta-blue/30 hover:bg-uta-blue-light hover:text-uta-blue"
        aria-label={no_leidos > 0
          ? `Mensajería del curso, ${no_leidos} sin leer`
          : 'Mensajería del curso'}
      >
        <MessageSquare class="h-3.5 w-3.5" />
        Mensajes
        {#if no_leidos > 0}
          <span
            class="rounded-full border border-red-200 bg-red-50 px-1.5 text-[10.5px] font-semibold text-red-700"
          >
            {no_leidos}
          </span>
        {/if}
      </Link>
    {/if}
  </div>
</article>
