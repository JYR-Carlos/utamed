<script lang="ts">
  /**
   * AgendaHilo.svelte
   *
   * Componente compartido de visualización del hilo conversacional de la Agenda.
   * Usado tanto por la vista de Estudiante (Agenda.svelte) como de Docente (AgendaDocente.svelte).
   */
  import type { Rubrica } from '@/types/rubrica';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';
  import VistoPor from '@/components/mensajeria/VistoPor.svelte';
  import { formatBytes, formatFechaTextoLargo } from '@/utils/formatters';
  import {
    Award,
    ChevronRight,
    Download,
    Eye,
    FileX2,
    Lock,
    MessageSquareOff,
    PackageCheck,
  } from 'lucide-svelte';

  export type { InteraccionItem, RubricaDetalleEvent };

  interface InteraccionDecorada extends InteraccionItem {
    esConsecutivo?: boolean;
    marcaTiempoSeparador?: string | null;
  }

  interface Props {
    listado_interacciones: InteraccionItem[];
    esDocente?: boolean;
    urlDescargaEntrega: (idInteraccion: number, ver?: boolean) => string;
    onVerRubrica?: (detalle: RubricaDetalleEvent) => void;
    isLoading?: boolean;
    errorMensaje?: string | null;
  }

  let {
    listado_interacciones,
    esDocente = false,
    urlDescargaEntrega,
    onVerRubrica,
    isLoading = false,
    errorMensaje = null,
  }: Props = $props();

  let listaRef = $state<HTMLDivElement | null>(null);

  const ENTREGA = 'Entrega de archivo';
  const CANCELACION = 'Cancelación de entrega';

  function formatHora(val: string | null | undefined): string {
    if (!val) return '';
    const d = new Date(val);
    if (!isNaN(d.getTime())) {
      const h = String(d.getHours()).padStart(2, '0');
      const m = String(d.getMinutes()).padStart(2, '0');
      return `${h}:${m} hrs`;
    }
    const match = String(val).match(/(\d{1,2}):(\d{2})/);
    return match ? `${match[1].padStart(2, '0')}:${match[2]} hrs` : '';
  }

  function esLadoDerecho(item: InteraccionDecorada): boolean {
    if (esDocente) {
      // Para el docente: a la derecha van sus mensajes emitidos (Feedback / Docente) y Evaluaciones.
      // A la izquierda van las entregas y mensajes de los estudiantes.
      return Boolean(item.es_de_docente || item.tipo_interaccion === 'Feedback' || item.tipo_interaccion === 'Evaluación' || item.es_propio);
    }
    // Para el estudiante: a la derecha van sus entregas y mensajes de alumnos (propios o compañeros).
    // A la izquierda van los mensajes del docente y evaluaciones.
    return !item.es_de_docente && item.tipo_interaccion !== 'Evaluación';
  }

  const interaccionesProcesadas = $derived.by((): InteraccionDecorada[] => {
    const lista: InteraccionDecorada[] = listado_interacciones.map((item) => ({ ...item }));

    // Paso 1: Mutar cancelaciones de entrega
    for (let i = 0; i < lista.length; i++) {
      const item = lista[i];
      if (item.tipo_interaccion === CANCELACION) {
        let entregaIndex = -1;
        if (item.uuid_archivo) {
          entregaIndex = lista.slice(0, i).findLastIndex(
            (prev) => prev.tipo_interaccion === ENTREGA && prev.uuid_archivo === item.uuid_archivo,
          );
        }
        if (entregaIndex === -1) {
          entregaIndex = lista.slice(0, i).findLastIndex(
            (prev) => prev.tipo_interaccion === ENTREGA && !prev.fue_cancelada,
          );
        }

        if (entregaIndex !== -1) {
          lista[entregaIndex].fue_cancelada = true;
          lista[entregaIndex].fecha_cancelacion = item.fecha_emision;
          lista[entregaIndex].cancelado_por = item.emisor;
        }
      }
    }

    const visibles = lista.filter((item) => item.tipo_interaccion !== CANCELACION);

    // Paso 2: Consecutividad y marcas de tiempo
    for (let i = 0; i < visibles.length; i++) {
      const actual = visibles[i];
      const anterior = i > 0 ? visibles[i - 1] : null;

      if (anterior) {
        const tActual = new Date(actual.fecha_emision).getTime();
        const tAnterior = new Date(anterior.fecha_emision).getTime();
        const diffMinutos = Math.abs(tActual - tAnterior) / (1000 * 60);

        const diaActual = actual.fecha_emision.slice(0, 10);
        const diaAnterior = anterior.fecha_emision.slice(0, 10);
        const mismoDia = diaActual === diaAnterior;

        if (diffMinutos >= 30) {
          actual.marcaTiempoSeparador = mismoDia
            ? formatHora(actual.fecha_emision)
            : `${formatFechaTextoLargo(diaActual)} · ${formatHora(actual.fecha_emision)}`;
        }

        const esMensajeTexto =
          (actual.tipo_interaccion === 'Mensaje al profesor' || actual.tipo_interaccion === 'Feedback') &&
          (anterior.tipo_interaccion === 'Mensaje al profesor' || anterior.tipo_interaccion === 'Feedback');
        if (
          !actual.marcaTiempoSeparador &&
          mismoDia &&
          actual.emisor === anterior.emisor &&
          Boolean(actual.es_propio) === Boolean(anterior.es_propio) &&
          actual.es_de_docente === anterior.es_de_docente &&
          esMensajeTexto
        ) {
          actual.esConsecutivo = true;
        }
      }
    }

    return visibles;
  });

  interface Grupo {
    etiqueta: string;
    items: InteraccionDecorada[];
  }

  const gruposPorFecha = $derived.by((): Grupo[] => {
    const grupos: Grupo[] = [];
    for (const item of interaccionesProcesadas) {
      const dia = item.fecha_emision.slice(0, 10);
      const etiqueta = formatFechaTextoLargo(dia);
      const ultimo = grupos[grupos.length - 1];
      if (ultimo && ultimo.etiqueta === etiqueta) {
        ultimo.items.push(item);
      } else {
        grupos.push({ etiqueta, items: [item] });
      }
    }
    return grupos;
  });

  $effect(() => {
    interaccionesProcesadas;
    if (listaRef) {
      listaRef.scrollTop = listaRef.scrollHeight;
    }
  });
</script>

<div bind:this={listaRef} class="flex-1 overflow-y-auto px-4 sm:px-6 py-4 space-y-2 bg-slate-50/50">
  {#if isLoading}
    <div class="flex flex-col items-center justify-center py-16 text-slate-400 gap-2">
      <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-[#002F6C]"></div>
      <span class="text-xs">Cargando interacciones…</span>
    </div>
  {:else if errorMensaje}
    <div class="flex items-center justify-center py-16 text-xs text-red-500 text-center px-4">
      {errorMensaje}
    </div>
  {:else}
    {#each gruposPorFecha as grupo (grupo.etiqueta)}
      <div class="flex justify-center py-1.5">
        <span class="rounded-full bg-slate-200/80 px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-slate-600">
          {grupo.etiqueta}
        </span>
      </div>

      {#each grupo.items as item (item.id_interaccion)}
        <!-- Marca de tiempo intermedia sin borde cuando hay salto >= 30 min -->
        {#if item.marcaTiempoSeparador}
          <div class="flex justify-center py-1.5">
            <span class="text-[11px] font-medium text-slate-400 tracking-wide select-none">
              {item.marcaTiempoSeparador}
            </span>
          </div>
        {/if}

        <!-- CASO A: Cierre de actividad (Centro) -->
        {#if item.tipo_interaccion === 'Cierre de actividad'}
          <div class="flex justify-center">
            <div class="flex items-center gap-1.5 rounded-xl border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 shadow-2xs">
              <Lock class="h-3 w-3 text-slate-500 shrink-0" />
              <span>Actividad cerrada · {item.mensaje || 'Plazo finalizado'}</span>
              <span class="font-mono text-[10px] text-slate-400">· {formatHora(item.fecha_emision)}</span>
            </div>
          </div>

        <!-- CASO B: Entrega Cancelada (Beige) -->
        {:else if item.tipo_interaccion === ENTREGA && item.fue_cancelada}
          <div class="flex {esLadoDerecho(item) ? 'justify-end' : 'justify-start'}">
            <div class="w-fit max-w-[66.6%] rounded-2xl border-2 border-[#D2CCC0] bg-[#F4F1EA] shadow-xs overflow-hidden">
              <div class="flex items-center justify-between border-b border-[#D2CCC0] bg-[#E4DFD5]/80 px-3.5 py-1.5 text-xs gap-2.5">
                <div class="flex items-center gap-1.5 min-w-0">
                  <FileX2 class="h-3.5 w-3.5 text-slate-600 shrink-0" />
                  <span class="truncate font-bold text-slate-800 text-[11.5px]" title="Entrega · {item.emisor}">
                    Entrega · {item.emisor}
                  </span>
                  <span class="rounded-full border border-[#CBC4B7] bg-[#DCD6CA] px-1.5 py-0.2 text-[9px] font-bold uppercase tracking-wider text-slate-700 shrink-0">
                    Cancelada
                  </span>
                </div>
              </div>

              <div class="p-3 space-y-2">
                {#if item.mensaje}
                  <p class="text-[12.5px] text-slate-600 italic leading-snug break-words">
                    "{item.mensaje}"
                  </p>
                {/if}

                <!-- Ficha de archivo cancelado histórico -->
                <div class="flex items-center justify-between gap-2.5 rounded-xl border border-[#D6CFC3] bg-white/75 p-2">
                  <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-slate-100 font-mono text-[9px] font-bold text-slate-400 line-through">
                      {(item.archivo?.nombre_original?.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
                    </span>
                    <div class="min-w-0">
                      <p class="truncate text-[11.5px] font-semibold text-slate-500 line-through" title={item.archivo?.nombre_original ?? ''}>
                        {item.archivo?.nombre_original ?? 'Archivo entregado'}
                      </p>
                      <p class="text-[9.5px] text-slate-500 font-medium">
                        {#if item.fecha_cancelacion}
                          Cancelada a las {formatHora(item.fecha_cancelacion)}
                        {:else}
                          Cancelada · Retirado
                        {/if}
                      </p>
                    </div>
                  </div>

                  <div class="flex items-center gap-1 shrink-0">
                    {#if item.archivo?.visualizable}
                      <a
                        href={urlDescargaEntrega(item.id_interaccion, true)}
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold text-slate-700 bg-white/90 border border-[#D2CCC0] transition-colors hover:bg-white no-underline shadow-2xs"
                        title="Ver archivo cancelado"
                      >
                        <Eye class="h-3 w-3 text-slate-600" />
                        Ver
                      </a>
                    {:else}
                      <a
                        href={urlDescargaEntrega(item.id_interaccion)}
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold text-slate-700 bg-white/90 border border-[#D2CCC0] transition-colors hover:bg-white no-underline shadow-2xs"
                        title="Descargar archivo cancelado"
                      >
                        <Download class="h-3 w-3 text-slate-600" />
                        Descargar
                      </a>
                    {/if}
                  </div>
                </div>

                <div class="flex justify-end pt-0.5">
                  <span class="font-mono text-[10px] text-slate-400 select-none">
                    {formatHora(item.fecha_emision)}
                  </span>
                </div>
              </div>
            </div>
          </div>

        <!-- CASO C: Entrega Activa (Amarillo cálido) -->
        {:else if item.tipo_interaccion === ENTREGA}
          <div class="flex {esLadoDerecho(item) ? 'justify-end' : 'justify-start'}">
            <div class="w-fit max-w-[66.6%] rounded-2xl border-2 border-amber-300 bg-[#FEFCE8] shadow-xs overflow-hidden">
              <div class="flex items-center justify-between border-b border-amber-200 bg-amber-100/80 px-3.5 py-1.5 text-xs gap-2.5">
                <div class="flex items-center gap-1.5 min-w-0">
                  <PackageCheck class="h-3.5 w-3.5 text-amber-800 shrink-0" />
                  <span class="truncate font-bold text-amber-900 text-[11.5px]" title="Entrega · {item.emisor}">
                    Entrega · {item.emisor}
                  </span>
                  {#if esDocente}
                    {#if item.tiene_evaluacion}
                      <span class="rounded-full border border-emerald-300 bg-emerald-100 px-1.5 py-0.2 text-[9px] font-bold uppercase tracking-wider text-emerald-800 shrink-0">
                        Evaluada
                      </span>
                    {:else}
                      <span class="rounded-full border border-amber-300 bg-amber-200/80 px-1.5 py-0.2 text-[9px] font-bold uppercase tracking-wider text-amber-900 shrink-0">
                        Pendiente
                      </span>
                    {/if}
                  {/if}
                </div>
              </div>

              <div class="p-3 space-y-2">
                {#if item.mensaje}
                  <p class="text-[12.5px] text-slate-800 leading-snug break-words">{item.mensaje}</p>
                {/if}

                <!-- Ficha con acción inteligente según MIME -->
                <div class="flex items-center justify-between gap-2.5 rounded-xl border border-amber-200 bg-white/90 p-2 shadow-2xs">
                  <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-amber-300 bg-amber-100 font-mono text-[9px] font-black text-amber-900">
                      {(item.archivo?.nombre_original?.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
                    </span>
                    <div class="min-w-0">
                      <p class="truncate text-[11.5px] font-bold text-slate-800" title={item.archivo?.nombre_original ?? ''}>
                        {item.archivo?.nombre_original ?? 'Archivo entregado'}
                      </p>
                      {#if item.archivo?.peso_bytes}
                        <p class="font-mono text-[9.5px] text-slate-500">{formatBytes(item.archivo.peso_bytes)}</p>
                      {/if}
                    </div>
                  </div>

                  <div class="flex items-center gap-1 shrink-0">
                    {#if item.archivo?.visualizable}
                      <a
                        href={urlDescargaEntrega(item.id_interaccion, true)}
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold text-amber-900 transition-colors hover:bg-amber-50 no-underline"
                        title="Ver entrega en línea"
                      >
                        <Eye class="h-3.5 w-3.5" />
                        Ver
                      </a>
                    {:else}
                      <a
                        href={urlDescargaEntrega(item.id_interaccion)}
                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold text-amber-900 transition-colors hover:bg-amber-50 no-underline"
                        title="Descargar archivo"
                      >
                        <Download class="h-3.5 w-3.5" />
                        Descargar
                      </a>
                    {/if}
                  </div>
                </div>

                <div class="flex justify-end pt-0.5">
                  <span class="font-mono text-[10px] text-amber-800/80 select-none">
                    {formatHora(item.fecha_emision)}
                  </span>
                </div>
              </div>
            </div>
          </div>

        <!-- CASO D: Evaluación (Verde si aprobada / Roja si reprobada) -->
        {:else if item.tipo_interaccion === 'Evaluación'}
          {@const esAprobada = item.puntaje_obtenido != null ? item.puntaje_obtenido >= 4.0 : true}
          <div class="flex {esLadoDerecho(item) ? 'justify-end' : 'justify-start'}">
            <div
              class="w-fit max-w-[66.6%] rounded-2xl border-2 shadow-xs overflow-hidden {esAprobada
                ? 'border-emerald-400/70 bg-[#F0FDF4]'
                : 'border-red-300/80 bg-[#FEF2F2]'}"
            >
              <div
                class="flex items-center justify-between border-b px-3.5 py-1.5 text-xs gap-2.5 {esAprobada
                  ? 'border-emerald-200 bg-emerald-100/80'
                  : 'border-red-200 bg-red-100/80'}"
              >
                <div class="flex items-center gap-1.5 min-w-0">
                  <Award class="h-3.5 w-3.5 shrink-0 {esAprobada ? 'text-emerald-800' : 'text-red-700'}" />
                  <span class="truncate font-bold text-[11.5px] {esAprobada ? 'text-emerald-950' : 'text-red-950'}" title="Evaluación · {item.emisor}">
                    Evaluación · {item.es_propio && esDocente ? 'Tú' : item.emisor}
                  </span>
                  <span
                    class="rounded-full border px-1.5 py-0.2 text-[9px] font-bold uppercase tracking-wider shrink-0 {esAprobada
                      ? 'border-emerald-300 bg-emerald-200/60 text-emerald-800'
                      : 'border-red-300 bg-red-200/60 text-red-700'}"
                  >
                    {esAprobada ? 'Aprobada' : 'Reprobada'}
                  </span>
                </div>
              </div>

              <div class="p-3 space-y-2.5">
                {#if item.entrega_evaluada}
                  <p class="text-[11.5px] text-slate-600">
                    Califica la entrega <strong class="font-semibold text-slate-800 truncate inline-block max-w-[160px] align-bottom" title={item.entrega_evaluada.nombre_original ?? ''}>{item.entrega_evaluada.nombre_original ?? 'anterior'}</strong>
                  </p>
                {/if}

                {#if item.mensaje}
                  <p class="text-[12.5px] text-slate-800 leading-snug break-words">{item.mensaje}</p>
                {/if}

                <!-- Chip de Calificación y Acceso a Rúbrica -->
                <div class="flex items-center justify-between gap-3 rounded-xl border bg-white/90 p-2.5 {esAprobada ? 'border-emerald-200' : 'border-red-200'}">
                  <div class="flex items-baseline gap-1.5">
                    <span class="text-[11px] uppercase font-bold tracking-wider {esAprobada ? 'text-emerald-800' : 'text-red-800'}">Nota:</span>
                    <span class="text-xl font-black leading-none {esAprobada ? 'text-emerald-700' : 'text-red-700'}">
                      {item.puntaje_obtenido ?? '-'}
                    </span>
                  </div>

                  {#if (item.adjunta_rubrica || item.rubrica) && onVerRubrica}
                    <button
                      onclick={() => {
                        onVerRubrica?.({
                          rubrica: item.rubrica,
                          puntaje_obtenido: item.puntaje_obtenido,
                          retroalimentacion: item.mensaje,
                          resultado: item.resultado,
                          evaluador: item.emisor,
                          fecha: item.fecha_emision,
                        });
                      }}
                      class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold text-white shadow-2xs transition-colors cursor-pointer {esAprobada
                        ? 'bg-emerald-700 hover:bg-emerald-800'
                        : 'bg-red-700 hover:bg-red-800'}"
                    >
                      <span>Ver Rúbrica</span>
                      <ChevronRight class="h-3 w-3" />
                    </button>
                  {/if}
                </div>

                <div class="flex justify-end pt-0.5">
                  <span class="font-mono text-[10px] {esAprobada ? 'text-emerald-800/80' : 'text-red-700/80'} select-none">
                    {formatHora(item.fecha_emision)}
                  </span>
                </div>
              </div>
            </div>
          </div>

        <!-- CASO E: Docente / Feedback (Blanco con acento azul, sin icono, corta header al ser consecutivo) -->
        {:else if item.tipo_interaccion === 'Feedback' || item.es_de_docente}
          <div class="flex {esLadoDerecho(item) ? 'justify-end' : 'justify-start'}">
            <div class="w-fit max-w-[66.6%] rounded-2xl border border-[#C9D6E6] border-l-4 border-l-[#002F6C] bg-white shadow-xs overflow-hidden">
              {#if !item.esConsecutivo}
                <div class="flex items-center gap-1.5 border-b border-[#C9D6E6] bg-[#E8EDF5]/70 px-3.5 py-1 text-xs">
                  <span class="truncate font-semibold text-[#002F6C] text-[11.5px]" title={item.emisor}>
                    {item.es_propio && esDocente ? 'Tú' : item.emisor}
                  </span>
                  <span class="rounded border border-[#C9D6E6] bg-white/90 px-1 py-0.1 text-[9.5px] font-medium text-[#002F6C] shrink-0">
                    Docente
                  </span>
                </div>
              {/if}

              <div class="relative px-3.5 py-2">
                <p class="text-[13px] text-slate-800 leading-relaxed break-words whitespace-pre-wrap">
                  {item.mensaje}<span class="inline-block w-14 h-[13px] align-baseline pointer-events-none select-none" aria-hidden="true"></span>
                </p>

                {#if (item.adjunta_rubrica || item.rubrica) && onVerRubrica}
                  <div class="mt-2.5 mb-1">
                    <button
                      onclick={() => {
                        onVerRubrica?.({
                          rubrica: item.rubrica,
                          puntaje_obtenido: item.puntaje_obtenido,
                          retroalimentacion: item.mensaje,
                          resultado: item.resultado,
                          evaluador: item.emisor,
                          fecha: item.fecha_emision,
                        });
                      }}
                      class="inline-flex items-center gap-1 rounded-lg border border-[#C9D6E6] bg-white px-2 py-0.5 text-xs font-semibold text-[#002F6C] transition-colors hover:bg-slate-50 cursor-pointer"
                    >
                      <span>Ver rúbrica</span>
                      <ChevronRight class="h-3 w-3" />
                    </button>
                  </div>
                {/if}

                <span class="absolute bottom-1 right-2.5 font-mono text-[10px] text-slate-400 select-none">
                  {formatHora(item.fecha_emision)}
                </span>
              </div>
            </div>
          </div>

        <!-- CASO F: Mensaje Propio Estudiante (LAVANDA - SIN ÍCONO) -->
        {:else if item.es_propio}
          <div class="flex {esLadoDerecho(item) ? 'justify-end' : 'justify-start'}">
            <div class="w-fit max-w-[66.6%] rounded-2xl border border-[#DDD6FE] bg-[#F5EEFD] shadow-xs overflow-hidden">
              {#if !item.esConsecutivo}
                <div class="border-b border-[#DDD6FE] bg-[#E9DDFB]/70 px-3.5 py-1 text-xs">
                  <span class="font-bold text-purple-950 text-[11.5px]">{esDocente ? item.emisor : 'Tú'}</span>
                </div>
              {/if}

              <div class="relative px-3.5 py-2">
                <p class="text-[13px] text-slate-800 leading-relaxed break-words whitespace-pre-wrap">
                  {item.mensaje}<span class="inline-block w-14 h-[13px] align-baseline pointer-events-none select-none" aria-hidden="true"></span>
                </p>
                <span class="absolute bottom-1 right-2.5 font-mono text-[10px] text-purple-700/60 select-none">
                  {formatHora(item.fecha_emision)}
                </span>
              </div>
            </div>
          </div>

        <!-- CASO G: Mensaje de Compañero / Estudiante (LAVANDA GRISÁCEO - SIN ÍCONO) -->
        {:else}
          <div class="flex {esLadoDerecho(item) ? 'justify-end' : 'justify-start'}">
            <div class="w-fit max-w-[66.6%] rounded-2xl border border-[#D5D3DE] bg-[#F2F1F6] shadow-xs overflow-hidden">
              {#if !item.esConsecutivo}
                <div class="flex items-center gap-1.5 border-b border-[#D5D3DE] bg-[#E4E2ED]/70 px-3.5 py-1 text-xs">
                  <span class="truncate font-semibold text-slate-800 text-[11.5px]" title={item.emisor}>
                    {item.emisor}
                  </span>
                  <span class="rounded border border-[#D5D3DE] bg-white/90 px-1 py-0.1 text-[9.5px] font-medium text-slate-600 shrink-0">
                    {esDocente ? 'Estudiante' : 'Compañero (G3)'}
                  </span>
                </div>
              {/if}

              <div class="relative px-3.5 py-2">
                <p class="text-[13px] text-slate-800 leading-relaxed break-words whitespace-pre-wrap">
                  {item.mensaje}<span class="inline-block w-14 h-[13px] align-baseline pointer-events-none select-none" aria-hidden="true"></span>
                </p>
                <span class="absolute bottom-1 right-2.5 font-mono text-[10px] text-slate-400 select-none">
                  {formatHora(item.fecha_emision)}
                </span>
              </div>
            </div>
          </div>
        {/if}

        {#if item.visto_por && item.visto_por.length > 0}
          <VistoPor lectores={item.visto_por} alinear={esLadoDerecho(item) ? 'derecha' : 'izquierda'} />
        {/if}
      {/each}
    {:else}
      <div class="flex flex-col items-center justify-center py-16 gap-2 text-center px-4">
        <MessageSquareOff class="size-8 text-slate-300" stroke-width={1.5} />
        <p class="text-xs italic text-slate-400">No hay interacciones registradas en esta actividad.</p>
      </div>
    {/each}
  {/if}
</div>
