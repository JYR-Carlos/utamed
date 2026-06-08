<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
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
      rubrica?: Rubrica | null;
      puntaje_obtenido?: number | null;
    }>;
    id_actividad_asignada_grupo?: number | null;
    onAgendaClick: () => void;
  }

  let {
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    listado_interacciones,
    id_actividad_asignada_grupo,
    onAgendaClick,
  }: Props = $props();
</script>

<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
  <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
      <svg
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
    </div>
    <div class="flex-1">
      <h2 class="text-base font-bold text-slate-900">Agenda de la Actividad</h2>
      <p class="text-xs text-slate-500">
        Retroalimentaciones y mensajes entre docente y estudiante
      </p>
    </div>
    <button
      class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
      onclick={onAgendaClick}
    >
      <svg
        width="14"
        height="14"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
      Abrir mensajes
    </button>
  </div>

  <div class="rounded-xl bg-slate-50 p-4">
    {#if listado_interacciones.length > 0}
      {@const last = listado_interacciones[listado_interacciones.length - 1]}
      <div class="flex items-start gap-3">
        <div
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-sm
          {last.es_de_docente ? 'bg-indigo-600' : 'bg-emerald-600'}"
        >
          {last.es_de_docente ? 'D' : 'T'}
        </div>
        <div class="flex-1 space-y-1">
          <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <span class="font-bold text-slate-900">{last.es_de_docente ? 'Docente' : 'Tú'}</span>
            <span class="text-slate-300">·</span>
            <span class="font-medium text-slate-500">{last.tipo_interaccion}</span>
            <span class="ml-auto text-slate-400 font-medium">
              {listado_interacciones.length} mensaje{listado_interacciones.length !== 1 ? 's' : ''}
              en total
            </span>
          </div>
          <p class="text-sm leading-relaxed text-slate-600 line-clamp-2">
            {last.mensaje}
          </p>
        </div>
      </div>
    {:else}
      <div class="flex flex-col items-center justify-center gap-2 py-4 text-center text-slate-400">
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <span class="text-xs font-medium"
          >No hay mensajes aún. Abre la agenda para enviar una consulta.</span
        >
      </div>
    {/if}
  </div>
</div>
