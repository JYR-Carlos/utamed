<script lang="ts">
  /**
   * Bandeja de mensajería del personal del curso (docente y ayudante).
   *
   * Modelo: curso.mensaje. El hilo lo sostiene el COMPONENTE, no la actividad —
   * nada de esto pasa por agenda.agenda.
   *
   *  - Avisos  : difusiones al componente (MENSAJE_PARA_TODO_EL_CURSO).
   *  - Alumnos : un canal por alumno (MENSAJE_INDIVIDUAL). El canal es
   *              (componente, alumno) y lo comparten todos los docentes del
   *              componente, así que si responde un colegiado o un ayudante el
   *              mensaje entra en la misma conversación.
   *
   * Endpoints:
   *   GET  {base}?componente_id=…&alumno_id=…                  (lazy 'panel')
   *   POST {base}/componentes/{componente}/difusion
   *   POST {base}/componentes/{componente}/alumnos/{alumno}/mensaje
   */
  import { router } from '@inertiajs/svelte';
  import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Folder,
    Inbox,
    Loader2,
    Megaphone,
    MessageSquare,
    Search,
    Send,
    User,
    Users,
  } from 'lucide-svelte';

  // ── Types ──────────────────────────────────────────────────────────────────
  interface ComponenteItem {
    id_componente: number;
    tipo: string;
    no_leidos: number;
  }
  interface CursoItem {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    agno_real: number;
    semestre_real: number;
    letra_grupo: string | null;
    no_leidos: number;
    componentes: ComponenteItem[];
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
    curso: string;
    difusiones: Difusion[];
    canales: CanalItem[];
    canal: Canal | null;
  }

  interface Props {
    cursos: CursoItem[];
    base_ruta: string;
    panel?: Panel | null;
  }

  let { cursos = [], base_ruta, panel = null }: Props = $props();

  // ── State ──────────────────────────────────────────────────────────────────
  let query = $state('');
  let expandedCursos = $state<Record<number, boolean>>({});
  let selectedComponente = $state<number | null>(null);
  let selectedAlumno = $state<number | null>(null);
  let tab = $state<'avisos' | 'alumnos'>('alumnos');
  let loadingPanel = $state(false);

  // Redacción
  let temaAviso = $state('');
  let cuerpoAviso = $state('');
  let cuerpoMensaje = $state('');
  let sending = $state(false);
  let sendError = $state<string | null>(null);

  // ── Derived ────────────────────────────────────────────────────────────────
  const cursosFiltrados = $derived(
    cursos
      .map((c) => {
        if (!query.trim()) return c;
        const q = query.toLowerCase();
        if (c.nombre.toLowerCase().includes(q) || c.cod_curso.toLowerCase().includes(q)) return c;
        return { ...c, componentes: c.componentes.filter((k) => k.tipo.toLowerCase().includes(q)) };
      })
      .filter((c) => c.componentes.length > 0),
  );

  const totalNoLeidos = $derived(cursos.reduce((acc, c) => acc + c.no_leidos, 0));

  const canalesFiltrados = $derived(
    (panel?.canales ?? []).filter((k) =>
      !query.trim() ? true : k.alumno.toLowerCase().includes(query.toLowerCase()),
    ),
  );

  // ── Helpers ────────────────────────────────────────────────────────────────
  function periodoLabel(c: CursoItem) {
    const letra = c.letra_grupo ? ` · Grupo ${c.letra_grupo}` : '';
    return `Año ${c.agno_real} · Semestre ${c.semestre_real}${letra}`;
  }

  function isCursoOpen(c: CursoItem) {
    return expandedCursos[c.id_curso] ?? (!!query.trim() || c.no_leidos > 0);
  }

  function toggleCurso(c: CursoItem) {
    expandedCursos[c.id_curso] = !isCursoOpen(c);
  }

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
  function recargarPanel(componenteId: number, alumnoId: number | null) {
    loadingPanel = true;
    sendError = null;
    const data: Record<string, number> = { componente_id: componenteId };
    if (alumnoId) data.alumno_id = alumnoId;
    router.reload({
      only: ['panel'],
      data,
      onFinish: () => (loadingPanel = false),
    });
  }

  function selectComponente(id: number) {
    if (selectedComponente === id) return;
    selectedComponente = id;
    selectedAlumno = null;
    tab = 'alumnos';
    temaAviso = '';
    cuerpoAviso = '';
    cuerpoMensaje = '';
    recargarPanel(id, null);
  }

  function selectAlumno(id: number) {
    if (!selectedComponente) return;
    selectedAlumno = id;
    cuerpoMensaje = '';
    recargarPanel(selectedComponente, id);
  }

  function volverAAlumnos() {
    if (!selectedComponente) return;
    selectedAlumno = null;
    recargarPanel(selectedComponente, null);
  }

  function enviarAviso() {
    if (!selectedComponente || !temaAviso.trim() || !cuerpoAviso.trim()) return;
    sending = true;
    sendError = null;
    router.post(
      `${base_ruta}/componentes/${selectedComponente}/difusion`,
      { tema: temaAviso.trim(), mensaje: cuerpoAviso.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          temaAviso = '';
          cuerpoAviso = '';
          if (selectedComponente) recargarPanel(selectedComponente, selectedAlumno);
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
    if (!selectedComponente || !selectedAlumno || !cuerpoMensaje.trim()) return;
    sending = true;
    sendError = null;
    router.post(
      `${base_ruta}/componentes/${selectedComponente}/alumnos/${selectedAlumno}/mensaje`,
      { mensaje: cuerpoMensaje.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          cuerpoMensaje = '';
          if (selectedComponente) recargarPanel(selectedComponente, selectedAlumno);
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
  <!-- ── Header ──────────────────────────────────────────────────────────── -->
  <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center gap-3 shrink-0">
    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0">
      <MessageSquare size={20} />
    </div>
    <div>
      <h1 class="text-lg font-extrabold text-slate-900 leading-tight">Mensajería</h1>
      <p class="text-xs text-slate-500">
        Avisos y conversaciones por componente. Independiente de las actividades.
      </p>
    </div>
    {#if totalNoLeidos > 0}
      <span class="ml-auto text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
        {totalNoLeidos} sin leer
      </span>
    {/if}
  </div>

  <div class="flex flex-1 min-h-0 overflow-hidden">
    <!-- ── Sidebar: curso → componente ───────────────────────────────────── -->
    <aside
      class="{selectedComponente
        ? 'hidden md:flex'
        : 'flex'} w-full md:w-80 border-r border-slate-200 bg-white flex-col shrink-0"
    >
      <div class="px-3 py-3 border-b border-slate-100">
        <div class="relative">
          <Search size={14} class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            bind:value={query}
            type="search"
            placeholder="Buscar curso, componente o alumno…"
            class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400 bg-slate-50"
          />
        </div>
      </div>

      <div class="flex-1 overflow-y-auto p-2">
        {#if cursosFiltrados.length === 0}
          <div class="px-4 py-10 text-center text-sm text-slate-400">
            {query ? 'Sin resultados.' : 'No tienes componentes asignados.'}
          </div>
        {:else}
          {#each cursosFiltrados as c (c.id_curso)}
            {@const open = isCursoOpen(c)}
            <div class="mb-1">
              <button
                onclick={() => toggleCurso(c)}
                class="w-full flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-slate-50 transition-colors text-left"
              >
                <span class="text-slate-400 shrink-0">
                  {#if open}<ChevronDown size={15} />{:else}<ChevronRight size={15} />{/if}
                </span>
                <Folder size={16} class="text-indigo-400 shrink-0" />
                <span class="flex-1 min-w-0">
                  <span class="block text-sm font-bold text-slate-700 truncate">{c.nombre}</span>
                  <span class="block text-[10px] text-slate-400">{periodoLabel(c)}</span>
                </span>
                {#if c.no_leidos > 0}
                  <span
                    class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0"
                    title="Mensajes sin leer"
                  >
                    {c.no_leidos}
                  </span>
                {/if}
              </button>

              {#if open}
                <div class="ml-6 mt-0.5 space-y-0.5">
                  {#each c.componentes as k (k.id_componente)}
                    <button
                      onclick={() => selectComponente(k.id_componente)}
                      class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-left transition-colors {selectedComponente ===
                      k.id_componente
                        ? 'bg-indigo-50 text-indigo-700'
                        : 'hover:bg-slate-50 text-slate-600'}"
                    >
                      <Users size={14} class="shrink-0 opacity-60" />
                      <span class="flex-1 text-sm truncate">{k.tipo}</span>
                      {#if k.no_leidos > 0}
                        <span
                          class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0"
                        >
                          {k.no_leidos}
                        </span>
                      {/if}
                    </button>
                  {/each}
                </div>
              {/if}
            </div>
          {/each}
        {/if}
      </div>
    </aside>

    <!-- ── Panel derecho ─────────────────────────────────────────────────── -->
    <section class="flex-1 flex flex-col min-w-0 bg-slate-50">
      {#if !selectedComponente}
        <div class="flex-1 flex flex-col items-center justify-center text-slate-400 gap-2 px-6">
          <Inbox size={40} class="opacity-40" />
          <p class="text-sm">Elige un componente para ver sus avisos y conversaciones.</p>
        </div>
      {:else if loadingPanel && !panel}
        <div class="flex-1 flex items-center justify-center text-slate-400 gap-2">
          <Loader2 size={18} class="animate-spin" /> <span class="text-sm">Cargando…</span>
        </div>
      {:else if panel}
        <!-- Cabecera del componente -->
        <div class="bg-white border-b border-slate-200 px-4 py-3 flex items-center gap-3 shrink-0">
          <button
            onclick={() => {
              selectedComponente = null;
              selectedAlumno = null;
            }}
            class="md:hidden text-slate-400 hover:text-slate-600"
            aria-label="Volver"
          >
            <ChevronLeft size={18} />
          </button>
          <div class="min-w-0">
            <h2 class="text-sm font-extrabold text-slate-900 truncate">{panel.componente}</h2>
            <p class="text-xs text-slate-500 truncate">{panel.curso}</p>
          </div>

          <div class="ml-auto flex gap-1 bg-slate-100 p-0.5 rounded-lg shrink-0">
            <button
              onclick={() => (tab = 'alumnos')}
              class="px-3 py-1 text-xs font-bold rounded-md transition-colors {tab === 'alumnos'
                ? 'bg-white text-indigo-700 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'}"
            >
              Alumnos
            </button>
            <button
              onclick={() => (tab = 'avisos')}
              class="px-3 py-1 text-xs font-bold rounded-md transition-colors {tab === 'avisos'
                ? 'bg-white text-indigo-700 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'}"
            >
              Avisos
            </button>
          </div>
        </div>

        {#if sendError}
          <div class="mx-4 mt-3 px-3 py-2 rounded-lg bg-rose-50 text-rose-700 text-xs">
            {sendError}
          </div>
        {/if}

        {#if tab === 'avisos'}
          <!-- ── Avisos del componente ─────────────────────────────────── -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">
            {#if panel.difusiones.length === 0}
              <div class="text-center text-sm text-slate-400 py-10">
                Todavía no hay avisos en este componente.
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
                placeholder="Escribe el aviso para todo el componente…"
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
          <!-- ── Conversación con un alumno ────────────────────────────── -->
          <div class="bg-white border-b border-slate-200 px-4 py-2 flex items-center gap-2 shrink-0">
            <button
              onclick={volverAAlumnos}
              class="text-slate-400 hover:text-slate-600"
              aria-label="Volver a la lista"
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
                    <p
                      class="text-[10px] mt-1 {m.es_alumno ? 'text-slate-400' : 'text-indigo-200'}"
                    >
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
          <!-- ── Lista de canales por alumno ───────────────────────────── -->
          <div class="flex-1 overflow-y-auto p-2">
            {#if canalesFiltrados.length === 0}
              <div class="text-center text-sm text-slate-400 py-10">
                {query ? 'Ningún alumno coincide.' : 'No hay alumnos inscritos en este componente.'}
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
</div>
