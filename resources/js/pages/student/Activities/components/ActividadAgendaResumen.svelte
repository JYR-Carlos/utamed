<script lang="ts">
  /**
   * Los últimos mensajes de la agenda de la actividad y el acceso a ella. La
   * conversación completa (entregas, evaluaciones, «Visto») vive en el modal;
   * aquí sólo lo que se escribió, para saber si hay algo nuevo.
   */
  import { PenLine } from 'lucide-svelte';
  import type { InteraccionItem } from '@/types/agenda';
  import { btnSecundario, pildora, tarjeta, tono } from '@/lib/dm';
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
  }

  let { interacciones, esGrupal, calificada, vista, onAbrir }: Props = $props();

  // Lo conversacional: mensajes, retroalimentación y apelaciones con texto.
  const CONVERSACION = ['Mensaje al profesor', 'Feedback', 'Solicitud de apelación', 'Resolución de apelación'];
  const MAXIMO = 3;

  const mensajes = $derived(
    interacciones.filter((i) => CONVERSACION.includes(i.tipo_interaccion) && i.mensaje.trim() !== '').slice(-MAXIMO),
  );
  const nuevos = $derived(vista ? 0 : interacciones.filter((i) => i.nuevo).length);

  function rol(i: InteraccionItem): string {
    if (i.es_de_docente) return 'Docente';
    if (i.es_propio) return 'Tú';
    return 'Tu grupo';
  }

  // «mar 20 oct, 10:12».
  function cuando(fecha: string): string {
    const d = new Date(fecha);
    return `${formatDiaCorto(d)}, ${d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })}`;
  }
</script>

<section aria-labelledby="t-agenda" class="{tarjeta} overflow-hidden">
  <div
    class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 border-b border-dm-linea px-4 py-3.5 lg:justify-between lg:gap-3 lg:px-6 lg:py-4"
  >
    <div class="flex items-center gap-2.5">
      <h2 id="t-agenda" class="text-lg leading-6 font-semibold">Agenda de la actividad</h2>
      {#if nuevos > 0}
        <span class="{pildora} {tono.accion} font-bold">{nuevos} {nuevos === 1 ? 'nuevo' : 'nuevos'}</span>
      {/if}
    </div>
    <span class="text-[13px] leading-[18px] text-dm-ink-3 max-lg:basis-full">
      {esGrupal ? 'Tu grupo y tu docente' : 'Tú y tu docente'}
    </span>
  </div>

  {#if mensajes.length > 0}
    <ol class="px-4 py-1 lg:px-6 lg:py-2">
      {#each mensajes as m (m.id_interaccion)}
        {@const nuevo = m.nuevo && !vista}
        <li class="flex gap-3 border-b border-dm-linea py-3 lg:py-3.5">
          <span
            aria-hidden="true"
            class="flex size-8 shrink-0 items-center justify-center rounded-full text-[13px] font-bold lg:size-9 {m.es_de_docente
              ? 'bg-dm-accion-suave text-dm-accion-texto'
              : 'bg-dm-sutil text-dm-ink-2'}"
          >
            {initials(m.emisor)}
          </span>
          <div class="flex min-w-0 flex-1 flex-col gap-0.5 lg:gap-1">
            <span class="flex flex-col lg:flex-row lg:flex-wrap lg:items-baseline lg:gap-2">
              <span class="text-sm font-semibold">{titleCase(m.emisor)}</span>
              <span class="text-[13px] leading-[18px] text-dm-ink-3">
                {rol(m)} · {cuando(m.fecha_emision)}{#if nuevo}<span class="font-bold text-dm-accion-texto lg:hidden"> · Nuevo</span>{/if}
              </span>
              {#if nuevo}<span class="text-[13px] leading-[18px] font-bold text-dm-accion-texto max-lg:hidden">Nuevo</span>{/if}
            </span>
            <p class="mt-1 text-[15px] leading-[22px] break-words whitespace-pre-line text-dm-ink lg:mt-0">{m.mensaje}</p>
          </div>
        </li>
      {/each}
    </ol>
  {:else}
    <p class="border-b border-dm-linea px-4 py-4 text-sm leading-5 text-dm-ink-2 lg:px-6">Todavía no hay mensajes.</p>
  {/if}

  <div class="flex flex-col gap-2.5 px-4 pt-3 pb-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-3 lg:px-6">
    <span class="text-[13px] leading-[18px] text-dm-ink-2">
      {#if calificada}
        ¿Dudas sobre la nota? Escríbele a tu docente aquí.
      {:else}
        Las preguntas sobre esta actividad van aquí; {esGrupal ? 'lo ve todo tu grupo' : 'las lee tu docente'}.
      {/if}
    </span>
    <button type="button" class="{btnSecundario} lg:min-h-10" onclick={onAbrir}>
      <PenLine size={16} aria-hidden="true" />{calificada ? 'Escribir en la agenda' : 'Abrir la agenda y escribir'}
    </button>
  </div>
</section>
