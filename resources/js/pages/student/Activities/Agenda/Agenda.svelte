<script lang="ts">
  /**
   * Agenda de la actividad: hilo conversacional unificado con equipo docente y compañeros.
   *
   * Rediseño según tarjetas T42 (chat y burbujas temáticas) y T07 (confirmación de lectura).
   * Utiliza el subcomponente compartido AgendaHilo.svelte para el renderizado del hilo.
   */
  import type { Rubrica } from '@/types/rubrica';
  import RubricaView from './Rubrica.svelte';
  import AgendaHilo from './AgendaHilo.svelte';
  import type { InteraccionItem, RubricaDetalleEvent } from '@/types/agenda';
  import { router } from '@inertiajs/svelte';
  import { X, Send } from 'lucide-svelte';

  interface Props {
    onCerrar: () => void;
    onInteraccionEnviada: (data: { tipo: string; mensaje: string }) => void;
    id_curso?: number;
    idCurso?: number;
    cod_curso: string;
    nombre_curso?: string;
    cod_actividad?: string;
    idActividad?: number;
    nombre_actividad: string;
    entrega_obligatoria?: boolean;
    inline?: boolean;
    id_actividad_asignada_grupo?: number | null;
    idGrupo?: number | null;
    listado_interacciones: InteraccionItem[];
    equipoDocente?: Array<{ nombre: string; es_titular: boolean }>;
    esSumativa?: boolean;
    esDocente?: boolean;
    nombre_grupo?: string;
    isLoading?: boolean;
    errorMensaje?: string | null;
  }

  let {
    onCerrar,
    onInteraccionEnviada,
    id_curso,
    idCurso,
    cod_curso,
    nombre_curso = '',
    cod_actividad = '',
    idActividad,
    nombre_actividad,
    entrega_obligatoria = false,
    listado_interacciones,
    inline = false,
    esSumativa = undefined,
    esDocente = false,
    nombre_grupo = undefined,
    idGrupo = undefined,
    isLoading = false,
    errorMensaje = null,
  }: Props = $props();

  const cursoId = $derived(id_curso ?? idCurso ?? 0);
  const actividadIdentificador = $derived(idActividad ?? cod_actividad ?? '');

  let nuevoMensaje = $state('');
  let interaccionSeleccionada = $state<RubricaDetalleEvent | null>(null);

  $effect(() => {
    // Polling cada 3 segundos para refrescar nuevos mensajes y confirmaciones de lectura («Visto por»)
    const poll = router.poll(3000, {
      only: ['listado_interacciones'],
      showProgress: false,
    });
    return () => {
      poll.stop();
    };
  });

  function manejarEnvio() {
    if (nuevoMensaje.trim() === '') return;
    onInteraccionEnviada({
      tipo: 'Consulta',
      mensaje: nuevoMensaje.trim(),
    });
    nuevoMensaje = '';
  }

  function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      manejarEnvio();
    }
  }

  function urlDescargaEntrega(idInteraccion: number, ver = false): string {
    let url = '';
    if (esDocente && idGrupo) {
      url = `/docente/cursos/${cursoId}/actividades/${actividadIdentificador}/grupos/${idGrupo}/entregas/${idInteraccion}/descargar`;
    } else {
      url = `/estudiante/cursos/${cursoId}/actividades/${actividadIdentificador}/entregas/${idInteraccion}/descargar`;
    }
    return ver ? `${url}?ver=1` : url;
  }
</script>

<svelte:window
  onkeydown={(e) => {
    if (e.key === 'Escape' && interaccionSeleccionada) {
      interaccionSeleccionada = null;
    }
  }}
/>

<div
  class="overflow-hidden relative {inline
    ? 'w-full rounded-b-xl bg-white min-h-[560px] flex'
    : 'flex w-[96%] sm:w-[94%] lg:w-[92%] 2xl:w-[90%] h-[90vh] max-w-[1440px] rounded-2xl bg-white shadow-2xl'}"
>
  <div class="flex min-w-0 flex-1 flex-col h-full relative">
    <!-- 1. CABECERA: TÍTULO PRESERVADO SIN FILTROS REDUNDANTES -->
    <div class="flex shrink-0 items-center justify-between border-b border-[#E5E7EB] px-5 py-3.5 bg-white z-10">
      <div class="flex min-w-0 flex-col">
        <span class="text-[15px] font-semibold text-[#1A1A24]">
          {esDocente ? 'Agenda del Grupo' : 'Agenda de la actividad'}
        </span>
        <span class="truncate text-xs text-[#5A5E6E]">
          {cod_curso} · {nombre_actividad}{nombre_grupo ? ` · ${nombre_grupo}` : ''}
        </span>
      </div>
      {#if !inline}
        <button
          class="rounded-full p-1.5 text-[#5A5E6E] transition-colors hover:bg-[#F8FAFC] cursor-pointer"
          onclick={onCerrar}
          aria-label="Cerrar agenda"
        >
          <X class="h-5 w-5" />
        </button>
      {/if}
    </div>

    <!-- 2. HILO CONVERSACIONAL (Componente compartido con estilo WhatsApp) -->
    <AgendaHilo
      {listado_interacciones}
      {esDocente}
      {urlDescargaEntrega}
      onVerRubrica={(detalle) => (interaccionSeleccionada = detalle)}
      {isLoading}
      {errorMensaje}
    />

    <!-- 3. COMPOSITOR INFERIOR: ESTRUCTURA PRESERVADA SIN PÍLDORAS REDUNDANTES -->
    <div class="shrink-0 border-t border-[#E5E7EB] bg-white px-5 py-3.5 z-10">
      {#if entrega_obligatoria}
        <div class="mb-2.5 flex items-start gap-2 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-2.5 py-1.5 text-[11.5px] text-[#B45309]">
          Un mensaje no entrega la actividad. Debes realizar la entrega en la página de la actividad usando el botón <strong class="font-semibold">Entregar</strong>.
        </div>
      {/if}

      <div class="flex items-end gap-2.5">
        <textarea
          bind:value={nuevoMensaje}
          placeholder={esDocente ? 'Escribe una retroalimentación al grupo…' : 'Escribe tu mensaje al equipo docente…'}
          rows="2"
          maxlength="2000"
          onkeydown={handleKeydown}
          class="flex-1 resize-none rounded-lg border border-[#D6D9E0] px-3.5 py-2.5 text-[13px] text-[#1A1A24] outline-none transition-colors focus:border-[#002F6C]"
        ></textarea>
        <button
          onclick={manejarEnvio}
          disabled={!nuevoMensaje.trim()}
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

  <!-- 4. SLIDEOVER DE RÚBRICA EVALUADA -->
  {#if interaccionSeleccionada?.rubrica}
    <div
      class="absolute inset-0 z-40 bg-slate-900/40 backdrop-blur-2xs transition-opacity"
      onclick={() => (interaccionSeleccionada = null)}
      role="presentation"
    ></div>

    <div
      class="absolute inset-y-0 right-0 z-50 flex w-[95%] max-w-full flex-col border-l border-slate-200 bg-white shadow-2xl animate-in slide-in-from-right duration-200"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4 shrink-0">
        <div>
          <h3 class="text-sm font-bold text-slate-900">Rúbrica de evaluación evaluada</h3>
          {#if interaccionSeleccionada.evaluador}
            <p class="text-xs text-slate-500">Evaluado por {interaccionSeleccionada.evaluador}</p>
          {/if}
        </div>
        <button
          class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-700 cursor-pointer"
          onclick={() => (interaccionSeleccionada = null)}
          aria-label="Cerrar rúbrica"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-5">
        <RubricaView
          rubrica={interaccionSeleccionada.rubrica}
          puntaje_obtenido={interaccionSeleccionada.puntaje_obtenido ?? 0}
          retroalimentacion={interaccionSeleccionada.retroalimentacion}
          resultado={interaccionSeleccionada.resultado}
          modoLectura={true}
          {esSumativa}
        />
      </div>
    </div>
  {/if}
</div>
