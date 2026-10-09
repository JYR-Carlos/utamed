<script lang="ts">
  /**
   * AgendaDocente.svelte
   *
   * Modal de "Agenda del Grupo" del docente: muestra el historial de
   * interacciones (mensajes, feedback y evaluaciones) de un grupo en una
   * actividad y permite al docente enviar nuevas interacciones. En modo
   * "Evaluación" despliega la rúbrica de la actividad (panel lateral en
   * escritorio, overlay a pantalla completa en móvil); al seleccionar un nivel
   * por criterio calcula automáticamente la nota chilena (1–7) vía
   * `@/lib/notas`, que el docente puede ajustar manualmente.
   *
   * Props:
   * - onCerrar: cierra el modal.
   * - onInteraccionEnviada: callback con los datos de la interacción a enviar
   *   ({ tipo, mensaje, nota?, resultado_rubrica?,
   *   puntaje_obtenido? }); el componente padre se encarga de persistirla.
   * - cod_curso, nombre_actividad, nombre_grupo: textos del encabezado.
   * - idCurso, idActividad, idGrupo: arman la URL para ver/descargar entregas.
   * - listado_interacciones: historial a renderizar.
   * - isLoading, errorMensaje: estado de carga/error del historial.
   * - rubricaActividad: rúbrica de la actividad (null si no existe).
   */
  import type { Rubrica } from '@/types/rubrica';
  import type { InteraccionItem } from '@/types/agenda';
  import { router } from '@inertiajs/svelte';
  import AgendaHilo from '../../../student/Activities/Agenda/AgendaHilo.svelte';
  import RubricaView from '../../../student/Activities/Agenda/Rubrica.svelte';
  import { calcularNotaChilena } from '@/lib/notas';
  import { formatFechaHora } from '@/utils/formatters';
  import { X, Send } from 'lucide-svelte';

  interface Props {
    onCerrar: () => void;
    onInteraccionEnviada: (data: {
      tipo: string;
      mensaje: string;
      nota?: number;
      /** Resultado cualitativo con el que cierra una actividad formativa. */
      evaluacion_obtenida?: string | null;
      resultado_rubrica?: Record<string, string>;
      puntaje_obtenido?: number;
    }) => void;
    cod_curso: string;
    nombre_actividad: string;
    nombre_grupo: string;
    idCurso: number;
    idActividad: number;
    idGrupo: number;
    listado_interacciones: InteraccionItem[];
    isLoading?: boolean;
    errorMensaje?: string | null;
    rubricaActividad?: Rubrica | null;
    /**
     * Sumativa → la evaluación cierra con nota. Formativa → con la escala
     * cualitativa de la rúbrica. Lo decide la actividad; llega del padre.
     */
    esSumativa?: boolean;
  }

  let {
    onCerrar,
    onInteraccionEnviada,
    cod_curso,
    nombre_actividad,
    nombre_grupo,
    idCurso,
    idActividad,
    idGrupo,
    listado_interacciones,
    isLoading = false,
    errorMensaje = null,
    rubricaActividad = null,
    esSumativa = true,
  }: Props = $props();

  // ── Estado del formulario ────────────────────────────────────────────────────
  let nuevoMensaje = $state('');
  let tipoSeleccionado = $state('Feedback');
  let notaEvaluacion = $state<number | null>(null);
  let notaManualOverride = $state(false);

  $effect(() => {
    if (!idGrupo) return;
    // Polling cada 3 segundos para refrescar nuevos mensajes y confirmaciones de lectura («Visto por»)
    const poll = router.poll(3000, {
      only: ['interaccionesGrupo'],
      data: { grupo_id: idGrupo },
      preserveUrl: true,
      replace: true,
      showProgress: false,
    });
    return () => {
      poll.stop();
    };
  });

  // Panel derecho de detalle de evaluación pasada
  type PanelDetalle = {
    rubrica: Rubrica;
    puntaje_obtenido?: number;
    retroalimentacion?: string;
    resultado?: Record<string, string> | null;
  };
  let panelDetalle = $state<PanelDetalle | null>(null);

  // Slideover de rúbrica en modo Evaluación
  let mostrarSlideoverEvaluacion = $state(false);

  // Selección de celdas de rúbrica: id_nivel → id_escala
  let seleccionRubrica = $state<Record<string, string>>({});

  // ── Derivados de rúbrica ─────────────────────────────────────────────────────

  const puntajeRubrica = $derived(
    rubricaActividad?.niveles?.reduce((acc, nivel) => {
      const escalaId = seleccionRubrica[nivel.id];
      if (!escalaId) return acc;
      const escala = nivel.escalas.find((e) => e.id === escalaId);
      return acc + (escala ? escala.puntos : 0);
    }, 0) ?? 0,
  );

  const puntajeMaximo = $derived(
    rubricaActividad?.detalles_evaluacion?.puntaje_total ??
      rubricaActividad?.niveles?.reduce((acc, n) => acc + n.puntaje_total, 0) ??
      0,
  );



  const criteriosEvaluados = $derived(
    rubricaActividad?.niveles?.filter((n) => !!seleccionRubrica[n.id]).length ?? 0,
  );
  const totalCriterios = $derived(rubricaActividad?.niveles?.length ?? 0);
  const todosEvaluados = $derived(totalCriterios > 0 && criteriosEvaluados === totalCriterios);

  const notaCalculada = $derived(
    esSumativa && todosEvaluados && puntajeMaximo > 0
      ? calcularNotaChilena(puntajeRubrica, puntajeMaximo)
      : null,
  );

  /**
   * Resultado cualitativo con el que cierra una formativa, leído de la escala
   * de la rúbrica. Es el equivalente de `notaCalculada` para ese caso: sin él,
   * evaluar una formativa desde la agenda quedaría sin nada que enviar, porque
   * el servidor rechaza la nota numérica.
   */
  const evaluacionCualitativa = $derived(
    (() => {
      const escala = rubricaActividad?.detalles_evaluacion?.escala_evaluacion;
      if (esSumativa || !escala?.length || !todosEvaluados) return null;
      const ordenada = [...escala].sort((a, b) => b.puntaje_minimo - a.puntaje_minimo);
      return ordenada.find((e) => puntajeRubrica >= e.puntaje_minimo)?.evaluacion ?? null;
    })(),
  );

  /** Qué falta para poder enviar una evaluación, según el tipo de actividad. */
  const resultadoListo = $derived(esSumativa ? notaEvaluacion !== null : !!evaluacionCualitativa);

  // Auto-poblar nota cuando se completa la rúbrica
  $effect(() => {
    if (notaCalculada !== null && !notaManualOverride) {
      notaEvaluacion = notaCalculada;
    }
  });

  // ── Derivados de UI ──────────────────────────────────────────────────────────

  const tiposInteraccion = ['Feedback', 'Evaluación'];
  const esEvaluacion = $derived(tipoSeleccionado === 'Evaluación');

  function urlEntrega(idAgenda: number, ver = false): string {
    const url = `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${idGrupo}/entregas/${idAgenda}/descargar`;
    return ver ? `${url}?ver=1` : url;
  }

  // ── Acciones ─────────────────────────────────────────────────────────────────

  function seleccionarEscala(nivelId: string, escalaId: string) {
    seleccionRubrica[nivelId] = escalaId;
    notaManualOverride = false;
  }

  function manejarEnvio() {
    if (!esEvaluacion && nuevoMensaje.trim() === '') return;
    if (esEvaluacion && !resultadoListo) return;

    const data: Parameters<Props['onInteraccionEnviada']>[0] = {
      tipo: tipoSeleccionado,
      mensaje: nuevoMensaje,
    };

    if (esEvaluacion) {
      // Uno u otro, nunca los dos: es la misma regla que aplica el servidor.
      if (esSumativa) {
        if (notaEvaluacion !== null) data.nota = notaEvaluacion;
      } else {
        data.evaluacion_obtenida = evaluacionCualitativa;
      }
      if (rubricaActividad) {
        data.resultado_rubrica = { ...seleccionRubrica };
        data.puntaje_obtenido = puntajeRubrica;
      }
    }

    onInteraccionEnviada(data);

    nuevoMensaje = '';
    notaEvaluacion = null;
    seleccionRubrica = {};
    notaManualOverride = false;
    tipoSeleccionado = tiposInteraccion[0];
    mostrarSlideoverEvaluacion = false;
  }

  function cambiarTipo(tipo: string) {
    tipoSeleccionado = tipo;
    if (tipo === 'Evaluación') {
      if (rubricaActividad) {
        mostrarSlideoverEvaluacion = true;
      }
    } else {
      mostrarSlideoverEvaluacion = false;
      panelDetalle = null;
    }
  }
</script>

<!-- ─────────────────────────────────────────────────────────────────────────── -->
<!-- Modal principal                                                            -->
<!-- ─────────────────────────────────────────────────────────────────────────── -->
<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape') {
      if (mostrarSlideoverEvaluacion) {
        mostrarSlideoverEvaluacion = false;
        e.stopPropagation();
      } else if (panelDetalle) {
        panelDetalle = null;
        e.stopPropagation();
      }
    }
  }}
/>

<div class="w-[96%] sm:w-[94%] lg:w-[92%] 2xl:w-[90%] max-w-[1440px] h-[90vh] flex rounded-2xl bg-white shadow-2xl overflow-hidden relative">

  <!-- ── Panel principal: historial + formulario ───────────────────────────── -->
  <div class="flex min-w-0 flex-1 flex-col h-full relative overflow-hidden border-r border-[#E5E7EB]">

    <!-- Cabecera idéntica a estudiante -->
    <div class="flex shrink-0 items-center justify-between border-b border-[#E5E7EB] px-5 py-3.5 bg-white z-10">
      <div class="flex min-w-0 flex-col">
        <span class="text-[15px] font-semibold text-[#1A1A24]">Agenda del Grupo</span>
        <span class="truncate text-xs text-[#5A5E6E]">{cod_curso} · {nombre_actividad} · {nombre_grupo}</span>
      </div>
      <button
        class="rounded-full p-1.5 text-[#5A5E6E] transition-colors hover:bg-[#F8FAFC] cursor-pointer"
        onclick={onCerrar}
        aria-label="Cerrar agenda"
      >
        <X class="h-5 w-5" />
      </button>
    </div>

    <!-- Hilo conversacional compartido (edge-to-edge) -->
    <AgendaHilo
      {listado_interacciones}
      esDocente={true}
      urlDescargaEntrega={urlEntrega}
      onVerRubrica={(detalle) => {
        const rub = detalle.rubrica ?? rubricaActividad;
        panelDetalle = rub ? {
          rubrica: rub,
          puntaje_obtenido: detalle.puntaje_obtenido ?? undefined,
          retroalimentacion: detalle.retroalimentacion,
          resultado: detalle.resultado,
        } : null;
      }}
      {isLoading}
      {errorMensaje}
    />

    <!-- Compositor inferior integrado y pegado al borde -->
    <div class="shrink-0 border-t border-[#E5E7EB] bg-white px-5 py-3.5 z-10">

      <!-- Selector de tipo -->
      <div class="flex items-center gap-1.5 mb-2.5">
        {#each tiposInteraccion as tipo}
          <button
            type="button"
            onclick={() => cambiarTipo(tipo)}
            class="px-2.5 py-1 text-xs font-semibold rounded-lg border transition-colors cursor-pointer
              {tipoSeleccionado === tipo
                ? 'bg-uta-blue text-white border-uta-blue shadow-2xs'
                : 'bg-white text-gray-600 border-gray-300 hover:border-uta-blue/50 hover:bg-slate-50'}"
          >
            {tipo}
          </button>
        {/each}
      </div>

      {#if esEvaluacion}
        <!-- Resumen compacto de la rúbrica -->
        {#if rubricaActividad}
          <div class="mb-3 px-3 py-2 rounded-xl bg-white border border-gray-200 flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-3 text-xs">
              <span class="text-gray-500">Puntaje:</span>
              <span class="font-black text-uta-blue">{puntajeRubrica}<span class="font-normal text-gray-400">/{puntajeMaximo}</span></span>
              <span class="{criteriosEvaluados < totalCriterios ? 'text-amber-600' : 'text-emerald-600'} font-semibold">
                {criteriosEvaluados}/{totalCriterios} criterios
              </span>
            </div>
            {#if notaCalculada !== null}
              <span class="text-sm font-black text-emerald-700">Nota: {notaCalculada.toFixed(1)}</span>
            {:else}
              <span class="text-xs text-gray-400 italic">Completa la rúbrica →</span>
            {/if}
            <button
              type="button"
              onclick={() => (mostrarSlideoverEvaluacion = !mostrarSlideoverEvaluacion)}
              class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-uta-blue text-white hover:bg-uta-blue-hover transition cursor-pointer flex items-center gap-1 shadow-2xs"
            >
              <span>{mostrarSlideoverEvaluacion ? 'Ocultar Rúbrica' : 'Abrir Rúbrica'}</span>
              <span class="text-[10px] opacity-80">({criteriosEvaluados}/{totalCriterios})</span>
            </button>
          </div>
        {:else}
          <!-- Sin rúbrica: advertencia -->
          <div class="mb-3 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 font-medium">
            ⚠ No hay rúbrica creada para esta actividad. Crea una rúbrica antes de poder evaluar.
          </div>
        {/if}

        <!-- Resultado: nota en las sumativas, apreciación en las formativas -->
        {#if esSumativa}
          <div class="flex items-center gap-2 mb-3 flex-wrap">
            <label for="nota-eval" class="text-xs font-bold text-gray-700 shrink-0">Nota (1–7):</label>
            <input
              id="nota-eval"
              type="number"
              min="1" max="7" step="0.1"
              bind:value={notaEvaluacion}
              oninput={() => { notaManualOverride = true; }}
              placeholder="ej. 5.5"
              class="w-20 text-sm border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:border-uta-blue focus:ring-1 focus:ring-primary/30"
            />
            {#if notaCalculada !== null && notaManualOverride}
              <button
                type="button"
                onclick={() => { notaEvaluacion = notaCalculada; notaManualOverride = false; }}
                class="text-xs text-uta-blue underline hover:no-underline"
              >Restaurar ({notaCalculada.toFixed(1)})</button>
            {/if}
          </div>
        {:else}
          <div class="flex items-center gap-2 mb-3 flex-wrap">
            <span class="text-xs font-bold text-gray-700 shrink-0">Resultado:</span>
            {#if evaluacionCualitativa}
              <span class="text-sm font-black text-uta-blue">{evaluacionCualitativa}</span>
              <span class="text-xs text-gray-400">· actividad formativa, sin nota numérica</span>
            {:else if todosEvaluados}
              <span class="text-xs text-amber-600"
                >La rúbrica no tiene escala de evaluación con la que cerrar una formativa.</span
              >
            {:else}
              <span class="text-xs text-gray-400">Completa la rúbrica para obtenerlo.</span>
            {/if}
          </div>
        {/if}
      {/if}

      <!-- Textarea + enviar -->
      <div class="flex items-end gap-2.5">
        <textarea
          bind:value={nuevoMensaje}
          placeholder={esEvaluacion ? 'Retroalimentación para el grupo (opcional)…' : 'Escribe tu retroalimentación al grupo…'}
          rows="2"
          maxlength="2000"
          class="flex-1 resize-none rounded-lg border border-[#D6D9E0] px-3.5 py-2.5 text-[13px] text-[#1A1A24] outline-none transition-colors focus:border-[#002F6C]"
        ></textarea>
        <button
          onclick={manejarEnvio}
          disabled={esEvaluacion
            ? (!resultadoListo || (!!rubricaActividad && !todosEvaluados) || !rubricaActividad)
            : !nuevoMensaje.trim()}
          class="flex shrink-0 items-center gap-1.5 rounded-lg border border-[#002F6C] bg-white px-4 py-2.5 text-[13px] font-semibold text-[#002F6C] transition-colors hover:bg-[#F8FAFC] disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
        >
          <Send class="h-3.5 w-3.5" />
          Enviar
        </button>
      </div>
      <span class="mt-1 block text-right font-mono text-[10.5px] text-[#5A5E6E]">
        {nuevoMensaje.length} / 2.000
      </span>
    </div>
  </div>

  <!-- ── Slideover de Rúbrica de Evaluación (Modo Evaluación) ── -->
  {#if mostrarSlideoverEvaluacion && rubricaActividad}
    <div
      class="absolute inset-0 z-40 bg-slate-900/40 backdrop-blur-2xs transition-opacity"
      onclick={() => (mostrarSlideoverEvaluacion = false)}
      role="presentation"
    ></div>

    <div
      class="absolute inset-y-0 right-0 z-50 flex w-[95%] max-w-full flex-col border-l border-slate-200 bg-white shadow-2xl animate-in slide-in-from-right duration-200"
      role="dialog"
      aria-modal="true"
    >
      <!-- Encabezado limpio sin header azul -->
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4 shrink-0">
        <div>
          <h3 class="text-sm font-bold text-slate-900">Rúbrica de Evaluación</h3>
          <p class="text-xs text-slate-500">{nombre_actividad} — {nombre_grupo}</p>
        </div>
        <button
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-700 cursor-pointer"
          onclick={() => (mostrarSlideoverEvaluacion = false)}
          aria-label="Cerrar rúbrica"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <!-- Componente RubricaView (blanquito) en modo interactivo -->
      <div class="flex-1 overflow-y-auto p-5">
        <RubricaView
          rubrica={rubricaActividad}
          resultado={seleccionRubrica}
          puntaje_obtenido={puntajeRubrica}
          onSeleccionarEscala={seleccionarEscala}
          modoLectura={false}
          {esSumativa}
        />
      </div>

      <!-- Footer con resumen y acción de volver -->
      <div
        class="shrink-0 px-5 py-3 border-t bg-slate-50 flex items-center justify-between gap-3"
      >
        <div class="text-xs text-slate-600">
          <span class="font-bold">{criteriosEvaluados}/{totalCriterios}</span> criterios evaluados
          {#if notaCalculada !== null}
            · Nota calculada: <strong class="text-emerald-700 font-bold">{notaCalculada.toFixed(1)}</strong>
          {/if}
        </div>
        <button
          type="button"
          onclick={() => (mostrarSlideoverEvaluacion = false)}
          class="px-4 py-2 text-xs font-bold rounded-xl transition-colors cursor-pointer {todosEvaluados
            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
            : 'bg-uta-blue text-white hover:bg-uta-blue-hover'}"
        >
          {todosEvaluados ? 'Confirmar y volver al mensaje' : 'Cerrar rúbrica'}
        </button>
      </div>
    </div>
  {/if}

  <!-- ── Slideover de Detalle de Evaluación (Lectura) ── -->
  {#if panelDetalle?.rubrica}
    <div
      class="absolute inset-0 z-40 bg-slate-900/40 backdrop-blur-2xs transition-opacity"
      onclick={() => (panelDetalle = null)}
      role="presentation"
    ></div>

    <div
      class="absolute inset-y-0 right-0 z-50 flex w-[95%] max-w-full flex-col border-l border-slate-200 bg-white shadow-2xl animate-in slide-in-from-right duration-200"
      role="dialog"
      aria-modal="true"
    >
      <div class="shrink-0 flex justify-between items-center px-6 py-4 border-b bg-slate-50">
        <div>
          <p class="font-bold text-sm text-slate-900">Detalle de Evaluación</p>
          <p class="text-xs text-slate-500">{nombre_actividad} — {nombre_grupo}</p>
        </div>
        <button
          onclick={() => (panelDetalle = null)}
          class="rounded-lg p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition-colors cursor-pointer"
          aria-label="Cerrar detalle"
        >
          <X class="h-5 w-5" />
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-white">
        <RubricaView
          rubrica={panelDetalle.rubrica}
          puntaje_obtenido={panelDetalle.puntaje_obtenido ?? 0}
          retroalimentacion={panelDetalle.retroalimentacion}
          resultado={panelDetalle.resultado}
          modoLectura={true}
          {esSumativa}
        />
      </div>
    </div>
  {/if}

</div>

<style>
  .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
