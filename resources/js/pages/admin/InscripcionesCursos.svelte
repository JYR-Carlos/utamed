<script lang="ts">
  /**
   * Gestión de Inscripciones — paradigma Roster Management (centrado en el Curso).
   *
   * Modo A (Selector de Curso): cuando no hay id_curso en filtros, se muestra
   *   una cuadrícula de cursos para elegir.
   *
   * Modo B (Roster): cuando hay id_curso activo, se muestra la lista completa
   *   de alumnos con máquina de estados inline y acción "+ Agregar Estudiantes".
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import type { PaginatedResponse } from '@/types/admin.types';
  import axios from 'axios';

  // ── Types ─────────────────────────────────────────────────────────────────
  interface CursoItem {
    id_curso: number;
    cod_curso: string;
    nome?: string;
    nome_real?: string;
    nombre?: string;
    asignatura_nombre?: string;
    carrera_nombre?: string;
    agno_real?: number;
    semestre_real?: number;
  }

  type EstadoInscripcion = 'INSCRITO' | 'RETIRADO' | 'ANULADO' | 'SUSPENDIDO' | 'APROBADO' | 'REPROBADO';

  interface RosterItem {
    id_inscripcion_curso: number;
    id_curso: number;
    id_estudiante: number;
    cod_inscripcion_uta: string | null;
    fecha_inscripcion: string;
    estado_inscripcion: EstadoInscripcion;
    num_intento: number;
    promedio_parcial: number | null;
    curso?: CursoItem;
    estudiante?: {
      id_estudiante: number;
      usuario?: { nombre1: string; apellido1: string; username: string };
    };
    _saving?: boolean;
    _prevEstado?: EstadoInscripcion;
  }

  interface EstudianteDisponible {
    id_estudiante: number;
    usuario?: { nombre1: string; apellido1: string; username: string };
  }

  interface Props {
    inscripciones: PaginatedResponse<RosterItem>;
    cursos: CursoItem[];
    filters: { search?: string; id_curso?: number | string; estado_inscripcion?: string };
  }

  let { inscripciones, cursos, filters }: Props = $props();

  // ── State machine config ───────────────────────────────────────────────────
  const TRANSITIONS: Partial<Record<EstadoInscripcion, { label: string; next: EstadoInscripcion; icon: string }[]>> = {
    INSCRITO: [
      { label: 'Marcar Retirado', next: 'RETIRADO', icon: '⏸' },
      { label: 'Anular inscripción', next: 'ANULADO', icon: '✕' },
    ],
    RETIRADO: [
      { label: 'Reinscribir', next: 'INSCRITO', icon: '↩' },
      { label: 'Anular inscripción', next: 'ANULADO', icon: '✕' },
    ],
    ANULADO: [{ label: 'Reinscribir', next: 'INSCRITO', icon: '↩' }],
    SUSPENDIDO: [
      { label: 'Reinscribir', next: 'INSCRITO', icon: '↩' },
      { label: 'Retirar', next: 'RETIRADO', icon: '⏸' },
    ],
  };

  const ESTADO_CFG: Record<EstadoInscripcion, { label: string; cls: string; rowCls: string }> = {
    INSCRITO: { label: 'Inscrito', cls: 'bg-emerald-100 text-emerald-800', rowCls: 'normal' },
    RETIRADO: { label: 'Retirado', cls: 'bg-amber-100 text-amber-800', rowCls: 'dimmed' },
    ANULADO: { label: 'Anulado', cls: 'bg-gray-100 text-gray-500', rowCls: 'voided' },
    SUSPENDIDO: { label: 'Suspendido', cls: 'bg-indigo-100 text-indigo-800', rowCls: 'dimmed' },
    APROBADO: { label: 'Aprobado', cls: 'bg-emerald-100 text-emerald-800', rowCls: 'normal' },
    REPROBADO: { label: 'Reprobado', cls: 'bg-red-100 text-red-700', rowCls: 'normal' },
  };

  // ── Mode detection ─────────────────────────────────────────────────────────
  const activeCursoId = $derived(filters.id_curso ? Number(filters.id_curso) : null);
  const isRosterMode = $derived(activeCursoId !== null);
  const selectedCurso = $derived(activeCursoId ? (cursos.find((c) => c.id_curso === activeCursoId) ?? null) : null);

  // ── Roster state ───────────────────────────────────────────────────────────
  let roster = $state<RosterItem[]>([]);
  let loadingRoster = $state(false);
  let rosterError = $state('');
  let openMenuId = $state<number | null>(null);

  const inscritoCount = $derived(roster.filter((r) => r.estado_inscripcion === 'INSCRITO').length);
  const totalCount = $derived(roster.filter((r) => r.estado_inscripcion !== 'ANULADO').length);

  $effect(() => {
    if (!isRosterMode) {
      roster = [];
      return;
    }
    loadRoster(activeCursoId!);
  });

  async function loadRoster(idCurso: number) {
    loadingRoster = true;
    rosterError = '';
    try {
      const res = await fetch(`/admin/inscripciones_cursos/ajax/by-curso?id_curso=${idCurso}`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error('Error al cargar el roster');
      const json = await res.json();
      roster = json.inscripciones ?? [];
    } catch (e) {
      rosterError = e instanceof Error ? e.message : 'Error desconocido';
    } finally {
      loadingRoster = false;
    }
  }

  // ── State machine: change estado ───────────────────────────────────────────
  async function changeEstado(item: RosterItem, next: EstadoInscripcion) {
    openMenuId = null;
    const prev = item.estado_inscripcion;
    item.estado_inscripcion = next;
    item._saving = true;
    item._prevEstado = prev;
    try {
      const { data: json } = await axios.patch(`/admin/inscripciones_cursos/${item.id_inscripcion_curso}/estado`, { estado_inscripcion: next });
      const idx = roster.findIndex((r) => r.id_inscripcion_curso === item.id_inscripcion_curso);
      if (idx !== -1) roster[idx] = { ...roster[idx], ...(json.inscripcion ?? {}), _saving: false };
    } catch (e) {
      item.estado_inscripcion = prev;
      item._saving = false;
      const msg = (e as any)?.response?.data?.message ?? (e instanceof Error ? e.message : 'Error al cambiar estado');
      showToast(msg, 'error');
    }
  }

  // ── Add Students Modal ─────────────────────────────────────────────────────
  let showAddModal = $state(false);
  let disponibles = $state<EstudianteDisponible[]>([]);
  let loadingDisponibles = $state(false);
  let selectedIds = $state<Set<number>>(new Set());
  let studentSearch = $state('');
  let submittingBulk = $state(false);
  let bulkContextError = $state('');

  const filteredDisponibles = $derived(
    studentSearch.trim().length === 0
      ? disponibles
      : disponibles.filter((e) => {
          const term = studentSearch.toLowerCase();
          return `${e.usuario?.nombre1 ?? ''} ${e.usuario?.apellido1 ?? ''} ${e.usuario?.username ?? ''}`.toLowerCase().includes(term);
        }),
  );

  async function openAddModal() {
    showAddModal = true;
    selectedIds = new Set();
    studentSearch = '';
    bulkContextError = '';
    disponibles = [];
    loadingDisponibles = true;
    try {
      const res = await fetch(`/admin/inscripciones_cursos/ajax/disponibles?id_curso=${activeCursoId}`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error('Error al cargar estudiantes');
      const json = await res.json();
      disponibles = json.estudiantes ?? [];
    } catch (_) {
      disponibles = [];
    } finally {
      loadingDisponibles = false;
    }
  }

  function closeAddModal() {
    showAddModal = false;
  }

  function toggleSelect(id: number) {
    if (selectedIds.has(id)) selectedIds.delete(id);
    else selectedIds.add(id);
    selectedIds = new Set(selectedIds);
  }

  function toggleAll() {
    selectedIds =
      selectedIds.size === filteredDisponibles.length && filteredDisponibles.length > 0
        ? new Set()
        : new Set(filteredDisponibles.map((e) => e.id_estudiante));
  }

  async function submitBulk() {
    if (selectedIds.size === 0) return;
    bulkContextError = '';
    submittingBulk = true;
    try {
      const { data: json } = await axios.post('/admin/inscripciones_cursos/bulk', {
        id_curso: activeCursoId,
        id_estudiantes: [...selectedIds],
      });
      roster = [...roster, ...(json.created ?? [])];
      const skipped = (json.skipped ?? []).length;
      closeAddModal();
      showToast(
        `${(json.created ?? []).length} estudiante${(json.created ?? []).length !== 1 ? 's' : ''} inscrito${(json.created ?? []).length !== 1 ? 's' : ''}.` +
          (skipped > 0 ? ` ${skipped} omitido(s) por ya estar inscritos.` : ''),
        'success',
      );
    } catch (e) {
      const err = (e as any)?.response?.data;
      bulkContextError = err?.errors?.id_estudiantes?.[0] ?? err?.message ?? (e instanceof Error ? e.message : 'Error de red.');
    } finally {
      submittingBulk = false;
    }
  }

  // ── Course selector ────────────────────────────────────────────────────────
  let courseSearch = $state('');
  const filteredCursos = $derived(
    courseSearch.trim().length === 0
      ? cursos
      : cursos.filter((c) => {
          const term = courseSearch.toLowerCase();
          return (
            c.cod_curso.toLowerCase().includes(term) ||
            (c.nombre ?? '').toLowerCase().includes(term) ||
            (c.asignatura_nombre ?? '').toLowerCase().includes(term) ||
            (c.carrera_nombre ?? '').toLowerCase().includes(term)
          );
        }),
  );

  function selectCurso(id: number) {
    router.visit(`/admin/inscripciones_cursos?id_curso=${id}`, { preserveScroll: false });
  }

  function backToSelector() {
    router.visit('/admin/inscripciones_cursos');
  }

  // ── Hard delete ────────────────────────────────────────────────────────────
  let showDeleteDialog = $state(false);
  let deletingItem = $state<RosterItem | null>(null);
  let isDeleting = $state(false);

  function openDelete(item: RosterItem) {
    deletingItem = item;
    showDeleteDialog = true;
  }

  function handleDelete() {
    if (!deletingItem) return;
    isDeleting = true;
    router.delete(`/admin/inscripciones_cursos/${deletingItem.id_inscripcion_curso}`, {
      onSuccess: () => {
        roster = roster.filter((r) => r.id_inscripcion_curso !== deletingItem!.id_inscripcion_curso);
        showDeleteDialog = false;
        deletingItem = null;
        isDeleting = false;
      },
      onError: () => {
        isDeleting = false;
      },
    });
  }

  // ── Toast ──────────────────────────────────────────────────────────────────
  let toast = $state<{ msg: string; type: 'success' | 'error' } | null>(null);
  let toastTimer: ReturnType<typeof setTimeout> | null = null;

  function showToast(msg: string, type: 'success' | 'error' = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast = { msg, type };
    toastTimer = setTimeout(() => (toast = null), 4500);
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
      if (showAddModal) closeAddModal();
      else openMenuId = null;
    }
  }

  function studentName(item: RosterItem): string {
    const u = item.estudiante?.usuario;
    return u ? `${u.nombre1} ${u.apellido1}` : `Estudiante #${item.id_estudiante}`;
  }
  function studentUsername(item: RosterItem): string {
    return item.estudiante?.usuario?.username ?? '';
  }
  function initials(item: RosterItem): string {
    const u = item.estudiante?.usuario;
    return ((u?.nombre1?.[0] ?? '') + (u?.apellido1?.[0] ?? '')).toUpperCase() || '?';
  }
  function disponibleName(e: EstudianteDisponible): string {
    const u = e.usuario;
    return u ? `${u.nombre1} ${u.apellido1}` : `#${e.id_estudiante}`;
  }
  function cursoDisplayName(c: CursoItem): string {
    return c.nombre || c.asignatura_nombre || c.cod_curso;
  }
</script>

<svelte:window onkeydown={handleKeydown} />

<AdminLayout>
  {#if isRosterMode}
    <!-- ══════════════════════════════════════════════════ ROSTER MODE ══ -->
    <div class="py-7 px-8 max-w-[1100px] mx-auto">
      <nav class="mb-4">
        <button
          onclick={backToSelector}
          class="inline-flex items-center gap-1.5 text-[0.8125rem] text-gray-500 bg-transparent border-0 cursor-pointer p-0 hover:text-gray-900 transition-colors"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg
          >
          Todos los Cursos
        </button>
      </nav>

      <div class="flex justify-between items-start flex-wrap gap-4 mb-6">
        <div class="flex items-start gap-5 flex-wrap">
          <div>
            <h1 class="text-[1.625rem] font-bold text-gray-900 mb-0.5 mt-0">
              {selectedCurso ? cursoDisplayName(selectedCurso) : `Curso ${activeCursoId}`}
            </h1>
            {#if selectedCurso}
              <p class="text-[0.8125rem] text-gray-500 m-0">
                {selectedCurso.cod_curso}
                {#if selectedCurso.asignatura_nombre}&nbsp;·&nbsp;{selectedCurso.asignatura_nombre}{/if}
                {#if selectedCurso.carrera_nombre}&nbsp;·&nbsp;{selectedCurso.carrera_nombre}{/if}
                {#if selectedCurso.agno_real}&nbsp;·&nbsp;{selectedCurso.agno_real} S{selectedCurso.semestre_real}{/if}
              </p>
            {/if}
          </div>
          <div class="flex flex-col items-center bg-green-50 border-[1.5px] border-green-200 rounded-[10px] px-3.5 py-2">
            <div class="flex items-baseline gap-0.5">
              <span class="text-xl font-extrabold text-green-600">{inscritoCount}</span>
              <span class="text-sm text-gray-400">/</span>
              <span class="text-base font-semibold text-gray-700">{totalCount}</span>
            </div>
            <span class="text-[0.6875rem] text-gray-500 mt-0.5">Inscritos activos</span>
          </div>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
          <a
            href="/admin/inscripciones_cursos/export/csv?id_curso={activeCursoId}"
            class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer no-underline hover:bg-gray-50 hover:border-gray-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Exportar CSV
          </a>
          <button
            onclick={openAddModal}
            class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm no-underline hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Agregar Estudiantes
          </button>
        </div>
      </div>

      {#if loadingRoster}
        <div class="flex items-center justify-center gap-3 py-12 text-gray-500">
          <div class="w-5 h-5 border-[2.5px] border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
          <span>Cargando roster…</span>
        </div>
      {:else if rosterError}
        <div class="flex flex-col items-center gap-4 py-16 text-center">
          <p class="text-red-600 text-sm">{rosterError}</p>
          <button
            onclick={() => loadRoster(activeCursoId!)}
            class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer no-underline hover:bg-gray-50 hover:border-gray-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >Reintentar</button
          >
        </div>
      {:else if roster.length === 0}
        <div class="flex flex-col items-center gap-4 py-16 text-center">
          <p class="text-gray-500 text-[0.9375rem]">Este curso no tiene estudiantes inscritos aún.</p>
          <button
            onclick={openAddModal}
            class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm no-underline hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
            >+ Agregar Estudiantes</button
          >
        </div>
      {:else}
        <div class="overflow-x-auto border border-gray-200 rounded-xl shadow-sm">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-slate-50">
                <th class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
                  >Estudiante</th
                >
                <th class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
                  >Usuario</th
                >
                <th class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
                  >Fecha Inscripción</th
                >
                <th class="px-4 py-3 text-center text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
                  >Intentos</th
                >
                <th class="px-4 py-3 text-center text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
                  >Promedio</th
                >
                <th class="px-4 py-3 text-left text-[0.6875rem] font-bold text-gray-500 uppercase tracking-[0.04em] border-b border-gray-200"
                  >Estado</th
                >
                <th class="px-4 py-3 border-b border-gray-200"></th>
              </tr>
            </thead>
            <tbody>
              {#each roster as item, idx (item.id_inscripcion_curso)}
                {@const cfg = ESTADO_CFG[item.estado_inscripcion] ?? ESTADO_CFG['INSCRITO']}
                {@const transitions = TRANSITIONS[item.estado_inscripcion] ?? []}
                {@const dimCls = cfg.rowCls === 'dimmed' ? 'opacity-60' : ''}
                {@const voidCls = cfg.rowCls === 'voided' ? 'opacity-40 line-through' : ''}
                <tr class={idx < roster.length - 1 ? 'border-b border-slate-100' : ''} class:opacity-60={item._saving}>
                  <td class="px-4 py-3 align-middle flex items-center gap-2.5 min-w-[160px] {dimCls} {voidCls}">
                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-[0.6875rem] font-bold flex items-center justify-center shrink-0">
                      {initials(item)}
                    </div>
                    <span class="font-medium text-gray-900">{studentName(item)}</span>
                  </td>
                  <td class="px-4 py-3 align-middle font-mono text-[0.8125rem] text-gray-500 {dimCls} {voidCls}">{studentUsername(item)}</td>
                  <td class="px-4 py-3 align-middle whitespace-nowrap text-gray-500 {dimCls} {voidCls}">{item.fecha_inscripcion ?? '—'}</td>
                  <td class="px-4 py-3 align-middle text-center text-gray-700 {dimCls}">{item.num_intento}</td>
                  <td class="px-4 py-3 align-middle text-center text-gray-700 {dimCls}"
                    >{item.promedio_parcial != null ? item.promedio_parcial : '—'}</td
                  >
                  <td class="px-4 py-3 align-middle min-w-[140px]">
                    {#if transitions.length > 0}
                      <div class="relative inline-block">
                        <button
                          type="button"
                          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border-0 cursor-pointer transition-opacity whitespace-nowrap disabled:cursor-wait {cfg.cls}"
                          class:animate-pulse={item._saving}
                          onclick={() => (openMenuId = openMenuId === item.id_inscripcion_curso ? null : item.id_inscripcion_curso)}
                          disabled={item._saving}
                        >
                          {cfg.label}
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="10"
                            height="10"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                          >
                            <polyline points="6 9 12 15 18 9" />
                          </svg>
                        </button>
                        {#if openMenuId === item.id_inscripcion_curso}
                          <!-- svelte-ignore a11y_no_static_element_interactions -->
                          <div class="fixed inset-0 z-10" onclick={() => (openMenuId = null)} role="presentation"></div>
                          <div
                            class="absolute top-[calc(100%+6px)] left-0 z-20 bg-white border border-gray-200 rounded-lg shadow-[0_4px_16px_rgba(0,0,0,0.12)] min-w-[185px] overflow-hidden"
                          >
                            {#each transitions as t}
                              <button
                                type="button"
                                class="flex items-center gap-2 w-full px-3.5 py-2 text-[0.8125rem] text-gray-700 bg-white border-0 cursor-pointer text-left hover:bg-gray-100 transition-colors"
                                onclick={() => changeEstado(item, t.next)}
                              >
                                <span class="text-sm">{t.icon}</span>{t.label}
                              </button>
                            {/each}
                          </div>
                        {/if}
                      </div>
                    {:else}
                      <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap cursor-default {cfg.cls}"
                        >{cfg.label}</span
                      >
                    {/if}
                  </td>
                  <td class="px-4 py-3 align-middle w-10 text-center">
                    <button
                      type="button"
                      class="p-1.5 text-gray-300 bg-transparent border-0 cursor-pointer rounded inline-flex items-center hover:bg-red-50 hover:text-red-500 transition-all"
                      onclick={() => openDelete(item)}
                      title="Eliminar permanentemente"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      >
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                      </svg>
                    </button>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </div>

    <!-- ══════════════ ADD STUDENTS MODAL ══════════════ -->
    {#if showAddModal}
      <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
      <div class="fixed inset-0 bg-black/45 z-50" onclick={closeAddModal} role="presentation"></div>
      <div
        class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[60] bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.18)] w-[min(540px,calc(100vw-2rem))] max-h-[88dvh] flex flex-col overflow-hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Agregar Estudiantes"
      >
        <div class="flex justify-between items-start px-6 pt-5 pb-4 border-b border-slate-100 shrink-0">
          <div>
            <h2 class="text-[1.0625rem] font-bold text-gray-900 m-0">Agregar Estudiantes</h2>
            <p class="text-[0.8125rem] text-gray-500 mt-0.5 mb-0">{selectedCurso ? cursoDisplayName(selectedCurso) : ''}</p>
          </div>
          <button
            class="p-1.5 border-0 bg-transparent rounded-md text-gray-400 cursor-pointer hover:bg-gray-100 hover:text-gray-900 transition-all"
            onclick={closeAddModal}
            aria-label="Cerrar"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg
            >
          </button>
        </div>

        <div class="px-6 py-3.5 border-b border-slate-100 shrink-0">
          <div class="relative">
            <svg
              class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
              xmlns="http://www.w3.org/2000/svg"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input
              type="search"
              bind:value={studentSearch}
              placeholder="Buscar por nombre o usuario…"
              class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
            />
          </div>
          {#if bulkContextError}
            <div class="mt-2 flex items-center gap-1.5 text-red-600 text-[0.8125rem]">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              {bulkContextError}
            </div>
          {/if}
        </div>

        <div class="overflow-y-auto flex-1 min-h-0">
          {#if loadingDisponibles}
            <div class="flex items-center justify-center gap-3 py-10 text-gray-500">
              <div class="w-5 h-5 border-[2.5px] border-gray-200 border-t-blue-500 rounded-full animate-spin"></div>
              <span>Buscando estudiantes disponibles…</span>
            </div>
          {:else if disponibles.length === 0}
            <p class="py-10 text-center text-gray-500 text-sm">Todos los estudiantes ya están inscritos en este curso.</p>
          {:else if filteredDisponibles.length === 0}
            <p class="py-10 text-center text-gray-500 text-sm">Sin resultados para «{studentSearch}».</p>
          {:else}
            <label class="flex items-center gap-2.5 px-5 py-2.5 border-b border-slate-100 cursor-pointer hover:bg-gray-50">
              <input type="checkbox" checked={selectedIds.size === filteredDisponibles.length} onchange={toggleAll} />
              <span class="text-sm font-medium text-gray-700">Seleccionar todos <small>({filteredDisponibles.length})</small></span>
            </label>
            <div class="divide-y divide-slate-100">
              {#each filteredDisponibles as e (e.id_estudiante)}
                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <label
                  class="flex items-center gap-3 px-5 py-2.5 cursor-pointer transition-colors {selectedIds.has(e.id_estudiante)
                    ? 'bg-blue-50'
                    : 'hover:bg-gray-50'}"
                  onclick={() => toggleSelect(e.id_estudiante)}
                  tabindex="0"
                >
                  <input
                    type="checkbox"
                    checked={selectedIds.has(e.id_estudiante)}
                    onchange={() => toggleSelect(e.id_estudiante)}
                    onclick={(ev) => ev.stopPropagation()}
                  />
                  <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-[0.625rem] font-bold flex items-center justify-center shrink-0">
                    {((e.usuario?.nombre1?.[0] ?? '') + (e.usuario?.apellido1?.[0] ?? '')).toUpperCase() || '?'}
                  </div>
                  <div class="flex flex-col min-w-0">
                    <span class="text-sm font-medium text-gray-900 truncate">{disponibleName(e)}</span>
                    <span class="text-xs text-gray-500">{e.usuario?.username ?? ''}</span>
                  </div>
                </label>
              {/each}
            </div>
          {/if}
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex justify-between items-center shrink-0">
          <span class="text-sm text-gray-500">{selectedIds.size} seleccionado{selectedIds.size !== 1 ? 's' : ''}</span>
          <div class="flex gap-2.5">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 px-4 py-[0.55rem] bg-white text-gray-700 border border-gray-300 rounded-lg text-sm font-medium cursor-pointer no-underline hover:bg-gray-50 hover:border-gray-400 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              onclick={closeAddModal}
              disabled={submittingBulk}>Cancelar</button
            >
            <button
              type="button"
              class="inline-flex items-center gap-1.5 px-[1.125rem] py-[0.55rem] bg-gradient-to-br from-blue-500 to-blue-600 text-white border-0 rounded-lg text-sm font-medium cursor-pointer transition-all shadow-sm no-underline hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={selectedIds.size === 0 || submittingBulk}
              onclick={submitBulk}
            >
              {#if submittingBulk}<span class="inline-block w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"
                ></span>Inscribiendo…{:else}Confirmar Inscripción{/if}
            </button>
          </div>
        </div>
      </div>
    {/if}
  {:else}
    <!-- ═══════════════════════════════════ COURSE SELECTOR MODE ══ -->
    <div class="py-7 px-8 max-w-[1100px] mx-auto">
      <div class="mb-5">
        <h1 class="text-[1.625rem] font-bold text-gray-900 mb-0.5 mt-0">Inscripciones de Cursos</h1>
        <p class="text-[0.8125rem] text-gray-500 m-0">Selecciona un Curso Ofertado para gestionar su lista de alumnos.</p>
      </div>

      <div class="relative mb-5">
        <svg
          class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          type="search"
          bind:value={courseSearch}
          placeholder="Buscar curso por nombre, asignatura o carrera…"
          class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-900 bg-white focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
        />
      </div>

      {#if filteredCursos.length === 0}
        <p class="text-gray-500 text-[0.9375rem]">{courseSearch ? `Sin resultados para «${courseSearch}».` : 'No hay cursos disponibles.'}</p>
      {:else}
        <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-3">
          {#each filteredCursos as c (c.id_curso)}
            <button
              type="button"
              class="text-left bg-white border border-gray-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 hover:shadow-md transition-all"
              onclick={() => selectCurso(c.id_curso)}
            >
              <div class="flex justify-between items-center mb-1.5">
                <span class="text-xs font-mono font-semibold text-blue-600">{c.cod_curso}</span>
                {#if c.agno_real}<span class="text-xs text-gray-400">{c.agno_real} S{c.semestre_real}</span>{/if}
              </div>
              <h3 class="font-semibold text-gray-900 text-sm mb-1">{cursoDisplayName(c)}</h3>
              {#if c.carrera_nombre}<p class="text-xs text-gray-500 mb-2.5">{c.carrera_nombre}</p>{/if}
              <div class="flex items-center gap-1 text-xs font-medium text-blue-500 mt-2">
                Ver Roster
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg
                >
              </div>
            </button>
          {/each}
        </div>
      {/if}
    </div>
  {/if}

  <!-- ── Delete Confirmation ── -->
  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar inscripción?"
    message="Esta acción es permanente. Considera cambiar el estado a 'Retirado' o 'Anulado' en su lugar para mantener el historial."
    onConfirm={handleDelete}
    onCancel={() => {
      showDeleteDialog = false;
      deletingItem = null;
    }}
    isLoading={isDeleting}
  />

  <!-- ── Toast ── -->
  {#if toast}
    <div
      role="status"
      aria-live="polite"
      class="fixed bottom-6 right-6 z-[10000] flex items-center gap-2.5 px-5 py-3 rounded-xl text-sm font-medium shadow-xl {toast.type === 'success'
        ? 'bg-green-50 border border-green-200 text-green-800'
        : 'bg-red-50 border border-red-200 text-red-700'}"
    >
      {#if toast.type === 'success'}
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
        >
      {:else}
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="15"
          height="15"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
      {/if}
      {toast.msg}
    </div>
  {/if}
</AdminLayout>
