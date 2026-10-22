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
  let soloConMensajes = $state(true);
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

  // Con todo el curso en la lista, las pocas conversaciones quedan enterradas
  // entre alumnos «Sin mensajes». El filtro no rige al buscar (es como se le
  // escribe a alguien por primera vez), ni para el canal abierto, ni cuando
  // nadie ha escrito todavía.
  const conMensajes = $derived((panel?.canales ?? []).filter((k) => k.ultimo_mensaje).length);
  const filtraSinMensajes = $derived(soloConMensajes && !query.trim() && conMensajes > 0);

  const canalesFiltrados = $derived(
    (panel?.canales ?? []).filter((k) =>
      query.trim()
        ? k.alumno.toLowerCase().includes(query.toLowerCase())
        : !filtraSinMensajes || !!k.ultimo_mensaje || k.id_alumno === panel?.canal?.id_alumno,
    ),
  );

  const ocultosSinMensajes = $derived(
    filtraSinMensajes ? (panel?.canales.length ?? 0) - canalesFiltrados.length : 0,
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

<!--
  La altura sale del hueco que deja el armazón (cabecera global de 64px más el
  relleno de PageContentWrapper), en `svh` para que la barra del navegador móvil
  no recorte el redactor. Así la lista scrollea por dentro y el cuadro de escribir
  queda siempre a la vista, en teléfono y en escritorio.
-->
<div
  class="flex flex-col h-[calc(100svh-7rem)] md:h-[calc(100svh-8rem)] min-h-[26rem] overflow-hidden"
>
  <!-- ── Cabecera del curso ──────────────────────────────────────────────── -->
  <div class="bg-white border-b border-slate-200 px-4 sm:px-6 pt-3 sm:pt-4 shrink-0">
    <div class="flex items-center gap-2.5 sm:gap-3">
      <div
        class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-brand text-white flex items-center justify-center shrink-0"
      >
        <MessageSquare size={20} />
      </div>
      <div class="min-w-0 flex-1">
        <h1 class="text-base sm:text-lg font-extrabold text-slate-900 leading-tight truncate">
          {curso.nombre}
        </h1>
        <p class="text-xs text-slate-600 truncate">{curso.cod_curso} · {periodo}</p>
      </div>
      {#if totalNoLeidos > 0}
        <span
          class="text-xs font-bold px-2 sm:px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 shrink-0"
        >
          {totalNoLeidos}<span class="hidden sm:inline">{' '}sin leer</span>
        </span>
      {/if}
    </div>

    <!-- Pestañas por componente. Se desbordan en horizontal y el tirador llega
         hasta el borde de la pantalla, que en móvil es donde cae el pulgar. -->
    <div
      class="flex gap-1 mt-3 -mb-px overflow-x-auto -mx-4 px-4 sm:-mx-6 sm:px-6"
      role="tablist"
    >
      {#each componentes as c (c.id_componente)}
        <button
          role="tab"
          aria-selected={componente_activo === c.id_componente}
          onclick={() => selectComponente(c.id_componente)}
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap {componente_activo ===
          c.id_componente
            ? 'border-brand text-brand'
            : 'border-transparent text-slate-500 hover:text-slate-700'}"
        >
          {c.tipo}
          {#if c.no_leidos > 0}
            <span
              class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-amber-100 text-amber-800 text-xs font-bold"
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
      <div class="flex-1 flex items-center justify-center text-sm text-muted-foreground px-6 text-center">
        Este curso no tiene componentes a los que tengas acceso.
      </div>
    {:else}
      <!-- Generales / Individuales. En móvil el buscador baja a su propia línea
           en vez de estrujar al selector. -->
      <div
        class="bg-white border-b border-slate-200 px-3 sm:px-4 py-2.5 flex flex-wrap items-center gap-2 sm:gap-3 shrink-0"
      >
        <div class="flex gap-1 bg-slate-100 p-0.5 rounded-lg">
          <button
            onclick={() => (vista = 'generales')}
            class="px-3 py-1 text-xs font-bold rounded-md transition-colors {vista === 'generales'
              ? 'bg-white text-brand shadow-sm'
              : 'text-slate-600 hover:text-slate-800'}"
          >
            Generales
            {#if panel.difusiones.length > 0}
              <span>({panel.difusiones.length})</span>
            {/if}
          </button>
          <button
            onclick={() => (vista = 'individuales')}
            class="px-3 py-1 text-xs font-bold rounded-md transition-colors {vista ===
            'individuales'
              ? 'bg-white text-brand shadow-sm'
              : 'text-slate-600 hover:text-slate-800'}"
          >
            Individuales
          </button>
        </div>

        <!-- Sólo cuando hay de los dos tipos: si todos o ninguno tienen mensajes,
             el interruptor no cambiaría nada. -->
        {#if vista === 'individuales' && conMensajes > 0 && conMensajes < panel.canales.length}
          <button
            type="button"
            aria-pressed={soloConMensajes}
            onclick={() => (soloConMensajes = !soloConMensajes)}
            class="px-3 py-1 text-xs font-bold rounded-lg border transition-colors {soloConMensajes
              ? 'border-brand bg-brand-light text-brand'
              : 'border-slate-200 bg-white text-slate-600 hover:text-slate-800'} {panel.canal
              ? 'hidden md:inline-flex'
              : 'inline-flex'}"
          >
            Solo con mensajes ({conMensajes})
          </button>
        {/if}

        <!-- En escritorio la lista de alumnos no desaparece al abrir un hilo, así
             que el buscador sigue siendo útil aunque haya canal abierto. -->
        {#if vista === 'individuales'}
          <div
            class="relative w-full sm:w-auto sm:flex-1 sm:max-w-[240px] sm:ml-auto {panel.canal
              ? 'hidden md:block'
              : ''}"
          >
            <Search size={14} class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              bind:value={query}
              type="search"
              placeholder="Buscar alumno…"
              class="w-full pl-8 pr-8 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-brand bg-slate-50"
            />
            <!-- El spinner va dentro del input (absoluto) para que su
                 aparición no empuje ni encoja el buscador. -->
            {#if cargando}
              <Loader2
                size={14}
                class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 animate-spin text-muted-foreground"
              />
            {/if}
          </div>
        {:else}
          <!-- Sin buscador el spinner conserva un hueco fijo: aparece y
               desaparece sin mover el resto de la barra. -->
          <span class="ml-auto flex h-[15px] w-[15px] shrink-0 items-center justify-center" aria-hidden={!cargando}>
            {#if cargando}
              <Loader2 size={15} class="animate-spin text-slate-400" />
            {/if}
          </span>
        {/if}
      </div>

      {#if sendError}
        <div class="mx-4 mt-3 px-3 py-2 rounded-lg bg-rose-50 text-rose-700 text-xs">
          {sendError}
        </div>
      {/if}

      {#if vista === 'generales'}
        <!-- ── Avisos del componente ───────────────────────────────────── -->
        <!-- Los avisos ocupan todo el ancho, así que en pantallas grandes se
             limita la medida: una línea de 2000px no se lee. -->
        <div class="flex-1 overflow-y-auto p-3 sm:p-4">
          <div class="mx-auto w-full max-w-3xl space-y-3">
            {#if panel.difusiones.length === 0}
              <div class="text-center text-sm text-muted-foreground py-10">
                Todavía no hay avisos en {panel.componente}.
              </div>
            {:else}
              {#each panel.difusiones as d (d.id_mensaje)}
                <article class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4">
                  <div class="flex items-start gap-2">
                    <Megaphone size={16} class="text-brand mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                      <h3 class="text-sm font-bold text-slate-900 break-words">{d.tema}</h3>
                      <p class="text-sm text-slate-700 mt-1 whitespace-pre-wrap break-words">
                        {d.mensaje}
                      </p>
                      <p class="text-xs text-slate-600 mt-2">
                        {d.es_mio ? 'Tú' : d.emisor} · {fmt(d.fecha_creacion)}
                      </p>
                    </div>
                  </div>
                </article>
              {/each}
            {/if}
          </div>
        </div>

        <div class="bg-white border-t border-slate-200 p-3 shrink-0">
          <div class="mx-auto w-full max-w-3xl space-y-2">
            <input
              bind:value={temaAviso}
              type="text"
              maxlength="150"
              placeholder="Asunto del aviso"
              class="w-full px-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-brand"
            />
            <div class="flex gap-2">
              <textarea
                bind:value={cuerpoAviso}
                rows="2"
                maxlength="2000"
                placeholder={`Escribe el aviso para todo ${panel.componente}…`}
                class="flex-1 min-w-0 px-3 py-2 text-sm border border-slate-200 rounded-lg resize-none focus:outline-none focus:border-brand"
              ></textarea>
              <button
                onclick={enviarAviso}
                disabled={sending || !temaAviso.trim() || !cuerpoAviso.trim()}
                aria-label="Enviar aviso"
                class="px-3 sm:px-4 rounded-lg bg-brand text-white font-bold text-sm disabled:opacity-40 hover:bg-brand-hover transition-colors flex items-center gap-1.5 shrink-0"
              >
                {#if sending}<Loader2 size={15} class="animate-spin" />{:else}<Send size={15} />{/if}
                <span class="hidden sm:inline">Enviar</span>
              </button>
            </div>
          </div>
        </div>
      {:else}
        <!-- ── Alumnos y conversación ──────────────────────────────────── -->
        <!-- Desde `md` conviven los dos paneles: abrir un hilo no hace perder de
             vista al resto del curso. En un teléfono no caben lado a lado, así
             que se turnan y la conversación trae su flecha de volver. -->
        <div class="flex-1 flex min-h-0">
          <!-- Lista de canales por alumno -->
          <div
            class="{panel.canal
              ? 'hidden md:flex'
              : 'flex'} w-full md:w-72 lg:w-80 md:shrink-0 flex-col min-h-0 md:border-r md:border-slate-200"
          >
            <div class="flex-1 overflow-y-auto p-2">
              {#if canalesFiltrados.length === 0}
                <div class="text-center text-sm text-muted-foreground py-10 px-3">
                  {query
                    ? 'Ningún alumno coincide.'
                    : `No hay alumnos inscritos en ${panel.componente}.`}
                </div>
              {:else}
                {#each canalesFiltrados as k (k.id_alumno)}
                  <button
                    onclick={() => selectAlumno(k.id_alumno)}
                    aria-current={panel.canal?.id_alumno === k.id_alumno}
                    class="w-full flex items-start gap-3 px-3 py-2.5 rounded-xl transition-colors text-left {panel
                      .canal?.id_alumno === k.id_alumno
                      ? 'bg-white ring-1 ring-brand/30'
                      : 'hover:bg-white'}"
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
                            class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 shrink-0"
                          >
                            {k.no_leidos}
                          </span>
                        {/if}
                        <span class="ml-auto text-xs text-slate-600 shrink-0">
                          {fmt(k.ultima_fecha)}
                        </span>
                      </div>
                      <p class="text-xs text-slate-600 truncate mt-0.5">
                        {#if k.ultimo_mensaje}
                          {k.es_mio ? 'Tú: ' : ''}{k.ultimo_mensaje}
                        {:else}
                          <span class="italic">Sin mensajes</span>
                        {/if}
                      </p>
                    </div>
                  </button>
                {/each}
              {/if}
              {#if ocultosSinMensajes > 0}
                <button
                  type="button"
                  onclick={() => (soloConMensajes = false)}
                  class="w-full px-3 py-2.5 mt-1 rounded-xl text-left text-xs text-slate-600 hover:bg-white"
                >
                  {ocultosSinMensajes}
                  {ocultosSinMensajes === 1 ? 'alumno sin mensajes oculto' : 'alumnos sin mensajes ocultos'} ·
                  <span class="font-semibold text-uta-blue underline underline-offset-2">Mostrar</span>
                </button>
              {/if}
            </div>
          </div>

          <!-- Conversación con un alumno -->
          <div
            class="{panel.canal
              ? 'flex'
              : 'hidden md:flex'} flex-1 min-w-0 flex-col min-h-0 bg-slate-50"
          >
            {#if panel.canal}
              <div
                class="bg-white border-b border-slate-200 px-3 sm:px-4 py-2 flex items-center gap-2 shrink-0"
              >
                <!-- En escritorio la lista sigue ahí al lado: la flecha sólo hace
                     falta cuando el hilo ocupa toda la pantalla. -->
                <button
                  onclick={volverAAlumnos}
                  class="md:hidden text-muted-foreground hover:text-slate-600 -ml-1 p-1"
                  aria-label="Volver a la lista de alumnos"
                >
                  <ChevronLeft size={18} />
                </button>
                <User size={15} class="text-brand shrink-0" />
                <span class="text-sm font-bold text-slate-800 truncate">{panel.canal.alumno}</span>
              </div>

              <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2">
                {#if panel.canal.mensajes.length === 0}
                  <div class="text-center text-sm text-muted-foreground py-10 px-3">
                    Sin mensajes todavía. Escribe el primero.
                  </div>
                {:else}
                  {#each panel.canal.mensajes as m (m.id_mensaje)}
                    <div class="flex {m.es_alumno ? 'justify-start' : 'justify-end'}">
                      <div
                        class="max-w-[85%] sm:max-w-[75%] rounded-2xl px-3.5 py-2 {m.es_alumno
                          ? 'bg-white border border-slate-200 text-slate-800'
                          : 'bg-brand text-white'}"
                      >
                        <p class="text-sm whitespace-pre-wrap break-words">{m.mensaje}</p>
                        <p
                          class="text-xs mt-1 {m.es_alumno
                            ? 'text-slate-600'
                            : 'text-white/90'}"
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
                  class="flex-1 min-w-0 px-3 py-2 text-sm border border-slate-200 rounded-lg resize-none focus:outline-none focus:border-brand"
                ></textarea>
                <button
                  onclick={enviarMensaje}
                  disabled={sending || !cuerpoMensaje.trim()}
                  aria-label="Enviar mensaje"
                  class="px-3 sm:px-4 rounded-lg bg-brand text-white font-bold text-sm disabled:opacity-40 hover:bg-brand-hover transition-colors flex items-center gap-1.5 shrink-0"
                >
                  {#if sending}<Loader2 size={15} class="animate-spin" />{:else}<Send
                      size={15}
                    />{/if}
                  <span class="hidden sm:inline">Enviar</span>
                </button>
              </div>
            {:else}
              <!-- Sólo se ve en escritorio: en móvil este panel está oculto
                   mientras no haya hilo abierto. -->
              <div
                class="flex-1 flex flex-col items-center justify-center gap-2 text-muted-foreground px-6 text-center"
              >
                <MessageSquare size={28} class="opacity-40" />
                <p class="text-sm">Elige un alumno para ver la conversación.</p>
              </div>
            {/if}
          </div>
        </div>
      {/if}
    {/if}
  </section>
</div>
