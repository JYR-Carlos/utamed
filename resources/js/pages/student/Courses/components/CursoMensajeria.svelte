<script lang="ts" module>
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

  /** Prop diferida `mensajeria` de la ficha (MensajeriaCursoEstudiante::bandeja). */
  export interface BandejaMensajeria {
    componentes: ComponenteItem[];
    componente_activo: number | null;
    panel: Panel | null;
  }
</script>

<script lang="ts">
  /**
   * Pestaña «Mensajería» de la ficha del curso — hilos sostenidos por el
   * componente.
   *
   * El curso ya está elegido, así que aquí sólo se navegan sus componentes.
   * Cada componente (Cátedra, Laboratorio…) es una pestaña, y dentro hay dos
   * vistas:
   *
   *  - Generales    : avisos del equipo docente al componente (sólo lectura).
   *  - Individuales : su conversación con ese equipo. Elige a qué docente dirige
   *                   el mensaje, pero lo ven todos, así que puede responderle
   *                   cualquiera de ellos sin abrir otra conversación.
   *
   * No pasa por agenda.agenda: las entregas y su feedback siguen en la actividad.
   *
   * Cambiar de componente recarga sólo la prop `mensajeria` con ?componente_id=…;
   * enviar hace POST a
   * /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje.
   */
  import { router } from '@inertiajs/svelte';
  import { Loader2, Megaphone, Send } from 'lucide-svelte';

  interface Props {
    idCurso: number;
    bandeja: BandejaMensajeria | null;
    /** La ficha está pidiendo la bandeja al abrir la pestaña. */
    cargando?: boolean;
    error?: string | null;
  }

  let { idCurso, bandeja, cargando = false, error = null }: Props = $props();

  const componentes = $derived(bandeja?.componentes ?? []);
  const componente_activo = $derived(bandeja?.componente_activo ?? null);
  const panel = $derived(bandeja?.panel ?? null);

  // ── State ──────────────────────────────────────────────────────────────────
  let vista = $state<'generales' | 'individuales'>('individuales');
  let recargando = $state(false);

  let cuerpo = $state('');
  let receptor = $state<number | null>(null);
  let tema = $state('');
  let sending = $state(false);
  let sendError = $state<string | null>(null);

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
  function selectComponente(id: number) {
    if (componente_activo === id) return;
    vista = 'individuales';
    cuerpo = '';
    tema = '';
    receptor = null;
    sendError = null;
    recargando = true;
    // componente_id queda en la URL: al volver de un envío se abre el mismo.
    router.reload({
      only: ['mensajeria'],
      data: { componente_id: id },
      onFinish: () => (recargando = false),
    });
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
      `/estudiante/cursos/${idCurso}/mensajeria/componentes/${componente_activo}/mensaje`,
      {
        mensaje: cuerpo.trim(),
        tema: tema.trim() || null,
        id_usuario_receptor: receptor,
      },
      {
        // El servidor vuelve a la ficha; sólo se trae la bandeja actualizada.
        only: ['mensajeria'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          cuerpo = '';
          tema = '';
        },
        onError: (errors) => {
          sendError = (errors as Record<string, string>)?.mensaje ?? 'No se pudo enviar el mensaje.';
        },
        onFinish: () => (sending = false),
      },
    );
  }
</script>

{#if !bandeja}
  <div class="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 p-10 text-sm text-slate-500">
    {#if error}
      {error}
    {:else}
      <Loader2 size={16} class="animate-spin" /> Cargando mensajes…
    {/if}
  </div>
{:else}
  <!-- Altura fija: la lista scrollea por dentro y el redactor no se mueve. -->
  <div
    class="flex h-[calc(100svh-14rem)] min-h-[26rem] max-h-[48rem] flex-col overflow-hidden rounded-2xl border border-slate-200"
  >
    <!-- Pestañas por componente. -->
    <div class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 bg-white px-3 sm:px-4" role="tablist">
      {#each componentes as c (c.id_componente)}
        <button
          role="tab"
          aria-selected={componente_activo === c.id_componente}
          onclick={() => selectComponente(c.id_componente)}
          class="-mb-px inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors {componente_activo ===
          c.id_componente
            ? 'border-brand text-brand'
            : 'border-transparent text-slate-500 hover:text-slate-700'}"
        >
          {c.tipo}
          {#if c.no_leidos > 0}
            <span
              class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-amber-100 px-1.5 text-[10px] font-bold text-amber-700"
            >
              {c.no_leidos}
            </span>
          {/if}
        </button>
      {/each}
    </div>

    <!-- ── Contenido del componente activo ───────────────────────────────── -->
    <section class="flex min-h-0 flex-1 flex-col bg-slate-50">
      {#if !panel}
        <div class="flex flex-1 items-center justify-center px-6 text-center text-sm text-slate-400">
          No estás inscrito en ningún componente de este curso.
        </div>
      {:else}
        <div
          class="flex shrink-0 flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-3 py-2.5 sm:gap-3 sm:px-4"
        >
          <div class="flex gap-1 rounded-lg bg-slate-100 p-0.5">
            <button
              onclick={() => (vista = 'generales')}
              class="rounded-md px-3 py-1 text-xs font-bold transition-colors {vista === 'generales'
                ? 'bg-white text-brand shadow-sm'
                : 'text-slate-500 hover:text-slate-700'}"
            >
              Generales
              {#if panel.difusiones.length > 0}
                <span class="opacity-60">({panel.difusiones.length})</span>
              {/if}
            </button>
            <button
              onclick={() => (vista = 'individuales')}
              class="rounded-md px-3 py-1 text-xs font-bold transition-colors {vista === 'individuales'
                ? 'bg-white text-brand shadow-sm'
                : 'text-slate-500 hover:text-slate-700'}"
            >
              Individuales
            </button>
          </div>

          {#if cargando || recargando}
            <Loader2 size={15} class="ml-auto shrink-0 animate-spin text-slate-400" />
          {/if}
        </div>

        {#if sendError}
          <div class="mx-4 mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">
            {sendError}
          </div>
        {/if}

        {#if vista === 'generales'}
          <!-- Una sola columna: en pantallas anchas se limita la medida. -->
          <div class="flex-1 overflow-y-auto p-3 sm:p-4">
            <div class="mx-auto w-full max-w-3xl space-y-3">
              {#if panel.difusiones.length === 0}
                <div class="py-10 text-center text-sm text-slate-400">
                  El equipo docente todavía no ha publicado avisos en {panel.componente}.
                </div>
              {:else}
                {#each panel.difusiones as d (d.id_mensaje)}
                  <article class="rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
                    <div class="flex items-start gap-2">
                      <Megaphone size={16} class="mt-0.5 shrink-0 text-brand" />
                      <div class="min-w-0 flex-1">
                        <h3 class="break-words text-sm font-bold text-slate-900">{d.tema}</h3>
                        <p class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-700">
                          {d.mensaje}
                        </p>
                        <p class="mt-2 text-[11px] text-slate-400">
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
                <div class="py-10 text-center text-sm text-slate-400">
                  Aún no has escrito al equipo docente de {panel.componente}.
                </div>
              {:else}
                {#each panel.mensajes as m (m.id_mensaje)}
                  <div class="flex {m.es_mio ? 'justify-end' : 'justify-start'}">
                    <div
                      class="max-w-[85%] rounded-2xl px-3.5 py-2 sm:max-w-[75%] {m.es_mio
                        ? 'bg-brand text-white'
                        : 'border border-slate-200 bg-white text-slate-800'}"
                    >
                      <p class="whitespace-pre-wrap break-words text-sm">{m.mensaje}</p>
                      <p class="mt-1 text-[10px] {m.es_mio ? 'text-white/90' : 'text-slate-400'}">
                        {m.es_mio ? 'Tú' : m.emisor} · {fmt(m.fecha_creacion)}
                      </p>
                    </div>
                  </div>
                {/each}
              {/if}
            </div>
          </div>

          <div class="shrink-0 border-t border-slate-200 bg-white p-3">
            <div class="mx-auto w-full max-w-3xl space-y-2">
              <!-- Destinatario y asunto se apilan en móvil: uno al lado del otro
                   dejaba dos campos demasiado estrechos para escribir. -->
              <div class="flex flex-col gap-2 sm:flex-row">
                <select
                  bind:value={receptor}
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs focus:border-brand focus:outline-none sm:w-auto sm:max-w-[55%] sm:py-1.5"
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
                    class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:outline-none sm:py-1.5"
                  />
                {/if}
              </div>
              <div class="flex gap-2">
                <textarea
                  bind:value={cuerpo}
                  rows="2"
                  maxlength="2000"
                  placeholder="Escribe tu consulta…"
                  class="min-w-0 flex-1 resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                ></textarea>
                <button
                  onclick={enviar}
                  disabled={sending || !cuerpo.trim()}
                  aria-label="Enviar consulta"
                  class="flex shrink-0 items-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-bold text-white transition-colors hover:bg-brand-hover disabled:opacity-40 sm:px-4"
                >
                  {#if sending}<Loader2 size={15} class="animate-spin" />{:else}<Send size={15} />{/if}
                  <span class="hidden sm:inline">Enviar</span>
                </button>
              </div>
              <p class="text-[10px] text-slate-400">
                Lo verá todo el equipo docente de {panel.componente}; puede responderte cualquiera de ellos.
              </p>
            </div>
          </div>
        {/if}
      {/if}
    </section>
  </div>
{/if}
