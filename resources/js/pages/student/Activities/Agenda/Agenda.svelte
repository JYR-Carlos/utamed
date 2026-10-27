<script lang="ts">
  /**
   * Cuerpo de la ventana «Agenda de la actividad» del alumno: el hilo con su
   * equipo docente (y su grupo) y el campo para escribir. La ventana misma
   * (ModalAlumno) y la rúbrica que se abre desde una evaluación las pone la
   * página.
   */
  import { router } from '@inertiajs/svelte';
  import { Info, Send } from 'lucide-svelte';
  import AgendaHiloAlumno from './AgendaHiloAlumno.svelte';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';

  interface Props {
    idCurso: number;
    codActividad: string;
    listado_interacciones: InteraccionItem[];
    esGrupal: boolean;
    /** La actividad aún espera la entrega: se recuerda que un mensaje no lo es. */
    entregaPendiente?: boolean;
    onEnviar: (mensaje: string) => void;
    onVerRubrica?: (detalle: RubricaDetalleEvent) => void;
  }

  let { idCurso, codActividad, listado_interacciones, esGrupal, entregaPendiente = false, onEnviar, onVerRubrica }: Props = $props();

  const MAXIMO = 2000;
  let mensaje = $state('');

  // Mientras está abierta, mensajes nuevos y «Visto por» llegan solos.
  $effect(() => {
    const poll = router.poll(3000, { only: ['listado_interacciones'], showProgress: false });
    return () => poll.stop();
  });

  function enviar(e?: SubmitEvent) {
    e?.preventDefault();
    const texto = mensaje.trim();
    if (!texto) return;
    onEnviar(texto);
    mensaje = '';
  }

  function alTeclear(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      enviar();
    }
  }

  function urlDescargaEntrega(idInteraccion: number, ver = false): string {
    const url = `/estudiante/cursos/${idCurso}/actividades/${codActividad}/entregas/${idInteraccion}/descargar`;
    return ver ? `${url}?ver=1` : url;
  }

  const contador = $derived(`${mensaje.length.toLocaleString('es-CL')} / ${MAXIMO.toLocaleString('es-CL')}`);
</script>

<AgendaHiloAlumno {listado_interacciones} {esGrupal} {urlDescargaEntrega} {onVerRubrica} />

<form class="flex shrink-0 flex-col gap-1.5 border-t border-dm-linea bg-dm-elevada px-3 pt-2.5 pb-3.5 lg:gap-2 lg:px-7 lg:pt-4 lg:pb-4.5" onsubmit={enviar}>
  {#if entregaPendiente}
    <p class="flex items-start gap-2 text-[13px] leading-[18px] text-dm-ink-2">
      <Info size={16} aria-hidden="true" class="mt-px shrink-0 text-dm-info" />
      Escribir aquí no entrega la actividad: la entrega se sube desde la página con «Subir entrega».
    </p>
  {/if}
  <label for="agenda-mensaje" class="max-lg:sr-only text-sm leading-5 font-semibold">Mensaje a tu equipo docente</label>
  <div class="flex items-end gap-2 lg:gap-3">
    <textarea
      id="agenda-mensaje"
      bind:value={mensaje}
      rows="2"
      maxlength={MAXIMO}
      aria-describedby="agenda-ayuda"
      onkeydown={alTeclear}
      class="min-h-14 flex-1 resize-none rounded-[10px] border border-dm-control bg-dm-tarjeta px-3 py-2.5 font-dm text-base leading-[22px] text-dm-ink placeholder:text-dm-ink-3 focus:border-dm-enlace focus:outline-2 focus:outline-offset-0 focus:outline-dm-enlace lg:min-h-16 lg:text-[15px]"
    ></textarea>
    <button
      type="submit"
      disabled={!mensaje.trim()}
      aria-label="Enviar"
      class="inline-flex size-12 shrink-0 cursor-pointer items-center justify-center gap-2 rounded-[10px] bg-dm-accion text-dm-sobre-accion transition-colors hover:bg-dm-accion-hover disabled:cursor-not-allowed disabled:opacity-50 lg:size-auto lg:min-h-11 lg:px-4.5 lg:py-2.5 lg:text-[15px] lg:font-bold"
    >
      <Send size={18} aria-hidden="true" /><span class="max-lg:hidden">Enviar</span>
    </button>
  </div>
  <div id="agenda-ayuda" class="flex justify-between gap-3 text-[13px] leading-[18px] text-dm-ink-3 max-lg:justify-end max-lg:pr-14">
    <span class="max-lg:hidden">Enter envía · Mayús + Enter hace un salto de línea</span>
    <span class="tabular-nums">{contador}</span>
  </div>
</form>
