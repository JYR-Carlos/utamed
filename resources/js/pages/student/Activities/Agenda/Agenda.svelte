<script lang="ts">
  /**
   * Agenda de la actividad: hilo cronológico con el equipo docente.
   *
   * Dos gramáticas conviven: las burbujas son conversación (alineadas a la
   * derecha, gris, sin color — lo que envía el alumno; a la izquierda, con
   * color por tipo — lo que recibe) y el registro de entrega es un acto del
   * sistema, de ancho completo. No hay recibo ni intentos: subir el archivo
   * es la entrega, y mientras la actividad siga activa se puede reemplazar
   * (ver ActivitySubmittedCard) — no se puede anular.
   *
   * "Consulta" / "Duda sobre rúbrica" / "Otro" comparten el mismo valor real
   * en BD (`Mensaje al profesor`, ver AgendaController::mapearTipoMensaje),
   * así que un mensaje ya guardado no puede recuperar cuál de los tres fue:
   * se muestra con una etiqueta genérica en vez de inventar la distinción.
   */
  import type { Rubrica } from '@/types/rubrica';
  import RubricaView from './Rubrica.svelte';
  import { formatBytes, formatFechaHora, formatFechaTextoLargo, parseFechaSoloDia } from '@/utils/formatters';
  import {
    X,
    Send,
    MessageCircle,
    HelpCircle,
    ClipboardList,
    MessageSquareQuote,
    Award,
    FileX2,
    Lock,
    PackageCheck,
    Download,
    ChevronRight,
  } from 'lucide-svelte';

  interface Interaccion {
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
    resultado?: Record<string, string> | null;
    archivo?: { nombre_original: string | null; peso_bytes: number | null } | null;
  }

  interface Props {
    onCerrar: () => void;
    onInteraccionEnviada: (data: { tipo: string; mensaje: string }) => void;
    id_curso: number;
    cod_curso: string;
    nombre_curso: string;
    cod_actividad: string;
    nombre_actividad: string;
    entrega_obligatoria: boolean;
    inline?: boolean;
    id_actividad_asignada_grupo?: number | null;
    listado_interacciones: Interaccion[];
    equipoDocente?: Array<{ nombre: string; es_titular: boolean }>;
  }

  let {
    onCerrar,
    onInteraccionEnviada,
    id_curso,
    cod_curso,
    nombre_curso,
    cod_actividad,
    nombre_actividad,
    entrega_obligatoria,
    listado_interacciones,
    equipoDocente = [],
    inline = false,
  }: Props = $props();

  type Filtro = 'todo' | 'docente' | 'mios' | 'entregas';
  let filtro = $state<Filtro>('todo');

  let nuevoMensaje = $state('');
  let tipoSeleccionado = $state<'Consulta' | 'Duda sobre Rúbrica' | 'Otro'>('Consulta');
  let listaRef = $state<HTMLDivElement | null>(null);

  let interaccionSeleccionada = $state<{
    rubrica?: Rubrica | null;
    puntaje_obtenido?: number | null;
    retroalimentacion?: string;
    resultado?: Record<string, string> | null;
  } | null>(null);

  const ENTREGA = 'Entrega de archivo';

  const interaccionesFiltradas = $derived(
    listado_interacciones.filter((item) => {
      if (filtro === 'docente') return item.es_de_docente;
      if (filtro === 'mios') return !item.es_de_docente;
      if (filtro === 'entregas') return item.tipo_interaccion === ENTREGA;
      return true;
    }),
  );

  interface Grupo {
    etiqueta: string;
    items: Interaccion[];
  }

  const gruposPorFecha = $derived.by((): Grupo[] => {
    const grupos: Grupo[] = [];
    for (const item of interaccionesFiltradas) {
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
    interaccionesFiltradas;
    if (listaRef) {
      listaRef.scrollTop = listaRef.scrollHeight;
    }
  });

  const tiposComposer: Array<{ tipo: typeof tipoSeleccionado; label: string; Icon: any }> = [
    { tipo: 'Consulta', label: 'Consulta', Icon: HelpCircle },
    { tipo: 'Duda sobre Rúbrica', label: 'Duda sobre rúbrica', Icon: ClipboardList },
    { tipo: 'Otro', label: 'Otro', Icon: MessageCircle },
  ];

  function tonoRecibido(tipo: string) {
    if (tipo === 'Feedback') {
      return { border: 'border-[#C9D6E6]', accent: 'border-l-[#002F6C]', iconBg: 'bg-[#E8EDF5]', iconColor: 'text-[#002F6C]', label: 'Feedback', labelColor: 'text-[#002F6C]', Icon: MessageSquareQuote };
    }
    if (tipo === 'Evaluación') {
      return { border: 'border-[#A7F3D0]', accent: 'border-l-[#059669]', iconBg: 'bg-[#ECFDF5]', iconColor: 'text-[#047857]', label: 'Evaluación', labelColor: 'text-[#047857]', Icon: Award };
    }
    if (tipo === 'Cancelación de entrega') {
      return { border: 'border-[#FECACA]', accent: 'border-l-[#DC2626]', iconBg: 'bg-[#FEF2F2]', iconColor: 'text-[#B91C1C]', label: 'Cancelación de entrega', labelColor: 'text-[#B91C1C]', Icon: FileX2 };
    }
    if (tipo === 'Cierre de actividad') {
      return { border: 'border-[#CBD5E1]', accent: 'border-l-[#64748B]', iconBg: 'bg-[#F1F5F9]', iconColor: 'text-[#475569]', label: 'Cierre de actividad', labelColor: 'text-[#475569]', Icon: Lock };
    }
    return { border: 'border-[#C9D6E6]', accent: 'border-l-[#002F6C]', iconBg: 'bg-[#E8EDF5]', iconColor: 'text-[#002F6C]', label: tipo, labelColor: 'text-[#002F6C]', Icon: MessageSquareQuote };
  }

  function manejarEnvio() {
    if (nuevoMensaje.trim() === '') return;

    onInteraccionEnviada({
      tipo: tipoSeleccionado,
      mensaje: nuevoMensaje,
    });

    nuevoMensaje = '';
    tipoSeleccionado = 'Consulta';
  }

  function urlDescargaEntrega(idInteraccion: number): string {
    return `/estudiante/cursos/${id_curso}/actividades/${cod_actividad}/entregas/${idInteraccion}/descargar`;
  }
</script>

<div
  class="w-full overflow-hidden {inline
    ? 'rounded-b-xl bg-white min-h-[520px] flex'
    : 'flex sm:w-[92%] h-[90vh] max-w-[1200px] rounded-2xl bg-white shadow-2xl'}"
>
  <div class="flex min-w-0 flex-1 flex-col">
    <div class="flex shrink-0 items-center gap-3 border-b border-[#E5E7EB] px-5 py-4">
      <div class="flex min-w-0 flex-col">
        <span class="text-[15px] font-semibold text-[#1A1A24]">Agenda de la actividad</span>
        <span class="truncate text-xs text-[#5A5E6E]">{cod_curso} · {nombre_actividad}</span>
      </div>
      <div class="ml-auto flex items-center gap-1.5">
        {#each [{ v: 'todo', l: 'Todo' }, { v: 'docente', l: 'Del docente' }, { v: 'mios', l: 'Míos' }, { v: 'entregas', l: 'Entregas' }] as opt (opt.v)}
          <button
            class="rounded-full px-2.5 py-1 text-[11.5px] font-semibold transition-colors {filtro === opt.v
              ? 'bg-[#F8F7F4] border border-[#D6D9E0] text-[#1A1A24]'
              : 'border border-transparent text-[#5A5E6E] hover:bg-[#F8FAFC]'}"
            onclick={() => (filtro = opt.v as Filtro)}
          >
            {opt.l}
          </button>
        {/each}
      </div>
      {#if !inline}
        <button class="rounded-full p-1.5 text-[#5A5E6E] transition-colors hover:bg-[#F8FAFC]" onclick={onCerrar} aria-label="cerrar">
          <X class="h-5 w-5" />
        </button>
      {/if}
    </div>

    <div bind:this={listaRef} class="flex-1 overflow-y-auto px-5 py-4">
      {#each gruposPorFecha as grupo (grupo.etiqueta)}
        <div class="flex justify-center pb-3.5">
          <span class="rounded-full bg-[#F5F1EA] px-2.5 py-0.5 font-mono text-[10.5px] uppercase tracking-wide text-[#5A5E6E]">
            {grupo.etiqueta}
          </span>
        </div>

        {#each grupo.items as item (item.id_interaccion)}
          {#if item.tipo_interaccion === ENTREGA}
            <div class="pb-4">
              <div class="overflow-hidden rounded-[10px] border border-[#C9D6E6] bg-[#F8FAFC]">
                <div class="flex items-center gap-2 border-b border-[#C9D6E6] bg-[#E8EDF5] px-3.5 py-2">
                  <PackageCheck class="h-[15px] w-[15px] text-[#002F6C]" />
                  <span class="text-[11px] font-bold uppercase tracking-wide text-[#002F6C]">Registro de entrega</span>
                  <span class="ml-auto font-mono text-[11px] text-[#002F6C]">{formatFechaHora(item.fecha_emision)}</span>
                </div>
                <div class="flex flex-wrap items-center gap-3 p-3.5">
                  <div class="flex min-w-0 flex-1 items-center gap-2.5">
                    <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[5px] border border-[#C9D6E6] bg-white font-mono text-[9.5px] font-bold text-[#002F6C]">
                      {(item.archivo?.nombre_original?.split('.').pop() ?? 'ARC').slice(0, 3).toUpperCase()}
                    </span>
                    <div class="flex min-w-0 flex-col">
                      <span class="truncate text-[13px] font-semibold text-[#1A1A24]">{item.archivo?.nombre_original ?? 'Archivo entregado'}</span>
                      {#if item.archivo?.peso_bytes}
                        <span class="font-mono text-[11px] text-[#5A5E6E]">{formatBytes(item.archivo.peso_bytes)}</span>
                      {/if}
                    </div>
                  </div>
                  {#if item.mensaje}
                    <p class="w-full text-[12.5px] text-[#4A4E5C]">{item.mensaje}</p>
                  {/if}
                  <a
                    href={urlDescargaEntrega(item.id_interaccion)}
                    class="flex shrink-0 items-center gap-1.5 rounded-lg border border-[#C9D6E6] bg-white px-3 py-1.5 text-[12.5px] font-semibold text-[#002F6C] no-underline transition-colors hover:bg-[#F8FAFC]"
                  >
                    <Download class="h-3.5 w-3.5" />
                    Descargar
                  </a>
                </div>
              </div>
            </div>
          {:else if item.es_de_docente}
            {@const t = tonoRecibido(item.tipo_interaccion)}
            <div class="flex justify-start pb-4">
              <button
                class="max-w-[78%] rounded-[10px] border {t.border} bg-white p-3.5 text-left border-l-[3px] {t.accent} shadow-sm transition-colors {item.adjunta_rubrica
                  ? 'cursor-pointer hover:bg-[#FAFBFC]'
                  : 'cursor-default'}"
                onclick={() => {
                  if (item.adjunta_rubrica && item.rubrica) {
                    interaccionSeleccionada = {
                      rubrica: item.rubrica,
                      puntaje_obtenido: item.puntaje_obtenido,
                      retroalimentacion: item.mensaje,
                      resultado: item.resultado,
                    };
                  }
                }}
              >
                <div class="flex flex-wrap items-center gap-2">
                  <t.Icon class="h-3.5 w-3.5 {t.iconColor}" />
                  <span class="text-[11px] font-bold uppercase tracking-wide {t.labelColor}">{t.label}</span>
                  <span class="h-[11px] w-px bg-[#D6D9E0]"></span>
                  <span class="text-[12.5px] font-semibold text-[#1A1A24]">{item.emisor}</span>
                  <span class="ml-auto font-mono text-[11px] text-[#5A5E6E]">{formatFechaHora(item.fecha_emision)}</span>
                </div>
                {#if item.tipo_interaccion === 'Evaluación' && item.puntaje_obtenido != null}
                  <div class="mt-2 flex items-center gap-3 border-t border-[#E5E7EB] pt-2.5">
                    <span class="text-2xl font-semibold leading-none tracking-tight text-[#1A1A24]">{item.puntaje_obtenido}</span>
                    {#if item.adjunta_rubrica}
                      <span class="ml-auto flex items-center gap-1 text-[12px] font-semibold text-[#002F6C]">
                        Ver rúbrica evaluada
                        <ChevronRight class="h-3.5 w-3.5" />
                      </span>
                    {/if}
                  </div>
                {/if}
                <p class="mt-2 text-[13.5px] text-[#1A1A24]">{item.mensaje}</p>
              </button>
            </div>
          {:else}
            <div class="flex justify-end pb-4">
              <div class="max-w-[78%] rounded-[10px] border border-[#DFDCD3] bg-[#EFEDE7] p-3.5">
                <div class="flex items-center gap-2">
                  <MessageCircle class="h-3.5 w-3.5 text-[#5A5E6E]" />
                  <span class="text-[11px] font-bold uppercase tracking-wide text-[#5A5E6E]">Mensaje</span>
                  <span class="h-[11px] w-px bg-[#CFCBC2]"></span>
                  <span class="text-[12.5px] font-semibold text-[#1A1A24]">Tú</span>
                  <span class="ml-auto font-mono text-[11px] text-[#5A5E6E]">{formatFechaHora(item.fecha_emision)}</span>
                </div>
                <p class="mt-2 text-[13.5px] text-[#1A1A24]">{item.mensaje}</p>
              </div>
            </div>
          {/if}
        {/each}
      {:else}
        <p class="py-10 text-center text-sm italic text-[#5A5E6E]">No hay interacciones registradas.</p>
      {/each}
    </div>

    <div class="shrink-0 border-t border-[#E5E7EB] px-5 py-3.5">
      {#if entrega_obligatoria}
        <div class="mb-2.5 flex items-start gap-2 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-2.5 py-1.5 text-[11.5px] text-[#B45309]">
          Un mensaje no entrega la actividad. Usa el botón <strong class="font-semibold">Entregar</strong>, arriba.
        </div>
      {/if}
      <div class="mb-2.5 flex gap-1.5 flex-wrap">
        {#each tiposComposer as opt (opt.tipo)}
          <button
            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[12.5px] font-medium transition-colors {tipoSeleccionado === opt.tipo
              ? 'border-[#002F6C] bg-[#E8EDF5] text-[#002F6C] font-semibold'
              : 'border-[#D6D9E0] bg-white text-[#1A1A24] hover:bg-[#F8FAFC]'}"
            onclick={() => (tipoSeleccionado = opt.tipo)}
          >
            <opt.Icon class="h-3.5 w-3.5" />
            {opt.label}
          </button>
        {/each}
      </div>
      <div class="flex items-end gap-2.5">
        <textarea
          bind:value={nuevoMensaje}
          placeholder="Escribe tu mensaje al equipo docente…"
          rows="2"
          maxlength="2000"
          class="flex-1 resize-none rounded-lg border border-[#D6D9E0] px-3 py-2.5 text-[13.5px] text-[#1A1A24] outline-none focus:border-[#002F6C]"
        ></textarea>
        <button
          onclick={manejarEnvio}
          disabled={!nuevoMensaje.trim()}
          class="flex shrink-0 items-center gap-1.5 rounded-lg border border-[#002F6C] bg-white px-4 py-2.5 text-[13.5px] font-semibold text-[#002F6C] transition-colors hover:bg-[#F8FAFC] disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Send class="h-[15px] w-[15px]" />
          Enviar
        </button>
      </div>
      <span class="mt-1 block text-right font-mono text-[11px] text-[#5A5E6E]">{nuevoMensaje.length} / 2.000</span>
    </div>
  </div>

  {#if interaccionSeleccionada?.rubrica}
    <div
      class="{inline ? 'relative w-[380px]' : 'fixed inset-0 z-50 sm:relative sm:inset-auto sm:w-[420px] w-full'} shrink-0 overflow-y-auto border-l border-[#E5E7EB] bg-[#FAFBFC] p-5"
    >
      <div class="mb-4 flex items-center justify-between">
        <span class="text-sm font-semibold text-[#1A1A24]">Detalle de evaluación</span>
        <button class="text-xs font-semibold text-[#5A5E6E] hover:text-[#002F6C]" onclick={() => (interaccionSeleccionada = null)}>
          Cerrar
        </button>
      </div>
      <RubricaView
        rubrica={interaccionSeleccionada.rubrica}
        puntaje_obtenido={interaccionSeleccionada.puntaje_obtenido ?? 0}
        retroalimentacion={interaccionSeleccionada.retroalimentacion}
        resultado={interaccionSeleccionada.resultado}
        modoLectura={true}
      />
    </div>
  {:else if !inline}
    <div class="hidden w-[280px] shrink-0 flex-col gap-4 overflow-y-auto border-l border-[#E5E7EB] bg-[#FAFBFC] p-4 lg:flex">
      <section class="flex flex-col gap-2 rounded-xl border border-[#E5E7EB] bg-white p-3.5 shadow-sm">
        <span class="text-[13px] font-semibold text-[#1A1A24]">Tipos del hilo</span>
        <div class="flex flex-col gap-1.5">
          <span class="text-[10.5px] font-bold uppercase tracking-wide text-[#5A5E6E]">Tú envías · sin color</span>
          {#each tiposComposer as opt (opt.tipo)}
            <div class="flex items-center gap-2 text-[12px]">
              <span class="flex h-[20px] w-[20px] items-center justify-center rounded-[5px] border border-[#DFDCD3] bg-[#EFEDE7]">
                <opt.Icon class="h-3 w-3 text-[#5A5E6E]" />
              </span>
              {opt.label}
            </div>
          {/each}
        </div>
        <div class="mt-1 flex flex-col gap-1.5 border-t border-[#E5E7EB] pt-2.5">
          <span class="text-[10.5px] font-bold uppercase tracking-wide text-[#5A5E6E]">Tú recibes · con color</span>
          {#each [{ label: 'Feedback', tono: tonoRecibido('Feedback') }, { label: 'Evaluación', tono: tonoRecibido('Evaluación') }] as row (row.label)}
            <div class="flex items-center gap-2 text-[12px]">
              <span class="flex h-[20px] w-[20px] items-center justify-center rounded-[5px] {row.tono.iconBg}">
                <row.tono.Icon class="h-3 w-3 {row.tono.iconColor}" />
              </span>
              {row.label}
            </div>
          {/each}
        </div>
      </section>

      {#if equipoDocente.length > 0}
        <section class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-white p-3.5 shadow-sm">
          <span class="text-[13px] font-semibold text-[#1A1A24]">Equipo docente</span>
          {#each equipoDocente as persona, i (persona.nombre)}
            <div class="flex items-center gap-2.5 {i > 0 ? 'border-t border-[#E5E7EB] pt-2.5' : ''}">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {persona.es_titular ? 'bg-[#E8EDF5] text-[#002F6C]' : 'bg-[#F5F1EA] text-[#5A5E6E]'} text-[12px] font-semibold">
                {persona.nombre.split(' ').slice(0, 2).map((w) => w[0] ?? '').join('').toUpperCase()}
              </div>
              <div class="flex flex-col">
                <span class="text-[13px] font-semibold text-[#1A1A24]">{persona.nombre}</span>
                <span class="text-[11.5px] text-[#5A5E6E]">{persona.es_titular ? 'Docente titular' : 'Ayudante'}</span>
              </div>
            </div>
          {/each}
          <span class="border-t border-[#E5E7EB] pt-2.5 text-[11.5px] text-[#5A5E6E]">
            Escribes al equipo, no a una persona: cualquiera puede responder.
          </span>
        </section>
      {/if}
    </div>
  {/if}
</div>
