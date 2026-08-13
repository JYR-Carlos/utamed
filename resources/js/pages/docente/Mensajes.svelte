<script lang="ts">
  /**
   * Mensajes de actividades del docente — bandeja transversal a todos sus cursos.
   *
   * Es la mensajería de NIVEL ACTIVIDAD: se entra desde la actividad (tarjeta de
   * docente/Actividades → ?actividad_id=…), no desde el menú lateral. La
   * mensajería de nivel curso es otra cosa y vive en /docente/mensajeria
   * (curso.mensaje), sin pasar por agenda.
   *
   * Modelo: agenda.agenda. Cada mensaje cuelga de un grupo → actividad → curso.
   * - Izquierda: árbol Curso → Actividad (con badges de pendientes).
   * - Derecha  : hilos (un hilo por grupo) de la actividad seleccionada, con
   *              caja de redacción que permite enviar a TODOS los grupos
   *              (grupal) o a un grupo concreto (individual).
   *
   * Visibilidad: el backend sólo entrega cursos donde el docente es titular.
   *
   * Endpoints:
   *   GET  /docente/mensajes?actividad_id=…                                  (lazy 'hilo')
   *   POST /docente/mensajes/cursos/{curso}/actividades/{actividad}/enviar
   */
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { onMount } from 'svelte';
  import { Link, page, router } from '@inertiajs/svelte';
  import type { BreadcrumbItem } from '@/types';
  import {
    MessageSquare,
    MessageSquarePlus,
    Send,
    Loader2,
    Search,
    Inbox,
    ChevronDown,
    ChevronRight,
    ChevronLeft,
    Folder,
    Users,
    User,
    GraduationCap,
  } from 'lucide-svelte';

  // ── Types ──────────────────────────────────────────────────────────────────
  interface ActividadItem {
    id_actividad: number;
    nombre: string;
    total: number;
    total_estudiante: number;
    pendientes: number;
    total_grupos: number;
    ultima_fecha: string | null;
  }
  interface CursoItem {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    agno_real: number;
    semestre_real: number;
    pendientes: number;
    actividades: ActividadItem[];
  }
  interface MensajeItem {
    id_agenda: number;
    fecha_envio: string;
    mensaje: string;
    es_docente: boolean;
    emisor: string;
    tipo: string;
  }

  interface Integrante {
    rut: string;
    nombre: string;
  }

  interface HiloGrupo {
    grupo: number;
    integrantes: Integrante[];
    es_individual: boolean;
    mensajes: MensajeItem[];
  }

  interface Hilo {
    id_actividad: number;
    nombre: string;
    id_curso: number;
    hilos: HiloGrupo[];
  }

  interface Props {
    cursos: CursoItem[];
    hilo?: Hilo | null;
  }

  let { cursos = [], hilo = null }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/docente/dashboard' },
    { title: 'Mensajes de actividades', href: '/docente/mensajes' },
  ];

  // ── State ──────────────────────────────────────────────────────────────────
  let query = $state('');
  let expandedCursos = $state<Record<number, boolean>>({});
  let selectedActividad = $state<{ id_curso: number; id_actividad: number; nombre: string } | null>(
    null,
  );
  let loadingHilo = $state(false);

  // Redacción
  let destino = $state<'todos' | number>('todos');
  let mensaje = $state('');
  let sending = $state(false);
  let sendError = $state<string | null>(null);

  // ── Derived ────────────────────────────────────────────────────────────────
  const cursosFiltrados = $derived(
    cursos
      .map((c) => {
        if (!query.trim()) return c;
        const q = query.toLowerCase();
        if (c.nombre.toLowerCase().includes(q) || c.cod_curso.toLowerCase().includes(q)) return c;
        return {
          ...c,
          actividades: c.actividades.filter((a) => a.nombre.toLowerCase().includes(q)),
        };
      })
      .filter((c) => c.actividades.length > 0),
  );

  const totalPendientes = $derived(cursos.reduce((acc, c) => acc + c.pendientes, 0));

  // ── Helpers ────────────────────────────────────────────────────────────────
  function periodoLabel(c: CursoItem) {
    return `Año ${c.agno_real} · Semestre ${c.semestre_real}`;
  }

  function isCursoOpen(c: CursoItem) {
    // Por defecto abierto si hay búsqueda activa o pendientes.
    return expandedCursos[c.id_curso] ?? (!!query.trim() || c.pendientes > 0);
  }

  function toggleCurso(id: number, c: CursoItem) {
    expandedCursos[id] = !isCursoOpen(c);
  }

  function fmtFull(iso: string) {
    return new Date(iso).toLocaleString('es-CL', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function grupoLabel(h: HiloGrupo) {
    if (h.integrantes.length === 0) return `Grupo #${h.grupo}`;
    if (h.es_individual) return h.integrantes[0].nombre;
    return h.integrantes.map((i) => i.nombre).join(', ');
  }

  // ── Actions ────────────────────────────────────────────────────────────────
  function selectActividad(c: CursoItem, a: ActividadItem) {
    if (selectedActividad?.id_actividad === a.id_actividad) return;
    selectedActividad = { id_curso: c.id_curso, id_actividad: a.id_actividad, nombre: a.nombre };
    destino = 'todos';
    mensaje = '';
    sendError = null;
    loadingHilo = true;
    router.reload({
      only: ['hilo'],
      data: { actividad_id: a.id_actividad },
      onFinish: () => (loadingHilo = false),
    });
  }

  // Deep-link desde la tarjeta de la actividad: ?actividad_id=… abre su hilo.
  // El prop `hilo` es lazy, así que en la carga inicial hay que pedirlo.
  onMount(() => {
    const qs = $page.url.split('?')[1] ?? '';
    const id = Number(new URLSearchParams(qs).get('actividad_id'));
    if (!id) return;

    for (const c of cursos) {
      const a = c.actividades.find((x) => x.id_actividad === id);
      if (a) {
        expandedCursos[c.id_curso] = true;
        selectActividad(c, a);
        return;
      }
    }
  });

  function enviar() {
    if (!selectedActividad || !mensaje.trim()) return;
    sending = true;
    sendError = null;
    router.post(
      `/docente/mensajes/cursos/${selectedActividad.id_curso}/actividades/${selectedActividad.id_actividad}/enviar`,
      { destino: destino === 'todos' ? 'todos' : String(destino), mensaje: mensaje.trim() },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          mensaje = '';
          if (selectedActividad) {
            router.reload({
              only: ['hilo'],
              data: { actividad_id: selectedActividad.id_actividad },
            });
          }
        },
        onError: (errors) => {
          sendError = (errors as any)?.mensaje ?? 'No se pudo enviar el mensaje.';
        },
        onFinish: () => (sending = false),
      },
    );
  }
</script>

<DocenteLayout {breadcrumbs}>
  <div class="flex flex-col h-full">
    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center gap-3 shrink-0">
      <div
        class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0"
      >
        <MessageSquare size={20} />
      </div>
      <div>
        <h1 class="text-lg font-extrabold text-slate-900 leading-tight">
          Mensajes de actividades
        </h1>
        <p class="text-xs text-slate-500">
          Consultas y feedback dentro de cada actividad, por grupo de entrega.
        </p>
      </div>
      <div class="ml-auto flex items-center gap-3">
        {#if totalPendientes > 0}
          <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">
            {totalPendientes} por responder
          </span>
        {/if}
        <!-- El otro canal: avisos y consultas que no cuelgan de una actividad. -->
        <Link
          href="/docente/mensajeria"
          class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 no-underline"
        >
          Mensajería del curso
          <ChevronRight size={14} />
        </Link>
      </div>
    </div>

    <!-- ── Two-column layout ───────────────────────────────────────────────── -->
    <div class="flex flex-1 min-h-0 overflow-hidden">
      <!-- ── Sidebar: árbol curso → actividad ──────────────────────────────── -->
      <!-- En móvil funciona como maestro/detalle: la lista ocupa todo el ancho
           y se oculta al elegir una actividad. -->
      <aside
        class="{selectedActividad
          ? 'hidden md:flex'
          : 'flex'} w-full md:w-80 border-r border-slate-200 bg-white flex-col flex-shrink-0"
      >
        <div class="px-3 py-3 border-b border-slate-100">
          <div class="relative">
            <Search size={14} class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              bind:value={query}
              type="search"
              placeholder="Buscar curso o actividad…"
              class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400 bg-slate-50"
            />
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-2">
          {#if cursosFiltrados.length === 0}
            <div class="px-4 py-10 text-center text-sm text-slate-400">
              {query ? 'Sin resultados.' : 'No hay actividades con grupos en tus cursos.'}
            </div>
          {:else}
            {#each cursosFiltrados as c (c.id_curso)}
              {@const open = isCursoOpen(c)}
              <div class="mb-1">
                <!-- Curso header -->
                <button
                  onclick={() => toggleCurso(c.id_curso, c)}
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
                  {#if c.pendientes > 0}
                    <span
                      class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0"
                      title="Mensajes por responder"
                    >
                      {c.pendientes}
                    </span>
                  {/if}
                </button>

                {#if open}
                  <div class="pl-7 flex flex-col gap-0.5 mt-0.5">
                    {#each c.actividades as a (a.id_actividad)}
                      <button
                        onclick={() => selectActividad(c, a)}
                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-left transition-colors
                          {selectedActividad?.id_actividad === a.id_actividad
                          ? 'bg-indigo-50 text-indigo-700'
                          : 'hover:bg-slate-50 text-slate-600'}"
                      >
                        <span class="flex-1 min-w-0 text-sm font-medium truncate">{a.nombre}</span>
                        {#if a.pendientes > 0}
                          <span
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0"
                          >
                            {a.pendientes}
                          </span>
                        {:else if a.total > 0}
                          <span class="text-[10px] text-slate-400 shrink-0">{a.total}</span>
                        {:else}
                          <span
                            class="text-slate-300 shrink-0"
                            title="Sin mensajes — inicia una conversación"
                          >
                            <MessageSquarePlus size={13} />
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

      <!-- ── Main: hilos de la actividad ───────────────────────────────────── -->
      <div
        class="{selectedActividad
          ? 'flex'
          : 'hidden md:flex'} flex-1 overflow-y-auto bg-slate-50 flex-col"
      >
        {#if selectedActividad}
          <!-- Volver a la lista (solo móvil) -->
          <button
            onclick={() => (selectedActividad = null)}
            class="md:hidden flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-indigo-600 bg-white border-b border-slate-200 sticky top-0 z-20"
          >
            <ChevronLeft size={16} /> Conversaciones
          </button>
        {/if}
        {#if !selectedActividad}
          <div class="flex flex-col items-center justify-center gap-4 h-full text-center px-8">
            <div
              class="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center"
            >
              <Inbox size={28} class="text-indigo-400" />
            </div>
            <div>
              <p class="font-semibold text-slate-700">Selecciona una actividad</p>
              <p class="text-sm text-slate-400 mt-1">
                Verás los mensajes de cada grupo y podrás responder.
              </p>
            </div>
          </div>
        {:else if loadingHilo && !hilo}
          <div class="flex items-center justify-center gap-2 h-full text-slate-400 text-sm">
            <Loader2 size={18} class="animate-spin" /> Cargando mensajes…
          </div>
        {:else if hilo}
          <!-- Cabecera de la actividad -->
          <div
            class="bg-white border-b border-slate-200 px-5 py-3 flex items-center gap-3 sticky top-0 z-10"
          >
            <GraduationCap size={18} class="text-indigo-500 shrink-0" />
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-900 truncate">{hilo.nombre}</p>
              <p class="text-xs text-slate-400">
                {hilo.hilos.length} grupo{hilo.hilos.length !== 1 ? 's' : ''}
              </p>
            </div>
          </div>

          <!-- ── Caja de redacción (grupal / individual) ─────────────────── -->
          <div class="bg-white border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Enviar a</span>
              <select
                bind:value={destino}
                class="text-sm border border-slate-200 rounded-lg px-2 py-1 bg-slate-50 focus:outline-none focus:border-indigo-400 max-w-[60%]"
              >
                <option value="todos">Todos los grupos ({hilo.hilos.length})</option>
                {#each hilo.hilos as h (h.grupo)}
                  <option value={h.grupo}>
                    {h.es_individual ? '👤 ' : '👥 '}{grupoLabel(h)}
                  </option>
                {/each}
              </select>
            </div>
            {#if sendError}
              <p class="text-xs text-red-500 mb-1">{sendError}</p>
            {/if}
            <div class="flex gap-2 items-end">
              <textarea
                bind:value={mensaje}
                rows={2}
                placeholder={destino === 'todos'
                  ? 'Mensaje para todos los grupos de la actividad…'
                  : 'Mensaje para el grupo seleccionado…'}
                class="flex-1 resize-none px-3 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:outline-none focus:border-indigo-400"
              ></textarea>
              <button
                onclick={enviar}
                disabled={!mensaje.trim() || sending}
                class="px-3 py-2.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors shrink-0 flex items-center gap-1.5 text-sm font-semibold"
              >
                {#if sending}
                  <Loader2 size={16} class="animate-spin" />
                {:else}
                  <Send size={16} />
                {/if}
                Enviar
              </button>
            </div>
          </div>

          <!-- ── Hilos por grupo ─────────────────────────────────────────── -->
          <div class="p-5 flex flex-col gap-5">
            {#if hilo.hilos.length === 0}
              <p class="text-center text-sm text-slate-400 py-10">
                Esta actividad aún no tiene grupos.
              </p>
            {/if}
            {#each hilo.hilos as h (h.grupo)}
              <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <!-- Cabecera del hilo -->
                <div
                  class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 flex items-center gap-2"
                >
                  {#if h.es_individual}
                    <User size={14} class="text-slate-400 shrink-0" />
                  {:else}
                    <Users size={14} class="text-slate-400 shrink-0" />
                  {/if}
                  <div class="min-w-0">
                    {#if h.es_individual}
                      <div class="flex">
                        <!-- Nombres de integrante -->
                        <p class="text-xs font-bold text-slate-700 truncate">{grupoLabel(h)}</p>
                        <!-- Espacio -->
                        <span class="mx-1 text-xs text-slate-400"></span>
                        <!-- Rut de los integrantes -->
                        {#if h.integrantes.length > 0}
                          <span
                            role="button"
                            tabindex="0"
                            class="select-all cursor-pointer text-xs font-light text-slate-400 hover:text-slate-600 transition-colors truncate"
                            ondblclick={() =>
                              navigator.clipboard.writeText(h.integrantes[0]?.rut ?? '')}
                            title="Doble clic para copiar al portapapeles"
                          >
                            {h.integrantes[0]?.rut ?? 'Sin rut'}
                          </span>
                        {:else}
                          <p class="text-xs font-light text-slate-400 truncate">Sin integrantes</p>
                        {/if}
                      </div>
                    {:else}
                      <div class="flex flex-col">
                        <p class="text-xs font-bold text-slate-700 truncate">{grupoLabel(h)}</p>
                        <div class="my-1 gap-0 text-xs font-light text-slate-400 truncate">
                          {#each h.integrantes as i, index (index)}
                            <span
                              role="button"
                              tabindex="0"
                              class="text-xs select-all cursor-pointer hover:text-slate-600 transition-colors"
                              title="Doble clic para copiar al portapapeles"
                              ondblclick={() => navigator.clipboard.writeText(i.rut)}
                            >
                              {i.rut}
                            </span>

                            {#if index < h.integrantes.length - 1}
                              <span class="text-xs mr-2">,</span>
                            {/if}
                          {/each}
                        </div>
                      </div>
                    {/if}
                    <p class="text-[10px] text-slate-400">
                      Grupo #{h.grupo} · {h.es_individual
                        ? 'Individual'
                        : `${h.integrantes.length} integrantes`}
                    </p>
                  </div>
                </div>

                <!-- Mensajes -->
                <div class="flex flex-col">
                  {#if h.mensajes.length === 0}
                    <p class="px-4 py-4 text-xs text-slate-400 italic">
                      Sin mensajes en este grupo.
                    </p>
                  {/if}
                  {#each h.mensajes as m (m.id_agenda)}
                    <div
                      class="px-4 py-3 border-b border-slate-100 last:border-b-0 {m.es_docente
                        ? 'bg-indigo-50/50'
                        : 'bg-white'}"
                    >
                      <div class="flex items-center gap-2 mb-1">
                        <span
                          class="text-[10px] font-bold px-2 py-0.5 rounded-full {m.es_docente
                            ? 'bg-indigo-100 text-indigo-700'
                            : 'bg-slate-100 text-slate-600'}"
                        >
                          {m.es_docente ? 'Tú (docente)' : 'Estudiante'}
                        </span>
                        <span class="text-[11px] font-medium text-slate-500 truncate"
                          >{m.emisor}</span
                        >
                        <span class="text-[10px] text-slate-400 ml-auto shrink-0"
                          >{fmtFull(m.fecha_envio)}</span
                        >
                      </div>
                      <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">
                        {m.mensaje}
                      </p>
                    </div>
                  {/each}
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </div>
  </div>
</DocenteLayout>
