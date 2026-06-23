<script lang="ts">
  /**
   * MensajesCurso — Vista general de mensajería (nivel 1) del curso.
   *
   * Arquitectura: usa agenda.agenda (tipos "Mensaje al profesor" / "Feedback"),
   * sin ninguna tabla adicional. La misma infraestructura que usan las entregas.
   *
   * Diseño: dos columnas.
   * - Izquierda : lista de estudiantes con badge de actividad (total mensajes enviados)
   *               y fecha del último intercambio.
   * - Derecha   : hilos agrupados por actividad/grupo del estudiante seleccionado,
   *               con formulario de feedback por hilo.
   *
   * Endpoints usados:
   *   GET  /docente/cursos/{curso}/estudiantes/{idEstudiante}/mensajes → hilo
   *   POST /docente/cursos/{curso}/grupos/{grupo}/feedback             → responder
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import { ArrowLeft, MessageSquare, Send, Loader2, Search, Inbox } from 'lucide-svelte';
  import type { MensajeEstudiante } from '@/types/actividad';

  // ── Types ─────────────────────────────────────────────────────────────────

  interface Curso {
    id_curso: number;
    cod_curso: string;
    nombre: string;
    asignatura?: { nombre: string };
  }

  interface EstudianteItem {
    id_estudiante: number;
    id_usuario: number;
    nombre: string;
    email: string;
    total_mensajes: number;
    ultima_fecha: string | null;
  }

  interface Props {
    curso: Curso;
    estudiantes: EstudianteItem[];
    mensajesEstudiante?: MensajeEstudiante[];
  }

  // ── Props ─────────────────────────────────────────────────────────────────

  let { curso, estudiantes, mensajesEstudiante = [] }: Props = $props();

  // ── State ─────────────────────────────────────────────────────────────────

  let query = $state('');
  let selectedEstudiante = $state<EstudianteItem | null>(null);

  let loadingHilo = $state(false);
  let hiloError = $state<string | null>(null);

  // Reply per grupo: Map<grupoId, text>
  let replyByGrupo = $state<Map<number, string>>(new Map());
  let sendingByGrupo = $state<Set<number>>(new Set());
  let sendErrorByGrupo = $state<Map<number, string>>(new Map());

  // ── Derived ───────────────────────────────────────────────────────────────

  const estudiantesFiltrados = $derived(
    estudiantes.filter((e) => {
      if (!query.trim()) return true;
      const q = query.toLowerCase();
      return e.nombre.toLowerCase().includes(q) || e.email.toLowerCase().includes(q);
    }),
  );

  const totalConMensajes = $derived(estudiantes.filter((e) => e.total_mensajes > 0).length);

  /** Agrupa los mensajes por grupo → { actividad_nombre, items } */
  function hilosPorGrupo(msgs: MensajeEstudiante[]) {
    const map = new Map<
      number,
      { actividad_nombre: string; id_actividad: number; items: MensajeEstudiante[] }
    >();
    for (const m of msgs) {
      if (!map.has(m.grupo)) {
        map.set(m.grupo, {
          actividad_nombre: m.actividad_nombre,
          id_actividad: m.id_actividad,
          items: [],
        });
      }
      map.get(m.grupo)!.items.push(m);
    }
    return map;
  }

  const hilos = $derived(hilosPorGrupo(mensajesEstudiante));

  // ── Helpers ───────────────────────────────────────────────────────────────

  function fmtDate(iso: string | null) {
    if (!iso) return '';
    const d = new Date(iso);
    const now = new Date();
    const sameDay =
      d.getDate() === now.getDate() &&
      d.getMonth() === now.getMonth() &&
      d.getFullYear() === now.getFullYear();
    return sameDay
      ? d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })
      : d.toLocaleDateString('es-CL', { day: '2-digit', month: 'short' });
  }

  function fmtFull(iso: string) {
    return new Date(iso).toLocaleString('es-CL', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function initials(name: string) {
    return name
      .split(' ')
      .slice(0, 2)
      .map((w) => w[0] ?? '')
      .join('')
      .toUpperCase();
  }

  function csrfToken() {
    return (
      (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
    );
  }

  // ── Actions ───────────────────────────────────────────────────────────────

  async function selectEstudiante(e: EstudianteItem) {
    if (selectedEstudiante?.id_estudiante === e.id_estudiante) return;
    selectedEstudiante = e;
    hiloError = null;
    replyByGrupo = new Map();
    sendingByGrupo = new Set();
    sendErrorByGrupo = new Map();
    await loadHilo(e.id_estudiante);
  }

  import { router } from '@inertiajs/svelte';

  function loadHilo(idEstudiante: number) {
    loadingHilo = true;
    hiloError = null;

    router.reload({
      only: ['mensajesEstudiante'],
      data: { estudiante_id: idEstudiante },
      onSuccess: () => {
        loadingHilo = false;
      },
      onError: () => {
        hiloError = 'Error al cargar mensajes.';
        loadingHilo = false;
      },
    });
  }

  function enviarFeedback(grupoId: number) {
    const texto = replyByGrupo.get(grupoId)?.trim();
    if (!texto) return;

    sendingByGrupo = new Set([...sendingByGrupo, grupoId]);
    const errMap = new Map(sendErrorByGrupo);
    errMap.delete(grupoId);
    sendErrorByGrupo = errMap;

    router.post(
      `/docente/cursos/${curso.id_curso}/grupos/${grupoId}/feedback`,
      { mensaje: texto },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          // Limpia texto y recarga hilo
          const r = new Map(replyByGrupo);
          r.set(grupoId, '');
          replyByGrupo = r;
          sendingByGrupo = new Set([...sendingByGrupo].filter((id) => id !== grupoId));
          if (selectedEstudiante) loadHilo(selectedEstudiante.id_estudiante);
        },
        onError: () => {
          const e2 = new Map(sendErrorByGrupo);
          e2.set(grupoId, 'Error al enviar.');
          sendErrorByGrupo = e2;
          sendingByGrupo = new Set([...sendingByGrupo].filter((id) => id !== grupoId));
        },
      }
    );
  }
</script>

<DocenteLayout>
  <div class="flex flex-col h-full">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex-shrink-0">
      <nav class="flex items-center gap-1.5 text-xs text-slate-500 mb-2">
        <Link href="/docente/cursos" class="hover:text-indigo-600">Mis Cursos</Link>
        <span class="text-slate-300">/</span>
        <Link href="/docente/cursos/{curso.id_curso}" class="hover:text-indigo-600">
          {curso.cod_curso}
        </Link>
        <span class="text-slate-300">/</span>
        <span class="text-slate-700 font-medium">Mensajes</span>
      </nav>
      <div class="flex items-center gap-3">
        <Link
          href="/docente/cursos/{curso.id_curso}"
          class="text-slate-400 hover:text-slate-600 transition-colors"
        >
          <ArrowLeft size={20} />
        </Link>
        <div>
          <h1 class="text-lg font-extrabold text-slate-900 leading-tight">Mensajes del Curso</h1>
          <p class="text-xs text-slate-500">{curso.asignatura?.nombre ?? curso.nombre}</p>
        </div>
        {#if totalConMensajes > 0}
          <span
            class="ml-auto text-xs font-bold px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700"
          >
            {totalConMensajes} con mensajes
          </span>
        {/if}
      </div>
    </div>

    <!-- ── Two-column layout ─────────────────────────────────────────────── -->
    <div class="flex flex-1 min-h-0 overflow-hidden">
      <!-- ── Sidebar: lista de estudiantes ──────────────────────────────── -->
      <!-- En móvil funciona como maestro/detalle: la lista ocupa todo el ancho
           y se oculta al elegir un estudiante. -->
      <aside
        class="{selectedEstudiante
          ? 'hidden md:flex'
          : 'flex'} w-full md:w-72 border-r border-slate-200 bg-white flex-col flex-shrink-0"
      >
        <!-- Buscador -->
        <div class="px-3 py-3 border-b border-slate-100">
          <div class="relative">
            <Search size={14} class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              bind:value={query}
              type="search"
              placeholder="Buscar estudiante…"
              class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400 bg-slate-50"
            />
          </div>
        </div>

        <!-- Lista -->
        <ul class="flex-1 overflow-y-auto divide-y divide-slate-100">
          {#each estudiantesFiltrados as e (e.id_estudiante)}
            <li>
              <button
                onclick={() => selectEstudiante(e)}
                class="w-full text-left px-4 py-3 flex items-center gap-3 hover:bg-slate-50 transition-colors
                  {selectedEstudiante?.id_estudiante === e.id_estudiante
                  ? 'bg-indigo-50 border-r-2 border-indigo-500'
                  : ''}"
              >
                <div
                  class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shrink-0
                    {selectedEstudiante?.id_estudiante === e.id_estudiante
                    ? 'bg-indigo-600 text-white'
                    : 'bg-slate-200 text-slate-600'}"
                >
                  {initials(e.nombre)}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span class="text-sm font-semibold text-slate-800 truncate">{e.nombre}</span>
                    {#if e.ultima_fecha}
                      <span class="text-[10px] text-slate-400 shrink-0"
                        >{fmtDate(e.ultima_fecha)}</span
                      >
                    {/if}
                  </div>
                  {#if e.total_mensajes > 0}
                    <span
                      class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600"
                    >
                      <MessageSquare size={9} />
                      {e.total_mensajes} mensaje{e.total_mensajes !== 1 ? 's' : ''}
                    </span>
                  {:else}
                    <span class="text-[11px] text-slate-400 truncate">{e.email}</span>
                  {/if}
                </div>
              </button>
            </li>
          {/each}

          {#if estudiantesFiltrados.length === 0}
            <li class="px-4 py-8 text-center text-sm text-slate-400">
              {query ? 'Sin resultados.' : 'No hay estudiantes inscritos.'}
            </li>
          {/if}
        </ul>
      </aside>

      <!-- ── Main: hilos de conversación ────────────────────────────────── -->
      <div
        class="{selectedEstudiante
          ? 'block'
          : 'hidden md:block'} flex-1 overflow-y-auto bg-slate-50"
      >
        {#if selectedEstudiante}
          <!-- Volver a la lista (solo móvil) -->
          <button
            onclick={() => (selectedEstudiante = null)}
            class="md:hidden flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-indigo-600 bg-white border-b border-slate-200 sticky top-0 z-20 w-full"
          >
            <ArrowLeft size={16} /> Estudiantes
          </button>
        {/if}
        {#if !selectedEstudiante}
          <div class="flex flex-col items-center justify-center gap-4 h-full text-center px-8">
            <div
              class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center"
            >
              <Inbox size={28} class="text-indigo-400" />
            </div>
            <div>
              <p class="font-semibold text-slate-700">Selecciona un estudiante</p>
              <p class="text-sm text-slate-400 mt-1">
                Verás aquí los mensajes que ha enviado en sus actividades del curso.
              </p>
            </div>
          </div>
        {:else if loadingHilo}
          <div class="flex items-center justify-center gap-2 h-full text-slate-400 text-sm">
            <Loader2 size={18} class="animate-spin" />
            Cargando mensajes…
          </div>
        {:else if hiloError}
          <div class="flex items-center justify-center h-full text-red-500 text-sm">
            {hiloError}
          </div>
        {:else if hilos.size === 0}
          <div class="flex flex-col items-center justify-center gap-4 h-full text-center px-8">
            <MessageSquare size={32} class="text-slate-300" />
            <div>
              <p class="font-semibold text-slate-600">{selectedEstudiante.nombre}</p>
              <p class="text-sm text-slate-400 mt-1">
                Aún no ha enviado mensajes en ninguna actividad.
              </p>
            </div>
          </div>
        {:else}
          <!-- Cabecera del panel -->
          <div
            class="bg-white border-b border-slate-200 px-5 py-3 flex items-center gap-3 sticky top-0 z-10"
          >
            <div
              class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold shrink-0"
            >
              {initials(selectedEstudiante.nombre)}
            </div>
            <div>
              <p class="text-sm font-bold text-slate-900">{selectedEstudiante.nombre}</p>
              <p class="text-xs text-slate-400">
                {hilos.size} actividad{hilos.size !== 1 ? 'es' : ''} con mensajes
              </p>
            </div>
          </div>

          <!-- Hilos por actividad -->
          <div class="p-5 flex flex-col gap-5">
            {#each [...hilos.entries()] as [grupoId, hilo] (grupoId)}
              <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <!-- Cabecera del hilo -->
                <div
                  class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center justify-between gap-2"
                >
                  <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-700 truncate">{hilo.actividad_nombre}</p>
                    <p class="text-[10px] text-slate-400">Grupo #{grupoId}</p>
                  </div>
                  <Link
                    href="/docente/cursos/{curso.id_curso}/actividades/{hilo.id_actividad}/evaluacion"
                    class="text-[10px] text-indigo-500 hover:underline font-medium shrink-0 no-underline"
                  >
                    Ver evaluación →
                  </Link>
                </div>

                <!-- Mensajes del hilo -->
                <div class="flex flex-col">
                  {#each hilo.items as msg (msg.id_agenda)}
                    {@const esFeedback = msg.tipo_registro === 'Feedback'}
                    <div
                      class="px-4 py-3 border-b border-slate-100 last:border-b-0 {esFeedback
                        ? 'bg-indigo-50'
                        : 'bg-white'}"
                    >
                      <div class="flex items-center gap-2 mb-1">
                        <span
                          class="text-[10px] font-bold px-2 py-0.5 rounded-full
                          {esFeedback
                            ? 'bg-indigo-100 text-indigo-700'
                            : 'bg-slate-100 text-slate-600'}"
                        >
                          {esFeedback ? 'Docente' : 'Estudiante'}
                        </span>
                        <span class="text-[10px] text-slate-400">{fmtFull(msg.fecha_envio)}</span>
                      </div>
                      <p class="text-sm text-slate-800 leading-relaxed">{msg.mensaje}</p>
                    </div>
                  {/each}
                </div>

                <!-- Formulario de respuesta -->
                <div class="px-4 py-3 border-t border-slate-100 bg-white">
                  {#if sendErrorByGrupo.has(grupoId)}
                    <p class="text-xs text-red-500 mb-1">{sendErrorByGrupo.get(grupoId)}</p>
                  {/if}
                  <div class="flex gap-2 items-end">
                    <textarea
                      value={replyByGrupo.get(grupoId) ?? ''}
                      oninput={(ev) => {
                        const m = new Map(replyByGrupo);
                        m.set(grupoId, (ev.target as HTMLTextAreaElement).value);
                        replyByGrupo = m;
                      }}
                      rows={2}
                      placeholder="Escribe feedback para este grupo…"
                      class="flex-1 resize-none px-3 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:outline-none focus:border-indigo-400"
                    ></textarea>
                    <button
                      onclick={() => enviarFeedback(grupoId)}
                      disabled={!replyByGrupo.get(grupoId)?.trim() || sendingByGrupo.has(grupoId)}
                      class="p-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors shrink-0"
                      title="Enviar feedback"
                    >
                      {#if sendingByGrupo.has(grupoId)}
                        <Loader2 size={16} class="animate-spin" />
                      {:else}
                        <Send size={16} />
                      {/if}
                    </button>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </div>
  </div>
</DocenteLayout>
