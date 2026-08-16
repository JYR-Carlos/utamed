<script lang="ts">
  /**
   * Gestión de Inscripciones — paradigma Roster Management (centrado en el Curso).
   *
   * Modo A (Selector de Curso): cuando no hay id_curso en filtros, se muestra
   *   una cuadrícula de cursos para elegir.
   *
   * Modo B (Roster): cuando hay id_curso activo, se muestra la lista completa
   *   de alumnos con máquina de estados inline y acción "+ Agregar Estudiantes".
   *
   * Refactorizado para usar componentes modulares:
   * - CursoSelector: Grid de selección de curso
   * - RosterTable: Tabla con máquina de estados inline
   * - AddEstudiantesModal: Modal de inscripción masiva
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import { fly } from 'svelte/transition';
  import CursoSelector from '@/modules/resources/inscripcion/components/cursoSelector.svelte';
  import RosterTable from '@/modules/resources/inscripcion/components/rosterTable.svelte';
  import AddEstudiantesModal from '@/modules/resources/inscripcion/components/addEstudiantesModal.svelte';
  import InscripcionDeleteConfirm from '@/modules/resources/inscripcion/components/inscripcionDeleteConfirm.svelte';
  import {
    fetchRoster,
    patchEstado,
    deleteInscripcion,
  } from '@/modules/resources/inscripcion/services/inscripcionApi';
  import type {
    CursoItem,
    RosterItem,
    EstadoInscripcion,
  } from '@/modules/resources/inscripcion/types/inscripcion.types';
  import type { PaginatedResponse } from '@/types/admin.types';
  import type { BreadcrumbItem } from '@/types';

  interface Props {
    inscripciones: PaginatedResponse<RosterItem>;
    cursos: CursoItem[];
    filters: { search?: string; id_curso?: number | string; estado_inscripcion?: string };
  }

  let { inscripciones, cursos, filters }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inscripciones', href: '/admin/inscripciones_cursos' },
  ];

  // ── Mode detection ──────────────────────────────────────────────────────────
  const activeCursoId = $derived(filters.id_curso ? Number(filters.id_curso) : null);
  const isRosterMode = $derived(activeCursoId !== null);
  const selectedCurso = $derived(
    activeCursoId ? (cursos.find((c) => c.id_curso === activeCursoId) ?? null) : null,
  );

  // ── Roster state ────────────────────────────────────────────────────────────
  let roster = $state<RosterItem[]>([]);
  let loadingRoster = $state(false);
  let rosterError = $state('');

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
      roster = await fetchRoster(idCurso);
    } catch (e) {
      rosterError = e instanceof Error ? e.message : 'Error desconocido';
    } finally {
      loadingRoster = false;
    }
  }

  // ── Estado change (optimistic) ──────────────────────────────────────────────
  async function changeEstado(item: RosterItem, next: EstadoInscripcion) {
    const prev = item.estado_inscripcion;
    item.estado_inscripcion = next;
    item._saving = true;
    try {
      const json = await patchEstado(item.id_inscripcion_curso, next);
      const idx = roster.findIndex((r) => r.id_inscripcion_curso === item.id_inscripcion_curso);
      if (idx !== -1) roster[idx] = { ...roster[idx], ...(json.inscripcion ?? {}), _saving: false };
    } catch (e) {
      item.estado_inscripcion = prev;
      item._saving = false;
      const msg =
        (e as any)?.response?.data?.message ??
        (e instanceof Error ? e.message : 'Error al cambiar estado');
      showToast(msg, 'error');
    }
  }

  // ── Add Students ────────────────────────────────────────────────────────────
  let showAddModal = $state(false);

  function handleInscribed(created: RosterItem[]) {
    roster = [...roster, ...created];
  }

  // ── Delete ──────────────────────────────────────────────────────────────────
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
    deleteInscripcion(deletingItem.id_inscripcion_curso, {
      onSuccess: () => {
        roster = roster.filter(
          (r) => r.id_inscripcion_curso !== deletingItem!.id_inscripcion_curso,
        );
        showDeleteDialog = false;
        deletingItem = null;
        isDeleting = false;
      },
      onError: () => {
        isDeleting = false;
      },
    });
  }

  // ── Navigation ───────────────────────────────────────────────────────────────
  function selectCurso(id: number) {
    router.visit(`/admin/inscripciones_cursos?id_curso=${id}`, { preserveScroll: false });
  }

  function backToSelector() {
    router.visit('/admin/inscripciones_cursos');
  }

  // ── Toast ────────────────────────────────────────────────────────────────────
  let toast = $state<{ msg: string; type: 'success' | 'error' } | null>(null);
  let toastTimer: ReturnType<typeof setTimeout> | null = null;

  function showToast(msg: string, type: 'success' | 'error' = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast = { msg, type };
    toastTimer = setTimeout(() => (toast = null), 4500);
  }
</script>

<AdminLayout {breadcrumbs}>
  {#if isRosterMode}
    <RosterTable
      {roster}
      {loadingRoster}
      {rosterError}
      activeCursoId={activeCursoId!}
      {selectedCurso}
      onBack={backToSelector}
      onRetry={() => loadRoster(activeCursoId!)}
      onAddStudents={() => (showAddModal = true)}
      onChangeEstado={changeEstado}
      onDelete={openDelete}
    />

    <AddEstudiantesModal
      bind:isOpen={showAddModal}
      activeCursoId={activeCursoId!}
      {selectedCurso}
      onInscribed={handleInscribed}
      onToast={showToast}
    />
  {:else}
    <CursoSelector {cursos} onSelect={selectCurso} />
  {/if}

  <InscripcionDeleteConfirm
    bind:isOpen={showDeleteDialog}
    inscripcion={deletingItem}
    onConfirm={handleDelete}
    onCancel={() => {
      showDeleteDialog = false;
      deletingItem = null;
    }}
    isLoading={isDeleting}
  />

  {#if toast}
    <div
      role="status"
      aria-live="polite"
      transition:fly={{ y: 12, duration: 220 }}
      class="fixed bottom-6 right-6 z-[10000] flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium shadow-lg border {toast.type ===
      'success'
        ? 'bg-white border-emerald-200 text-emerald-800 shadow-emerald-100'
        : 'bg-white border-red-200 text-red-700 shadow-red-100'}"
    >
      {#if toast.type === 'success'}
        <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="text-emerald-600"><polyline points="20 6 9 17 4 12" /></svg
          >
        </div>
      {:else}
        <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center shrink-0">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="13"
            height="13"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="text-red-500"
          >
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
        </div>
      {/if}
      {toast.msg}
    </div>
  {/if}
</AdminLayout>
