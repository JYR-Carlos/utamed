<script lang="ts">
  /**
   * Mensajería de un curso, para el personal (docente y ayudante).
   *
   * Se entra desde el curso, así que la bandeja no lista los demás: el curso ya
   * está elegido y aquí sólo se navegan sus componentes. Cada componente
   * (Cátedra, Laboratorio…) es una pestaña, y dentro hay dos vistas:
   *
   *  - Generales    : difusiones al componente (MENSAJE_PARA_TODO_EL_CURSO).
   *  - Individuales : un canal por alumno (MENSAJE_INDIVIDUAL). El canal es
   *                   (componente, alumno) y lo comparten todos los docentes del
   *                   componente, así que si responde un colegiado o un ayudante
   *                   el mensaje entra en la misma conversación.
   *
   * Modelo: curso.mensaje. Nada de esto pasa por agenda.agenda, que es el
   * feedback de entregas y vive dentro de cada actividad.
   *
   * Qué pestaña y qué canal están abiertos lo decide el servidor (`componente_activo`
   * y `panel.canal`), no el cliente: así una recarga o un enlace compartido
   * aterrizan en el mismo sitio.
   *
   * Endpoints:
   *   GET  {base}?componente_id=…&alumno_id=…
   *   POST {base}/componentes/{componente}/difusion
   *   POST {base}/componentes/{componente}/alumnos/{alumno}/mensaje
   */
  import { router } from '@inertiajs/svelte';
  import {
    ChevronLeft,
    Loader2,
    Megaphone,
    MessageSquare,
    Search,
    Send,
    User,
  } from 'lucide-svelte';

  // ── Types ──────────────────────────────────────────────────────────────────
  interface CursoInfo {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    agno_real: number;
    semestre_real: number;
    letra_grupo: string | null;
  }
  interface ComponenteItem {
    id_componente: number;
    tipo: string;
    no_leidos: number;
  }
  interface Difusion {
    id_mensaje: number;
    tema: string;
    mensaje: string;
    fecha_creacion: string;
    emisor: string;
    es_mio: boolean;
  }
  interface CanalItem {
    id_alumno: number;
    alumno: string;
    rut: string;
    ultimo_mensaje: string | null;
    ultima_fecha: string | null;
    es_mio: boolean;
    no_leidos: number;
  }
  interface MensajeItem {
    id_mensaje: number;
    tema: string;
    mensaje: string;
    fecha_creacion: string;
    emisor: string;
    receptor: string | null;
    es_alumno: boolean;
    es_mio: boolean;
  }
  interface Canal {
    id_alumno: number;
    alumno: string;
    mensajes: MensajeItem[];
  }
  interface Panel {
    id_componente: number;
    componente: string;
    difusiones: Difusion[];
    canales: CanalItem[];
    canal: Canal | null;
  }

  interface Props {
    curso: CursoInfo;
    componentes: ComponenteItem[];
    componente_activo: number | null;
    base_ruta: string;
    panel?: Panel | null;
  }

  let { curso, componentes = [], componente_activo = null, base_ruta, panel = null }: Props =
    $props();

  // ── State ──────────────────────────────────────────────────────────────────
  let query = $state('');
  let vista = $state<'generales' | 'individuales'>('individuales');
  let cargando = $state(false);

  // Redacción
  let temaAviso = $state('');
  let cuerpoAviso = $state('');
  let cuerpoMensaje = $state('');
  let sending = $state(false);
  let sendError = $state<string | null>(null);

  // ── Derived ────────────────────────────────────────────────────────────────
  const totalNoLeidos = $derived(componentes.reduce((acc, c) => acc + c.no_leidos, 0));

  const periodo = $derived(
    `Año ${curso.agno_real} · Semestre ${curso.semestre_real}` +
      (curso.letra_grupo ? ` · Grupo ${curso.letra_grupo}` : ''),
  );

  const canalesFiltrados = $derived(
    (panel?.canales ?? []).filter((k) =>
      !query.trim() ? true : k.alumno.toLowerCase().includes(query.toLowerCase()),
    ),
  );

  // ── Helpers ────────────────────────────────────────────────────────────────
  function fmt(iso: string | null) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-CL', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  // ── Actions ────────────────────────────────────────────────────────────────
  /**
   * Recarga el panel en el servidor. `componentes` viaja también porque abrir un
   * componente marca sus mensajes como leídos y los badges tienen que bajar.
   */
  function recargar(componenteId: number, alumnoId: number | null) {
    cargando = true;
    sendError = null;
    const data: Record<string, number> = { componente_id: componenteId };
    if (alumnoId) data.alumno_id = alumnoId;
    router.reload({
      only: ['panel', 'componentes', 'componente_activo'],
      data,
      onFinish: () => (cargando = false),
    });
  }

  function selectComponente(id: number) {
    if (componente_activo === id) return;
    vista = 'individuales';
    temaAviso = '';
    cuerpoAviso = '';
    cuerpoMensaje = '';
    recargar(id, null);
  }

  function selectAlumno(id: number) {
    if (!componente_activo) return;
    cuerpoMensaje = '';
    recargar(componente_activo, id);
  }

  function volverAAlumnos() {
    if (!componente_activo) return;
    recargar(componente_activo, null);
  }

  function enviarAviso() {
    if (!componente_activo || !temaAviso.trim() || !cuerpoAviso.trim()) return;
    sending = true;
    sendError = null;
    router.post(
      `${base_ruta}/componentes/${componente_activo}/difusion`,
      { tema: temaAviso.trim(), mensaje: cuerpoAviso.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          temaAviso = '';
          cuerpoAviso = '';
          if (componente_activo) recargar(componente_activo, panel?.canal?.id_alumno ?? null);
        },
        onError: (errors) => {
          sendError =
            (errors as Record<string, string>)?.mensaje ??
            (errors as Record<string, string>)?.tema ??
            'No se pudo enviar el aviso.';
        },
        onFinish: () => (sending = false),
      },
    );
  }

  function enviarMensaje() {
    const alumno = panel?.canal?.id_alumno;
    if (!componente_activo || !alumno || !cuerpoMensaje.trim()) return;
    sending = true;
    sendError = null;
    router.post(
      `${base_ruta}/componentes/${componente_activo}/alumnos/${alumno}/mensaje`,
      { mensaje: cuerpoMensaje.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          cuerpoMensaje = '';
          if (componente_activo) recargar(componente_activo, alumno);
        },
        onError: (errors) => {
          sendError = (errors as Record<string, string>)?.mensaje ?? 'No se pudo enviar el mensaje.';
        },
        onFinish: () => (sending = false),
      },
    );
  }
</script>

<div class="flex flex-col h-full">
  <!-- ── Cabecera del curso ──────────────────────────────────────────────── -->
  <div class="bg-white border-b border-slate-200 px-6 pt-4 shrink-0">
    <div class="flex items-center gap-3">
      <div
        class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0"
      >
        <MessageSquare size={20} />
      </div>
      <div class="min-w-0">
        <h1 class="text-lg font-extrabold text-slate-900 leading-tight truncate">
          {curso.nombre}
        </h1>
        <p class="text-xs text-slate-500">{curso.cod_curso} · {periodo}</p>
      </div>
      {#if totalNoLeidos > 0}
        <span
          class="ml-auto text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 shrink-0"
        >
          {totalNoLeidos} sin leer
        </span>
      {/if}
    </div>

    <!-- Pestañas por componente -->
    <div class="flex gap-1 mt-3 -mb-px overflow-x-auto" role="tablist">
      {#each componentes as c (c.id_componente)}
        <button
          role="tab"
          aria-selected={componente_activo === c.id_componente}
          onclick={() => selectComponente(c.id_componente)}
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap {componente_activo ===
          c.id_componente
            ? 'border-indigo-600 text-indigo-700'
            : 'border-transparent text-slate-500 hover:text-slate-700'}"
        >
          {c.tipo}
          {#if c.no_leidos > 0}
            <span
              class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold"
            >
              {c.no_leidos}
            </span>
          {/if}
        </button>
      {/each}
    </div>
  </div>

  <!-- ── Contenido del componente activo ─────────────────────────────────── -->
  <section class="flex-1 flex flex-col min-h-0 bg-slate-50">
    {#if !panel}
      <div class="flex-1 flex items-center justify-center text-sm text-slate-400 px-6 text-center">
        Este curso no tiene componentes a los que tengas acceso.
      </div>
    {:else}
      <!-- Generales / Individuales -->
      <div class="bg-white border-b border-slate-200 px-4 py-2.5 flex items-center gap-3 shrink-0">
        <div class="flex gap-1 bg-slate-100 p-0.5 rounded-lg">
          <button
            onclick={() => (vista = 'generales')}
            class="px-3 py-1 text-xs font-bold rounded-md transition-colors {vista === 'generales'
              ? 'bg-white text-indigo-700 shadow-sm'
              : 'text-slate-500 hover:text-slate-700'}"
          >
            Generales
            {#if panel.difusiones.length > 0}
              <span class="opacity-60">({panel.difusiones.length})</span>
            {/if}
          </button>
          <button
            onclick={() => (vista = 'individuales')}
            class="px-3 py-1 text-xs font-bold rounded-md transition-colors {vista ===
            'individuales'
              ? 'bg-white text-indigo-700 shadow-sm'
              : 'text-slate-500 hover:text-slate-700'}"
          >
            Individuales
          </button>
        </div>

        {#if vista === 'individuales' && !panel.canal}
          <div class="relative ml-auto max-w-[240px] w-full">
            <Search size={14} class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              bind:value={query}
              type="search"
              placeholder="Buscar alumno…"
              class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400 bg-slate-50"
            />
          </div>
        {/if}

        {#if cargando}
          <Loader2 size={15} class="animate-spin text-slate-400 shrink-0 ml-auto" />
        {/if}
      </div>

      {#if sendError}
        <div class="mx-4 mt-3 px-3 py-2 rounded-lg bg-rose-50 text-rose-700 text-xs">
          {sendError}
        </div>
      {/if}

      {#if vista === 'generales'}
        <!-- ── Avisos del componente ───────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          {#if panel.difusiones.length === 0}
            <div class="text-center text-sm text-slate-400 py-10">
              Todavía no hay avisos en {panel.componente}.
            </div>
          {:else}
            {#each panel.difusiones as d (d.id_mensaje)}
              <article class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start gap-2">
                  <Megaphone size={16} class="text-indigo-500 mt-0.5 shrink-0" />
                  <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-slate-900">{d.tema}</h3>
                    <p class="text-sm text-slate-700 mt-1 whitespace-pre-wrap">{d.mensaje}</p>
                    <p class="text-[11px] text-slate-400 mt-2">
                      {d.es_mio ? 'Tú' : d.emisor} · {fmt(d.fecha_creacion)}
                    </p>
                  </div>
                </div>
              </article>
            {/each}
          {/if}
        </div>

        <div class="bg-white border-t border-slate-200 p-3 space-y-2 shrink-0">
          <input
            bind:value={temaAviso}
            type="text"
            maxlength="150"
            placeholder="Asunto del aviso"
            class="w-full px-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400"
          />
          <div class="flex gap-2">
            <textarea
              bind:value={cuerpoAviso}
              rows="2"
              maxlength="2000"
              placeholder={`Escribe el aviso para todo ${panel.componente}…`}
              class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg resize-none focus:outline-none focus:border-indigo-400"
            ></textarea>
            <button
              onclick={enviarAviso}
              disabled={sending || !temaAviso.trim() || !cuerpoAviso.trim()}
              class="px-4 rounded-lg bg-indigo-600 text-white font-bold text-sm disabled:opacity-40 hover:bg-indigo-700 transition-colors flex items-center gap-1.5"
            >
              {#if sending}<Loader2 size={15} class="animate-spin" />{:else}<Send size={15} />{/if}
              Enviar
            </button>
          </div>
        </div>
      {:else if panel.canal}
        <!-- ── Conversación con un alumno ──────────────────────────────── -->
        <div class="bg-white border-b border-slate-200 px-4 py-2 flex items-center gap-2 shrink-0">
          <button
            onclick={volverAAlumnos}
            class="text-slate-400 hover:text-slate-600"
            aria-label="Volver a la lista de alumnos"
          >
            <ChevronLeft size={18} />
          </button>
          <User size={15} class="text-indigo-400" />
          <span class="text-sm font-bold text-slate-800 truncate">{panel.canal.alumno}</span>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2">
          {#if panel.canal.mensajes.length === 0}
            <div class="text-center text-sm text-slate-400 py-10">
              Sin mensajes todavía. Escribe el primero.
            </div>
          {:else}
            {#each panel.canal.mensajes as m (m.id_mensaje)}
              <div class="flex {m.es_alumno ? 'justify-start' : 'justify-end'}">
                <div
                  class="max-w-[75%] rounded-2xl px-3.5 py-2 {m.es_alumno
                    ? 'bg-white border border-slate-200 text-slate-800'
                    : 'bg-indigo-600 text-white'}"
                >
                  <p class="text-sm whitespace-pre-wrap">{m.mensaje}</p>
                  <p class="text-[10px] mt-1 {m.es_alumno ? 'text-slate-400' : 'text-indigo-200'}">
                    {m.es_mio ? 'Tú' : m.emisor} · {fmt(m.fecha_creacion)}
                  </p>
                </div>
              </div>
            {/each}
          {/if}
        </div>

        <div class="bg-white border-t border-slate-200 p-3 flex gap-2 shrink-0">
          <textarea
            bind:value={cuerpoMensaje}
            rows="2"
            maxlength="2000"
            placeholder="Escribe tu respuesta…"
            class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-lg resize-none focus:outline-none focus:border-indigo-400"
          ></textarea>
          <button
            onclick={enviarMensaje}
            disabled={sending || !cuerpoMensaje.trim()}
            class="px-4 rounded-lg bg-indigo-600 text-white font-bold text-sm disabled:opacity-40 hover:bg-indigo-700 transition-colors flex items-center gap-1.5"
          >
            {#if sending}<Loader2 size={15} class="animate-spin" />{:else}<Send size={15} />{/if}
            Enviar
          </button>
        </div>
      {:else}
        <!-- ── Lista de canales por alumno ─────────────────────────────── -->
        <div class="flex-1 overflow-y-auto p-2">
          {#if canalesFiltrados.length === 0}
            <div class="text-center text-sm text-slate-400 py-10">
              {query ? 'Ningún alumno coincide.' : `No hay alumnos inscritos en ${panel.componente}.`}
            </div>
          {:else}
            {#each canalesFiltrados as k (k.id_alumno)}
              <button
                onclick={() => selectAlumno(k.id_alumno)}
                class="w-full flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white transition-colors text-left"
              >
                <div
                  class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center shrink-0"
                >
                  <User size={15} />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-slate-800 truncate">{k.alumno}</span>
                    {#if k.no_leidos > 0}
                      <span
                        class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0"
                      >
                        {k.no_leidos}
                      </span>
                    {/if}
                    <span class="ml-auto text-[10px] text-slate-400 shrink-0">
                      {fmt(k.ultima_fecha)}
                    </span>
                  </div>
                  <p class="text-xs text-slate-500 truncate mt-0.5">
                    {#if k.ultimo_mensaje}
                      {k.es_mio ? 'Tú: ' : ''}{k.ultimo_mensaje}
                    {:else}
                      <span class="italic text-slate-400">Sin mensajes</span>
                    {/if}
                  </p>
                </div>
              </button>
            {/each}
          {/if}
        </div>
      {/if}
    {/if}
  </section>
</div>
