<script lang="ts">
  /**
   * Aviso de mensajes de curso pendientes de leer.
   *
   * El alumno no tiene bandeja global: la mensajería se entra desde cada curso,
   * así que sin este aviso un mensaje del equipo docente puede quedar días sin
   * abrirse. Por eso el cuadro lista los cursos con pendientes y cada uno lleva
   * directo a su bandeja.
   *
   * Sólo cuenta los mensajes de nivel curso (avisos al componente y el canal con
   * el equipo docente). Las consultas de una entrega viven en la actividad.
   */
  import { Link } from '@inertiajs/svelte';
  import { MessagesSquare, ChevronRight } from 'lucide-svelte';

  interface CursoConMensajes {
    id_curso: number;
    nombre: string;
    no_leidos: number;
  }

  interface Props {
    /** Total de mensajes sin leer; con 0 el cuadro no se muestra. */
    total?: number;
    cursos?: CursoConMensajes[];
  }

  let { total = 0, cursos = [] }: Props = $props();

  const resumen = $derived(
    `${total} ${total === 1 ? 'mensaje sin leer' : 'mensajes sin leer'} · ` +
      `${cursos.length} ${cursos.length === 1 ? 'curso' : 'cursos'}`,
  );
</script>

{#if total > 0}
  <section
    class="mb-8 rounded-3xl border border-amber-200 bg-amber-50/60 p-6"
    aria-label="Mensajes de curso sin leer"
  >
    <div class="flex items-start gap-4">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white"
      >
        <MessagesSquare class="h-5 w-5" />
      </div>

      <div class="min-w-0 flex-1">
        <h2 class="text-base font-bold text-amber-900">
          Tienes mensajes de curso pendientes de leer
        </h2>
        <p class="mt-0.5 text-sm text-amber-800/80">{resumen}</p>

        <ul class="mt-4 flex flex-col gap-2">
          {#each cursos as curso (curso.id_curso)}
            <li>
              <Link
                href={`/estudiante/cursos/${curso.id_curso}/mensajeria`}
                class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 no-underline transition-colors hover:border-amber-300 hover:bg-amber-50"
              >
                <span class="truncate">{curso.nombre}</span>
                <span class="flex shrink-0 items-center gap-2">
                  <span
                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800"
                  >
                    {curso.no_leidos}
                  </span>
                  <ChevronRight class="h-4 w-4 text-amber-700" />
                </span>
              </Link>
            </li>
          {/each}
        </ul>
      </div>
    </div>
  </section>
{/if}
