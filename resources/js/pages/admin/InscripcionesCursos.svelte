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
   *
   * Sobre el roster vive además la sincronización con la Intranet, que es la
   * fuente de verdad del ramo: inscribe a quien apareció y retira a quien lo
   * botó. Es una acción destructiva en el sentido de que cambia el estado de
   * alumnos que hoy figuran inscritos, así que pide confirmación y muestra
   * nombre por nombre a quién tocó.
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

  // ── Sincronización con la Intranet ──────────────────────────────────────────

  /** Lo que devuelve /admin/cursos/{id}/sincronizar-inscripciones. */
  interface ResultadoSync {
    inscripcion: {
      inscritos_exitosamente: number;
      alumnos_creados: number;
      ya_inscritos: number;
      advertencias: string[];
    };
    retirados: Array<{ rut: string | null; nombre: string | null }>;
    reactivados: Array<{ rut: string | null; nombre: string | null }>;
    componentes_sin_respaldo: number;
    retiro_aplicado: boolean;
    advertencias: string[];
  }

  let sincronizando = $state(false);
  let confirmandoSync = $state(false);
  let resultadoSync = $state<ResultadoSync | null>(null);

  async function sincronizarConIntranet() {
    if (!activeCursoId) return;
    confirmandoSync = false;
    sincronizando = true;
    resultadoSync = null;

    try {
      const res = await fetch(`/admin/cursos/${activeCursoId}/sincronizar-inscripciones`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN':
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
      });
      const json = await res.json();

      if (!res.ok || !json.success) {
        throw new Error(json.message ?? 'No se pudo sincronizar con la Intranet.');
      }

      resultadoSync = json.data as ResultadoSync;
      // El roster acaba de cambiar en el servidor: se recarga en vez de
      // parchearlo a mano, porque la sincronización toca filas que esta
      // pantalla no sabe cuáles son hasta ver la respuesta.
      await loadRoster(activeCursoId);
      showToast(json.message, resultadoSync.retiro_aplicado ? 'success' : 'error');
    } catch (e) {
      showToast(e instanceof Error ? e.message : 'Error al sincronizar', 'error');
    } finally {
      sincronizando = false;
    }
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
    <!-- ── Sincronización con la Intranet ── -->
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-[#C9D6E6] bg-[#F5F8FC] px-4 py-3">
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-[#002F6C]">Sincronizar con la Intranet</p>
        <p class="mt-0.5 text-xs text-[#5A5E6E]">
          Inscribe a los alumnos que aparecen en el acta y deja en RETIRADO a los que ya no
          figuran. No borra inscripciones: el estado es reversible.
        </p>
      </div>

      {#if confirmandoSync}
        <div class="flex items-center gap-2">
          <span class="text-xs font-medium text-[#B45309]">
            Se dará de baja a quien ya no figure en la Intranet. ¿Continuar?
          </span>
          <button
            onclick={sincronizarConIntranet}
            class="rounded-lg bg-[#002F6C] px-3 py-2 text-xs font-semibold text-white transition hover:opacity-90"
          >
            Sí, sincronizar
          </button>
          <button
            onclick={() => (confirmandoSync = false)}
            class="rounded-lg border border-[#D6D9E0] bg-white px-3 py-2 text-xs font-semibold text-[#5A5E6E] transition hover:text-[#1A1A24]"
          >
            Cancelar
          </button>
        </div>
      {:else}
        <button
          onclick={() => (confirmandoSync = true)}
          disabled={sincronizando}
          class="shrink-0 rounded-lg bg-[#002F6C] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
        >
          {sincronizando ? 'Sincronizando…' : 'Sincronizar roster'}
        </button>
      {/if}
    </div>

    <!--
      Resultado nombre por nombre. Un contador («3 retirados») no permite
      revisar si la baja fue correcta, que es justo lo que alguien querría
      comprobar después de una acción que cambia el estado de sus alumnos.
    -->
    {#if resultadoSync}
      <div class="mb-4 space-y-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm">
        <p class="font-semibold text-[#1A1A24]">
          {resultadoSync.inscripcion.inscritos_exitosamente} inscrito(s) ·
          {resultadoSync.reactivados.length} reactivado(s) ·
          {resultadoSync.retirados.length} retirado(s)
        </p>

        {#if resultadoSync.retirados.length > 0}
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-[#B45309]">Retirados</p>
            <ul class="mt-1 space-y-0.5 text-xs text-[#5A5E6E]">
              {#each resultadoSync.retirados as alumno}
                <li>{alumno.nombre ?? '—'} <span class="text-[#98A0AE]">({alumno.rut ?? 's/rut'})</span></li>
              {/each}
            </ul>
          </div>
        {/if}

        {#if resultadoSync.reactivados.length > 0}
          <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Reactivados</p>
            <ul class="mt-1 space-y-0.5 text-xs text-[#5A5E6E]">
              {#each resultadoSync.reactivados as alumno}
                <li>{alumno.nombre ?? '—'} <span class="text-[#98A0AE]">({alumno.rut ?? 's/rut'})</span></li>
              {/each}
            </ul>
          </div>
        {/if}

        {#each [...resultadoSync.inscripcion.advertencias, ...resultadoSync.advertencias] as aviso}
          <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{aviso}</p>
        {/each}
      </div>
    {/if}

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
