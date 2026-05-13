<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';
  import RubricaView from './Rubrica.svelte';

  interface Props {
    onCerrar: () => void;
    onInteraccionEnviada: (data: { tipo: string; mensaje: string }) => void;
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    listado_interacciones: Array<{
      id_interaccion: number;
      fecha_emision: string;
      tipo_interaccion: string;
      emisor: string;
      mensaje: string;
      es_de_docente: boolean;
      es_retroalimentacion: boolean;
      adjunta_rubrica: boolean;
      rubrica?: Rubrica;
      puntaje_obtenido?: number;
    }>;
  }

  let {
    onCerrar,
    onInteraccionEnviada,
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    listado_interacciones,
  }: Props = $props();

  let nuevoMensaje = $state('');
  let tipoSeleccionado = $state('Consulta');

  let interaccionSeleccionada = $state<{
    rubrica?: Rubrica;
    puntaje_obtenido?: number;
    retroalimentacion?: string;
  } | null>(null);

  const tiposInteraccion = ['Consulta', 'Entrega de Avance', 'Duda sobre Rúbrica', 'Otro'];

  function manejarEnvio() {
    if (nuevoMensaje.trim() === '') return;

    onInteraccionEnviada({
      tipo: tipoSeleccionado,
      mensaje: nuevoMensaje,
    });

    nuevoMensaje = '';
    tipoSeleccionado = tiposInteraccion[0];
  }
</script>

<div class="w-full sm:w-[90%] h-[90vh] flex rounded-4xl bg-white shadow-2xl overflow-hidden">
  <div class="flex-1 flex flex-col sm:w-1/3 px-8 md:px-16 py-8 overflow-hidden">
    <div class="flex justify-between items-center mb-6 shrink-0 border-b pb-4">
      <div>
        <p class="text-2xl font-bold text-primary">Agenda de Actividad</p>

        <p class="text-sm text-gray-500">
          {cod_curso} - {nombre_actividad}
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

    <div class="flex-1 overflow-y-auto pr-4 mb-6 custom-scrollbar">
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
                retroalimentacion: item.mensaje
              };
            }
          }}
        >
          <div class="flex justify-between items-start">
            <div class="flex gap-1 flex-wrap">
              <span class="text-[10px] font-bold bg-white/50 px-2 py-1 rounded-md border">
                {item.tipo_interaccion}
              </span>
              <span class="text-[10px] font-bold bg-white/50 px-2 py-1 ">
                {item.fecha_emision}
              </span>
            </div>
            
            {#if item.adjunta_rubrica}
              <div class="flex gap-4 items-center">
                <span class="text-[10px] font-black text-primary uppercase px-2 py-1 rounded">
                  Ver Evaluación
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
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
        </button>
      {:else}
        <p class="text-center text-gray-400 py-10 italic">No hay interacciones registradas.</p>
      {/each}
    </div>

    <div class="shrink-0 bg-gray-50 p-6 rounded-3xl">
      <div class="flex flex-wrap items-center gap-4 mb-3">
        <p class="text-sm font-bold text-gray-600">Tipo de Mensaje:</p>

        <select
          bind:value={tipoSeleccionado}
          class="text-sm border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white px-4 py-1"
        >
          {#each tiposInteraccion as tipo}
            <option value={tipo}>
              {tipo}
            </option>
          {/each}
        </select>
      </div>

      <div class="relative">
        <textarea
          bind:value={nuevoMensaje}
          placeholder="Escribe tu mensaje aquí..."
          class="w-full p-4 pr-16 text-sm border-gray-300 rounded-2xl focus:ring-primary focus:border-primary resize-none shadow-sm"
          rows="3"
        ></textarea>

        <button
          onclick={manejarEnvio}
          disabled={!nuevoMensaje.trim()}
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

  {#if interaccionSeleccionada?.rubrica}
    <div class="fixed inset-0 z-50 sm:relative sm:inset-auto sm:w-2/3 w-full border-l bg-gray-50 h-full overflow-y-auto p-6 animate-slide-in">
        <div class="flex justify-between items-center mb-6">
        <p class="font-bold text-lg text-primary">Detalle de Evaluación</p>

        <button
            class="p-2 hover:bg-gray-200 rounded-full transition-colors flex items-center gap-2 group"
            onclick={() => (interaccionSeleccionada = null)}
        >
            <span class="text-xs font-bold uppercase text-gray-400 group-hover:text-primary transition-colors">
            Cerrar Detalle
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
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