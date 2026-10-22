<script lang="ts">
  /** Mensajes de curso (curso.mensaje) que el alumno aún no abre, por curso. Cada fila lleva a la mensajería del curso. */
  import { Link } from '@inertiajs/svelte';
  import { ChevronRight } from 'lucide-svelte';
  import { pildora, tarjeta, tono } from '@/lib/dm';

  interface CursoConMensajes {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    cod_asignatura?: string | null;
    no_leidos: number;
  }

  interface Props {
    total: number;
    cursos: CursoConMensajes[];
    colorDe: (idCurso: number) => string;
  }

  let { total, cursos, colorDe }: Props = $props();
</script>

<section aria-labelledby="t-mensajes" class="overflow-hidden {tarjeta}">
  <div class="flex items-center justify-between gap-2 border-b border-dm-linea px-4 py-3.5 lg:px-5 lg:py-4">
    <h2 id="t-mensajes" class="text-lg leading-6 font-semibold">Mensajes sin leer</h2>
    <span class="{pildora} {tono.accion} font-bold">{total}</span>
  </div>
  {#each cursos.filter((c) => c.no_leidos > 0) as c (c.id_curso)}
    <Link
      href={`/estudiante/cursos/${c.id_curso}?seccion=mensajeria`}
      style={colorDe(c.id_curso)}
      class="flex min-h-13 items-center gap-3 border-b border-dm-linea px-4 py-2 text-dm-ink no-underline last:border-b-0 hover:bg-dm-sutil lg:px-5"
    >
      <span class="{pildora} {tono.curso} shrink-0 font-bold">
        <span aria-hidden="true" class="size-2 rounded-full bg-(--curso)"></span>{c.cod_asignatura || c.cod_curso}
      </span>
      <span class="min-w-0 flex-1 truncate text-sm font-medium">{c.nombre}</span>
      <span class="shrink-0 text-[13px] leading-[18px] font-semibold text-dm-accion-texto">
        {c.no_leidos === 1 ? '1 nuevo' : `${c.no_leidos} nuevos`}
      </span>
      <ChevronRight size={16} aria-hidden="true" class="shrink-0 text-dm-ink-3" />
    </Link>
  {/each}
</section>
