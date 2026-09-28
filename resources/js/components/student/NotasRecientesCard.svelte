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
   * Últimas notas y retroalimentaciones que el equipo docente dejó en las
   * actividades del alumno, una por actividad. Cubre las sumativas (nota) y
   * las formativas (resultado cualitativo o comentario).
   */
  import { Link } from '@inertiajs/svelte';
  import { Award, ChevronRight } from 'lucide-svelte';
  import { formatDate } from '@/utils/formatters';

  interface Props {
    items?: NotaReciente[];
  }

  let { items = [] }: Props = $props();

  const formatNota = (nota: number) => nota.toFixed(1).replace('.', ',');
</script>

<section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  <div class="flex items-center gap-2">
    <Award class="h-4 w-4 text-slate-500" />
    <h3 class="text-[15px] font-semibold text-slate-900">Notas y retroalimentaciones recientes</h3>
  </div>

  {#if items.length > 0}
    <div class="flex flex-col gap-2">
      {#each items as item (item.id_agenda)}
        <Link
          href={`/estudiante/cursos/${item.id_curso}/actividad/${item.id_actividad}`}
          class="flex items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 transition-colors hover:border-slate-300 hover:bg-slate-50"
        >
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="font-mono text-[10.5px] text-slate-500">
              {item.curso} · {formatDate(item.fecha.slice(0, 10))}
            </span>
            <span class="truncate text-[13px] font-semibold text-slate-900">{item.nombre}</span>
            {#if item.comentario}
              <p class="line-clamp-2 text-[12px] text-slate-600">{item.comentario}</p>
            {/if}
          </div>
          {#if item.nota !== null}
            <span
              class="shrink-0 rounded-md border px-2 py-0.5 font-mono text-[13px] font-bold tabular-nums {item.nota >= 4
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-red-200 bg-red-50 text-red-700'}"
            >
              {formatNota(item.nota)}
            </span>
          {:else if item.resultado}
            <span
              class="shrink-0 rounded-full border border-uta-blue/20 bg-uta-blue-light px-2 py-0.5 text-[11px] font-semibold text-uta-blue"
            >
              {item.resultado}
            </span>
          {:else}
            <span
              class="shrink-0 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"
            >
              Comentario
            </span>
          {/if}
          <ChevronRight class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
        </Link>
      {/each}
    </div>
  {:else}
    <div
      class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-slate-200 p-4 text-center"
    >
      <span class="text-[13px] font-semibold text-slate-900">Aún no hay notas ni comentarios</span>
      <p class="text-[12px] text-slate-500">
        Aparecerán aquí las notas y comentarios cuando tus docentes evalúen o retroalimenten tus entregas.
      </p>
    </div>
  {/if}
</section>
