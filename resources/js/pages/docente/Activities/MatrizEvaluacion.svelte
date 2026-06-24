<!--
  MatrizEvaluacion.svelte — Pantalla completa para evaluar a un grupo con una
  rúbrica. El docente selecciona un nivel por cada criterio; el puntaje y la
  nota chilena (1–7) se calculan automáticamente (vía `@/lib/notas`) y pueden
  ajustarse manualmente antes de confirmar. Al confirmar persiste la evaluación
  en el backend (POST a /docente/cursos/.../evaluacion) y llama `onSuccess`.

  Props: rubrica + rubricaId, identificadores del contexto (idCurso, idActividad,
  idGrupo, idAgendaEntrega opcional), nombres para el encabezado, y callbacks
  `onClose` / `onSuccess`.
-->
<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';
  import { router } from '@inertiajs/svelte';
  import { X, CheckCircle2 } from 'lucide-svelte';
  import { calcularNotaChilena } from '@/lib/notas';

  interface Props {
    rubrica: Rubrica;
    rubricaId: number;
    nombreActividad: string;
    nombreGrupo: string;
    idCurso: number;
    idActividad: number;
    idGrupo: number;
    idAgendaEntrega?: number | null;
    onClose: () => void;
    onSuccess?: () => void;
  }

  let {
    rubrica,
    rubricaId,
    nombreActividad,
    nombreGrupo,
    idCurso,
    idActividad,
    idGrupo,
    idAgendaEntrega = null,
    onClose,
    onSuccess = undefined,
  }: Props = $props();

  let seleccion = $state<Record<string, string>>({});
  let retroalimentacion = $state('');
  let notaOverride = $state<number | null>(null);
  let notaManualOverride = $state(false);
  let saving = $state(false);
  let error = $state<string | null>(null);

  // ── Derivados ──────────────────────────────────────────────────────────────

  const maxEscalas = $derived(
    Math.max(...rubrica.niveles.map((n) => n.escalas.length), 0),
  );

  const puntajeMaximo = $derived(
    rubrica.detalles_evaluacion?.puntaje_total ??
      rubrica.niveles.reduce((acc, n) => acc + n.puntaje_total, 0),
  );

  const puntajeObtenido = $derived(
    rubrica.niveles.reduce((acc, nivel) => {
      const escId = seleccion[nivel.id];
      if (!escId) return acc;
      return acc + (nivel.escalas.find((e) => e.id === escId)?.puntos ?? 0);
    }, 0),
  );

  const criteriosEvaluados = $derived(
    rubrica.niveles.filter((n) => !!seleccion[n.id]).length,
  );
  const totalCriterios = $derived(rubrica.niveles.length);
  const todosEvaluados = $derived(totalCriterios > 0 && criteriosEvaluados === totalCriterios);

  const porcentaje = $derived(
    puntajeMaximo > 0
      ? Math.min(100, Math.round((puntajeObtenido / puntajeMaximo) * 100))
      : 0,
  );

  const notaCalculada = $derived(
    todosEvaluados && puntajeMaximo > 0 ? calcularNotaChilena(puntajeObtenido, puntajeMaximo) : null,
  );

  const evaluacionLabel = $derived(
    (() => {
      const escala = rubrica.detalles_evaluacion?.escala_evaluacion;
      if (!escala?.length || !todosEvaluados) return null;
      const sorted = [...escala].sort((a, b) => b.puntaje_minimo - a.puntaje_minimo);
      return sorted.find((e) => porcentaje >= e.puntaje_minimo)?.evaluacion ?? null;
    })(),
  );

  // Auto-poblar nota cuando se completa la rúbrica
  $effect(() => {
    if (notaCalculada !== null && !notaManualOverride) {
      notaOverride = notaCalculada;
    }
  });

  // ── Acciones ───────────────────────────────────────────────────────────────

  function seleccionarEscala(nivelId: string, escalaId: string) {
    seleccion = { ...seleccion, [nivelId]: escalaId };
    notaManualOverride = false;
  }

  function confirmar() {
    if (!todosEvaluados) {
      error = 'Selecciona un nivel para cada criterio antes de confirmar.';
      return;
    }
    error = null;
    saving = true;
    router.post(
      `/docente/cursos/${idCurso}/actividades/${idActividad}/grupos/${idGrupo}/evaluacion`,
      {
        id_agenda_entrega: idAgendaEntrega,
        id_rubrica: rubricaId,
        nota: notaOverride,
        mensaje: retroalimentacion,
        resultado_rubrica: { ...seleccion },
        puntaje_obtenido: puntajeObtenido,
      },
      {
        onSuccess: () => {
          saving = false;
          onSuccess?.();
          onClose();
        },
        onError: () => {
          saving = false;
          error = 'Error al guardar la evaluación. Intenta nuevamente.';
        },
      },
    );
  }
</script>

<!-- Pantalla completa sobre todo lo demás -->
<div class="fixed inset-0 z-[70] flex flex-col bg-white overflow-hidden">

  <!-- ── Header ── -->
  <div class="shrink-0 bg-uta-blue text-white px-4 sm:px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
    <div class="flex items-center gap-3 min-w-0">
      <button
        onclick={onClose}
        class="p-1.5 rounded-full hover:bg-white/10 transition shrink-0"
        title="Cerrar"
      >
        <X class="w-5 h-5" />
      </button>
      <div class="min-w-0">
        <h2 class="text-sm sm:text-base font-bold truncate">Evaluar: {nombreActividad}</h2>
        <p class="text-xs text-white/70 truncate mt-0.5">{nombreGrupo}</p>
      </div>
    </div>

    <!-- Métricas resumidas -->
    <div class="flex items-center gap-5 text-center shrink-0">
      <div>
        <p class="text-[10px] uppercase tracking-wider text-white/60">Puntaje</p>
        <p class="font-black text-xl leading-none">
          {puntajeObtenido}<span class="text-sm font-normal text-white/60">/{puntajeMaximo}</span>
        </p>
      </div>
      <div>
        <p class="text-[10px] uppercase tracking-wider text-white/60">Criterios</p>
        <p
          class="font-black text-xl leading-none {criteriosEvaluados < totalCriterios
            ? 'text-yellow-300'
            : 'text-green-300'}"
        >
          {criteriosEvaluados}/{totalCriterios}
        </p>
      </div>
      {#if notaCalculada !== null}
        <div>
          <p class="text-[10px] uppercase tracking-wider text-white/60">Nota</p>
          <p class="font-black text-2xl leading-none text-yellow-300">{notaCalculada.toFixed(1)}</p>
        </div>
      {/if}
    </div>

    <button
      onclick={confirmar}
      disabled={!todosEvaluados || saving}
      class="shrink-0 px-5 py-2 bg-white text-uta-blue text-sm font-bold rounded-xl hover:bg-gray-100 transition disabled:opacity-40"
    >
      {saving ? 'Guardando…' : 'Confirmar Evaluación'}
    </button>
  </div>

  <!-- Barra de progreso -->
  {#if puntajeMaximo > 0}
    <div class="h-1.5 bg-white/20 bg-gray-200 shrink-0">
      <div
        class="h-full transition-all duration-300 {porcentaje >= 60 ? 'bg-emerald-500' : 'bg-amber-400'}"
        style="width: {porcentaje}%"
      ></div>
    </div>
  {/if}

  <!-- Error -->
  {#if error}
    <div class="px-6 py-2 bg-red-50 border-b border-red-200 text-sm text-red-700 shrink-0">
      {error}
    </div>
  {/if}

  <!-- ── Cuerpo ── -->
  <div class="flex-1 overflow-auto">
    <div class="px-4 sm:px-6 lg:px-8 py-6 max-w-[1400px] mx-auto">

      <!-- Tabla de evaluación -->
      <div class="overflow-x-auto rounded-3xl border-2 border-gray-100 mb-8">
        <table
          class="w-full border-collapse"
          style="min-width: {240 + maxEscalas * 220}px"
        >
          <thead>
            <tr class="bg-gray-50 border-b-2 border-gray-100">
              <th
                class="px-5 py-4 text-left text-xs font-black text-gray-500 uppercase tracking-wider border-r-2 border-gray-100"
                style="width:240px; min-width:220px"
              >
                Criterio de Evaluación
              </th>
              {#each Array(maxEscalas) as _, ci}
                <th
                  class="px-5 py-4 text-center text-xs font-black text-gray-500 uppercase tracking-wider border-r border-gray-100 last:border-r-0"
                  style="min-width:210px"
                >
                  Nivel {ci + 1}
                </th>
              {/each}
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            {#each rubrica.niveles as nivel (nivel.id)}
              {@const nivelEvaluado = !!seleccion[nivel.id]}
              {@const escalaElegida = nivel.escalas.find((e) => e.id === seleccion[nivel.id])}
              <tr
                class="{nivelEvaluado
                  ? 'bg-white'
                  : 'bg-amber-50/40'} transition-colors group"
              >
                <!-- Columna criterio -->
                <td class="px-5 py-4 align-top border-r-2 border-gray-100 bg-gray-50/50">
                  <p class="font-bold text-gray-800 leading-snug mb-1">{nivel.nombre}</p>
                  {#if nivel.descripcion}
                    <p class="text-xs text-gray-500 italic leading-snug mb-2">{nivel.descripcion}</p>
                  {/if}
                  <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs text-gray-400">máx. {nivel.puntaje_total} pts</span>
                    {#if !nivelEvaluado}
                      <span
                        class="text-[11px] font-bold text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full"
                      >
                        Pendiente
                      </span>
                    {:else}
                      <span
                        class="text-[11px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full"
                      >
                        ✓ {escalaElegida?.puntos} pts
                      </span>
                    {/if}
                  </div>
                </td>

                <!-- Columnas de niveles/escalas -->
                {#each Array(maxEscalas) as _, ci}
                  <td class="px-3 py-3 align-top border-r border-gray-100 last:border-r-0">
                    {#if nivel.escalas[ci]}
                      {@const escala = nivel.escalas[ci]}
                      {@const elegida = seleccion[nivel.id] === escala.id}
                      <button
                        type="button"
                        onclick={() => seleccionarEscala(nivel.id, escala.id)}
                        class="w-full h-full min-h-[90px] text-left p-3 rounded-xl border-2 transition-all duration-150
                          {elegida
                            ? 'border-uta-blue bg-uta-blue/10 shadow-md'
                            : 'border-gray-200 bg-white hover:border-uta-blue/40 hover:bg-uta-blue/5 hover:shadow-sm'}"
                      >
                        <div class="flex items-center gap-2 mb-2">
                          <span
                            class="text-xs font-black px-2 py-0.5 rounded-md transition-colors
                              {elegida ? 'bg-uta-blue text-white' : 'bg-gray-100 text-gray-600'}"
                          >
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

      <!-- Nota final + retroalimentación -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">

        <!-- Nota -->
        <div class="bg-gray-50/50 rounded-3xl border border-gray-100 p-6">
          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Nota Final (1–7)</p>
          <div class="flex items-start gap-4">
            <input
              type="number"
              bind:value={notaOverride}
              min="1"
              max="7"
              step="0.1"
              oninput={() => (notaManualOverride = true)}
              placeholder="—"
              class="w-28 text-3xl font-black text-uta-blue bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 focus:outline-none focus:border-uta-blue/50 focus:ring-2 focus:ring-uta-blue/20 text-center"
            />
            <div class="text-sm text-gray-500 leading-relaxed pt-1">
              {#if !todosEvaluados}
                <span class="text-amber-600 text-xs">
                  Completa todos los criterios para calcular la nota automáticamente.
                </span>
              {:else if notaCalculada !== null && notaManualOverride}
                <span class="text-xs">
                  Calculada automáticamente:
                  <strong class="text-uta-blue">{notaCalculada.toFixed(1)}</strong>
                </span>
                <br />
                <button
                  type="button"
                  onclick={() => {
                    notaOverride = notaCalculada;
                    notaManualOverride = false;
                  }}
                  class="text-uta-blue underline hover:no-underline text-xs mt-1"
                >
                  Restaurar calculada
                </button>
              {:else}
                <span class="text-emerald-600 font-semibold text-xs block">
                  Calculada con 60 % de exigencia.
                </span>
                {#if evaluacionLabel}
                  <span class="font-black text-gray-700 text-base mt-1 block">{evaluacionLabel}</span>
                {/if}
                <span class="text-xs text-gray-400 block mt-1">
                  {puntajeObtenido}/{puntajeMaximo} pts ({porcentaje}%)
                </span>
              {/if}
            </div>
          </div>
        </div>

        <!-- Retroalimentación -->
        <div class="bg-gray-50/50 rounded-3xl border border-gray-100 p-6">
          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">
            Retroalimentación <span class="font-normal normal-case text-gray-300">(opcional)</span>
          </p>
          <textarea
            bind:value={retroalimentacion}
            placeholder="Escribe un comentario para el grupo…"
            rows="4"
            class="w-full text-sm text-gray-700 bg-white border border-gray-200 rounded-2xl px-4 py-3 resize-none focus:outline-none focus:border-uta-blue/40 focus:ring-1 focus:ring-uta-blue/20 leading-snug"
          ></textarea>
        </div>
      </div>

    </div>
  </div>

  <!-- ── Footer de estado ── -->
  <div
    class="shrink-0 px-6 py-3 border-t {todosEvaluados
      ? 'bg-emerald-50 border-emerald-100'
      : 'bg-amber-50 border-amber-100'}"
  >
    {#if todosEvaluados}
      <p class="text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
        <CheckCircle2 class="w-4 h-4 shrink-0" />
        Todos los criterios evaluados — {puntajeObtenido}/{puntajeMaximo} pts ({porcentaje}%). Nota
        calculada: {notaCalculada?.toFixed(1) ?? '—'}. Puedes ajustar la nota manualmente arriba.
      </p>
    {:else}
      <p class="text-xs font-semibold text-amber-700">
        {criteriosEvaluados}/{totalCriterios} criterios evaluados — selecciona un nivel por cada
        criterio (celdas de la tabla) para completar la evaluación.
      </p>
    {/if}
  </div>
</div>
