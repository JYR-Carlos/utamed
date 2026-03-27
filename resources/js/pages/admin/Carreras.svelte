<script lang="ts">
  /**
   * Página de administración de carreras.
   *
   * ORQUESTADOR de componentes modulares para gestión de carreras.
   * Gestiona:
   * - Estado de UI (modales, diálogos)
   * - Carga de datos (cascada facultad→departamento)
   * - Callbacks entre componentes
   * - Manejo de filtros y búsqueda
   *
   * Componentes modulares:
   * - CarreraList: tabla con toolbar de filtros
   * - CarreraForm: modal crear/editar con cascada
   * - CarreraDiscontinueConfirm: confirmación discontinuación
   *
   * Servicios:
   * - carreraApi: abstracción HTTP (CRUD + cascada)
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router, page, useForm } from '@inertiajs/svelte';
  import CarreraList from '@/modules/resources/carrera/components/carreraList.svelte';
  import CarreraForm from '@/modules/resources/carrera/components/carreraForm.svelte';
  import CarreraDiscontinueConfirm from '@/modules/resources/carrera/components/carreraDiscontinueConfirm.svelte';
  import {
    createCarrera,
    updateCarrera,
    discontinueCarrera,
    loadDepartamentos,
  } from '@/modules/resources/carrera/services/carreraApi';
  import { useFilteredList } from '@/lib/composables/useFilteredList';
  import { PAGINATION_OPTIONS, DEFAULT_PER_PAGE, STATUS_OPTIONS } from '@/constants/admin';
  import type { Carrera, Facultad, Departamento, PaginatedResponse } from '@/types/admin.types';

  interface Props {
    carreras: PaginatedResponse<Carrera>;
    facultades: Facultad[];
    filters: {
      search?: string;
      id_facultad?: number;
      id_departamento?: number;
      status?: string;
    };
  }

  let { carreras, facultades, filters }: Props = $props();

  // ────── Filtros y búsqueda ──────
  const { searchTerm, status, perPage, currentPage, setSearch, setStatus, setPerPage, goToPage } =
    useFilteredList({
      pathname: '/admin/carreras',
      defaultPerPage: DEFAULT_PER_PAGE,
    });

  // ────── Estado de UI ──────
  let uiState = $state({
    showFormModal: false,
    showConfirmDialog: false,
    editingCarrera: null as Carrera | null,
    discontinuingCarrera: null as Carrera | null,
  });

  let departamentos = $state<Departamento[]>([]);
  let toast = $state<{ msg: string; type: 'success' | 'error' } | null>(null);
  let toastTimer: ReturnType<typeof setTimeout> | null = null;

  const formData = useForm({
    nombre: '',
    jornada: '',
    sede: '',
    modalidad: '',
    id_departamento: 0,
    id_facultad: 0,
  });

  // ────── Gestión de Facultad → Departamento (cascada) ──────
  async function handleFacultadChange(id: number) {
    if (id > 0) {
      departamentos = await loadDepartamentos(id);
    } else {
      departamentos = [];
    }
  }

  // ────── Modales ──────
  function openCreateModal() {
    uiState.editingCarrera = null;
    $formData.reset();
    $formData.clearErrors();
    departamentos = [];
    uiState.showFormModal = true;
  }

  async function openEditModal(carrera: Carrera) {
    uiState.editingCarrera = carrera;
    $formData.defaults({
      nombre: carrera.nombre,
      jornada: carrera.jornada ?? '',
      sede: carrera.sede ?? '',
      modalidad: carrera.modalidad ?? '',
      id_departamento: carrera.id_departamento,
      id_facultad: carrera.id_facultad,
    });
    $formData.reset();
    // En edición, incluir departamentos eliminados
    departamentos = await loadDepartamentos(carrera.id_facultad, true);
    uiState.showFormModal = true;
  }

  function closeModal() {
    uiState.showFormModal = false;
    uiState.editingCarrera = null;
    departamentos = [];
  }

  // ────── Envío de formulario ──────
  function handleSubmit() {
    const isEditing = !!uiState.editingCarrera;

    if (isEditing) {
      updateCarrera(uiState.editingCarrera!.id_carrera, $formData.data() as any, {
        onSuccess: () => {
          closeModal();
        },
      });
    } else {
      createCarrera($formData.data() as any, {
        onSuccess: () => {
          closeModal();
        },
      });
    }
  }

  // ────── Confirmación discontinuar ──────
  function openDiscontinueDialog(carrera: Carrera) {
    if (carrera.fecha_eliminacion) return;
    uiState.discontinuingCarrera = carrera;
    uiState.showConfirmDialog = true;
  }

  function closeDiscontinueDialog() {
    uiState.showConfirmDialog = false;
    uiState.discontinuingCarrera = null;
  }

  function handleDiscontinue() {
    if (!uiState.discontinuingCarrera) return;
    discontinueCarrera(uiState.discontinuingCarrera.id_carrera, {
      onSuccess: () => {
        closeDiscontinueDialog();
      },
    });
  }

  // ────── Flash messages y toast ──────
  const flashSuccess = $derived(($page.props as any).flash?.success as string | undefined);
  const flashError = $derived(($page.props as any).flash?.error as string | undefined);

  $effect(() => {
    const msg = flashSuccess ?? flashError;
    if (!msg) return;
    if (toastTimer) clearTimeout(toastTimer);
    toast = { msg, type: flashSuccess ? 'success' : 'error' };
    toastTimer = setTimeout(() => (toast = null), 5000);
  });
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto">
    <!-- Page header -->
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 leading-tight">Carreras</h1>
        <p class="mt-1 text-sm text-gray-500">Gestión de carreras por departamento</p>
      </div>
      <button
        onclick={openCreateModal}
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm shadow-blue-200/60 transition-all active:scale-95 cursor-pointer"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          ><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg
        >
        Nueva Carrera
      </button>
    </div>

    <!-- Componente: Lista de Carreras (tabla con toolbar) -->
    <CarreraList
      {carreras}
      searchTerm={$searchTerm}
      status={$status}
      perPage={$perPage}
      paginationOptions={PAGINATION_OPTIONS}
      statusOptions={STATUS_OPTIONS}
      onSearchChange={setSearch}
      onSearch={() => setSearch($searchTerm)}
      onStatusChange={setStatus}
      onPerPageChange={setPerPage}
      onPageChange={goToPage}
      onEdit={openEditModal}
      onDiscontinue={openDiscontinueDialog}
    />
  </div>

  <!-- Componente: Modal Formulario Carrera -->
  <CarreraForm
    bind:isOpen={uiState.showFormModal}
    editingCarrera={uiState.editingCarrera}
    {departamentos}
    {facultades}
    {formData}
    isLoading={$formData.processing}
    onSubmit={handleSubmit}
    onClose={closeModal}
    onFacultadChange={handleFacultadChange}
  />

  <!-- Componente: Diálogo Confirmación Discontinuar -->
  <CarreraDiscontinueConfirm
    bind:isOpen={uiState.showConfirmDialog}
    carrera={uiState.discontinuingCarrera}
    isLoading={$formData.processing}
    onConfirm={handleDiscontinue}
    onCancel={closeDiscontinueDialog}
  />

  <!-- ── Toast ───────────────────────────────────────────────────────────────── -->
  {#if toast}
    <div
      role="status"
      aria-live="polite"
      class={`toast-slide fixed bottom-6 right-6 z-[9999] flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm font-medium max-w-sm shadow-xl ${toast.type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'}`}
    >
      {#if toast.type === 'success'}
        <svg
          class="w-4 h-4 shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
        >
      {:else}
        <svg
          class="w-4 h-4 shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line
            x1="12"
            y1="16"
            x2="12.01"
            y2="16"
          /></svg
        >
      {/if}
      <span class="flex-1">{toast.msg}</span>
      <button
        onclick={() => (toast = null)}
        class="shrink-0 opacity-50 hover:opacity-100 transition cursor-pointer"
        aria-label="Cerrar"
      >
        <svg
          class="w-3.5 h-3.5"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg
        >
      </button>
    </div>
  {/if}

  <style>
    /* Keyframe animations not available as Tailwind utilities */
    @keyframes modal-fade-in {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
    @keyframes slide-up {
      from {
        opacity: 0;
        transform: translateY(8px) scale(0.97);
      }
      to {
        opacity: 1;
        transform: none;
      }
    }
    .modal-fade {
      animation: modal-fade-in 0.15s ease both;
    }
    .modal-fade > * {
      animation: slide-up 0.2s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .toast-slide {
      animation: slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
    }
  </style>
</AdminLayout>
