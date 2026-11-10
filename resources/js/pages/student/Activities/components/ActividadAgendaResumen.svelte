<script lang="ts">
  /**
   * Pestaña Agenda: lo último que pasó en la agenda de la actividad
   * (mensajes, entregas y evaluaciones) y el acceso a la conversación
   * completa, que vive en la ventana de la agenda y es donde se escribe. Una
   * evaluación con rúbrica lleva a la pestaña Rúbrica con esa evaluación.
   */
  import { Award, ChevronRight, FileCheck, PenLine } from 'lucide-svelte';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';
  import { btnSecundario, btnSuave, pildora, tono } from '@/lib/dm';
  import { ENTREGA } from '@/lib/agendaHilo';
  import { puntajeTotal } from '@/lib/rubricaAlumno';
  import { formatDiaCorto, initials } from '@/utils/formatters';
  import { titleCase } from '@/lib/utils';

  interface Props {
    interacciones: InteraccionItem[];
    esGrupal: boolean;
    /** Ya calificada: la invitación es a preguntar por la nota. */
    calificada: boolean;
    /** El alumno abrió la agenda en esta visita: lo nuevo ya lo vio. */
    vista: boolean;
    onAbrir: () => void;
    onVerRubrica?: (detalle: RubricaDetalleEvent) => void;
  }

  let { interacciones, esGrupal, calificada, vista, onAbrir, onVerRubrica }: Props = $props();

  // Lo conversacional (con texto), las entregas y las evaluaciones.
  const CONVERSACION = ['Mensaje al profesor', 'Feedback', 'Solicitud de apelación', 'Resolución de apelación'];
  const MAXIMO = 5;

  const recientes = $derived(
    interacciones
      .filter(
        (i) =>
          (CONVERSACION.includes(i.tipo_interaccion) && i.mensaje.trim() !== '') ||
          (i.tipo_interaccion === ENTREGA && !i.fue_cancelada) ||
          i.tipo_interaccion === 'Evaluación',
      )
      .slice(-MAXIMO),
  );
  const nuevos = $derived(vista ? 0 : interacciones.filter((i) => i.nuevo).length);

  function rol(i: InteraccionItem): string {
    if (i.es_de_docente || i.tipo_interaccion === 'Evaluación') return 'Docente';
    if (i.es_propio) return 'Tú';
    return 'Tu grupo';
  }

  // «mar 20 oct, 10:12».
  function cuando(fecha: string): string {
    const d = new Date(fecha);
    return `${formatDiaCorto(d)}, ${d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })}`;
  }

  // «nota 6,2 · 32 de 36 pts», o el resultado de una formativa («Bueno»).
  function resultadoDe(i: InteraccionItem): string {
    const valor = i.evaluacion_obtenida ?? null;
    const n = Number(valor);
    const nota = valor == null || valor === '' ? null : Number.isFinite(n) ? `nota ${n.toFixed(1).replace('.', ',')}` : String(valor);
    const total = puntajeTotal(i.rubrica);
    const puntos = i.puntaje_obtenido != null && total > 0 ? `${String(i.puntaje_obtenido).replace('.', ',')} de ${total} pts` : null;
    return [nota, puntos].filter(Boolean).join(' · ');
  }

  function verRubrica(i: InteraccionItem) {
    onVerRubrica?.({
      rubrica: i.rubrica,
      puntaje_obtenido: i.puntaje_obtenido,
      retroalimentacion: i.mensaje,
      resultado: i.resultado,
      evaluador: i.emisor,
      fecha: i.fecha_emision,
    });
  }
</script>

<section aria-labelledby="t-agenda" class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center gap-2.5">
    <h2 id="t-agenda" class="text-xl leading-7 font-bold lg:text-[22px]">Agenda de la actividad</h2>
    {#if nuevos > 0}
      <span class="{pildora} {tono.accion} font-bold">{nuevos} {nuevos === 1 ? 'nuevo' : 'nuevos'}</span>
    {/if}
  </div>
  <p class="text-[15px] leading-[22px] text-dm-ink-2">
    {#if calificada}
      Tus preguntas, tu entrega y la evaluación, en orden. {esGrupal ? 'Las ve todo tu grupo y tu docente.' : 'Sólo las ven tú y tu docente.'}
    {:else}
      Las preguntas sobre esta actividad van aquí; {esGrupal ? 'las ve todo tu grupo y tu docente.' : 'sólo las ven tú y tu docente.'}
    {/if}
  </p>

  {#if recientes.length > 0}
    <ol class="rounded-xl border border-dm-linea px-4 lg:px-5">
      {#each recientes as m (m.id_interaccion)}
        {@const nuevo = m.nuevo && !vista}
        {@const docente = rol(m) === 'Docente'}
        <li class="flex gap-3 border-b border-dm-linea py-3.5 last:border-b-0">
          <span
            aria-hidden="true"
            class="flex size-8 shrink-0 items-center justify-center rounded-full text-[13px] font-bold lg:size-9 {docente
              ? 'bg-dm-accion-suave text-dm-accion-texto'
              : 'bg-dm-sutil text-dm-ink-2'}"
          >
            {initials(m.emisor)}
          </span>
          <div class="flex min-w-0 flex-1 flex-col gap-1">
            <span class="flex flex-col lg:flex-row lg:flex-wrap lg:items-baseline lg:gap-2">
              <span class="text-sm font-semibold">{titleCase(m.emisor)}</span>
              <span class="text-[13px] leading-[18px] text-dm-ink-3">
                {rol(m)} · {cuando(m.fecha_emision)}{#if nuevo}<span class="font-bold text-dm-accion-texto">{' '}· Nuevo</span>{/if}
              </span>
            </span>
            {#if m.tipo_interaccion === ENTREGA}
              <span class="flex items-start gap-2 text-sm leading-5 text-dm-ink-2">
                <FileCheck size={16} aria-hidden="true" class="mt-0.5 shrink-0 text-dm-accion-texto" />
                <span class="min-w-0 break-words">
                  Subió la entrega{#if m.archivo?.nombre_original}{' '}· <strong class="font-semibold text-dm-ink">{m.archivo.nombre_original}</strong>{/if}
                  {#if m.fue_reemplazada}<span class="text-dm-ink-3">{' '}(reemplazada)</span>{/if}
                </span>
              </span>
            {:else if m.tipo_interaccion === 'Evaluación'}
              {@const detalle = resultadoDe(m)}
              <span class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <span class="flex items-start gap-2 text-sm leading-5 text-dm-ink-2">
                  <Award size={16} aria-hidden="true" class="mt-0.5 shrink-0 text-dm-activo-texto" />
                  <span>
                    Evaluó la actividad{#if detalle}{' '}· <strong class="font-semibold text-dm-ink">{detalle}</strong>{/if}
                    {#if m.fue_reevaluada}<span class="text-dm-ink-3">{' '}(anterior)</span>{/if}
                  </span>
                </span>
                {#if (m.adjunta_rubrica || m.rubrica) && onVerRubrica}
                  <button type="button" class="{btnSuave} min-h-10" onclick={() => verRubrica(m)}>
                    Ver en la rúbrica<ChevronRight size={16} aria-hidden="true" />
                  </button>
                {/if}
              </span>
            {:else}
              <p class="text-[15px] leading-[22px] break-words whitespace-pre-line text-dm-ink">{m.mensaje}</p>
            {/if}
          </div>
        </li>
      {/each}
    </ol>
  {:else}
    <p class="rounded-xl border border-dm-linea px-4 py-4 text-sm leading-5 text-dm-ink-2 lg:px-5">Todavía no hay mensajes.</p>
  {/if}

  <div class="flex flex-col gap-2.5 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-3">
    <span class="text-[13px] leading-[18px] text-dm-ink-2">
      {calificada ? '¿Dudas sobre la nota? Escríbele a tu docente.' : 'La conversación completa y el campo para escribir están en la agenda.'}
    </span>
    <button type="button" class={btnSecundario} onclick={onAbrir}>
      <PenLine size={16} aria-hidden="true" />Abrir la agenda y escribir
    </button>
  </div>
</section>
