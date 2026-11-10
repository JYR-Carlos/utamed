<script lang="ts">
  /**
   * «Próximas entregas» del curso, en la columna lateral de Actividades: las
   * que todavía se pueden entregar, de la más cercana a la más lejana. Las que
   * vencen hoy o ya están atrasadas llevan el plazo en ámbar; las demás, sólo
   * la fecha.
   */
  import { Link } from '@inertiajs/svelte';
  import { CalendarDays, Clock } from 'lucide-svelte';
  import { fechaLimiteTexto, type ActividadAlumno } from '@/lib/actividadAlumno';
  import { pildora, tarjeta, tono } from '@/lib/dm';
  import { claveDia } from '@/utils/formatters';

  interface Props {
    idCurso: number;
    entregas?: ActividadAlumno[];
    /** Cuántas se muestran; la tabla de al lado tiene el resto. */
    maximo?: number;
    class?: string;
  }

  let { idCurso, entregas = [], maximo = 3, class: clase = '' }: Props = $props();

  const hoy = claveDia();
</script>

<section aria-labelledby="t-proximas" class="overflow-hidden {tarjeta} {clase}">
  <h2 id="t-proximas" class="border-b border-dm-linea px-5 py-3.5 text-lg leading-6 font-semibold">Próximas entregas</h2>
  {#if entregas.length > 0}
    <ul>
      {#each entregas.slice(0, maximo) as entrega (entrega.id_actividad)}
        {@const dia = entrega.fecha_limite.slice(0, 10)}
        <li class="border-b border-dm-linea last:border-b-0">
          <Link
            href={`/estudiante/cursos/${idCurso}/actividad/${entrega.id_actividad}`}
            class="flex flex-col gap-1.5 px-5 py-3.5 text-dm-ink no-underline hover:bg-dm-sutil"
          >
            <span class="text-[15px] leading-5 font-semibold">{entrega.nombre}</span>
            {#if dia < hoy}
              <span class="{pildora} {tono.warn} self-start"><Clock size={14} aria-hidden="true" />Atrasada · aún se recibe</span>
            {:else if dia === hoy}
              <span class="{pildora} {tono.warn} self-start"><Clock size={14} aria-hidden="true" />Vence hoy, 23:59</span>
            {:else}
              <span class="inline-flex items-center gap-1.5 text-[13px] leading-[18px] text-dm-ink-2">
                <CalendarDays size={14} aria-hidden="true" />{fechaLimiteTexto(entrega.fecha_limite, hoy)}
              </span>
            {/if}
          </Link>
        </li>
      {/each}
    </ul>
  {:else}
    <p class="px-5 py-4 text-sm text-dm-ink-2">No tienes entregas pendientes en este curso.</p>
  {/if}
</section>
