<script lang="ts">
  /**
   * El hilo de la agenda como lo ve el alumno (guía dm). Lo de su lado (sus
   * mensajes, los de su grupo y las entregas) va a la derecha; lo del equipo
   * docente (mensajes y evaluaciones), a la izquierda. La lógica del hilo
   * (reemplazos, cancelaciones, reevaluaciones) es la misma del docente:
   * lib/agendaHilo.
   */
  import { tick } from 'svelte';
  import { Award, ChevronRight, CircleAlert, CircleCheck, Download, Eye, FileCheck, FileX2, Lock, RotateCcw } from 'lucide-svelte';
  import VistoPor from '@/components/mensajeria/VistoPor.svelte';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';
  import { agruparPorDia, ENTREGA, procesarHilo, type InteraccionDecorada } from '@/lib/agendaHilo';
  import { puntajeTotal } from '@/lib/rubricaAlumno';
  import { titleCase } from '@/lib/utils';
  import { formatBytes, parseFechaSoloDia } from '@/utils/formatters';

  interface Props {
    listado_interacciones: InteraccionItem[];
    esGrupal: boolean;
    urlDescargaEntrega: (idInteraccion: number, ver?: boolean) => string;
    onVerRubrica?: (detalle: RubricaDetalleEvent) => void;
  }

  let { listado_interacciones, esGrupal, urlDescargaEntrega, onVerRubrica }: Props = $props();

  const items = $derived(procesarHilo(listado_interacciones));
  const grupos = $derived(agruparPorDia(items));

  /** «Lunes 5 de octubre» (con el año si no es el actual). */
  function etiquetaDia(dia: string): string {
    const d = parseFechaSoloDia(dia);
    const semana = d.toLocaleDateString('es-CL', { weekday: 'long' });
    const mes = d.toLocaleDateString('es-CL', { month: 'long' });
    const agno = d.getFullYear() !== new Date().getFullYear() ? ` de ${d.getFullYear()}` : '';
    return `${semana.charAt(0).toUpperCase()}${semana.slice(1)} ${d.getDate()} de ${mes}${agno}`;
  }

  function hora(fecha: string | null | undefined): string {
    if (!fecha) return '';
    const d = new Date(fecha);
    return isNaN(d.getTime()) ? '' : d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  }

  const nota = (v: number | string | null | undefined) => {
    const n = Number(v);
    return v == null || v === '' || !Number.isFinite(n) ? String(v ?? '—') : n.toFixed(1).replace('.', ',');
  };

  const extension = (nombre?: string | null) => (nombre?.split('.').pop() ?? 'ARC').slice(0, 4).toUpperCase();

  // Lado del alumno: lo suyo y lo de su grupo. Lo del docente, enfrente.
  const deMiLado = (i: InteraccionDecorada) => !i.es_de_docente && i.tipo_interaccion !== 'Evaluación';

  function verRubrica(i: InteraccionDecorada) {
    onVerRubrica?.({
      rubrica: i.rubrica,
      puntaje_obtenido: i.puntaje_obtenido,
      retroalimentacion: i.mensaje,
      resultado: i.resultado,
      evaluador: i.emisor,
      fecha: i.fecha_emision,
    });
  }

  // Al abrir, al llegar un mensaje nuevo o si ya se estaba al fondo: al fondo.
  let lista = $state<HTMLDivElement | null>(null);
  let totalPrevio = 0;
  $effect(() => {
    const total = items.length;
    if (!lista || total === 0) return;
    const esInicial = totalPrevio === 0;
    const esNuevo = totalPrevio > 0 && total > totalPrevio;
    const cerca = lista.scrollHeight - lista.scrollTop - lista.clientHeight < 80;
    totalPrevio = total;
    if (esInicial || esNuevo || cerca) {
      tick().then(() =>
        requestAnimationFrame(() => lista?.scrollTo({ top: lista.scrollHeight, behavior: esNuevo ? 'smooth' : 'auto' })),
      );
    }
  });

  const PILDORA_FECHA =
    'rounded-full border border-dm-linea bg-dm-tarjeta px-3 py-1 text-[13px] leading-[18px] font-semibold text-dm-ink-2';
  const HORA = 'block text-right text-[13px] leading-[18px] text-dm-ink-3 tabular-nums';
  const BTN_CHICO =
    'inline-flex min-h-9 shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-dm-control bg-dm-tarjeta px-3 text-sm font-semibold text-dm-ink no-underline hover:bg-dm-sutil hover:text-dm-ink';
</script>

<!-- Una zona con scroll propio tiene que poder enfocarse para recorrerla con el teclado. -->
<!-- svelte-ignore a11y_no_noninteractive_tabindex -->
<div
  bind:this={lista}
  role="log"
  aria-label="Mensajes de la agenda"
  tabindex="0"
  class="min-h-0 flex-1 overflow-y-auto bg-dm-pagina px-4 py-4 outline-none lg:px-7 lg:py-5"
>
  {#if items.length === 0}
    <div class="flex h-full flex-col items-center justify-center gap-1 px-4 py-12 text-center">
      <span class="text-base font-semibold">Todavía no hay mensajes</span>
      <span class="max-w-90 text-sm leading-5 text-dm-ink-2">
        Escribe abajo tu pregunta sobre la actividad. La ve tu equipo docente{esGrupal ? ' y todo tu grupo' : ''}.
      </span>
    </div>
  {:else}
    <div class="flex flex-col gap-3.5">
      {#each grupos as grupo (grupo.dia)}
        <div class="flex justify-center"><span class={PILDORA_FECHA}>{etiquetaDia(grupo.dia)}</span></div>

        {#each grupo.items as item (item.id_interaccion)}
          {@const derecha = deMiLado(item)}
          {@const quien = item.es_propio ? 'Tú' : titleCase(item.emisor)}

          {#if item.tipo_interaccion === 'Cierre de actividad'}
            <div class="flex justify-center">
              <span class="inline-flex items-center gap-2 rounded-full bg-dm-off-s px-3.5 py-1.5 text-[13px] leading-[18px] font-semibold text-dm-off">
                <Lock size={14} aria-hidden="true" />Actividad cerrada · {item.mensaje || 'plazo finalizado'} · {hora(item.fecha_emision)}
              </span>
            </div>
          {:else if item.tipo_interaccion === ENTREGA}
            {@const historica = item.fue_cancelada || item.fue_reemplazada}
            {@const archivo = item.archivo}
            <div class="flex {derecha ? 'justify-end' : 'justify-start'}">
              <article class="w-110 max-w-full overflow-hidden rounded-xl border border-dm-linea bg-dm-tarjeta">
                <div class="flex items-center gap-2 px-3.5 py-2 {historica ? 'bg-dm-off-s text-dm-off' : 'bg-dm-accion-suave text-dm-accion-texto'}">
                  {#if item.fue_cancelada}<FileX2 size={16} aria-hidden="true" />{:else if item.fue_reemplazada}<RotateCcw
                      size={16}
                      aria-hidden="true"
                    />{:else}<FileCheck size={16} aria-hidden="true" />{/if}
                  <span class="min-w-0 truncate text-[13px] leading-[18px] font-bold">Entrega · {quien}</span>
                  {#if historica}
                    <span class="ml-auto shrink-0 rounded-full border border-dm-off px-2 text-xs leading-[18px] font-bold">
                      {item.fue_cancelada ? 'Cancelada' : 'Reemplazada'}
                    </span>
                  {:else}
                    <span class="ml-auto shrink-0 rounded-full bg-dm-accion px-2 text-xs leading-[18px] font-bold text-dm-sobre-accion">Vigente</span>
                  {/if}
                </div>
                <div class="flex flex-col gap-2.5 px-3.5 py-3">
                  {#if item.mensaje}
                    <p class="text-[15px] leading-[22px] break-words whitespace-pre-wrap {historica ? 'text-dm-ink-2' : ''}">{item.mensaje}</p>
                  {/if}
                  <div class="flex min-w-0 items-center gap-2.5 {historica ? '' : 'rounded-lg bg-dm-sutil px-2.5 py-2'}">
                    <span
                      aria-hidden="true"
                      class="flex size-9 shrink-0 items-center justify-center rounded-md text-[11px] font-extrabold tracking-[0.04em] {historica
                        ? 'bg-dm-off-s text-dm-off'
                        : 'bg-dm-accion text-dm-sobre-accion'}">{extension(archivo?.nombre_original)}</span
                    >
                    <span class="flex min-w-0 flex-col">
                      <span class="truncate text-sm leading-5 font-semibold {historica ? 'text-dm-ink-2' : ''}">
                        {archivo?.nombre_original ?? 'Archivo entregado'}
                      </span>
                      <span class="text-[13px] leading-[18px] {historica ? 'text-dm-ink-3' : 'text-dm-ink-2'}">
                        {[
                          archivo?.peso_bytes ? formatBytes(archivo.peso_bytes) : '',
                          item.fue_cancelada
                            ? `la retiró ${item.cancelado_por ? titleCase(item.cancelado_por) : 'el grupo'}${item.fecha_cancelacion ? ` a las ${hora(item.fecha_cancelacion)}` : ''}`
                            : item.fue_reemplazada
                              ? 'la reemplazó una entrega posterior'
                              : '',
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </span>
                    </span>
                    {#if archivo?.visualizable}
                      <a
                        href={urlDescargaEntrega(item.id_interaccion, true)}
                        target="_blank"
                        rel="noopener noreferrer"
                        class={historica
                          ? 'ml-auto inline-flex min-h-9 shrink-0 items-center px-2.5 text-sm font-semibold text-dm-enlace'
                          : `ml-auto ${BTN_CHICO}`}
                      >
                        {#if !historica}<Eye size={16} aria-hidden="true" />{/if}Ver<span class="sr-only"> (se abre en otra pestaña)</span>
                      </a>
                    {:else}
                      <a
                        href={urlDescargaEntrega(item.id_interaccion)}
                        class={historica
                          ? 'ml-auto inline-flex min-h-9 shrink-0 items-center px-2.5 text-sm font-semibold text-dm-enlace'
                          : `ml-auto ${BTN_CHICO}`}
                      >
                        {#if !historica}<Download size={16} aria-hidden="true" />{/if}Descargar
                      </a>
                    {/if}
                  </div>
                  <span class={HORA}>{hora(item.fecha_emision)}</span>
                </div>
              </article>
            </div>
          {:else if item.tipo_interaccion === 'Evaluación'}
            {@const opinion = item.evaluacion_obtenida != null ? String(item.evaluacion_obtenida) : null}
            {@const esFormativa = ['Bueno', 'Regular', 'Malo'].includes(opinion ?? '')}
            {@const valor = item.evaluacion_obtenida ?? item.puntaje_obtenido}
            {@const numero = !esFormativa && valor != null ? Number(valor) : null}
            {@const aprobada = esFormativa ? opinion !== 'Malo' : numero == null || isNaN(numero) || numero >= 4}
            {@const total = puntajeTotal(item.rubrica)}
            {@const cabecera = item.fue_reevaluada
              ? 'bg-dm-off-s text-dm-off'
              : esFormativa
                ? 'bg-dm-info-s text-dm-info'
                : aprobada
                  ? 'bg-dm-ok-s text-dm-ok'
                  : 'bg-dm-error-s text-dm-error'}
            <div class="flex {derecha ? 'justify-end' : 'justify-start'}">
              <article class="w-135 max-w-full overflow-hidden rounded-xl border border-dm-linea bg-dm-tarjeta">
                <div class="flex items-center gap-2 px-3.5 py-2 {cabecera}">
                  <Award size={16} aria-hidden="true" />
                  <span class="min-w-0 truncate text-[13px] leading-[18px] font-bold text-dm-ink">Evaluación · {titleCase(item.emisor)}</span>
                  {#if item.fue_reevaluada}
                    <span class="ml-auto shrink-0 rounded-full border border-dm-off px-2 text-xs leading-[18px] font-bold">Reevaluada</span>
                  {:else if esFormativa}
                    <span class="ml-auto shrink-0 rounded-full bg-dm-info px-2 text-xs leading-[18px] font-bold text-dm-tarjeta">{opinion}</span>
                  {:else}
                    <span
                      class="ml-auto inline-flex shrink-0 items-center gap-1 rounded-full px-2 text-xs leading-[18px] font-bold text-dm-tarjeta {aprobada
                        ? 'bg-dm-ok'
                        : 'bg-dm-error'}"
                    >
                      {#if aprobada}<CircleCheck size={13} aria-hidden="true" />Aprobada{:else}<CircleAlert
                          size={13}
                          aria-hidden="true"
                        />Reprobada{/if}
                    </span>
                  {/if}
                </div>
                <div class="flex flex-col gap-2.5 px-3.5 py-3">
                  {#if item.entrega_evaluada}
                    <span class="text-[13px] leading-[18px] text-dm-ink-2">
                      Califica la entrega <strong class="font-semibold break-all text-dm-ink">{item.entrega_evaluada.nombre_original ?? 'anterior'}</strong>
                    </span>
                  {/if}
                  {#if item.mensaje}
                    <p class="border-l-3 border-dm-amarillo pl-3 text-[15px] leading-[22px] break-words whitespace-pre-line">{item.mensaje}</p>
                  {/if}
                  <div class="flex flex-wrap items-center gap-3 rounded-[10px] border border-dm-linea px-3 py-2.5">
                    <span class="flex flex-wrap items-baseline gap-2">
                      {#if esFormativa}
                        <span class="text-[13px] leading-[18px] font-semibold text-dm-ink-2">Resultado</span>
                        <strong class="text-base font-bold">{opinion}</strong>
                      {:else}
                        <span class="text-[13px] leading-[18px] font-semibold text-dm-ink-2">Nota</span>
                        <strong
                          class="font-dm-cond text-[32px] leading-8 font-black tabular-nums {item.fue_reevaluada
                            ? 'text-dm-ink-3 line-through'
                            : aprobada
                              ? ''
                              : 'text-dm-error'}">{nota(valor)}</strong
                        >
                        {#if item.puntaje_obtenido != null && total > 0}
                          <span class="text-[13px] leading-[18px] text-dm-ink-2">{item.puntaje_obtenido} de {total} puntos</span>
                        {/if}
                        {#if item.fue_reevaluada}<span class="text-[13px] leading-[18px] text-dm-ink-3">(anterior)</span>{/if}
                      {/if}
                    </span>
                    {#if (item.adjunta_rubrica || item.rubrica) && onVerRubrica}
                      <button type="button" class="{BTN_CHICO} ml-auto min-h-10" onclick={() => verRubrica(item)}>
                        Ver rúbrica<ChevronRight size={16} aria-hidden="true" />
                      </button>
                    {/if}
                  </div>
                  <span class={HORA}>{hora(item.fecha_emision)}</span>
                </div>
              </article>
            </div>
          {:else}
            {@const docente = item.tipo_interaccion === 'Feedback' || item.es_de_docente}
            <div class="flex flex-col gap-1 {derecha ? 'items-end' : 'items-start'}">
              <div
                class="max-w-[min(560px,88%)] px-3.5 py-2.5 {item.es_propio
                  ? 'rounded-[14px_14px_4px_14px] bg-dm-accion-suave'
                  : docente
                    ? 'rounded-[14px_14px_14px_4px] border border-dm-linea bg-dm-tarjeta'
                    : 'rounded-[14px_14px_4px_14px] border border-dm-linea bg-dm-tarjeta'}"
              >
                {#if !item.esConsecutivo}
                  {#if item.es_propio}
                    <span class="block text-[13px] leading-[18px] font-bold text-dm-accion-texto">Tú</span>
                  {:else}
                    <span class="flex flex-wrap items-center gap-2">
                      <span class="text-[13px] leading-[18px] font-bold">{quien}</span>
                      <span
                        class="rounded-full px-2 text-xs leading-[18px] font-bold {docente
                          ? 'bg-dm-accion-suave text-dm-accion-texto'
                          : 'bg-dm-sutil text-dm-ink-2'}">{docente ? 'Docente' : 'Tu grupo'}</span
                      >
                    </span>
                  {/if}
                {/if}
                <p class="mt-0.5 text-[15px] leading-[22px] break-words whitespace-pre-wrap">{item.mensaje}</p>
                {#if (item.adjunta_rubrica || item.rubrica) && onVerRubrica}
                  <button type="button" class="{BTN_CHICO} mt-2" onclick={() => verRubrica(item)}>
                    Ver rúbrica<ChevronRight size={16} aria-hidden="true" />
                  </button>
                {/if}
                <span class="{HORA} mt-1">{hora(item.fecha_emision)}</span>
              </div>
            </div>
          {/if}

          {#if item.visto_por && item.visto_por.length > 0}
            <VistoPor lectores={item.visto_por} alinear={derecha ? 'derecha' : 'izquierda'} class="-mt-2 px-1" />
          {/if}
        {/each}
      {/each}
    </div>
  {/if}
</div>
