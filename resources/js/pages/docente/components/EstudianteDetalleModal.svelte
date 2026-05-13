<script lang="ts">
  /**
   * Modal deslizante de detalle de estudiante — Docente Titular.
   *
   * Muestra información contextual de un estudiante dentro de un curso,
   * organizada en tres pestañas:
   *
   *  1. Evaluaciones — Lista de actividades publicadas del curso.
   *                    Enlaza a la página de calificación por actividad.
   *  2. Mensajes     — Canal de comunicación privada (próximamente).
   *  3. Asistencia   — Registro de asistencia (próximamente).
   *
   * Props:
   *  - abierto     : boolean        — Controla la visibilidad del panel.
   *  - estudiante  : Estudiante     — Inscripción con datos del alumno.
   *  - actividades : Actividad[]    — Actividades del curso (filtradas aquí).
   *  - idCurso     : number         — ID del curso para construir rutas.
   *  - onCerrar    : () => void     — Callback para cerrar el panel.
   */
  import { Link } from '@inertiajs/svelte';
  import {
    X,
    BookOpenCheck,
    MessageSquare,
    ClipboardList,
    ExternalLink,
    GraduationCap,
    Construction,
    Calendar,
    Users,
    User,
    Send,
    Loader2,
  } from 'lucide-svelte';
  import type { Actividad } from '@/types/actividad';

  // ── Types ─────────────────────────────────────────────────────────────────

  interface Estudiante {
    id_inscripcion_componente: number;
    id_componente: number;
    tipo_componente: string;
    nota_componente: number | null;
    estudiante: {
      id_estudiante: number;
      nombre: string;
      username: string;
      email?: string;
    };
  }

  interface Props {
    abierto: boolean;
    estudiante: Estudiante;
    actividades: Actividad[];
    idCurso: number;
    onCerrar: () => void;
  }

  // ── Props ─────────────────────────────────────────────────────────────────

  let { abierto, estudiante, actividades, idCurso, onCerrar }: Props = $props();

  // ── Tab state ─────────────────────────────────────────────────────────────

  type Tab = 'evaluaciones' | 'mensajes' | 'asistencia';
  let tab = $state<Tab>('evaluaciones');

  const TABS: { id: Tab; label: string; Icon: any; soon?: true }[] = [
    { id: 'evaluaciones', label: 'Evaluaciones', Icon: BookOpenCheck },
    { id: 'mensajes', label: 'Mensajes', Icon: MessageSquare },
    { id: 'asistencia', label: 'Asistencia', Icon: ClipboardList, soon: true },
  ];

  // ── Mensajes state ────────────────────────────────────────────────────────

  interface Mensaje {
    id_agenda: number;
    fecha_envio: string;
    mensaje: string;
    grupo: number;
    tipo_registro: 'Mensaje al profesor' | 'Feedback' | string;
    emisor_nombre: string;
    emisor_id_usuario: number;
    actividad_nombre: string;
    id_actividad: number;
  }

  let mensajes = $state<Mensaje[]>([]);
  let mensajesLoading = $state(false);
  let mensajesError = $state<string | null>(null);

  // Reply form state: keyed by grupo ID
  let replyingGrupo = $state<number | null>(null);
  let replyText = $state('');
  let isSendingReply = $state(false);

  // ── Derived data ──────────────────────────────────────────────────────────

  /** Only show published activities (visible === true). */
  const actividadesPublicadas = $derived(actividades.filter((a) => a.visible));

  // Reset tab when modal re-opens for a different student
  $effect(() => {
    if (abierto) {
      tab = 'evaluaciones';
      mensajes = [];
      mensajesError = null;
      replyingGrupo = null;
      replyText = '';
    }
  });

  // Load messages when switching to mensajes tab
  $effect(() => {
    if (tab === 'mensajes' && abierto && mensajes.length === 0 && !mensajesLoading) {
      loadMensajes();
    }
  });

  // Close on Escape
  $effect(() => {
    function onKeydown(e: KeyboardEvent) {
      if (e.key === 'Escape' && abierto) onCerrar();
    }
    window.addEventListener('keydown', onKeydown);
    return () => window.removeEventListener('keydown', onKeydown);
  });

  // ── Helpers ───────────────────────────────────────────────────────────────

  function initials(name: string): string {
    return name
      .split(' ')
      .slice(0, 2)
      .map((w) => w[0] ?? '')
      .join('')
      .toUpperCase();
  }

  function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('es-CL', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  function isOverdue(d: string): boolean {
    return new Date(d) < new Date();
  }

  function formatNota(nota: number | null): string {
    if (nota === null || nota === undefined) return '—';
    return nota.toFixed(1);
  }

  function notaColorClass(nota: number | null): string {
    if (nota === null) return 'text-slate-400';
    return nota >= 4.0 ? 'text-emerald-700' : 'text-red-600';
  }

  async function loadMensajes() {
    mensajesLoading = true;
    mensajesError = null;
    try {
      const res = await fetch(
        `/docente/cursos/${idCurso}/estudiantes/${estudiante.estudiante.id_estudiante}/mensajes`,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
      );
      if (!res.ok) throw new Error('Error al cargar mensajes.');
      mensajes = await res.json();
    } catch (e: any) {
      mensajesError = e.message ?? 'Error desconocido.';
    } finally {
      mensajesLoading = false;
    }
  }

  async function enviarFeedback(grupoId: number) {
    if (!replyText.trim()) return;
    isSendingReply = true;
    try {
      const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ??
        '';
      const res = await fetch(`/docente/cursos/${idCurso}/grupos/${grupoId}/feedback`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mensaje: replyText.trim() }),
      });
      if (!res.ok) throw new Error('Error al enviar feedback.');
      replyText = '';
      replyingGrupo = null;
      // Reload thread
      mensajes = [];
      await loadMensajes();
    } catch (e: any) {
      mensajesError = e.message ?? 'Error al enviar.';
    } finally {
      isSendingReply = false;
    }
  }

  // Agrupa mensajes por grupo para mostrar hilos por actividad
  function mensajesPorGrupo(
    msgs: Mensaje[],
  ): Map<number, { actividad_nombre: string; items: Mensaje[] }> {
    const map = new Map<number, { actividad_nombre: string; items: Mensaje[] }>();
    for (const m of msgs) {
      if (!map.has(m.grupo)) {
        map.set(m.grupo, { actividad_nombre: m.actividad_nombre, items: [] });
      }
      map.get(m.grupo)!.items.push(m);
    }
    return map;
  }
</script>

{#if abierto}
  <!-- Backdrop -->
  <!-- svelte-ignore a11y_click_events_have_key_events -->
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-40 bg-black/30 backdrop-blur-[1px]"
    onclick={onCerrar}
    aria-hidden="true"
  ></div>

  <!-- Slide-over panel -->
  <aside
    class="fixed inset-y-0 right-0 z-50 flex flex-col w-full max-w-md bg-white shadow-2xl border-l border-slate-200 overflow-hidden"
    role="dialog"
    aria-modal="true"
    aria-label="Detalle del estudiante {estudiante.estudiante.nombre}"
  >
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div
      class="flex items-start justify-between gap-3 px-5 py-4 border-b border-slate-200 shrink-0"
    >
      <div class="flex items-center gap-3 min-w-0">
        <div
          class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold shrink-0 select-none"
          aria-hidden="true"
        >
          {initials(estudiante.estudiante.nombre)}
        </div>
        <div class="min-w-0">
          <p class="font-bold text-slate-900 leading-tight truncate">
            {estudiante.estudiante.nombre}
          </p>
          <p class="text-xs text-slate-400">{estudiante.estudiante.username}</p>
        </div>
      </div>
      <button
        onclick={onCerrar}
        class="mt-0.5 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
        aria-label="Cerrar panel"
      >
        <X size={18} />
      </button>
    </div>

    <!-- ── Context strip ──────────────────────────────────────────────────── -->
    <div
      class="flex items-center gap-3 px-5 py-2.5 bg-slate-50 border-b border-slate-100 text-sm shrink-0"
    >
      <span
        class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-slate-200 text-slate-700"
      >
        {estudiante.tipo_componente}
      </span>
      <span class="text-slate-400 text-xs">Nota final:</span>
      <span class="text-sm font-bold {notaColorClass(estudiante.nota_componente)}">
        {formatNota(estudiante.nota_componente)}
      </span>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────────── -->
    <nav class="flex border-b border-slate-200 bg-white shrink-0" aria-label="Secciones">
      {#each TABS as t (t.id)}
        {@const active = tab === t.id}
        <button
          onclick={() => !t.soon && (tab = t.id)}
          disabled={t.soon}
          aria-selected={active}
          role="tab"
          class="flex-1 flex items-center justify-center gap-1.5 py-3 text-xs font-semibold transition-colors border-b-2
            {active
            ? 'border-indigo-600 text-indigo-700'
            : t.soon
              ? 'border-transparent text-slate-300 cursor-not-allowed'
              : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'}"
        >
          <svelte:component this={t.Icon} size={13} />
          {t.label}
          {#if t.soon}
            <span
              class="ml-0.5 text-[9px] font-bold px-1 py-0.5 rounded bg-amber-100 text-amber-600 uppercase tracking-wide leading-none"
            >
              Pronto
            </span>
          {/if}
        </button>
      {/each}
    </nav>

    <!-- ── Tab panels ─────────────────────────────────────────────────────── -->
    <div class="flex-1 overflow-y-auto" role="tabpanel">
      <!-- Evaluaciones -->
      {#if tab === 'evaluaciones'}
        <div class="p-5 flex flex-col gap-3">
          <p class="text-xs text-slate-400 uppercase font-bold tracking-widest">
            Actividades publicadas ({actividadesPublicadas.length})
          </p>

          {#if actividadesPublicadas.length === 0}
            <div class="flex flex-col items-center gap-3 py-14 text-center text-slate-400">
              <GraduationCap size={36} class="opacity-40" />
              <p class="text-sm">No hay actividades publicadas en este curso.</p>
            </div>
          {:else}
            {#each actividadesPublicadas as act (act.id_actividad)}
              <div
                class="bg-white border border-slate-200 rounded-lg p-4 hover:border-indigo-200 hover:shadow-sm transition-all"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 text-sm leading-snug truncate">
                      {act.nombre}
                    </p>
                    <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1.5 text-xs text-slate-500">
                      <span class="flex items-center gap-1">
                        <Calendar size={11} />
                        {formatDate(act.fecha_limite)}
                        {#if isOverdue(act.fecha_limite)}
                          <span class="text-red-500 font-semibold">(Vencida)</span>
                        {/if}
                      </span>
                      <span class="flex items-center gap-1">
                        {#if act.es_grupal}
                          <Users size={11} />
                          Grupal
                        {:else}
                          <User size={11} />
                          Individual
                        {/if}
                      </span>
                    </div>
                  </div>

                  <Link
                    href="/docente/cursos/{idCurso}/actividades/{act.id_actividad}/evaluacion"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-md bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 transition-colors no-underline shrink-0"
                    title="Ir a calificaciones de esta actividad"
                  >
                    <ExternalLink size={11} />
                    Calificar
                  </Link>
                </div>
              </div>
            {/each}
          {/if}
        </div>

        <!-- Mensajes -->
      {:else if tab === 'mensajes'}
        <div class="p-5 flex flex-col gap-4">
          {#if mensajesLoading}
            <div class="flex items-center justify-center gap-2 py-12 text-slate-400 text-sm">
              <Loader2 size={18} class="animate-spin" />
              Cargando mensajes…
            </div>
          {:else if mensajesError}
            <div class="text-center py-10 text-red-500 text-sm">{mensajesError}</div>
          {:else if mensajes.length === 0}
            <div class="flex flex-col items-center gap-3 py-14 text-center text-slate-400">
              <MessageSquare size={32} class="opacity-30" />
              <p class="text-sm">Este estudiante no ha enviado mensajes aún.</p>
            </div>
          {:else}
            {@const hilos = mensajesPorGrupo(mensajes)}
            {#each [...hilos.entries()] as [grupoId, hilo]}
              <div class="border border-slate-200 rounded-xl overflow-hidden">
                <!-- Cabecera del hilo -->
                <div
                  class="bg-slate-50 px-4 py-2.5 flex items-center justify-between border-b border-slate-200"
                >
                  <span class="text-xs font-semibold text-slate-600 truncate"
                    >{hilo.actividad_nombre}</span
                  >
                  <span class="text-[10px] text-slate-400 ml-2 shrink-0">#{grupoId}</span>
                </div>

                <!-- Mensajes del hilo -->
                <div class="flex flex-col gap-0">
                  {#each hilo.items as msg (msg.id_agenda)}
                    {@const esDocente = msg.tipo_registro === 'Feedback'}
                    <div
                      class="px-4 py-3 border-b border-slate-100 last:border-b-0 {esDocente
                        ? 'bg-indigo-50'
                        : 'bg-white'}"
                    >
                      <div class="flex items-center gap-2 mb-1">
                        <span
                          class="text-[10px] font-bold px-2 py-0.5 rounded-full {esDocente
                            ? 'bg-indigo-100 text-indigo-700'
                            : 'bg-slate-100 text-slate-600'}"
                        >
                          {esDocente ? 'Docente' : 'Estudiante'}
                        </span>
                        <span class="text-[10px] text-slate-400">
                          {new Date(msg.fecha_envio).toLocaleString('es-CL', {
                            day: '2-digit',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </span>
                      </div>
                      <p class="text-sm text-slate-800 leading-relaxed">{msg.mensaje}</p>
                    </div>
                  {/each}
                </div>

                <!-- Formulario de respuesta -->
                {#if replyingGrupo === grupoId}
                  <div class="p-3 bg-indigo-50 border-t border-indigo-100">
                    <textarea
                      bind:value={replyText}
                      rows={3}
                      placeholder="Escribe tu feedback…"
                      class="w-full px-3 py-2 text-sm border border-indigo-200 rounded-lg bg-white focus:outline-none focus:border-indigo-400 resize-none"
                    ></textarea>
                    <div class="flex gap-2 mt-2 justify-end">
                      <button
                        onclick={() => {
                          replyingGrupo = null;
                          replyText = '';
                        }}
                        class="px-3 py-1.5 text-xs text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-100"
                      >
                        Cancelar
                      </button>
                      <button
                        onclick={() => enviarFeedback(grupoId)}
                        disabled={!replyText.trim() || isSendingReply}
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                      >
                        {#if isSendingReply}
                          <Loader2 size={12} class="animate-spin" />
                        {:else}
                          <Send size={12} />
                        {/if}
                        Enviar
                      </button>
                    </div>
                  </div>
                {:else}
                  <div class="px-4 py-2 bg-white">
                    <button
                      onclick={() => {
                        replyingGrupo = grupoId;
                        replyText = '';
                      }}
                      class="text-xs text-indigo-600 font-semibold hover:underline flex items-center gap-1"
                    >
                      <Send size={11} />
                      Responder con feedback
                    </button>
                  </div>
                {/if}
              </div>
            {/each}
          {/if}
        </div>

        <!-- Asistencia — próximamente -->
      {:else if tab === 'asistencia'}
        <div class="flex flex-col items-center justify-center gap-4 py-24 px-6 text-center">
          <div
            class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center"
          >
            <Construction size={28} class="text-amber-500" />
          </div>
          <div>
            <p class="font-bold text-slate-700">Registro de Asistencia</p>
            <p class="text-sm text-slate-400 mt-1.5 leading-relaxed max-w-xs">
              El módulo de consulta y registro de asistencia estará disponible próximamente.
            </p>
          </div>
          <span
            class="text-xs font-bold px-3 py-1 rounded-full bg-amber-100 text-amber-700 uppercase tracking-wider"
          >
            Próximamente
          </span>
        </div>
      {/if}
    </div>
  </aside>
{/if}
