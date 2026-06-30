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
   *   ({ tipo, mensaje, nota?, id_agenda_entrega?, resultado_rubrica?,
   *   puntaje_obtenido? }); el componente padre se encarga de persistirla.
   * - cod_curso, nombre_actividad, nombre_grupo: textos del encabezado.
   * - listado_interacciones: historial a renderizar.
   * - isLoading, errorMensaje: estado de carga/error del historial.
   * - rubricaActividad: rúbrica de la actividad (null si no existe).
   */
  import type { Rubrica } from '@/types/rubrica';
  import RubricaView from '../../../student/Activities/Agenda/Rubrica.svelte';
  import { calcularNotaChilena } from '@/lib/notas';
  import { X, Send, CheckCircle2, AlertTriangle, ChevronRight, MessageSquareOff } from 'lucide-svelte';

  interface Props {
    onCerrar: () => void;
    onInteraccionEnviada: (data: {
      tipo: string;
      mensaje: string;
      nota?: number;
      id_agenda_entrega?: number | null;
      resultado_rubrica?: Record<string, string>;
      puntaje_obtenido?: number;
    }) => void;
    cod_curso: string;
    nombre_actividad: string;
    nombre_grupo: string;
    listado_interacciones: Array<{
      id_interaccion: number;
      fecha_emision: string;
      tipo_interaccion: string;
      emisor: string;
      mensaje: string;
      es_de_docente: boolean;
      es_retroalimentacion: boolean;
      es_entrega?: boolean;
      tiene_evaluacion?: boolean;
      adjunta_rubrica: boolean;
      rubrica?: Rubrica;
      puntaje_obtenido?: number;
      resultado?: Record<string, string> | null;
    }>;
    isLoading?: boolean;
    errorMensaje?: string | null;
    rubricaActividad?: Rubrica | null;
  }

  let {
    onCerrar,
    onInteraccionEnviada,
    cod_curso,
    nombre_actividad,
    nombre_grupo,
    listado_interacciones,
    isLoading = false,
    errorMensaje = null,
    rubricaActividad = null,
  }: Props = $props();

  // ── Estado del formulario ────────────────────────────────────────────────────
  let nuevoMensaje = $state('');
  let tipoSeleccionado = $state('Feedback');
  let notaEvaluacion = $state<number | null>(null);
  let entregaSeleccionada = $state<number | null>(null);
  let notaManualOverride = $state(false);

  // Panel derecho: null = oculto; { tipo: 'detalle', ... } = evaluación pasada
  type PanelDetalle = {
    rubrica: Rubrica;
    puntaje_obtenido?: number;
    retroalimentacion?: string;
    resultado?: Record<string, string> | null;
  };
  let panelDetalle = $state<PanelDetalle | null>(null);

  // Panel de rúbrica en móvil (fullscreen overlay)
  let mostrarRubricaMobile = $state(false);

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

  const maxEscalas = $derived(
    Math.max(...(rubricaActividad?.niveles?.map((n) => n.escalas.length) ?? [0])),
  );

  const criteriosEvaluados = $derived(
    rubricaActividad?.niveles?.filter((n) => !!seleccionRubrica[n.id]).length ?? 0,
  );
  const totalCriterios = $derived(rubricaActividad?.niveles?.length ?? 0);
  const todosEvaluados = $derived(totalCriterios > 0 && criteriosEvaluados === totalCriterios);

  const notaCalculada = $derived(
    todosEvaluados && puntajeMaximo > 0 ? calcularNotaChilena(puntajeRubrica, puntajeMaximo) : null,
  );

  // Auto-poblar nota cuando se completa la rúbrica
  $effect(() => {
    if (notaCalculada !== null && !notaManualOverride) {
      notaEvaluacion = notaCalculada;
    }
  });

  // ── Derivados de UI ──────────────────────────────────────────────────────────

  const tiposInteraccion = ['Feedback', 'Evaluación'];
  const esEvaluacion = $derived(tipoSeleccionado === 'Evaluación');

  // El panel derecho muestra la rúbrica editable cuando estamos en modo Evaluación
  // y no hay un detalle de evaluación pasada abierto
  const mostrarPanelRubrica = $derived(esEvaluacion && !!rubricaActividad && !panelDetalle);

  const entregasSinEvaluar = $derived(
    listado_interacciones.filter((i) => i.es_entrega && !i.tiene_evaluacion),
  );

  // ── Acciones ─────────────────────────────────────────────────────────────────

  function seleccionarEscala(nivelId: string, escalaId: string) {
    seleccionRubrica[nivelId] = escalaId;
    notaManualOverride = false;
  }

  function manejarEnvio() {
    if (!esEvaluacion && nuevoMensaje.trim() === '') return;
    if (esEvaluacion && notaEvaluacion === null) return;

    const data: Parameters<Props['onInteraccionEnviada']>[0] = {
      tipo: tipoSeleccionado,
      mensaje: nuevoMensaje,
    };

    if (esEvaluacion) {
      if (notaEvaluacion !== null) data.nota = notaEvaluacion;
      data.id_agenda_entrega = entregaSeleccionada;
      if (rubricaActividad) {
        data.resultado_rubrica = { ...seleccionRubrica };
        data.puntaje_obtenido = puntajeRubrica;
      }
    }

    onInteraccionEnviada(data);

    nuevoMensaje = '';
    notaEvaluacion = null;
    entregaSeleccionada = null;
    seleccionRubrica = {};
    notaManualOverride = false;
    tipoSeleccionado = tiposInteraccion[0];
    mostrarRubricaMobile = false;
  }

  function cambiarTipo(tipo: string) {
    tipoSeleccionado = tipo;
    if (tipo !== 'Evaluación') {
      panelDetalle = null;
      mostrarRubricaMobile = false;
    }
  }
</script>

<!-- ─────────────────────────────────────────────────────────────────────────── -->
<!-- Modal principal                                                            -->
<!-- ─────────────────────────────────────────────────────────────────────────── -->
<div class="w-full sm:w-[95%] h-[90vh] flex rounded-3xl bg-white shadow-2xl overflow-hidden">

  <!-- ── Panel izquierdo: historial + formulario ───────────────────────────── -->
  <div class="flex flex-col w-full {mostrarPanelRubrica || panelDetalle ? 'sm:w-2/5' : ''} px-6 md:px-10 py-8 overflow-hidden shrink-0 border-r border-gray-100">

    <!-- Cabecera -->
    <div class="flex justify-between items-start mb-5 shrink-0 pb-4 border-b">
      <div>
        <p class="text-xl font-bold text-uta-blue">Agenda del Grupo</p>
        <p class="text-xs text-gray-500 mt-0.5">{cod_curso} — {nombre_actividad}</p>
        <p class="text-xs font-bold text-uta-blue/70 mt-0.5">{nombre_grupo}</p>
      </div>
      <button
        class="p-2 hover:bg-gray-100 rounded-full transition-colors shrink-0"
        onclick={onCerrar}
        aria-label="cerrar"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <!-- Lista de interacciones -->
    <div class="flex-1 overflow-y-auto pr-2 mb-4 custom-scrollbar">
      {#if isLoading}
        <div class="flex flex-col gap-3 animate-pulse">
          {#each [1,2,3] as _}
            <div class="h-16 rounded-xl bg-gray-100 border-l-4 border-gray-200"></div>
          {/each}
        </div>
      {:else if errorMensaje}
        <div class="flex items-center justify-center h-full text-sm text-red-500 text-center px-4">
          {errorMensaje}
        </div>
      {:else if listado_interacciones.length === 0}
        <div class="flex flex-col items-center justify-center h-full gap-2 text-center px-4">
          <MessageSquareOff class="size-9 text-gray-300" stroke-width={1.5} />
          <p class="text-sm font-semibold text-gray-500">Sin mensajes</p>
          <p class="text-xs text-gray-400">Selecciona <strong>Evaluación</strong> para calificar al grupo.</p>
        </div>
      {:else}
        {#each listado_interacciones as item}
          <button
            class="mb-3 p-3 w-full text-start border-l-4 transition-all rounded-r-xl
              {item.es_de_docente ? 'border-uta-blue bg-uta-blue/5' : 'border-gray-200 bg-gray-50/60'}
              {item.adjunta_rubrica && rubricaActividad ? 'cursor-pointer hover:bg-amber-50/60' : 'cursor-default'}"
            onclick={() => {
              if (item.adjunta_rubrica && rubricaActividad) {
                panelDetalle = {
                  rubrica: rubricaActividad,
                  puntaje_obtenido: item.puntaje_obtenido,
                  retroalimentacion: item.mensaje,
                  resultado: item.resultado,
                };
              }
            }}
          >
            <div class="flex justify-between items-start gap-2">
              <div class="flex gap-1 flex-wrap">
                <span class="text-[10px] font-bold bg-white px-2 py-0.5 rounded border">{item.tipo_interaccion}</span>
                {#if item.es_de_docente}
                  <span class="text-[10px] font-bold bg-uta-blue/10 text-uta-blue px-2 py-0.5 rounded">Docente</span>
                {/if}
              </div>
              <span class="text-[10px] text-gray-400 shrink-0">{item.fecha_emision}</span>
            </div>
            <p class="text-[11px] font-bold uppercase text-gray-500 mt-1.5">{item.emisor}</p>
            <p class="text-xs mt-0.5 text-gray-700 line-clamp-2">{item.mensaje}</p>
            {#if item.adjunta_rubrica && item.puntaje_obtenido !== undefined}
              <p class="text-[11px] font-bold text-uta-blue mt-1">Puntaje: {item.puntaje_obtenido} pts · Ver rúbrica →</p>
            {/if}
          </button>
        {/each}
      {/if}
    </div>

    <!-- Formulario -->
    <div class="shrink-0 bg-gray-50 rounded-2xl p-4">

      <!-- Selector de tipo -->
      <div class="flex items-center gap-2 mb-3">
        {#each tiposInteraccion as tipo}
          <button
            onclick={() => cambiarTipo(tipo)}
            class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors
              {tipoSeleccionado === tipo
                ? 'bg-uta-blue text-white border-uta-blue'
                : 'bg-white text-gray-600 border-gray-300 hover:border-uta-blue/50'}"
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
          </div>

          <!-- Botón para abrir la rúbrica en móvil -->
          <button
            type="button"
            onclick={() => (mostrarRubricaMobile = true)}
            class="sm:hidden w-full mb-3 py-2 px-3 rounded-xl border-2 text-xs font-semibold transition flex items-center justify-between gap-2
              {todosEvaluados
                ? 'border-emerald-400 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                : 'border-amber-400 bg-amber-50 text-amber-700 hover:bg-amber-100'}"
          >
            <span>{todosEvaluados ? '✓ Rúbrica completa — ver detalle' : '⚠ Completar Rúbrica (obligatorio)'}</span>
            <span class="font-bold">{criteriosEvaluados}/{totalCriterios}</span>
          </button>
        {:else}
          <!-- Sin rúbrica: advertencia -->
          <div class="mb-3 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 font-medium">
            ⚠ No hay rúbrica creada para esta actividad. Crea una rúbrica antes de poder evaluar.
          </div>
        {/if}

        <!-- Nota (1-7) -->
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

        <!-- Entrega a vincular -->
        {#if entregasSinEvaluar.length > 0}
          <div class="mb-3">
            <label for="entrega-sel" class="text-xs font-bold text-gray-700 block mb-1">Entrega a evaluar:</label>
            <select
              id="entrega-sel"
              bind:value={entregaSeleccionada}
              class="w-full text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-uta-blue"
            >
              <option value={null}>— Evaluación general —</option>
              {#each entregasSinEvaluar as e}
                <option value={e.id_interaccion}>#{e.id_interaccion} · {e.fecha_emision}</option>
              {/each}
            </select>
          </div>
        {/if}
      {/if}

      <!-- Textarea + enviar -->
      <div class="relative">
        <textarea
          bind:value={nuevoMensaje}
          placeholder={esEvaluacion ? 'Retroalimentación para el grupo (opcional)…' : 'Escribe un mensaje…'}
          rows="3"
          class="w-full p-3 pr-12 text-sm border border-gray-300 rounded-xl resize-none focus:outline-none focus:border-uta-blue focus:ring-1 focus:ring-primary/30 bg-white"
        ></textarea>
        <button
          onclick={manejarEnvio}
          disabled={esEvaluacion
            ? (notaEvaluacion === null || (!!rubricaActividad && !todosEvaluados) || !rubricaActividad)
            : !nuevoMensaje.trim()}
          class="absolute bottom-3 right-3 p-2 bg-uta-blue text-white rounded-lg hover:scale-105 disabled:opacity-40 disabled:scale-100 transition-all"
          aria-label="enviar"
        >
          <Send class="w-4 h-4" stroke-width={2.5} />
        </button>
      </div>
    </div>
  </div>

  <!-- ── Panel derecho: rúbrica editable O detalle de evaluación pasada ───── -->

  {#if mostrarPanelRubrica && rubricaActividad}
    <!-- Panel de evaluación con rúbrica -->
    <div class="hidden sm:flex flex-col flex-1 overflow-hidden bg-gray-50">

      <!-- Encabezado de la rúbrica -->
      <div class="shrink-0 bg-uta-blue text-white px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
          <p class="font-bold text-base">Rúbrica de Evaluación</p>
          <p class="text-xs text-white/70 mt-0.5">{nombre_actividad} — {nombre_grupo}</p>
        </div>
        <div class="flex items-center gap-4 flex-wrap text-sm">
          <!-- Puntaje -->
          <div class="text-center">
            <p class="text-[10px] uppercase tracking-wider text-white/60">Puntaje</p>
            <p class="font-black text-xl leading-none">
              {puntajeRubrica}<span class="text-sm font-normal text-white/60">/{puntajeMaximo}</span>
            </p>
          </div>
          <!-- Criterios -->
          <div class="text-center">
            <p class="text-[10px] uppercase tracking-wider text-white/60">Criterios</p>
            <p class="font-black text-xl leading-none {criteriosEvaluados < totalCriterios ? 'text-yellow-300' : 'text-green-300'}">
              {criteriosEvaluados}/{totalCriterios}
            </p>
          </div>
          <!-- Nota calculada -->
          <div class="text-center">
            <p class="text-[10px] uppercase tracking-wider text-white/60">Nota calculada</p>
            <p class="font-black text-2xl leading-none {notaCalculada !== null ? 'text-yellow-300' : 'text-white/40'}">
              {notaCalculada !== null ? notaCalculada.toFixed(1) : '—'}
            </p>
          </div>
        </div>
      </div>

      <!-- Barra de progreso -->
      {#if puntajeMaximo > 0}
        <div class="h-1.5 bg-gray-200 shrink-0">
          <div
            class="h-full transition-all duration-300 {puntajeRubrica / puntajeMaximo >= 0.6 ? 'bg-emerald-500' : 'bg-amber-400'}"
            style="width: {Math.min(100, Math.round((puntajeRubrica / puntajeMaximo) * 100))}%"
          ></div>
        </div>
      {/if}

      <!-- Tabla de rúbrica -->
      <div class="flex-1 overflow-auto custom-scrollbar p-4">
        <table class="w-full border-collapse text-sm min-w-max">
          <thead class="sticky top-0 z-10">
            <tr class="bg-gray-100">
              <th class="text-left px-4 py-3 font-semibold text-gray-600 border-b-2 border-gray-200 min-w-[200px] w-[220px] rounded-tl-lg">
                Criterio
              </th>
              {#each Array(maxEscalas) as _, i}
                <th class="text-center px-3 py-3 font-semibold text-gray-500 border-b-2 border-gray-200 min-w-[180px]">
                  Nivel {i + 1}
                </th>
              {/each}
            </tr>
          </thead>
          <tbody>
            {#each rubricaActividad.niveles as nivel, ri}
              {@const nivelEvaluado = !!seleccionRubrica[nivel.id]}
              {@const escalaElegida = nivel.escalas.find(e => e.id === seleccionRubrica[nivel.id])}
              <tr class="border-b border-gray-200 last:border-0 {!nivelEvaluado ? 'bg-amber-50/40' : 'bg-white'}">

                <!-- Columna criterio -->
                <td class="px-4 py-4 align-top border-r-2 border-gray-200">
                  <p class="font-bold text-gray-800 leading-snug mb-1">{nivel.nombre}</p>
                  {#if nivel.descripcion}
                    <p class="text-xs text-gray-500 leading-snug mb-2">{nivel.descripcion}</p>
                  {/if}
                  <div class="flex items-center gap-2 mt-auto">
                    <span class="text-xs text-gray-400">máx. {nivel.puntaje_total} pts</span>
                    {#if !nivelEvaluado}
                      <span class="text-[11px] font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">⚠ Pendiente</span>
                    {:else}
                      <span class="text-[11px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                        ✓ {escalaElegida?.puntos} pts
                      </span>
                    {/if}
                  </div>
                </td>

                <!-- Columnas de escalas -->
                {#each Array(maxEscalas) as _, ci}
                  <td class="px-2 py-2 align-top">
                    {#if nivel.escalas[ci]}
                      {@const escala = nivel.escalas[ci]}
                      {@const elegida = seleccionRubrica[nivel.id] === escala.id}
                      <button
                        type="button"
                        onclick={() => seleccionarEscala(nivel.id, escala.id)}
                        class="w-full h-full min-h-[80px] text-left p-3 rounded-xl border-2 transition-all duration-150
                          {elegida
                            ? 'border-uta-blue bg-uta-blue/10 shadow-md'
                            : 'border-gray-200 bg-white hover:border-uta-blue/40 hover:bg-uta-blue/5 hover:shadow-sm'}"
                      >
                        <div class="flex items-center gap-2 mb-2">
                          <span class="text-xs font-black px-2 py-0.5 rounded-md transition-colors
                            {elegida ? 'bg-uta-blue text-white' : 'bg-gray-100 text-gray-600'}">
                            {escala.puntos} pts
                          </span>
                          {#if elegida}
                            <CheckCircle2 class="w-4 h-4 text-uta-blue shrink-0" />
                          {/if}
                        </div>
                        <p class="text-xs text-gray-600 leading-snug">{escala.criterio}</p>
                      </button>
                    {/if}
                  </td>
                {/each}
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <!-- Aviso de estado -->
      <div class="shrink-0 px-4 py-2.5 border-t {todosEvaluados ? 'bg-emerald-50 border-emerald-100' : 'bg-amber-50 border-amber-100'}">
        {#if todosEvaluados}
          <p class="text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
            <CheckCircle2 class="w-4 h-4 shrink-0" />
            Rúbrica completa — nota calculada: {notaCalculada?.toFixed(1)} (60% exigencia). Puedes ajustarla manualmente en el panel izquierdo.
          </p>
        {:else}
          <p class="text-xs font-semibold text-amber-700 flex items-center gap-1.5">
            <AlertTriangle class="w-4 h-4 shrink-0" />
            Selecciona un nivel por cada criterio para calcular la nota automáticamente.
          </p>
        {/if}
      </div>
    </div>

  {:else if panelDetalle?.rubrica}
    <!-- Panel de detalle de evaluación pasada -->
    <div class="hidden sm:flex flex-col flex-1 overflow-hidden bg-gray-50">
      <div class="shrink-0 flex justify-between items-center px-6 py-4 border-b bg-white">
        <p class="font-bold text-base text-uta-blue">Detalle de Evaluación</p>
        <button
          onclick={() => (panelDetalle = null)}
          class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-uta-blue transition-colors px-3 py-1.5 rounded-lg hover:bg-gray-100"
        >
          Cerrar detalle
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
        <RubricaView
          rubrica={panelDetalle.rubrica}
          puntaje_obtenido={panelDetalle.puntaje_obtenido ?? 0}
          retroalimentacion={panelDetalle.retroalimentacion}
          resultado={panelDetalle.resultado}
        />
      </div>
    </div>
  {/if}

</div>

<!-- ── Panel de rúbrica en móvil (fullscreen overlay, z-[200] sobre el modal padre) ── -->
{#if mostrarRubricaMobile && rubricaActividad}
  <div class="fixed inset-0 z-[200] flex flex-col bg-white sm:hidden overflow-hidden">

    <!-- Encabezado -->
    <div class="shrink-0 bg-uta-blue text-white px-4 py-3 flex items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="font-bold text-sm leading-tight">Rúbrica de Evaluación</p>
        <p class="text-[11px] text-white/70 mt-0.5 truncate">{nombre_actividad} — {nombre_grupo}</p>
      </div>
      <div class="flex items-center gap-3 shrink-0 text-right">
        <div>
          <p class="text-[10px] text-white/60 uppercase tracking-wide">Puntaje</p>
          <p class="font-black text-lg leading-none">{puntajeRubrica}<span class="text-xs font-normal text-white/60">/{puntajeMaximo}</span></p>
        </div>
        <div>
          <p class="text-[10px] text-white/60 uppercase tracking-wide">Nota</p>
          <p class="font-black text-lg leading-none {notaCalculada !== null ? 'text-yellow-300' : 'text-white/40'}">
            {notaCalculada !== null ? notaCalculada.toFixed(1) : '—'}
          </p>
        </div>
        <button
          onclick={() => (mostrarRubricaMobile = false)}
          class="p-2 rounded-full hover:bg-white/10 transition shrink-0"
          aria-label="cerrar"
        >
          <X class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Barra de progreso -->
    {#if puntajeMaximo > 0}
      <div class="h-1.5 bg-gray-200 shrink-0">
        <div
          class="h-full transition-all duration-300 {puntajeRubrica / puntajeMaximo >= 0.6 ? 'bg-emerald-500' : 'bg-amber-400'}"
          style="width: {Math.min(100, Math.round((puntajeRubrica / puntajeMaximo) * 100))}%"
        ></div>
      </div>
    {/if}

    <!-- Criterios (scroll vertical) -->
    <div class="flex-1 overflow-y-auto p-3 space-y-4 custom-scrollbar">
      {#each rubricaActividad.niveles as nivel}
        {@const nivelEvaluado = !!seleccionRubrica[nivel.id]}
        {@const escalaElegida = nivel.escalas.find(e => e.id === seleccionRubrica[nivel.id])}

        <div class="border-2 rounded-2xl overflow-hidden {!nivelEvaluado ? 'border-amber-300' : 'border-emerald-400'}">
          <!-- Cabecera del criterio -->
          <div class="px-4 py-3 {!nivelEvaluado ? 'bg-amber-50' : 'bg-emerald-50'} border-b border-inherit">
            <p class="font-bold text-sm text-gray-800">{nivel.nombre}</p>
            {#if nivel.descripcion}
              <p class="text-xs text-gray-500 mt-0.5 leading-snug">{nivel.descripcion}</p>
            {/if}
            <div class="flex items-center gap-2 mt-1.5">
              <span class="text-xs text-gray-400">máx. {nivel.puntaje_total} pts</span>
              {#if !nivelEvaluado}
                <span class="text-[11px] font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">⚠ Pendiente</span>
              {:else}
                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                  ✓ {escalaElegida?.puntos} pts
                </span>
              {/if}
            </div>
          </div>

          <!-- Escalas -->
          <div class="p-3 flex flex-col gap-2 bg-white">
            {#each nivel.escalas as escala}
              {@const elegida = seleccionRubrica[nivel.id] === escala.id}
              <button
                type="button"
                onclick={() => seleccionarEscala(nivel.id, escala.id)}
                class="text-left w-full p-3 rounded-xl border-2 transition-all
                  {elegida
                    ? 'border-uta-blue bg-uta-blue/10 shadow-sm'
                    : 'border-gray-200 bg-gray-50 hover:border-uta-blue/40 hover:bg-uta-blue/5'}"
              >
                <div class="flex items-center gap-2 mb-1.5">
                  <span class="text-xs font-black px-2 py-0.5 rounded-md transition-colors
                    {elegida ? 'bg-uta-blue text-white' : 'bg-gray-200 text-gray-600'}">
                    {escala.puntos} pts
                  </span>
                  {#if elegida}
                    <CheckCircle2 class="w-4 h-4 text-uta-blue shrink-0" />
                  {/if}
                </div>
                <p class="text-xs text-gray-600 leading-snug">{escala.criterio}</p>
              </button>
            {/each}
          </div>
        </div>
      {/each}
    </div>

    <!-- Footer con resumen y confirmar -->
    <div class="shrink-0 px-4 py-3 border-t flex items-center justify-between gap-3
      {todosEvaluados ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'}">
      <div class="text-xs leading-snug">
        {#if todosEvaluados && notaCalculada !== null}
          <p class="font-bold text-emerald-700">Nota calculada: {notaCalculada.toFixed(1)}</p>
          <p class="text-emerald-600">{puntajeRubrica}/{puntajeMaximo} puntos (60% exigencia)</p>
        {:else}
          <p class="font-semibold text-amber-700">{criteriosEvaluados}/{totalCriterios} criterios evaluados</p>
          <p class="text-amber-600">Selecciona un nivel por cada criterio</p>
        {/if}
      </div>
      <button
        onclick={() => (mostrarRubricaMobile = false)}
        class="shrink-0 px-5 py-2.5 text-xs font-bold rounded-xl transition-colors
          {todosEvaluados
            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
            : 'bg-uta-blue text-white hover:bg-uta-blue-hover'}"
      >
        {todosEvaluados ? 'Confirmar y volver' : 'Volver al formulario'}
      </button>
    </div>
  </div>
{/if}

<style>
  .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
</style>
