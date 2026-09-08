<script lang="ts">
  /**
   * Seguimiento de syllabus — Jefatura de Carrera.
   *
   * Lámina «Seguimiento de syllabus»: data grid con filtros sobre un campo
   * derivado, slide-over de previsualización con índice romano y footer de
   * decisión persistente.
   *
   * Dos reglas mandan sobre el resto:
   *  - Las acciones por fila obedecen al ESTADO. Aprobar y rechazar sólo
   *    existen en «En revisión»; «No iniciado» no tiene previsualización porque
   *    no hay documento. Nada se dibuja en gris deshabilitado: lo que no se
   *    permite, no se dibuja.
   *  - El vacío nombra el filtro responsable y ofrece la salida; nunca dice
   *    sólo «sin resultados».
   *
   * Todos los datos llegan del controlador ya acotados a la carrera del jefe.
   * La vista no fabrica filas de ejemplo.
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { router } from '@inertiajs/svelte';
  import {
    Search,
    X,
    XCircle,
    Eye,
    Check,
    MoreHorizontal,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    MessageSquare,
    Send,
    FileText,
  } from 'lucide-svelte';
  import { untrack } from 'svelte';
  import { formatFechaCorta } from '@/utils/formatters';

  // ─── Tipos ───────────────────────────────────────────────────────────────

  type EstadoSyllabus = 'NO_INICIADO' | 'BORRADOR' | 'EN_REVISION' | 'APROBADO' | 'RECHAZADO';

  interface Docente {
    nombre: string;
    inicial: string;
    color: string;
  }

  interface CursoSeguimiento {
    id_curso: number;
    cod_asignatura: string;
    nombre_asignatura: string;
    seccion: string;
    letra_grupo: string | null;
    fecha_limite_syllabus: string | null;
    docente: Docente;
    estado_syllabus: EstadoSyllabus;
    fecha_actualizacion: string | null;
    completud: number;
    id_programa: number | null;
  }

  interface SyllabusData {
    titulo: string;
    codigo: string;
    letra_grupo: string | null;
    agno_real: number | null;
    semestre_real: number | null;
    docente: string;
    descripcion: string;
    objetivos: string[];
    unidades: { numero: number; titulo: string; horas: number; contenidos: string[] }[];
    evaluaciones: { descripcion: string; ponderacion: string; semana: number | string }[];
    version: number | null;
    autor: string | null;
    ultima_accion: string | null;
    completud: number;
  }

  interface Props {
    cursos?: CursoSeguimiento[];
    semestres_disponibles?: string[];
    agnos_disponibles?: number[];
    plazo_syllabus?: string | null;
    filters?: { q?: string; semestre?: string; agno?: string; estado?: string };
    pagination?: {
      current_page: number;
      last_page: number;
      total: number;
      total_carrera?: number;
    };
    carrera?: { nombre: string };
  }

  let {
    cursos = [],
    semestres_disponibles = [],
    agnos_disponibles = [],
    plazo_syllabus = null,
    filters = {},
    pagination = { current_page: 1, last_page: 1, total: 0, total_carrera: 0 },
    carrera = { nombre: 'Mi Carrera' },
  }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jefatura de Carrera', href: '/docente/jefe-carrera/dashboard' },
    { title: 'Seguimiento', href: '/docente/jefe-carrera/seguimiento' },
  ];

  // ─── Filtros ─────────────────────────────────────────────────────────────

  // untrack: captura el valor inicial del prop; a partir de ahí manda el estado local.
  let searchQ = $state(untrack(() => filters.q ?? ''));
  let filtroSemestre = $state(untrack(() => filters.semestre ?? ''));
  let filtroAgno = $state(untrack(() => filters.agno ?? ''));
  let filtroEstado = $state(untrack(() => filters.estado ?? ''));
  let openKebab = $state<number | null>(null);

  const hasFilters = $derived(!!(searchQ || filtroSemestre || filtroAgno || filtroEstado));
  const totalCarrera = $derived(pagination.total_carrera ?? pagination.total);

  /** Rango mostrado en el pie de la tabla (el controlador pagina de 10 en 10). */
  const POR_PAGINA = 10;
  const rangoDesde = $derived((pagination.current_page - 1) * POR_PAGINA + 1);
  const rangoHasta = $derived(Math.min(pagination.current_page * POR_PAGINA, pagination.total));

  const ESTADO_LABEL: Record<EstadoSyllabus, string> = {
    NO_INICIADO: 'No iniciado',
    BORRADOR: 'Borrador',
    EN_REVISION: 'En revisión',
    APROBADO: 'Aprobado',
    RECHAZADO: 'Rechazado',
  };

  function navegar(params: Record<string, string>) {
    router.get('/docente/jefe-carrera/seguimiento', params, { preserveState: true });
  }

  function aplicarFiltros(page?: number) {
    const params: Record<string, string> = {};
    if (searchQ) params.q = searchQ;
    if (filtroSemestre) params.semestre = filtroSemestre;
    if (filtroAgno) params.agno = filtroAgno;
    if (filtroEstado) params.estado = filtroEstado;
    if (page && page > 1) params.page = String(page);
    navegar(params);
  }

  function limpiarFiltros() {
    searchQ = '';
    filtroSemestre = '';
    filtroAgno = '';
    filtroEstado = '';
    navegar({});
  }

  /** Período que describe la cabecera, con lo que haya seleccionado. */
  const periodoLabel = $derived.by(() => {
    const partes: string[] = [];
    if (filtroSemestre) partes.push(`${filtroSemestre} semestre`);
    if (filtroAgno) partes.push(filtroAgno);
    return partes.join(' ');
  });

  /** Nombra el filtro culpable del vacío, para que la salida sea evidente. */
  const filtroCulpable = $derived.by(() => {
    if (filtroEstado) {
      const periodo = periodoLabel ? ` en ${periodoLabel}` : '';
      return `Ningún curso con estado «${ESTADO_LABEL[filtroEstado as EstadoSyllabus] ?? filtroEstado}»${periodo}`;
    }
    if (searchQ) return `Ningún curso coincide con «${searchQ}»`;
    if (periodoLabel) return `Ningún curso en ${periodoLabel}`;
    return 'Ningún curso coincide con los filtros';
  });

  // ─── Pastillas de estado ─────────────────────────────────────────────────

  const estadoConfig: Record<EstadoSyllabus, { label: string; cls: string; dot: string }> = {
    NO_INICIADO: {
      label: 'No iniciado',
      cls: 'border-[#CBD5E1] bg-[#F1F5F9] text-[#475569]',
      dot: 'bg-[#64748B]',
    },
    BORRADOR: {
      label: 'Borrador',
      cls: 'border-[#E5E7EB] bg-[#F5F1EA] text-[#5A5E6E]',
      dot: 'bg-[#A8A29E]',
    },
    EN_REVISION: {
      label: 'En revisión',
      cls: 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
      dot: 'bg-[#D97706]',
    },
    APROBADO: {
      label: 'Aprobado',
      cls: 'border-[#A7F3D0] bg-[#ECFDF5] text-[#047857]',
      dot: 'bg-[#059669]',
    },
    RECHAZADO: {
      label: 'Rechazado',
      cls: 'border-[#FECACA] bg-[#FEF2F2] text-[#B91C1C]',
      dot: 'bg-[#DC2626]',
    },
  };

  /** Hay documento que previsualizar en todo estado salvo «No iniciado». */
  const tieneDocumento = (c: CursoSeguimiento) =>
    c.estado_syllabus !== 'NO_INICIADO' && !!c.id_programa;

  /** Aprobar y rechazar sólo existen mientras el syllabus está en revisión. */
  const admiteDecision = (c: CursoSeguimiento) =>
    c.estado_syllabus === 'EN_REVISION' && !!c.id_programa;

  // ─── Slide-over de previsualización ──────────────────────────────────────

  interface SlideOverState {
    isOpen: boolean;
    curso: CursoSeguimiento | null;
    syllabus: SyllabusData | null;
    isLoading: boolean;
    approving: boolean;
  }

  let slideOver = $state<SlideOverState>({
    isOpen: false,
    curso: null,
    syllabus: null,
    isLoading: false,
    approving: false,
  });

  /** Índice del documento, construido con las secciones que TRAE el syllabus. */
  const ROMANOS = ['I.', 'II.', 'III.', 'IV.', 'V.', 'VI.', 'VII.', 'VIII.', 'IX.'];

  const secciones = $derived.by(() => {
    const s = slideOver.syllabus;
    if (!s) return [] as { id: string; titulo: string; romano: string }[];
    const presentes: { id: string; titulo: string }[] = [
      { id: 'identificacion', titulo: 'Identificación' },
    ];
    if (s.descripcion) presentes.push({ id: 'presentacion', titulo: 'Presentación' });
    if (s.objetivos.length > 0) presentes.push({ id: 'objetivos', titulo: 'Objetivos' });
    if (s.unidades.length > 0) presentes.push({ id: 'unidades', titulo: 'Unidades' });
    if (s.evaluaciones.length > 0) presentes.push({ id: 'evaluacion', titulo: 'Evaluación' });
    return presentes.map((sec, i) => ({ ...sec, romano: ROMANOS[i] ?? `${i + 1}.` }));
  });

  let seccionActiva = $state('identificacion');
  let panelDocumento = $state<HTMLElement | null>(null);

  function irASeccion(id: string) {
    seccionActiva = id;
    panelDocumento?.querySelector(`#sec-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /** Marca como activa la sección cuyo encabezado está más arriba pero visible. */
  function alDesplazar() {
    if (!panelDocumento) return;
    const tope = panelDocumento.getBoundingClientRect().top;
    let actual = secciones[0]?.id ?? '';
    for (const sec of secciones) {
      const el = panelDocumento.querySelector(`#sec-${sec.id}`);
      if (el && el.getBoundingClientRect().top - tope <= 24) actual = sec.id;
    }
    if (actual) seccionActiva = actual;
  }

  async function abrirSlideOver(curso: CursoSeguimiento) {
    slideOver = { ...slideOver, isOpen: true, curso, isLoading: true, syllabus: null };
    openKebab = null;
    seccionActiva = 'identificacion';
    cerrarSolicitud();

    if (!curso.id_programa) {
      slideOver = { ...slideOver, isLoading: false, syllabus: null };
      return;
    }

    try {
      const res = await fetch(`/docente/jefe-carrera/programas/${curso.id_programa}/preview`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      // Sólo aplica si el panel sigue abierto sobre el mismo curso.
      if (slideOver.isOpen && slideOver.curso?.id_curso === curso.id_curso) {
        slideOver = { ...slideOver, isLoading: false, syllabus: data?.syllabus ?? null };
      }
    } catch {
      if (slideOver.isOpen && slideOver.curso?.id_curso === curso.id_curso) {
        slideOver = { ...slideOver, isLoading: false, syllabus: null };
      }
    }
  }

  function cerrarSlideOver() {
    slideOver = { ...slideOver, isOpen: false };
    cerrarSolicitud();
  }

  function aprobarSyllabus() {
    const programa_id = slideOver.curso?.id_programa;
    if (!programa_id) return;
    slideOver = { ...slideOver, approving: true };
    router.post(
      `/docente/jefe-carrera/programas/${programa_id}/aprobar`,
      {},
      {
        onSuccess: () => cerrarSlideOver(),
        onFinish: () => {
          slideOver = { ...slideOver, approving: false };
        },
      },
    );
  }

  // ─── Solicitud de cambios: la razón es obligatoria ───────────────────────

  const RAZON_MAX = 500;

  let solicitudAbierta = $state(false);
  let razon = $state('');
  let enviandoSolicitud = $state(false);
  let razonError = $state<string | null>(null);
  let razonInput = $state<HTMLTextAreaElement | null>(null);

  const razonVacia = $derived(razon.trim().length === 0);

  function abrirSolicitud() {
    solicitudAbierta = true;
    razon = '';
    razonError = null;
  }

  function cerrarSolicitud() {
    solicitudAbierta = false;
    razon = '';
    razonError = null;
    enviandoSolicitud = false;
  }

  function enviarSolicitud() {
    const programa_id = slideOver.curso?.id_programa;
    if (!programa_id) return;

    // El botón nunca se dibuja gris: si falta la razón, el envío no sale y el
    // foco vuelve al campo, que ya estaba en error desde que se abrió.
    if (razonVacia) {
      razonError = 'La razón es obligatoria: se envía al docente y queda en el historial.';
      razonInput?.focus();
      return;
    }

    enviandoSolicitud = true;
    router.post(
      `/docente/jefe-carrera/programas/${programa_id}/rechazar`,
      { notas: razon.trim() },
      {
        onSuccess: () => cerrarSlideOver(),
        onError: (errors: Record<string, string>) => {
          razonError = (Object.values(errors)[0] as string) ?? 'No se pudo enviar la solicitud.';
          razonInput?.focus();
        },
        onFinish: () => {
          enviandoSolicitud = false;
        },
      },
    );
  }

  function handleWindowKeydown(e: KeyboardEvent) {
    if (e.key !== 'Escape') return;
    if (solicitudAbierta) cerrarSolicitud();
    else if (slideOver.isOpen) cerrarSlideOver();
    else openKebab = null;
  }

  // ─── Lenguaje visual ─────────────────────────────────────────────────────

  const CARD = 'rounded-xl border border-[#E5E7EB] bg-white shadow-[0_1px_3px_rgba(0,0,0,.08)]';
  const TH =
    'px-4 py-2.5 text-left text-[12px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]';
  const CAMPO =
    'h-[38px] rounded-lg border border-[#D6D9E0] bg-white text-[14px] text-[#1A1A24] focus:border-[#002F6C] focus:outline-none';
  const ICON_BTN =
    'inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg border transition-colors';
  const BTN_OUTLINE =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#D6D9E0] bg-white px-3.5 py-2.5 text-[14px] font-medium text-[#1A1A24] transition-colors hover:bg-[#F5F1EA]';
  const BTN_PRIMARY =
    'inline-flex items-center gap-[7px] rounded-lg border border-[#002F6C] bg-[#002F6C] px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#1B4789] disabled:opacity-60';
</script>

<svelte:window onkeydown={handleWindowKeydown} />

<AdminLayout {breadcrumbs}>
  <!-- ─── Cabecera ───────────────────────────────────────────────────────── -->
  <div class="mb-4 flex flex-wrap items-start gap-6">
    <div class="flex min-w-0 flex-col gap-1">
      <div class="flex items-center gap-1.5 text-[12px] text-[#5A5E6E]">
        <span>Jefatura de Carrera</span>
        <ChevronRight class="h-3 w-3" aria-hidden="true" />
        <span class="font-medium text-[#1A1A24]">Seguimiento</span>
      </div>
      <h1 class="m-0 text-[28px] font-semibold tracking-[-0.01em] text-[#1A1A24]">
        Seguimiento de syllabus — {carrera.nombre}
      </h1>
      <span class="text-[14px] text-[#5A5E6E]">
        {periodoLabel || 'Todos los períodos'}{plazo_syllabus
          ? ` · plazo de entrega ${formatFechaCorta(plazo_syllabus)}`
          : ''}
      </span>
    </div>
  </div>

  <!-- ─── Barra de filtros ───────────────────────────────────────────────── -->
  <div class="{CARD} mb-4 flex flex-wrap items-center gap-2.5 px-4 py-3.5">
    <!-- Búsqueda -->
    <div class="relative flex min-w-[260px] flex-1 items-center">
      <Search class="pointer-events-none absolute left-[11px] h-[15px] w-[15px] text-[#5A5E6E]" />
      <input
        bind:value={searchQ}
        placeholder="Buscar asignatura o docente…"
        aria-label="Buscar asignatura o docente"
        class="{CAMPO} w-full px-[34px]"
        onkeydown={(e) => e.key === 'Enter' && aplicarFiltros()}
      />
      {#if searchQ}
        <button
          onclick={() => {
            searchQ = '';
            aplicarFiltros();
          }}
          class="absolute right-[11px] text-[#5A5E6E] hover:text-[#1A1A24]"
          aria-label="Limpiar búsqueda"
        >
          <X class="h-[15px] w-[15px]" />
        </button>
      {/if}
    </div>

    <!-- Semestre: conmutador segmentado -->
    <div class="flex flex-none gap-[3px] rounded-lg bg-[#F1F5F9] p-[3px]">
      {#each semestres_disponibles as s (s)}
        {@const activo = filtroSemestre === s}
        <button
          onclick={() => {
            filtroSemestre = activo ? '' : s;
            aplicarFiltros();
          }}
          aria-pressed={activo}
          class="flex h-8 items-center rounded-md px-3 text-[13px] transition-colors {activo
            ? 'bg-white font-semibold text-[#002F6C] shadow-[0_1px_2px_rgba(0,0,0,.06)]'
            : 'font-medium text-[#5A5E6E] hover:text-[#1A1A24]'}"
        >
          {s}
        </button>
      {/each}
    </div>

    <!-- Año -->
    <div class="relative flex-none">
      <select
        bind:value={filtroAgno}
        onchange={() => aplicarFiltros()}
        aria-label="Filtrar por año"
        class="{CAMPO} appearance-none py-0 pl-3 pr-8"
      >
        <option value="">Año: todos</option>
        {#each agnos_disponibles as a (a)}
          <option value={String(a)}>Año {a}</option>
        {/each}
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-[11px] top-3 h-3.5 w-3.5 text-[#5A5E6E]"
      />
    </div>

    <!-- Estado: se resalta cuando hay filtro activo -->
    <div class="relative flex-none">
      <select
        bind:value={filtroEstado}
        onchange={() => aplicarFiltros()}
        aria-label="Filtrar por estado del syllabus"
        class="h-[38px] appearance-none rounded-lg border bg-white py-0 pl-3 pr-8 text-[14px] focus:outline-none {filtroEstado
          ? 'border-[#002F6C] font-medium text-[#002F6C]'
          : 'border-[#D6D9E0] text-[#1A1A24] focus:border-[#002F6C]'}"
      >
        <option value="">Estado: todos</option>
        <option value="NO_INICIADO">No iniciado</option>
        <option value="BORRADOR">Borrador</option>
        <option value="EN_REVISION">En revisión</option>
        <option value="APROBADO">Aprobado</option>
        <option value="RECHAZADO">Rechazado</option>
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-[11px] top-3 h-3.5 w-3.5 {filtroEstado
          ? 'text-[#002F6C]'
          : 'text-[#5A5E6E]'}"
      />
    </div>

    {#if hasFilters}
      <button
        onclick={limpiarFiltros}
        class="inline-flex flex-none items-center gap-1.5 rounded-lg px-2.5 py-2 text-[13px] font-semibold text-[#002F6C] transition-colors hover:bg-[#F5F1EA]"
      >
        <X class="h-3.5 w-3.5" />
        Limpiar
      </button>
    {/if}
  </div>

  <!-- ─── Tabla ──────────────────────────────────────────────────────────── -->
  <div class="{CARD} overflow-hidden">
    <div class="flex flex-wrap items-center gap-2.5 border-b border-[#E5E7EB] px-4 py-3">
      <span class="text-[14px] font-semibold text-[#1A1A24]">
        {pagination.total} de {totalCarrera}
        {totalCarrera === 1 ? 'curso' : 'cursos'}
      </span>
      <span class="text-[12px] text-[#5A5E6E]">
        coinciden con los filtros · el estado es un campo derivado, el total varía al filtrar
      </span>
    </div>

    {#if cursos.length === 0}
      <!-- El vacío nombra el filtro responsable y ofrece la salida -->
      <div class="flex flex-col items-center gap-3 px-8 py-11 text-center">
        {#if hasFilters}
          <div
            class="h-20 w-[120px] rounded-lg border border-[#E5E7EB]"
            style="background:repeating-linear-gradient(135deg,#F5F1EA 0 8px,#EFE9DF 8px 16px)"
            aria-hidden="true"
          ></div>
          <span class="text-[16px] font-semibold text-[#1A1A24]">{filtroCulpable}</span>
          <p class="m-0 max-w-[420px] text-[14px] text-[#5A5E6E]">
            {#if totalCarrera > 0}
              Los {totalCarrera}
              {totalCarrera === 1 ? 'curso' : 'cursos'} de la carrera siguen ahí; sólo la combinación
              de filtros deja la tabla sin filas.
            {:else}
              La carrera todavía no tiene cursos registrados.
            {/if}
          </p>
          {#if totalCarrera > 0}
            <div class="flex gap-2">
              <button onclick={limpiarFiltros} class={BTN_OUTLINE}>
                <X class="h-[15px] w-[15px] text-[#5A5E6E]" />
                Limpiar filtros
              </button>
              <button onclick={limpiarFiltros} class={BTN_PRIMARY}>
                Ver los {totalCarrera} cursos
              </button>
            </div>
          {/if}
        {:else}
          <FileText class="h-9 w-9 text-[#C9D6E6]" />
          <span class="text-[16px] font-semibold text-[#1A1A24]"
            >La carrera todavía no tiene cursos registrados</span
          >
          <p class="m-0 max-w-[420px] text-[14px] text-[#5A5E6E]">
            En cuanto se creen cursos para esta carrera, aparecerán aquí con el estado de su
            syllabus.
          </p>
        {/if}
      </div>
    {:else}
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[14px]">
          <thead class="bg-[#F5F1EA]">
            <tr>
              <th class="{TH} w-[104px]">Código</th>
              <th class={TH}>Asignatura</th>
              <th class="{TH} w-[70px] px-2">Grupo</th>
              <th class="{TH} w-[180px]">Docente titular</th>
              <th class="{TH} w-[150px]">Actualizado</th>
              <th class="{TH} w-[150px]">Estado</th>
              <th class="w-[190px] px-4 py-2.5"><span class="sr-only">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            {#each cursos as curso, i (curso.id_curso)}
              {@const badge = estadoConfig[curso.estado_syllabus] ?? estadoConfig.NO_INICIADO}
              {@const conDoc = tieneDocumento(curso)}
              {@const conDecision = admiteDecision(curso)}
              <tr class="border-t border-[#E5E7EB] {i % 2 === 1 ? 'bg-[#FCFBF9]' : ''}">
                <td class="px-4 py-2.5 font-mono text-[13px] text-[#5A5E6E]"
                  >{curso.cod_asignatura}</td
                >
                <td class="px-4 py-2.5 font-medium text-[#1A1A24]">{curso.nombre_asignatura}</td>
                <td class="px-2 py-2.5">
                  {#if curso.letra_grupo}
                    <span
                      class="inline-flex h-[22px] w-[22px] items-center justify-center rounded-md bg-[#F5F1EA] text-[12px] font-semibold text-[#5A5E6E]"
                      >{curso.letra_grupo}</span
                    >
                  {:else}
                    <span class="text-[#98A0AE]">—</span>
                  {/if}
                </td>
                <td class="px-4 py-2.5 text-[#1A1A24]">{curso.docente.nombre}</td>
                <td class="px-4 py-2.5 text-[#5A5E6E]">
                  {curso.fecha_actualizacion ? formatFechaCorta(curso.fecha_actualizacion) : '—'}
                </td>
                <td class="px-4 py-2.5">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-[3px] text-[12px] font-medium {badge.cls}"
                  >
                    <span class="h-1.5 w-1.5 rounded-full {badge.dot}" aria-hidden="true"></span>
                    {badge.label}
                  </span>
                </td>

                <!-- Acciones: sólo las que el estado permite -->
                <td class="px-4 py-2.5">
                  <div class="flex justify-end gap-1">
                    {#if conDoc}
                      <button
                        onclick={() => abrirSlideOver(curso)}
                        class="{ICON_BTN} border-[#D6D9E0] hover:bg-[#F5F1EA]"
                        aria-label="Previsualizar syllabus de {curso.nombre_asignatura}"
                        title="Previsualizar"
                      >
                        <Eye class="h-[15px] w-[15px] text-[#002F6C]" />
                      </button>
                    {/if}
                    {#if conDecision}
                      <button
                        onclick={() => abrirSlideOver(curso)}
                        class="{ICON_BTN} border-[#A7F3D0] bg-[#ECFDF5] hover:bg-[#D1FAE5]"
                        aria-label="Revisar para aprobar {curso.nombre_asignatura}"
                        title="Aprobar"
                      >
                        <Check class="h-[15px] w-[15px] text-[#047857]" />
                      </button>
                      <button
                        onclick={() => abrirSlideOver(curso)}
                        class="{ICON_BTN} border-[#FECACA] bg-[#FEF2F2] hover:bg-[#FEE2E2]"
                        aria-label="Revisar para solicitar cambios en {curso.nombre_asignatura}"
                        title="Solicitar cambios"
                      >
                        <XCircle class="h-[15px] w-[15px] text-[#B91C1C]" />
                      </button>
                    {/if}
                    <button
                      onclick={() =>
                        router.visit(`/docente/cursos/${curso.id_curso}/mensajeria`)}
                      class="{ICON_BTN} border-[#D6D9E0] hover:bg-[#F5F1EA]"
                      aria-label="Mensajería de {curso.nombre_asignatura}"
                      title="Mensajería del curso"
                    >
                      <MessageSquare class="h-[15px] w-[15px] text-[#5A5E6E]" />
                    </button>

                    <div class="relative">
                      <button
                        onclick={() => {
                          openKebab = openKebab === curso.id_curso ? null : curso.id_curso;
                        }}
                        class="{ICON_BTN} border-transparent hover:bg-[#F5F1EA]"
                        aria-label="Más opciones de {curso.nombre_asignatura}"
                      >
                        <MoreHorizontal class="h-[15px] w-[15px] text-[#5A5E6E]" />
                      </button>

                      {#if openKebab === curso.id_curso}
                        <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
                        <div
                          class="fixed inset-0 z-10"
                          onclick={() => (openKebab = null)}
                          role="presentation"
                        ></div>
                        <div
                          class="absolute right-0 top-full z-20 mt-1 w-52 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white py-1 shadow-lg"
                        >
                          <button
                            onclick={() => router.visit(`/docente/cursos/${curso.id_curso}`)}
                            class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-[13px] text-[#1A1A24] hover:bg-[#F5F1EA]"
                          >
                            <FileText class="h-3.5 w-3.5 text-[#5A5E6E]" />
                            Ver el curso
                          </button>
                          {#if conDoc}
                            <button
                              onclick={() => abrirSlideOver(curso)}
                              class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-[13px] text-[#1A1A24] hover:bg-[#F5F1EA]"
                            >
                              <Eye class="h-3.5 w-3.5 text-[#5A5E6E]" />
                              Previsualizar syllabus
                            </button>
                          {/if}
                        </div>
                      {/if}
                    </div>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <!-- Pie: recuento + paginación -->
      <div class="flex items-center gap-2 border-t border-[#E5E7EB] px-4 py-2.5">
        <span class="mr-auto text-[12px] text-[#5A5E6E]">
          Mostrando {rangoDesde}–{rangoHasta} de {pagination.total}
          {pagination.total === 1 ? 'curso filtrado' : 'cursos filtrados'}
        </span>
        {#if pagination.last_page > 1}
          <button
            onclick={() => aplicarFiltros(pagination.current_page - 1)}
            disabled={pagination.current_page <= 1}
            class="{ICON_BTN} border-[#D6D9E0] hover:bg-[#F5F1EA] disabled:opacity-40"
            aria-label="Página anterior"
          >
            <ChevronLeft class="h-3.5 w-3.5 text-[#5A5E6E]" />
          </button>
          {#each Array.from({ length: pagination.last_page }, (_, i) => i + 1) as page (page)}
            <button
              onclick={() => aplicarFiltros(page)}
              aria-current={page === pagination.current_page ? 'page' : undefined}
              class="inline-flex h-[30px] min-w-[30px] items-center justify-center rounded-lg border px-2 text-[13px] transition-colors {page ===
              pagination.current_page
                ? 'border-[#002F6C] bg-[#002F6C] font-semibold text-white'
                : 'border-[#D6D9E0] font-medium text-[#1A1A24] hover:bg-[#F5F1EA]'}"
            >
              {page}
            </button>
          {/each}
          <button
            onclick={() => aplicarFiltros(pagination.current_page + 1)}
            disabled={pagination.current_page >= pagination.last_page}
            class="{ICON_BTN} border-[#D6D9E0] hover:bg-[#F5F1EA] disabled:opacity-40"
            aria-label="Página siguiente"
          >
            <ChevronRight class="h-3.5 w-3.5 text-[#5A5E6E]" />
          </button>
        {/if}
      </div>
    {/if}
  </div>
</AdminLayout>

<!-- ═══════════════════════════════════════════════════════════════════════
     Slide-over de previsualización. Fuera del AdminLayout para apilar bien
     el z-index. El fondo se oscurece pero deja ver la tabla: no se pierde el
     sitio en la lista.
════════════════════════════════════════════════════════════════════════ -->

{#if slideOver.isOpen}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-40 bg-[rgba(26,26,36,.45)]"
    onclick={cerrarSlideOver}
    role="presentation"
  ></div>
{/if}

<div
  class="fixed right-0 top-0 z-50 flex h-full w-full max-w-[720px] flex-col bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)] transition-transform duration-300 ease-in-out {slideOver.isOpen
    ? 'translate-x-0'
    : 'translate-x-full'}"
  role="dialog"
  aria-modal="true"
  aria-label="Previsualización de syllabus"
>
  {#if slideOver.isOpen && slideOver.curso}
    {@const curso = slideOver.curso}
    {@const badge = estadoConfig[curso.estado_syllabus] ?? estadoConfig.NO_INICIADO}

    <!-- ── Cabecera ── -->
    <div class="flex flex-none items-center gap-3 border-b border-[#E5E7EB] px-5 py-4">
      <button
        onclick={cerrarSlideOver}
        class="{ICON_BTN} h-8 w-8 border-[#D6D9E0] hover:bg-[#F5F1EA]"
        aria-label="Volver a la lista"
      >
        <ArrowLeft class="h-4 w-4 text-[#1A1A24]" />
      </button>
      <div class="flex min-w-0 flex-col">
        <span class="truncate font-mono text-[11.5px] text-[#5A5E6E]">
          {curso.cod_asignatura}{curso.letra_grupo ? ` · Grupo ${curso.letra_grupo}` : ''}{periodoLabel
            ? ` · ${periodoLabel}`
            : ''}
        </span>
        <span class="truncate text-[16px] font-semibold leading-[1.3] text-[#1A1A24]"
          >{curso.nombre_asignatura}</span
        >
      </div>
      <span
        class="ml-auto inline-flex flex-none items-center gap-1.5 rounded-full border px-2.5 py-1 text-[12px] font-medium {badge.cls}"
      >
        <span class="h-1.5 w-1.5 rounded-full {badge.dot}" aria-hidden="true"></span>
        {badge.label}
      </span>
      <button
        onclick={cerrarSlideOver}
        class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg text-[#5A5E6E] transition-colors hover:bg-[#F5F1EA] hover:text-[#1A1A24]"
        aria-label="Cerrar panel"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <!-- ── Cuerpo: índice + documento ── -->
    <div class="flex min-h-0 flex-1">
      {#if secciones.length > 0}
        <nav
          class="hidden w-[212px] flex-none flex-col gap-0.5 overflow-y-auto border-r border-[#E5E7EB] bg-[#FCFBF9] px-3 py-3.5 sm:flex"
          aria-label="Contenido del syllabus"
        >
          <span
            class="px-2 pb-2 pt-0.5 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-[#5A5E6E]"
            >Contenido</span
          >
          {#each secciones as sec (sec.id)}
            {@const activa = seccionActiva === sec.id}
            <button
              onclick={() => irASeccion(sec.id)}
              class="flex gap-2 rounded-lg px-2 py-[7px] text-left text-[12.5px] transition-colors {activa
                ? 'bg-[#E8EDF5] font-semibold text-[#002F6C]'
                : 'text-[#1A1A24] hover:bg-[#F5F1EA]'}"
            >
              <span
                class="w-[22px] flex-none font-mono {activa ? 'text-[#002F6C]' : 'text-[#5A5E6E]'}"
                >{sec.romano}</span
              >
              {sec.titulo}
            </button>
          {/each}

          {#if slideOver.syllabus?.ultima_accion || slideOver.syllabus?.version != null}
            <div
              class="mt-auto flex flex-col gap-1 border-t border-[#E5E7EB] pt-2.5 text-[11.5px] text-[#5A5E6E]"
            >
              {#if slideOver.syllabus?.ultima_accion}
                <span>Movido {formatFechaCorta(slideOver.syllabus.ultima_accion)}</span>
              {/if}
              {#if slideOver.syllabus?.version != null}
                <span>
                  Versión {slideOver.syllabus.version}{slideOver.syllabus.autor
                    ? ` · ${slideOver.syllabus.autor}`
                    : ''}
                </span>
              {/if}
            </div>
          {/if}
        </nav>
      {/if}

      <div
        bind:this={panelDocumento}
        onscroll={alDesplazar}
        class="flex min-w-0 flex-1 flex-col gap-[18px] overflow-y-auto px-6 py-5"
      >
        {#if slideOver.isLoading}
          <div class="animate-pulse space-y-6">
            {#each [1, 2, 3] as bloque (bloque)}
              <div>
                <div class="mb-3 h-2.5 w-1/4 rounded-full bg-[#E5E7EB]"></div>
                <div class="h-3 w-full rounded bg-[#F5F1EA]"></div>
                <div class="mt-1.5 h-3 w-5/6 rounded bg-[#F5F1EA]"></div>
                <div class="mt-1.5 h-3 w-4/6 rounded bg-[#F5F1EA]"></div>
              </div>
            {/each}
          </div>
        {:else if !slideOver.syllabus}
          <div class="py-20 text-center">
            <FileText class="mx-auto mb-3 h-10 w-10 text-[#C9D6E6]" />
            <p class="m-0 text-[14px] font-medium text-[#5A5E6E]">
              Este curso no tiene un syllabus creado
            </p>
            <p class="mt-1 text-[12.5px] text-[#98A0AE]">
              El docente aún no ha iniciado el documento
            </p>
          </div>
        {:else}
          {@const s = slideOver.syllabus}
          {@const romano = (id: string) => secciones.find((x) => x.id === id)?.romano ?? ''}

          <!-- Identificación -->
          <section id="sec-identificacion" class="flex flex-col gap-2.5">
            <div class="flex items-baseline gap-2">
              <span class="font-mono text-[12px] text-[#5A5E6E]">{romano('identificacion')}</span>
              <h2 class="m-0 text-[16px] font-semibold text-[#1A1A24]">Identificación</h2>
            </div>
            <div
              class="grid grid-cols-1 gap-x-5 gap-y-2.5 rounded-[10px] border border-[#E5E7EB] bg-[#FCFBF9] p-3.5 sm:grid-cols-2"
            >
              <div class="flex flex-col">
                <span class="text-[11.5px] text-[#5A5E6E]">Asignatura</span>
                <span class="text-[13px] font-medium text-[#1A1A24]">{s.titulo}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[11.5px] text-[#5A5E6E]">Código</span>
                <span class="font-mono text-[13px] text-[#1A1A24]">{s.codigo}</span>
              </div>
              {#if s.letra_grupo}
                <div class="flex flex-col">
                  <span class="text-[11.5px] text-[#5A5E6E]">Grupo</span>
                  <span class="text-[13px] font-medium text-[#1A1A24]">{s.letra_grupo}</span>
                </div>
              {/if}
              {#if s.agno_real}
                <div class="flex flex-col">
                  <span class="text-[11.5px] text-[#5A5E6E]">Período</span>
                  <span class="text-[13px] font-medium text-[#1A1A24]"
                    >{s.semestre_real ?? '—'}º semestre {s.agno_real}</span
                  >
                </div>
              {/if}
              <div class="flex flex-col">
                <span class="text-[11.5px] text-[#5A5E6E]">Docente titular</span>
                <span class="text-[13px] font-medium text-[#1A1A24]">{s.docente}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[11.5px] text-[#5A5E6E]">Completud</span>
                <span class="text-[13px] font-medium text-[#1A1A24]">{s.completud}%</span>
              </div>
            </div>
          </section>

          <!-- Presentación -->
          {#if s.descripcion}
            <section id="sec-presentacion" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-[#5A5E6E]">{romano('presentacion')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-[#1A1A24]">Presentación</h2>
              </div>
              <p class="m-0 text-pretty text-[13.5px] leading-relaxed text-[#1A1A24]">
                {s.descripcion}
              </p>
            </section>
          {/if}

          <!-- Objetivos -->
          {#if s.objetivos.length > 0}
            <section id="sec-objetivos" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-[#5A5E6E]">{romano('objetivos')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-[#1A1A24]">Objetivos</h2>
              </div>
              <ul class="m-0 flex list-disc flex-col gap-1.5 pl-[18px] text-[13.5px] text-[#1A1A24]">
                {#each s.objetivos as obj, i (i)}
                  <li>{obj}</li>
                {/each}
              </ul>
            </section>
          {/if}

          <!-- Unidades -->
          {#if s.unidades.length > 0}
            <section id="sec-unidades" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-[#5A5E6E]">{romano('unidades')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-[#1A1A24]">Unidades</h2>
              </div>
              <div class="overflow-hidden rounded-[10px] border border-[#E5E7EB]">
                <table class="w-full border-collapse text-[13px]">
                  <thead class="bg-[#F5F1EA]">
                    <tr>
                      <th
                        class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]"
                        >Unidad</th
                      >
                      <th
                        class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]"
                        >Resultado de aprendizaje</th
                      >
                      <th
                        class="w-[70px] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]"
                        >Horas</th
                      >
                    </tr>
                  </thead>
                  <tbody>
                    {#each s.unidades as unidad, ui (ui)}
                      <tr class="border-t border-[#E5E7EB] align-top">
                        <td class="px-3 py-2.5 font-medium text-[#1A1A24]"
                          >{unidad.numero}. {unidad.titulo}</td
                        >
                        <td class="px-3 py-2.5 text-[#1A1A24]">
                          {#if unidad.contenidos.length > 0}
                            <ul class="m-0 flex list-disc flex-col gap-1 pl-4">
                              {#each unidad.contenidos as contenido, ci (ci)}
                                <li>{contenido}</li>
                              {/each}
                            </ul>
                          {:else}
                            <span class="text-[#98A0AE]">Sin resultados declarados</span>
                          {/if}
                        </td>
                        <td class="px-3 py-2.5 text-[#5A5E6E]">
                          {unidad.horas > 0 ? unidad.horas : '—'}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </section>
          {/if}

          <!-- Evaluación -->
          {#if s.evaluaciones.length > 0}
            <section id="sec-evaluacion" class="flex flex-col gap-2">
              <div class="flex items-baseline gap-2">
                <span class="font-mono text-[12px] text-[#5A5E6E]">{romano('evaluacion')}</span>
                <h2 class="m-0 text-[16px] font-semibold text-[#1A1A24]">Evaluación</h2>
              </div>
              <div class="overflow-hidden rounded-[10px] border border-[#E5E7EB]">
                <table class="w-full border-collapse text-[13px]">
                  <thead class="bg-[#F5F1EA]">
                    <tr>
                      <th
                        class="px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]"
                        >Evaluación</th
                      >
                      <th
                        class="w-[110px] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]"
                        >Ponderación</th
                      >
                      <th
                        class="w-[90px] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5A5E6E]"
                        >Semana</th
                      >
                    </tr>
                  </thead>
                  <tbody>
                    {#each s.evaluaciones as ev, i (i)}
                      <tr class="border-t border-[#E5E7EB]">
                        <td class="px-3 py-2.5 text-[#1A1A24]">{ev.descripcion}</td>
                        <td class="px-3 py-2.5 font-mono text-[#1A1A24]">{ev.ponderacion}</td>
                        <td class="px-3 py-2.5 text-[#5A5E6E]">
                          {ev.semana === '' || ev.semana === null ? '—' : `Sem. ${ev.semana}`}
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            </section>
          {/if}
        {/if}
      </div>
    </div>

    <!-- ── Footer de decisión: fijo, sólo cuando el estado la admite ── -->
    {#if admiteDecision(curso) && slideOver.syllabus}
      <div
        class="flex flex-none items-center gap-3 border-t border-[#E5E7EB] bg-white px-5 py-3.5 shadow-[0_-1px_3px_rgba(0,0,0,.06)]"
      >
        <div class="flex min-w-0 flex-col">
          <span class="text-[12.5px] font-semibold text-[#1A1A24]">Decisión de jefatura</span>
          <span class="text-[12px] text-[#5A5E6E]">Queda registrada con tu nombre y fecha</span>
        </div>
        <div class="ml-auto flex flex-none gap-2">
          <button onclick={abrirSolicitud} class={BTN_OUTLINE}>
            <MessageSquare class="h-[15px] w-[15px] text-[#5A5E6E]" />
            Solicitar cambios
          </button>
          <button onclick={aprobarSyllabus} disabled={slideOver.approving} class={BTN_PRIMARY}>
            <Check class="h-[15px] w-[15px]" />
            {slideOver.approving ? 'Aprobando…' : 'Aprobar'}
          </button>
        </div>
      </div>
    {/if}
  {/if}
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Solicitar cambios. La razón es obligatoria: el campo nace en error y el
     botón nunca se dibuja gris — si falta, el envío no sale y el foco vuelve
     al textarea. Rojo porque la acción devuelve el syllabus al docente.
════════════════════════════════════════════════════════════════════════ -->

{#if solicitudAbierta && slideOver.curso}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-[60] flex items-center justify-center bg-[rgba(26,26,36,.45)] p-4"
    onclick={cerrarSolicitud}
    role="presentation"
  >
    <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
    <div
      class="w-full max-w-[720px] overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-[0_20px_40px_rgba(0,0,0,.15)]"
      onclick={(e) => e.stopPropagation()}
      role="dialog"
      tabindex="-1"
      aria-modal="true"
      aria-label="Solicitar cambios"
    >
      <div
        class="flex flex-wrap items-center gap-2.5 border-b border-[#E5E7EB] bg-[#FCFBF9] px-5 py-3"
      >
        <MessageSquare class="h-[15px] w-[15px] text-[#5A5E6E]" />
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">
          Solicitar cambios — {slideOver.curso.cod_asignatura}
          {slideOver.curso.nombre_asignatura}
        </span>
        <span class="ml-auto text-[12px] text-[#5A5E6E]">volverá a estado Rechazado</span>
      </div>

      <div class="flex flex-col gap-2 px-5 py-4">
        <label for="razon-solicitud" class="text-[13px] font-semibold text-[#1A1A24]">
          Razón de la solicitud <span class="text-[#DC2626]">*</span>
        </label>
        <textarea
          id="razon-solicitud"
          bind:this={razonInput}
          bind:value={razon}
          rows="3"
          maxlength={RAZON_MAX}
          placeholder="Indica qué debe corregir el docente antes de reenviar"
          aria-invalid={razonVacia}
          class="w-full resize-y rounded-lg border px-3 py-2.5 text-[14px] text-[#1A1A24] placeholder-[#98A0AE] focus:outline-none {razonVacia
            ? 'border-[#DC2626]'
            : 'border-[#002F6C] outline outline-2 outline-offset-1 outline-[rgba(0,47,108,.25)]'}"
        ></textarea>
        <div class="flex items-center gap-2.5">
          <span class="text-[12px] {razonVacia ? 'text-[#DC2626]' : 'text-[#5A5E6E]'}">
            {razonError ??
              (razonVacia
                ? 'La razón es obligatoria: se envía al docente y queda en el historial.'
                : 'Se envía al docente y queda en el historial del syllabus.')}
          </span>
          <span class="ml-auto font-mono text-[11.5px] tabular-nums text-[#5A5E6E]">
            {razon.length} / {RAZON_MAX}
          </span>
        </div>
      </div>

      <div class="flex items-center gap-3 border-t border-[#E5E7EB] px-5 py-3.5">
        <button
          onclick={cerrarSolicitud}
          class="rounded-lg px-3 py-2.5 text-[14px] font-medium text-[#002F6C] transition-colors hover:bg-[#F5F1EA]"
        >
          Volver al documento
        </button>
        <button
          onclick={enviarSolicitud}
          disabled={enviandoSolicitud}
          class="ml-auto inline-flex items-center gap-[7px] rounded-lg border border-[#DC2626] bg-[#DC2626] px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-[#B91C1C] disabled:opacity-60"
        >
          <Send class="h-[15px] w-[15px]" />
          {enviandoSolicitud ? 'Enviando…' : 'Enviar solicitud'}
        </button>
      </div>
    </div>
  </div>
{/if}
