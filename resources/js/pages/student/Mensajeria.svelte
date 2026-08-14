<script lang="ts">
  /**
   * Mensajería del alumno para un curso — hilos sostenidos por el componente.
   *
   * Se entra desde la ficha del curso, así que el curso ya está elegido y aquí
   * sólo se navegan sus componentes. Cada componente (Cátedra, Laboratorio…) es
   * una pestaña, y dentro hay dos vistas:
   *
   *  - Generales    : avisos del equipo docente al componente (sólo lectura).
   *  - Individuales : su conversación con ese equipo. Elige a qué docente dirige
   *                   el mensaje, pero lo ven todos, así que puede responderle
   *                   cualquiera de ellos sin abrir otra conversación.
   *
   * No pasa por agenda.agenda: las entregas y su feedback siguen en la actividad.
   *
   * Endpoints:
   *   GET  /estudiante/cursos/{curso}/mensajeria?componente_id=…
   *   POST /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje
   */
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import { Loader2, Megaphone, MessageSquare, Send } from 'lucide-svelte';

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
  interface DocenteItem {
    id_usuario: number;
    nombre: string;
    es_titular: boolean;
  }
  interface Panel {
    id_componente: number;
    componente: string;
    difusiones: Difusion[];
    mensajes: MensajeItem[];
    docentes: DocenteItem[];
  }
  interface Props {
    curso: CursoInfo;
    componentes: ComponenteItem[];
    componente_activo: number | null;
    panel?: Panel | null;
  }

  let { curso, componentes = [], componente_activo = null, panel = null }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mis Cursos', href: '/estudiante/cursos' },
    { title: curso?.nombre ?? 'Curso', href: `/estudiante/cursos/${curso?.id_curso}` },
    { title: 'Mensajería', href: '' },
  ]);

  // ── State ──────────────────────────────────────────────────────────────────
  let vista = $state<'generales' | 'individuales'>('individuales');
  let cargando = $state(false);

  let cuerpo = $state('');
  let receptor = $state<number | null>(null);
  let tema = $state('');
  let sending = $state(false);
  let sendError = $state<string | null>(null);

  // ── Derived ────────────────────────────────────────────────────────────────
  const totalNoLeidos = $derived(componentes.reduce((acc, c) => acc + c.no_leidos, 0));

  const periodo = $derived(
    `Año ${curso.agno_real} · Semestre ${curso.semestre_real}` +
      (curso.letra_grupo ? ` · Grupo ${curso.letra_grupo}` : ''),
  );

  // Sólo pide asunto cuando la conversación aún no tiene ninguno.
  const necesitaTema = $derived((panel?.mensajes.length ?? 0) === 0);

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
  /** `componentes` viaja también porque abrir la pestaña baja su badge. */
  function recargar(id: number) {
    cargando = true;
    router.reload({
      only: ['panel', 'componentes', 'componente_activo'],
      data: { componente_id: id },
      onFinish: () => (cargando = false),
    });
  }

  function selectComponente(id: number) {
    if (componente_activo === id) return;
    vista = 'individuales';
    cuerpo = '';
    tema = '';
    receptor = null;
    sendError = null;
    recargar(id);
  }

  function enviar() {
    if (!componente_activo || !cuerpo.trim()) return;
    if (necesitaTema && !tema.trim()) {
      sendError = 'Escribe un asunto para iniciar la conversación.';
      return;
    }
    sending = true;
    sendError = null;
    router.post(
      `/estudiante/cursos/${curso.id_curso}/mensajeria/componentes/${componente_activo}/mensaje`,
      {
        mensaje: cuerpo.trim(),
        tema: tema.trim() || null,
        id_usuario_receptor: receptor,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          cuerpo = '';
          tema = '';
          if (componente_activo) recargar(componente_activo);
        },
        onError: (errors) => {
          sendError = (errors as Record<string, string>)?.mensaje ?? 'No se pudo enviar el mensaje.';
        },
        onFinish: () => (sending = false),
      },
    );
  }
</script>

<StudentLayout {breadcrumbs}>
  <!--
    Altura tomada del hueco del armazón (cabecera de 64px + relleno de
    PageContentWrapper), en `svh` para que la barra del navegador móvil no tape
    el cuadro de escribir. La lista scrollea por dentro; el redactor no se mueve.
  -->
  <div
    class="flex flex-col h-[calc(100svh-7rem)] md:h-[calc(100svh-8rem)] min-h-[26rem] overflow-hidden"
  >
    <!-- ── Cabecera del curso ────────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-200 px-4 sm:px-6 pt-3 sm:pt-4 shrink-0">
      <div class="flex items-center gap-2.5 sm:gap-3">
        <div
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0"
        >
          <MessageSquare size={20} />
        </div>
        <div class="min-w-0 flex-1">
          <h1 class="text-base sm:text-lg font-extrabold text-slate-900 leading-tight truncate">
            {curso.nombre}
          </h1>
          <p class="text-[11px] sm:text-xs text-slate-500 truncate">
            {curso.cod_curso} · {periodo}
          </p>
        </div>
        {#if totalNoLeidos > 0}
          <span
            class="text-[10px] sm:text-xs font-bold px-2 sm:px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 shrink-0"
          >
            {totalNoLeidos}<span class="hidden sm:inline"> sin leer</span>
          </span>
        {/if}
      </div>

      <!-- Pestañas por componente. Se desbordan en horizontal hasta el borde de
           la pantalla, que en móvil es donde cae el pulgar. -->
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

    <!-- ── Contenido del componente activo ───────────────────────────────── -->
    <section class="flex-1 flex flex-col min-h-0 bg-slate-50">
      {#if !panel}
        <div class="flex-1 flex items-center justify-center text-sm text-slate-400 px-6 text-center">
          No estás inscrito en ningún componente de este curso.
        </div>
      {:else}
        <div
          class="bg-white border-b border-slate-200 px-3 sm:px-4 py-2.5 flex flex-wrap items-center gap-2 sm:gap-3 shrink-0"
        >
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
          <!-- Una sola columna: en pantallas anchas se limita la medida, porque
               un párrafo de 2000px de ancho no se lee. -->
          <div class="flex-1 overflow-y-auto p-3 sm:p-4">
            <div class="mx-auto w-full max-w-3xl space-y-3">
              {#if panel.difusiones.length === 0}
                <div class="text-center text-sm text-slate-400 py-10">
                  El equipo docente todavía no ha publicado avisos en {panel.componente}.
                </div>
              {:else}
                {#each panel.difusiones as d (d.id_mensaje)}
                  <article class="bg-white rounded-xl border border-slate-200 p-3 sm:p-4">
                    <div class="flex items-start gap-2">
                      <Megaphone size={16} class="text-indigo-500 mt-0.5 shrink-0" />
                      <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-slate-900 break-words">{d.tema}</h3>
                        <p class="text-sm text-slate-700 mt-1 whitespace-pre-wrap break-words">
                          {d.mensaje}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-2">
                          {d.emisor} · {fmt(d.fecha_creacion)}
                        </p>
                      </div>
                    </div>
                  </article>
                {/each}
              {/if}
            </div>
          </div>
        {:else}
          <div class="flex-1 overflow-y-auto p-3 sm:p-4">
            <div class="mx-auto w-full max-w-3xl space-y-2">
              {#if panel.mensajes.length === 0}
                <div class="text-center text-sm text-slate-400 py-10">
                  Aún no has escrito al equipo docente de {panel.componente}.
                </div>
              {:else}
                {#each panel.mensajes as m (m.id_mensaje)}
                  <div class="flex {m.es_mio ? 'justify-end' : 'justify-start'}">
                    <div
                      class="max-w-[85%] sm:max-w-[75%] rounded-2xl px-3.5 py-2 {m.es_mio
                        ? 'bg-indigo-600 text-white'
                        : 'bg-white border border-slate-200 text-slate-800'}"
                    >
                      <p class="text-sm whitespace-pre-wrap break-words">{m.mensaje}</p>
                      <p class="text-[10px] mt-1 {m.es_mio ? 'text-indigo-200' : 'text-slate-400'}">
                        {m.es_mio ? 'Tú' : m.emisor} · {fmt(m.fecha_creacion)}
                      </p>
                    </div>
                  </div>
                {/each}
              {/if}
            </div>
          </div>

          <div class="bg-white border-t border-slate-200 p-3 shrink-0">
            <div class="mx-auto w-full max-w-3xl space-y-2">
              <!-- Destinatario y asunto se apilan en móvil: uno al lado del otro
                   dejaba dos campos demasiado estrechos para escribir. -->
              <div class="flex flex-col sm:flex-row gap-2">
                <select
                  bind:value={receptor}
                  class="text-xs border border-slate-200 rounded-lg px-2 py-2 sm:py-1.5 bg-slate-50 focus:outline-none focus:border-indigo-400 w-full sm:w-auto sm:max-w-[55%]"
                >
                  <option value={null}>Dirigir al titular</option>
                  {#each panel.docentes as d (d.id_usuario)}
                    <option value={d.id_usuario}>
                      {d.nombre}{d.es_titular ? ' (titular)' : ''}
                    </option>
                  {/each}
                </select>
                {#if necesitaTema}
                  <input
                    bind:value={tema}
                    type="text"
                    maxlength="150"
                    placeholder="Asunto"
                    class="flex-1 min-w-0 px-3 py-2 sm:py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400"
                  />
                {/if}
              </div>
              <div class="flex gap-2">
                <textarea
                  bind:value={cuerpo}
                  rows="2"
                  maxlength="2000"
                  placeholder="Escribe tu consulta…"
                  class="flex-1 min-w-0 px-3 py-2 text-sm border border-slate-200 rounded-lg resize-none focus:outline-none focus:border-indigo-400"
                ></textarea>
                <button
                  onclick={enviar}
                  disabled={sending || !cuerpo.trim()}
                  aria-label="Enviar consulta"
                  class="px-3 sm:px-4 rounded-lg bg-indigo-600 text-white font-bold text-sm disabled:opacity-40 hover:bg-indigo-700 transition-colors flex items-center gap-1.5 shrink-0"
                >
                  {#if sending}<Loader2 size={15} class="animate-spin" />{:else}<Send
                      size={15}
                    />{/if}
                  <span class="hidden sm:inline">Enviar</span>
                </button>
              </div>
              <p class="text-[10px] text-slate-400">
                Lo verá todo el equipo docente de {panel.componente}; puede responderte cualquiera de
                ellos.
              </p>
            </div>
          </div>
        {/if}
      {/if}
    </section>
  </div>
</StudentLayout>
