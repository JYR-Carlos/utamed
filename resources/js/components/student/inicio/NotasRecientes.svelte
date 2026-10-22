<script lang="ts" module>
  export interface NotaReciente {
    id_agenda: number;
    id_actividad: number;
    id_curso: number;
    curso: string;
    nombre: string;
    es_sumativa: boolean;
    tipo: 'evaluacion' | 'retroalimentacion';
    nota: number | null;
    resultado: string | null;
    comentario: string | null;
    fecha: string;
  }
</script>

<script lang="ts">
  /**
   * Últimas notas y retroalimentaciones del equipo docente, una por actividad.
   * Las sumativas muestran la nota; las formativas, un visto con su resultado.
   * Cada fila abre la agenda de la actividad.
   */
  import { Link } from '@inertiajs/svelte';
  import { CircleCheck } from 'lucide-svelte';
  import { formatDiaCorto } from '@/utils/formatters';
  import { tarjeta } from '@/lib/dm';

  interface Props {
    items?: NotaReciente[];
    colorDe: (idCurso: number) => string;
  }

  let { items = [], colorDe }: Props = $props();

  const formatNota = (nota: number) => nota.toFixed(1).replace('.', ',');

  function estado(n: NotaReciente): string {
    if (n.nota !== null) return n.nota >= 4 ? 'Aprobada' : 'Reprobada';
    return n.resultado ?? 'Con comentario';
  }
</script>

<section aria-labelledby="t-notas" class="overflow-hidden {tarjeta}">
  <div class="border-b border-dm-linea px-4 py-3.5 lg:px-5 lg:py-4">
    <h2 id="t-notas" class="text-lg leading-6 font-semibold">Notas recientes</h2>
  </div>
  {#each items as n (n.id_agenda)}
    <Link
      href={`/estudiante/cursos/${n.id_curso}/actividad/${n.id_actividad}?abrir=agenda`}
      style={colorDe(n.id_curso)}
      class="flex items-start gap-3.5 border-b border-dm-linea px-4 py-3.5 text-dm-ink no-underline last:border-b-0 hover:bg-dm-sutil lg:gap-4 lg:px-5 lg:py-4"
    >
      {#if n.nota !== null}
        <span class="w-12 shrink-0 font-dm-cond text-[32px] leading-9 font-extrabold tabular-nums lg:w-14">
          {formatNota(n.nota)}
        </span>
      {:else}
        <span class="flex h-9 w-12 shrink-0 items-center text-dm-ok lg:w-14">
          <CircleCheck size={26} aria-hidden="true" />
        </span>
      {/if}
      <span class="flex min-w-0 flex-col gap-1">
        <span class="text-[15px] leading-5 font-semibold lg:text-sm">{n.nombre}</span>
        <span class="text-[13px] leading-[18px] text-dm-ink-2">
          <span class="font-bold text-(--curso-t)">{n.curso}</span> · {estado(n)} · {formatDiaCorto(n.fecha.slice(0, 10))}
        </span>
        {#if n.comentario}
          <span class="line-clamp-2 text-[13px] leading-[18px] text-dm-ink-2">«{n.comentario}»</span>
        {/if}
      </span>
    </Link>
  {:else}
    <div class="flex flex-col gap-1 px-5 py-5">
      <span class="text-sm font-semibold">Aún no hay notas ni comentarios</span>
      <span class="text-[13px] leading-[18px] text-dm-ink-2">
        Aparecerán aquí cuando tus docentes evalúen o retroalimenten tus entregas.
      </span>
    </div>
  {/each}
</section>
