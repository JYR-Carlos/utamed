<script lang="ts">
  // 1. Autor: Juan Y
  // 2. Fecha: 04/06/2025
  // 3. Se eliminan tipos de interacción no válidos ('Recordatorio', 'Respuesta').
  //    Se agrega selección de entrega a vincular cuando el tipo es 'Evaluación'.
  //    Se actualiza la firma del callback onInteraccionEnviada para incluir
  //    id_agenda_entrega (opcional) y nota.
  import type { Rubrica } from '@/types/rubrica';
  import RubricaView from '../../../student/Activities/Agenda/Rubrica.svelte';

  interface Props {
    onCerrar: () => void;
    onInteraccionEnviada: (data: {
      tipo: string;
      mensaje: string;
      nota?: number;
      id_agenda_entrega?: number | null;
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
    }>;
    isLoading?: boolean;
    errorMensaje?: string | null;
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
  }: Props = $props();

  let nuevoMensaje = $state('');
  let tipoSeleccionado = $state('Feedback');
  let notaEvaluacion = $state<number | null>(null);
  let entregaSeleccionada = $state<number | null>(null);

  let interaccionSeleccionada = $state<{
    rubrica?: Rubrica;
    puntaje_obtenido?: number;
    retroalimentacion?: string;
  } | null>(null);

  // Solo tipos válidos según el ENUM de la base de datos
  const tiposInteraccion = ['Feedback', 'Evaluación'];

  const esEvaluacion = $derived(tipoSeleccionado === 'Evaluación');

  // Entregas del grupo que aún no tienen evaluación
  const entregasSinEvaluar = $derived(
    listado_interacciones.filter((i) => i.es_entrega && !i.tiene_evaluacion),
  );

  function manejarEnvio() {
    if (nuevoMensaje.trim() === '' && !esEvaluacion) return;
    if (esEvaluacion && notaEvaluacion === null) return;

    const data: {
      tipo: string;
      mensaje: string;
      nota?: number;
      id_agenda_entrega?: number | null;
    } = {
      tipo: tipoSeleccionado,
      mensaje: nuevoMensaje,
    };

    if (esEvaluacion) {
      if (notaEvaluacion !== null) data.nota = notaEvaluacion;
      data.id_agenda_entrega = entregaSeleccionada;
    }

    onInteraccionEnviada(data);

    nuevoMensaje = '';
    notaEvaluacion = null;
    entregaSeleccionada = null;
    tipoSeleccionado = tiposInteraccion[0];
  }
</script>

<div class="w-full sm:w-[90%] h-[90vh] flex rounded-4xl bg-white shadow-2xl overflow-hidden">
  <!-- Panel izquierdo: lista de interacciones + formulario -->
  <div class="flex-1 flex flex-col sm:w-1/3 px-8 md:px-16 py-8 overflow-hidden">
    <!-- Cabecera -->
    <div class="flex justify-between items-center mb-6 shrink-0 border-b pb-4">
      <div>
        <p class="text-2xl font-bold text-primary">Agenda del Grupo</p>
        <p class="text-sm text-gray-500">
          {cod_curso} — {nombre_actividad}
        </p>
        <p class="text-xs font-bold text-primary/70 mt-1">
          {nombre_grupo}
        </p>
      </div>

      <button
        class="p-2 hover:bg-gray-100 rounded-full transition-colors"
        onclick={onCerrar}
        aria-label="cerrar"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="w-6 h-6"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M6 18L18 6M6 6l12 12"
          />
        </svg>
      </button>
    </div>

    <!-- Lista de interacciones -->
    <div class="flex-1 overflow-y-auto pr-4 mb-6 custom-scrollbar">
      {#if isLoading}
        <div class="flex flex-col gap-3 animate-pulse">
          {#each [1, 2, 3] as _}
            <div class="h-20 rounded-xl bg-gray-100 border-l-4 border-gray-200"></div>
          {/each}
        </div>
      {:else if errorMensaje}
        <div class="flex items-center justify-center h-full text-sm text-red-500 text-center px-4">
          {errorMensaje}
        </div>
      {:else if listado_interacciones.length === 0}
        <div class="flex flex-col items-center justify-center h-full gap-3 text-center px-4">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-10 text-gray-300"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"
            />
          </svg>
          <p class="text-sm font-semibold text-gray-500">Sin mensajes todavía</p>
          <p class="text-xs text-gray-400">
            Escribe un <span class="font-bold">Feedback</span> abajo para iniciar el chat con el grupo.
          </p>
        </div>
      {:else}
        {#each listado_interacciones as item}
          <button
            class="mb-4 p-4 w-full text-start border-l-4 transition-all rounded-r-xl
          {item.es_de_docente ? 'border-primary bg-secondary/20' : 'border-gray-300'}
          {item.adjunta_rubrica ? 'cursor-pointer hover:bg-amber-50 shadow-sm' : 'cursor-default'}"
            onclick={() => {
              if (item.adjunta_rubrica && item.rubrica) {
                interaccionSeleccionada = {
                  rubrica: item.rubrica,
                  puntaje_obtenido: item.puntaje_obtenido,
                  retroalimentacion: item.mensaje,
                };
              }
            }}
          >
            <div class="flex justify-between items-start">
              <div class="flex gap-1 flex-wrap">
                <span class="text-[10px] font-bold bg-white/50 px-2 py-1 rounded-md border">
                  {item.tipo_interaccion}
                </span>
                <span class="text-[10px] font-bold bg-white/50 px-2 py-1">
                  {item.fecha_emision}
                </span>
                {#if item.es_de_docente}
                  <span
                    class="text-[10px] font-bold bg-primary/10 text-primary px-2 py-1 rounded-md"
                  >
                    Docente
                  </span>
                {/if}
              </div>

              {#if item.adjunta_rubrica}
                <div class="flex gap-4 items-center">
                  <span class="text-[10px] font-black text-primary uppercase px-2 py-1 rounded">
                    Ver Evaluación
                  </span>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                    />
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                    />
                  </svg>
                </div>
              {/if}
            </div>

            <p class="text-xs font-bold uppercase mt-2">
              {item.emisor}
            </p>

            <p class="text-sm mt-1 text-gray-700">
              {item.mensaje}
            </p>

            {#if item.adjunta_rubrica && item.puntaje_obtenido !== undefined}
              <p class="text-xs font-bold text-primary mt-2">
                Puntaje: {item.puntaje_obtenido} pts
              </p>
            {/if}
          </button>
        {/each}
      {/if}
    </div>

    <!-- Formulario de envío (docente) -->
    <div class="shrink-0 bg-gray-50 p-6 rounded-3xl">
      <div class="flex flex-wrap items-center gap-4 mb-3">
        <p class="text-sm font-bold text-gray-600">Tipo de Mensaje:</p>
        <select
          bind:value={tipoSeleccionado}
          class="text-sm border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white px-4 py-1"
        >
          {#each tiposInteraccion as tipo}
            <option value={tipo}>{tipo}</option>
          {/each}
        </select>
      </div>

      <!-- Campo extra para nota cuando es Evaluación -->
      {#if esEvaluacion}
        <div class="flex flex-col gap-3 mb-3 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <div class="flex items-center gap-3">
            <label for="nota-evaluacion" class="text-sm font-bold text-amber-800 shrink-0"
              >Nota (1–7):</label
            >
            <input
              id="nota-evaluacion"
              type="number"
              min="1"
              max="7"
              step="0.1"
              bind:value={notaEvaluacion}
              placeholder="ej. 5.5"
              class="w-24 text-sm border-amber-300 rounded-lg focus:ring-amber-400 focus:border-amber-400 px-3 py-1 bg-white"
            />
          </div>
          {#if entregasSinEvaluar.length > 0}
            <div class="flex items-center gap-3">
              <label for="entrega-evaluar" class="text-sm font-bold text-amber-800 shrink-0"
                >Entrega a evaluar:</label
              >
              <select
                id="entrega-evaluar"
                bind:value={entregaSeleccionada}
                class="flex-1 text-sm border-amber-300 rounded-lg focus:ring-amber-400 focus:border-amber-400 px-3 py-1 bg-white"
              >
                <option value={null}>— Ninguna (evaluación general) —</option>
                {#each entregasSinEvaluar as entrega}
                  <option value={entrega.id_interaccion}>
                    #{entrega.id_interaccion} · {entrega.fecha_emision}
                  </option>
                {/each}
              </select>
            </div>
          {/if}
          <p class="text-xs text-amber-600">
            La nota y retroalimentación quedarán registradas para el grupo.
          </p>
        </div>
      {/if}

      <div class="relative">
        <textarea
          bind:value={nuevoMensaje}
          placeholder={esEvaluacion
            ? 'Escribe tu retroalimentación detallada aquí...'
            : 'Escribe tu mensaje aquí...'}
          class="w-full p-4 pr-16 text-sm border-gray-300 rounded-2xl focus:ring-primary focus:border-primary resize-none shadow-sm"
          rows="3"
        ></textarea>

        <button
          onclick={manejarEnvio}
          disabled={esEvaluacion ? notaEvaluacion === null : !nuevoMensaje.trim()}
          class="absolute bottom-4 right-4 p-3 bg-primary text-secondary rounded-xl hover:scale-105 disabled:opacity-50 disabled:scale-100 transition-all shadow-lg"
          aria-label="enviar"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-5 h-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
          >
            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" />
          </svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Panel derecho: detalle de evaluación al hacer clic en interacción -->
  {#if interaccionSeleccionada?.rubrica}
    <div
      class="fixed inset-0 z-50 sm:relative sm:inset-auto sm:w-2/3 w-full border-l bg-gray-50 h-full overflow-y-auto p-6 animate-slide-in"
    >
      <div class="flex justify-between items-center mb-6">
        <p class="font-bold text-lg text-primary">Detalle de Evaluación</p>

        <button
          class="p-2 hover:bg-gray-200 rounded-full transition-colors flex items-center gap-2 group"
          onclick={() => (interaccionSeleccionada = null)}
        >
          <span
            class="text-xs font-bold uppercase text-gray-400 group-hover:text-primary transition-colors"
          >
            Cerrar Detalle
          </span>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-6 h-6"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            />
          </svg>
        </button>
      </div>

      <div class="pb-10">
        <RubricaView
          rubrica={interaccionSeleccionada.rubrica}
          puntaje_obtenido={interaccionSeleccionada.puntaje_obtenido ?? 0}
          retroalimentacion={interaccionSeleccionada.retroalimentacion}
        />
      </div>
    </div>
  {/if}
</div>

<style>
  .custom-scrollbar::-webkit-scrollbar {
    width: 6px;
  }

  .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
  }

  .custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
  }

  @keyframes slide-in {
    from {
      transform: translateX(100%);
      opacity: 0;
    }

    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  .animate-slide-in {
    animation: slide-in 0.25s ease-out;
  }
</style>
