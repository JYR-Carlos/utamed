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
    INSCRITO: { label: 'Inscrito', cls: 'badge-inscrito', rowCls: '' },
    RETIRADO: { label: 'Retirado', cls: 'badge-retirado', rowCls: 'row-dimmed' },
    ANULADO: { label: 'Anulado', cls: 'badge-anulado', rowCls: 'row-voided' },
    SUSPENDIDO: { label: 'Suspendido', cls: 'badge-suspendido', rowCls: 'row-dimmed' },
    APROBADO: { label: 'Aprobado', cls: 'badge-aprobado', rowCls: '' },
    REPROBADO: { label: 'Reprobado', cls: 'badge-reprobado', rowCls: '' },
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

  function getCsrf(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  }

  // ── State machine: change estado ───────────────────────────────────────────
  async function changeEstado(item: RosterItem, next: EstadoInscripcion) {
    openMenuId = null;
    const prev = item.estado_inscripcion;
    item.estado_inscripcion = next;
    item._saving = true;
    item._prevEstado = prev;
    try {
      const res = await fetch(`/admin/inscripciones_cursos/${item.id_inscripcion_curso}/estado`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        body: JSON.stringify({ estado_inscripcion: next }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({ message: 'Error' }));
        throw new Error(err.message ?? 'Error al cambiar estado');
      }
      const json = await res.json();
      const idx = roster.findIndex((r) => r.id_inscripcion_curso === item.id_inscripcion_curso);
      if (idx !== -1) roster[idx] = { ...roster[idx], ...(json.inscripcion ?? {}), _saving: false };
    } catch (e) {
      item.estado_inscripcion = prev;
      item._saving = false;
      showToast(e instanceof Error ? e.message : 'Error al cambiar estado', 'error');
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
      const res = await fetch('/admin/inscripciones_cursos/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        body: JSON.stringify({ id_curso: activeCursoId, id_estudiantes: [...selectedIds] }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        bulkContextError = err?.errors?.id_estudiantes?.[0] ?? err?.message ?? 'Error al inscribir.';
        return;
      }
      const json = await res.json();
      roster = [...roster, ...(json.created ?? [])];
      const skipped = (json.skipped ?? []).length;
      closeAddModal();
      showToast(
        `${(json.created ?? []).length} estudiante${(json.created ?? []).length !== 1 ? 's' : ''} inscrito${(json.created ?? []).length !== 1 ? 's' : ''}.` +
          (skipped > 0 ? ` ${skipped} omitido(s) por ya estar inscritos.` : ''),
        'success',
      );
    } catch (e) {
      bulkContextError = e instanceof Error ? e.message : 'Error de red.';
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
    <div class="rc-page">
      <nav class="rc-breadcrumb">
        <button onclick={backToSelector} class="rc-back-btn">
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

      <div class="rc-header">
        <div class="rc-header-left">
          <div>
            <h1 class="rc-title">{selectedCurso ? cursoDisplayName(selectedCurso) : `Curso ${activeCursoId}`}</h1>
            {#if selectedCurso}
              <p class="rc-subtitle">
                {selectedCurso.cod_curso}
                {#if selectedCurso.asignatura_nombre}&nbsp;·&nbsp;{selectedCurso.asignatura_nombre}{/if}
                {#if selectedCurso.carrera_nombre}&nbsp;·&nbsp;{selectedCurso.carrera_nombre}{/if}
                {#if selectedCurso.agno_real}&nbsp;·&nbsp;{selectedCurso.agno_real} S{selectedCurso.semestre_real}{/if}
              </p>
            {/if}
          </div>
          <div class="rc-capacity">
            <div class="capacity-ring">
              <span class="capacity-num">{inscritoCount}</span>
              <span class="capacity-sep">/</span>
              <span class="capacity-total">{totalCount}</span>
            </div>
            <span class="capacity-label">Inscritos activos</span>
          </div>
        </div>
        <div class="rc-header-actions">
          <a href="/admin/inscripciones_cursos/export/csv?id_curso={activeCursoId}" class="rc-btn-ghost">
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
          <button onclick={openAddModal} class="rc-btn-primary">
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
        <div class="rc-loading">
          <div class="rc-spinner"></div>
          <span>Cargando roster…</span>
        </div>
      {:else if rosterError}
        <div class="rc-empty">
          <p class="rc-error-msg">{rosterError}</p>
          <button onclick={() => loadRoster(activeCursoId!)} class="rc-btn-ghost">Reintentar</button>
        </div>
      {:else if roster.length === 0}
        <div class="rc-empty">
          <p class="rc-empty-msg">Este curso no tiene estudiantes inscritos aún.</p>
          <button onclick={openAddModal} class="rc-btn-primary">+ Agregar Estudiantes</button>
        </div>
      {:else}
        <div class="rc-table-wrap">
          <table class="rc-table">
            <thead>
              <tr>
                <th>Estudiante</th>
                <th>Usuario</th>
                <th>Fecha Inscripción</th>
                <th class="th-center">Intentos</th>
                <th class="th-center">Promedio</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {#each roster as item (item.id_inscripcion_curso)}
                {@const cfg = ESTADO_CFG[item.estado_inscripcion] ?? ESTADO_CFG['INSCRITO']}
                {@const transitions = TRANSITIONS[item.estado_inscripcion] ?? []}
                <tr class="rc-row {cfg.rowCls}" class:saving={item._saving}>
                  <td class="td-student">
                    <div class="student-avatar">{initials(item)}</div>
                    <span class="student-name">{studentName(item)}</span>
                  </td>
                  <td class="td-username">{studentUsername(item)}</td>
                  <td class="td-date">{item.fecha_inscripcion ?? '—'}</td>
                  <td class="td-center">{item.num_intento}</td>
                  <td class="td-center">{item.promedio_parcial != null ? item.promedio_parcial : '—'}</td>
                  <td class="td-estado">
                    {#if transitions.length > 0}
                      <div class="estado-wrapper">
                        <button
                          type="button"
                          class="estado-badge {cfg.cls}"
                          class:pulse={item._saving}
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
                          <div class="backdrop-close" onclick={() => (openMenuId = null)} role="presentation"></div>
                          <div class="estado-menu">
                            {#each transitions as t}
                              <button type="button" class="estado-menu-item" onclick={() => changeEstado(item, t.next)}>
                                <span class="t-icon">{t.icon}</span>{t.label}
                              </button>
                            {/each}
                          </div>
                        {/if}
                      </div>
                    {:else}
                      <span class="estado-badge {cfg.cls} no-dropdown">{cfg.label}</span>
                    {/if}
                  </td>
                  <td class="td-actions">
                    <button type="button" class="btn-delete" onclick={() => openDelete(item)} title="Eliminar permanentemente">
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
      <div class="modal-backdrop" onclick={closeAddModal} role="presentation"></div>
      <div class="modal-dialog" role="dialog" aria-modal="true" aria-label="Agregar Estudiantes">
        <div class="modal-header">
          <div>
            <h2 class="modal-title">Agregar Estudiantes</h2>
            <p class="modal-subtitle">{selectedCurso ? cursoDisplayName(selectedCurso) : ''}</p>
          </div>
          <button class="modal-close" onclick={closeAddModal} aria-label="Cerrar">
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

        <div class="modal-search-zone">
          <div class="modal-search-wrap">
            <svg
              class="search-icon"
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
            <input type="search" bind:value={studentSearch} placeholder="Buscar por nombre o usuario…" class="modal-search-input" />
          </div>
          {#if bulkContextError}
            <div class="bulk-error">
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

        <div class="modal-list-area">
          {#if loadingDisponibles}
            <div class="modal-loading">
              <div class="rc-spinner"></div>
              <span>Buscando estudiantes disponibles…</span>
            </div>
          {:else if disponibles.length === 0}
            <p class="modal-empty">Todos los estudiantes ya están inscritos en este curso.</p>
          {:else if filteredDisponibles.length === 0}
            <p class="modal-empty">Sin resultados para «{studentSearch}».</p>
          {:else}
            <label class="select-all-row">
              <input type="checkbox" checked={selectedIds.size === filteredDisponibles.length} onchange={toggleAll} />
              <span class="select-all-label">Seleccionar todos <small>({filteredDisponibles.length})</small></span>
            </label>
            <div class="student-check-list">
              {#each filteredDisponibles as e (e.id_estudiante)}
                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <label
                  class="student-check-row"
                  class:checked={selectedIds.has(e.id_estudiante)}
                  onclick={() => toggleSelect(e.id_estudiante)}
                  tabindex="0"
                >
                  <input
                    type="checkbox"
                    checked={selectedIds.has(e.id_estudiante)}
                    onchange={() => toggleSelect(e.id_estudiante)}
                    onclick={(ev) => ev.stopPropagation()}
                  />
                  <div class="student-avatar sm">
                    {((e.usuario?.nombre1?.[0] ?? '') + (e.usuario?.apellido1?.[0] ?? '')).toUpperCase() || '?'}
                  </div>
                  <div class="student-check-info">
                    <span class="student-check-name">{disponibleName(e)}</span>
                    <span class="student-check-user">{e.usuario?.username ?? ''}</span>
                  </div>
                </label>
              {/each}
            </div>
          {/if}
        </div>

        <div class="modal-footer">
          <span class="selected-counter">{selectedIds.size} seleccionado{selectedIds.size !== 1 ? 's' : ''}</span>
          <div class="modal-footer-actions">
            <button type="button" class="rc-btn-ghost" onclick={closeAddModal} disabled={submittingBulk}>Cancelar</button>
            <button type="button" class="rc-btn-primary" disabled={selectedIds.size === 0 || submittingBulk} onclick={submitBulk}>
              {#if submittingBulk}<span class="btn-spinner"></span>Inscribiendo…{:else}Confirmar Inscripción{/if}
            </button>
          </div>
        </div>
      </div>
    {/if}
  {:else}
    <!-- ═══════════════════════════════════ COURSE SELECTOR MODE ══ -->
    <div class="rc-page">
      <div class="sel-header">
        <h1 class="rc-title">Inscripciones de Cursos</h1>
        <p class="rc-subtitle">Selecciona un Curso Ofertado para gestionar su lista de alumnos.</p>
      </div>

      <div class="sel-search-wrap">
        <svg
          class="search-icon"
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
        <input type="search" bind:value={courseSearch} placeholder="Buscar curso por nombre, asignatura o carrera…" class="sel-search-input" />
      </div>

      {#if filteredCursos.length === 0}
        <p class="rc-empty-msg">{courseSearch ? `Sin resultados para «${courseSearch}».` : 'No hay cursos disponibles.'}</p>
      {:else}
        <div class="sel-grid">
          {#each filteredCursos as c (c.id_curso)}
            <button type="button" class="sel-card" onclick={() => selectCurso(c.id_curso)}>
              <div class="sel-card-top">
                <span class="sel-card-code">{c.cod_curso}</span>
                {#if c.agno_real}<span class="sel-card-period">{c.agno_real} S{c.semestre_real}</span>{/if}
              </div>
              <h3 class="sel-card-name">{cursoDisplayName(c)}</h3>
              {#if c.carrera_nombre}<p class="sel-card-meta">{c.carrera_nombre}</p>{/if}
              <div class="sel-card-arrow">
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
    <div class="toast toast-{toast.type}" role="status" aria-live="polite">
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

  <style>
    .rc-page {
      padding: 1.75rem 2rem;
      max-width: 1100px;
      margin: 0 auto;
    }

    /* Breadcrumb */
    .rc-breadcrumb {
      margin-bottom: 1rem;
    }
    .rc-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      font-size: 0.8125rem;
      color: #6b7280;
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      transition: color 0.15s;
    }
    .rc-back-btn:hover {
      color: #111827;
    }

    /* Header */
    .rc-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .rc-header-left {
      display: flex;
      align-items: flex-start;
      gap: 1.25rem;
      flex-wrap: wrap;
    }
    .rc-title {
      font-size: 1.625rem;
      font-weight: 700;
      color: #111827;
      margin: 0 0 0.2rem;
    }
    .rc-subtitle {
      font-size: 0.8125rem;
      color: #6b7280;
      margin: 0;
    }
    .rc-header-actions {
      display: flex;
      align-items: center;
      gap: 0.625rem;
      flex-shrink: 0;
    }

    /* Capacity pill */
    .rc-capacity {
      display: flex;
      flex-direction: column;
      align-items: center;
      background: #f0fdf4;
      border: 1.5px solid #bbf7d0;
      border-radius: 10px;
      padding: 0.5rem 0.875rem;
    }
    .capacity-ring {
      display: flex;
      align-items: baseline;
      gap: 0.125rem;
    }
    .capacity-num {
      font-size: 1.25rem;
      font-weight: 800;
      color: #16a34a;
    }
    .capacity-sep {
      font-size: 0.875rem;
      color: #9ca3af;
    }
    .capacity-total {
      font-size: 1rem;
      font-weight: 600;
      color: #374151;
    }
    .capacity-label {
      font-size: 0.6875rem;
      color: #6b7280;
      margin-top: 0.1rem;
    }

    /* Buttons */
    .rc-btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.55rem 1.125rem;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition:
        opacity 0.15s,
        transform 0.1s;
      box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
      text-decoration: none;
    }
    .rc-btn-primary:hover:not(:disabled) {
      opacity: 0.92;
      transform: translateY(-1px);
    }
    .rc-btn-primary:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }
    .rc-btn-ghost {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.55rem 1rem;
      background: white;
      color: #374151;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.15s;
    }
    .rc-btn-ghost:hover:not(:disabled) {
      background: #f9fafb;
      border-color: #9ca3af;
    }
    .rc-btn-ghost:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Loading */
    .rc-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      padding: 3rem;
      color: #6b7280;
    }
    .rc-spinner {
      width: 20px;
      height: 20px;
      border: 2.5px solid #e5e7eb;
      border-top-color: #3b82f6;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }
    .rc-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      padding: 4rem;
      text-align: center;
    }
    .rc-empty-msg {
      color: #6b7280;
      font-size: 0.9375rem;
    }
    .rc-error-msg {
      color: #dc2626;
      font-size: 0.875rem;
    }

    /* Table */
    .rc-table-wrap {
      overflow-x: auto;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }
    .rc-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.875rem;
    }
    .rc-table thead tr {
      background: #f8fafc;
    }
    .rc-table th {
      padding: 0.75rem 1rem;
      text-align: left;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border-bottom: 1px solid #e5e7eb;
    }
    .th-center {
      text-align: center;
    }
    .rc-table td {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }
    .rc-row:last-child td {
      border-bottom: none;
    }
    .rc-row.saving {
      opacity: 0.6;
    }
    .rc-row.row-dimmed td:not(.td-estado):not(.td-actions) {
      opacity: 0.55;
    }
    .rc-row.row-voided td:not(.td-estado):not(.td-actions) {
      opacity: 0.4;
      text-decoration: line-through;
    }

    .td-student {
      display: flex;
      align-items: center;
      gap: 0.625rem;
      min-width: 160px;
    }
    .student-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #dbeafe;
      color: #1d4ed8;
      font-size: 0.6875rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .student-avatar.sm {
      width: 28px;
      height: 28px;
      font-size: 0.625rem;
    }
    .student-name {
      font-weight: 500;
      color: #111827;
    }
    .td-username {
      font-family: 'Courier New', monospace;
      font-size: 0.8125rem;
      color: #6b7280;
    }
    .td-date {
      white-space: nowrap;
      color: #6b7280;
    }
    .td-center {
      text-align: center;
      color: #374151;
    }
    .td-estado {
      min-width: 140px;
    }
    .td-actions {
      width: 40px;
      text-align: center;
    }

    /* Estado badge */
    .estado-wrapper {
      position: relative;
      display: inline-block;
    }
    .estado-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.25rem 0.625rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: opacity 0.15s;
      white-space: nowrap;
    }
    .estado-badge.no-dropdown {
      cursor: default;
    }
    .estado-badge:disabled {
      cursor: wait;
    }
    .badge-inscrito {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-retirado {
      background: #fef3c7;
      color: #92400e;
    }
    .badge-anulado {
      background: #f3f4f6;
      color: #6b7280;
    }
    .badge-suspendido {
      background: #e0e7ff;
      color: #3730a3;
    }
    .badge-aprobado {
      background: #d1fae5;
      color: #065f46;
    }
    .badge-reprobado {
      background: #fee2e2;
      color: #991b1b;
    }

    .backdrop-close {
      position: fixed;
      inset: 0;
      z-index: 10;
    }
    .estado-menu {
      position: absolute;
      top: calc(100% + 6px);
      left: 0;
      z-index: 20;
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
      min-width: 185px;
      overflow: hidden;
    }
    .estado-menu-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      width: 100%;
      padding: 0.5rem 0.875rem;
      font-size: 0.8125rem;
      color: #374151;
      background: white;
      border: none;
      cursor: pointer;
      text-align: left;
      transition: background 0.1s;
    }
    .estado-menu-item:hover {
      background: #f3f4f6;
    }
    .t-icon {
      font-size: 0.875rem;
    }

    .btn-delete {
      padding: 0.375rem;
      color: #d1d5db;
      background: none;
      border: none;
      cursor: pointer;
      border-radius: 5px;
      display: inline-flex;
      align-items: center;
      transition: all 0.15s;
    }
    .btn-delete:hover {
      background: #fee2e2;
      color: #ef4444;
    }

    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
      z-index: 50;
      animation: fadeIn 0.15s ease;
    }
    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
    .modal-dialog {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 60;
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
      width: min(540px, calc(100vw - 2rem));
      max-height: 88dvh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      animation: slideUp 0.18s ease;
    }
    @keyframes slideUp {
      from {
        transform: translate(-50%, calc(-50% + 18px));
        opacity: 0;
      }
      to {
        transform: translate(-50%, -50%);
        opacity: 1;
      }
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 1.25rem 1.5rem 1rem;
      border-bottom: 1px solid #f1f5f9;
      flex-shrink: 0;
    }
    .modal-title {
      font-size: 1.0625rem;
      font-weight: 700;
      color: #111827;
      margin: 0;
    }
    .modal-subtitle {
      font-size: 0.8125rem;
      color: #6b7280;
      margin: 0.2rem 0 0;
    }
    .modal-close {
      padding: 0.375rem;
      border: none;
      background: transparent;
      border-radius: 6px;
      color: #9ca3af;
      cursor: pointer;
      transition: all 0.15s;
    }
    .modal-close:hover {
      background: #f3f4f6;
      color: #111827;
    }

    .modal-search-zone {
      padding: 0.875rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      flex-shrink: 0;
    }
    .modal-search-wrap {
      position: relative;
    }
    .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      pointer-events: none;
    }
    .modal-search-input {
      width: 100%;
      padding: 0.5rem 0.75rem 0.5rem 2.25rem;
      border: 1.5px solid #d1d5db;
      border-radius: 8px;
      font-size: 0.875rem;
      color: #111827;
      outline: none;
      box-sizing: border-box;
      transition: border-color 0.15s;
    }
    .modal-search-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
    }
    .bulk-error {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      margin-top: 0.625rem;
      padding: 0.5rem 0.75rem;
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 6px;
      font-size: 0.8125rem;
      color: #dc2626;
    }

    .modal-list-area {
      flex: 1;
      overflow-y: auto;
      padding: 0.5rem 0;
    }
    .modal-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.625rem;
      padding: 2rem;
      color: #6b7280;
      font-size: 0.875rem;
    }
    .modal-empty {
      padding: 2rem;
      text-align: center;
      color: #9ca3af;
      font-size: 0.875rem;
    }

    .select-all-row {
      display: flex;
      align-items: center;
      gap: 0.625rem;
      padding: 0.5rem 1.5rem;
      cursor: pointer;
      border-bottom: 1px solid #f1f5f9;
    }
    .select-all-label {
      font-size: 0.8125rem;
      font-weight: 600;
      color: #374151;
    }
    .select-all-label small {
      font-weight: 400;
      color: #9ca3af;
      margin-left: 0.25rem;
    }

    .student-check-list {
      display: flex;
      flex-direction: column;
    }
    .student-check-row {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.5rem 1.5rem;
      cursor: pointer;
      transition: background 0.1s;
    }
    .student-check-row:hover {
      background: #f8fafc;
    }
    .student-check-row.checked {
      background: #eff6ff;
    }
    .student-check-info {
      display: flex;
      flex-direction: column;
    }
    .student-check-name {
      font-size: 0.875rem;
      font-weight: 500;
      color: #111827;
    }
    .student-check-user {
      font-size: 0.75rem;
      color: #9ca3af;
      font-family: 'Courier New', monospace;
    }

    .modal-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.875rem 1.5rem;
      border-top: 1px solid #f1f5f9;
      flex-shrink: 0;
    }
    .selected-counter {
      font-size: 0.8125rem;
      color: #6b7280;
    }
    .modal-footer-actions {
      display: flex;
      gap: 0.625rem;
    }
    .btn-spinner {
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 2px solid rgba(255, 255, 255, 0.4);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    /* Course selector */
    .sel-header {
      margin-bottom: 1.25rem;
    }
    .sel-search-wrap {
      position: relative;
      margin-bottom: 1.5rem;
    }
    .sel-search-input {
      width: 100%;
      padding: 0.6rem 0.875rem 0.6rem 2.5rem;
      border: 1.5px solid #d1d5db;
      border-radius: 10px;
      font-size: 0.9375rem;
      color: #111827;
      outline: none;
      box-sizing: border-box;
      transition: border-color 0.15s;
    }
    .sel-search-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
    }
    .sel-search-wrap .search-icon {
      left: 0.875rem;
      top: 50%;
      transform: translateY(-50%);
    }
    .sel-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 0.875rem;
    }
    .sel-card {
      padding: 1rem;
      border: 1.5px solid #e5e7eb;
      border-radius: 12px;
      background: white;
      text-align: left;
      cursor: pointer;
      transition: all 0.15s;
      display: flex;
      flex-direction: column;
      gap: 0.375rem;
    }
    .sel-card:hover {
      border-color: #3b82f6;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
      transform: translateY(-2px);
    }
    .sel-card-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .sel-card-code {
      font-family: 'Courier New', monospace;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #2563eb;
      background: #eff6ff;
      padding: 0.1rem 0.4rem;
      border-radius: 4px;
    }
    .sel-card-period {
      font-size: 0.6875rem;
      color: #9ca3af;
    }
    .sel-card-name {
      font-size: 0.9375rem;
      font-weight: 600;
      color: #111827;
      margin: 0;
      line-height: 1.3;
    }
    .sel-card-meta {
      font-size: 0.75rem;
      color: #9ca3af;
      margin: 0;
    }
    .sel-card-arrow {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.75rem;
      color: #3b82f6;
      margin-top: 0.25rem;
      font-weight: 500;
    }

    /* Toast */
    .toast {
      position: fixed;
      bottom: 1.5rem;
      right: 1.5rem;
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 0.625rem;
      padding: 0.75rem 1.25rem;
      border-radius: 10px;
      font-size: 0.875rem;
      font-weight: 500;
      box-shadow:
        0 8px 24px rgba(0, 0, 0, 0.12),
        0 2px 8px rgba(0, 0, 0, 0.08);
      animation: toast-in 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .toast-success {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #166534;
    }
    .toast-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
    }
    @keyframes toast-in {
      from {
        opacity: 0;
        transform: translateY(12px) scale(0.96);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
  </style>
</AdminLayout>
