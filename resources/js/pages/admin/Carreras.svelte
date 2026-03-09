<script lang="ts">
  /**
   * Página de administración de carreras.
   *
   * Tabla enriquecida con métricas calculadas al vuelo por el controlador:
   * - Nombre del registro (con ID y dimming si está discontinuada)
   * - Atributos: badges visuales de Sede | Jornada | Modalidad
   * - Departamento + Facultad padre
   * - Planes Activos (count de planes sin fecha_eliminacion)
   * - Estado RBAC (has_director): existe un usuario con rol "Jefe de Carrera" activo
   *   en el contexto de tipo "carrera" de esta carrera (via usuario_rol_asignacion).
   * - Estado del Registro: Activa / Discontinuada (basado en fecha_eliminacion)
   *
   * Reglas de Inmutabilidad:
   * - id_departamento e id_facultad son READ-ONLY en modo edición.
   *   Cambiarlos post-creación rompería uq_carrera_departamento y la herencia RBAC.
   *
   * Persistencia en URL:
   * - ?status=active (default) | ?status=all  → toggle de estado
   * - ?search=xxx | ?per_page=N | ?page=N
   *
   * Tablas relacionadas:
   * - administrativo.carrera, administrativo.departamento, administrativo.facultad
   * - administrativo.plan (count activos), usuario.usuario_rol_asignacion (RBAC)
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router, page, useForm } from '@inertiajs/svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import StatusBadge from '@/components/admin/StatusBadge.svelte';
  import AttriBadges from '@/components/admin/AttriBadges.svelte';
  import PaginationControls from '@/components/admin/PaginationControls.svelte';
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

  // ────── Composable para filtros ──────
  const { searchTerm, status, perPage, currentPage, setSearch, setStatus, setPerPage, goToPage, navigate } = useFilteredList({
    pathname: '/admin/carreras',
    defaultPerPage: DEFAULT_PER_PAGE,
  });

  // ────── Estado de UI agrupado ──────
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

  async function loadDepartamentos(id_facultad: number, includeDeleted = false) {
    if (!id_facultad) {
      departamentos = [];
      return;
    }
    try {
      const r = await fetch(`/admin/facultades/${id_facultad}/departamentos`);
      let depts = await r.json();

      // En modo creación, filtrar solo departamentos activos
      if (!includeDeleted) {
        depts = depts.filter((dept: Departamento) => !dept.fecha_eliminacion);
      }

      departamentos = depts;
    } catch {
      departamentos = [];
    }
  }

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
    await loadDepartamentos(carrera.id_facultad, true); // Incluir eliminados en modo edición
    uiState.showFormModal = true;
  }

  function handleSubmit() {
    const url = uiState.editingCarrera ? `/admin/carreras/${uiState.editingCarrera.id_carrera}` : '/admin/carreras';
    const opts = {
      onSuccess: () => {
        uiState.showFormModal = false;
        uiState.editingCarrera = null;
        departamentos = [];
      },
    };
    if (uiState.editingCarrera) {
      $formData.put(url, opts);
    } else {
      $formData.post(url, opts);
    }
  }

  // Reload departamentos only in create mode (faculty is read-only when editing)
  $effect(() => {
    if ($formData.id_facultad && !uiState.editingCarrera) {
      loadDepartamentos($formData.id_facultad);
    }
  });

  function openDiscontinuarDialog(carrera: Carrera) {
    if (carrera.fecha_eliminacion) return;
    uiState.discontinuingCarrera = carrera;
    uiState.showConfirmDialog = true;
  }

  function handleDiscontinuar() {
    if (!uiState.discontinuingCarrera) return;
    $formData.delete(`/admin/carreras/${uiState.discontinuingCarrera.id_carrera}`, {
      onSuccess: () => {
        uiState.showConfirmDialog = false;
        uiState.discontinuingCarrera = null;
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
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <!-- ── Page header ─────────────────────────────────────────────────────── -->
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
          stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg
        >
        Nueva Carrera
      </button>
    </div>

    <!-- ── Table card ──────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- Toolbar -->
      <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3 bg-gray-50/60">
        <!-- Search -->
        <div class="flex gap-2 flex-1 min-w-[200px]">
          <input
            type="text"
            bind:value={$searchTerm}
            placeholder="Buscar carrera..."
            onkeydown={(e) => e.key === 'Enter' && setSearch($searchTerm)}
            class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          />
          <button
            onclick={() => setSearch($searchTerm)}
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition cursor-pointer"
          >
            Buscar
          </button>
        </div>

        <!-- Status toggle (URL-persistent) -->
        <div class="flex rounded-lg border border-gray-200 overflow-hidden text-[13px] font-medium shadow-sm">
          <button
            onclick={() => setStatus(STATUS_OPTIONS.ACTIVE)}
            class={`px-3 py-1.5 transition cursor-pointer ${$status === STATUS_OPTIONS.ACTIVE ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
          >
            Solo Activos
          </button>
          <button
            onclick={() => setStatus(STATUS_OPTIONS.ALL)}
            class={`px-3 py-1.5 border-l border-gray-200 transition cursor-pointer ${$status === STATUS_OPTIONS.ALL ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
          >
            Todos
          </button>
        </div>

        <!-- Per-page -->
        <select
          value={$perPage}
          onchange={(e) => setPerPage(Number((e.target as HTMLSelectElement).value))}
          class="px-2 py-1.5 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 cursor-pointer focus:outline-none focus:border-blue-400 transition"
        >
          {#each PAGINATION_OPTIONS as opt}
            <option value={opt}>{opt} por página</option>
          {/each}
        </select>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">Nombre</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">Atributos</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap">Departamento</th>
              <th class="px-4 py-3 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap">Planes Activos</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 whitespace-nowrap">Estado RBAC</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">Estado</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">Acciones</th>
            </tr>
          </thead>
          <tbody>
            {#if carreras.data.length === 0}
              <tr>
                <td colspan="7" class="px-4 py-14 text-center text-gray-400 text-sm"> No se encontraron resultados </td>
              </tr>
            {:else}
              {#each carreras.data as carrera}
                <tr
                  class={`border-b border-gray-100 last:border-0 transition-colors ${carrera.fecha_eliminacion ? 'opacity-60 bg-gray-50/40' : 'bg-white hover:bg-blue-50/20'}`}
                >
                  <!-- Nombre + ID -->
                  <td class="px-4 py-3 align-middle">
                    <div
                      class={`font-semibold text-[13px] leading-snug ${carrera.fecha_eliminacion ? 'text-gray-400 line-through' : 'text-gray-900'}`}
                    >
                      {carrera.nombre}
                    </div>
                    <div class="text-[11px] text-gray-400 font-mono mt-0.5">#{carrera.id_carrera}</div>
                  </td>

                  <!-- Atributos: sede | jornada | modalidad badges -->
                  <td class="px-4 py-3 align-middle">
                    <AttriBadges sede={carrera.sede} jornada={carrera.jornada} modalidad={carrera.modalidad} />
                  </td>

                  <!-- Departamento + Facultad -->
                  <td class="px-4 py-3 align-middle">
                    <div
                      class={`text-[13px] font-medium leading-snug ${carrera.departamento?.fecha_eliminacion ? 'line-through text-gray-400' : 'text-gray-900'}`}
                    >
                      {carrera.departamento?.nombre ?? '—'}
                      {#if carrera.departamento?.fecha_eliminacion}
                        <span class="text-[10px] font-semibold uppercase tracking-wide ml-1 px-1.5 py-0.5 rounded bg-gray-200 text-gray-600">
                          Eliminado
                        </span>
                      {/if}
                    </div>
                    {#if carrera.departamento?.facultad?.nombre}
                      <div class="text-[11px] text-gray-400 mt-0.5 leading-snug">
                        {carrera.departamento.facultad.nombre}
                      </div>
                    {/if}
                  </td>

                  <!-- Planes Activos count -->
                  <td class="px-4 py-3 align-middle text-center">
                    {#if (carrera.planes_activos_count ?? 0) > 0}
                      <span
                        class="inline-flex items-center justify-center min-w-[1.8rem] h-6 px-2 rounded-full bg-green-100 text-green-700 text-xs font-bold"
                      >
                        {carrera.planes_activos_count}
                      </span>
                    {:else}
                      <span
                        class="inline-flex items-center justify-center min-w-[1.8rem] h-6 px-2 rounded-full bg-gray-100 text-gray-400 text-xs font-bold"
                      >
                        0
                      </span>
                    {/if}
                  </td>

                  <!-- Estado RBAC -->
                  <td class="px-4 py-3 align-middle">
                    {#if carrera.has_director}
                      <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-green-50 text-green-700 text-[11px] font-semibold border border-green-100 leading-tight whitespace-nowrap"
                      >
                        <svg
                          class="w-3 h-3 shrink-0"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"><polyline points="20 6 9 17 4 12" /></svg
                        >
                        Jefe de Carrera
                      </span>
                    {:else}
                      <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 text-[11px] font-semibold border border-amber-200 leading-tight whitespace-nowrap"
                      >
                        <svg
                          class="w-3 h-3 shrink-0"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg
                        >
                        Sin Jefe de Carrera
                      </span>
                    {/if}
                  </td>

                  <!-- Estado del registro -->
                  <td class="px-4 py-3 align-middle">
                    <StatusBadge
                      status={carrera.fecha_eliminacion ? 'discontinued' : 'active'}
                      label={carrera.fecha_eliminacion ? 'Discontinuada' : 'Activa'}
                    />
                  </td>

                  <!-- Acciones -->
                  <td class="px-4 py-3 align-middle">
                    <div class="flex items-center gap-1.5 whitespace-nowrap">
                      <button
                        onclick={() => openEditModal(carrera)}
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md text-[12px] font-medium transition-colors cursor-pointer"
                        title="Editar campos mutables"
                      >
                        <svg
                          class="w-3.5 h-3.5"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          ><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path
                            d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                          /></svg
                        >
                        Editar
                      </button>
                      {#if !carrera.fecha_eliminacion}
                        <div class="w-px h-4 bg-gray-200 shrink-0"></div>
                        <button
                          onclick={() => openDiscontinuarDialog(carrera)}
                          class="inline-flex items-center gap-1 px-2.5 py-1.5 text-amber-600 hover:text-amber-800 hover:bg-amber-50 rounded-md text-[12px] font-medium transition-colors border border-transparent hover:border-amber-200 cursor-pointer"
                          title="Discontinuar carrera"
                        >
                          <svg
                            class="w-3.5 h-3.5"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="4.93" y1="4.93" x2="19.07" y2="19.07" /></svg
                          >
                          Discontinuar
                        </button>
                      {:else}
                        <span class="px-2.5 py-1.5 text-gray-300 text-[11px] italic select-none">Discontinuada</span>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <PaginationControls
        currentPage={carreras.current_page}
        lastPage={carreras.last_page}
        from={carreras.from}
        to={carreras.to}
        total={carreras.total}
        onPageChange={goToPage}
      />
    </div>
  </div>

  <!-- ── Create / Edit Modal ─────────────────────────────────────────────────── -->
  <FormModal
    bind:isOpen={uiState.showFormModal}
    title={uiState.editingCarrera ? 'Editar Carrera' : 'Nueva Carrera'}
    onClose={() => {
      uiState.showFormModal = false;
      uiState.editingCarrera = null;
      departamentos = [];
    }}
    onSubmit={handleSubmit}
    isLoading={$formData.processing}
  >
    <!-- Nombre -->
    <div class="mb-4">
      <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1.5">
        Nombre <span class="text-red-500">*</span>
      </label>
      <input
        id="nombre"
        type="text"
        bind:value={$formData.nombre}
        class={`w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-100 transition ${$formData.errors.nombre ? 'border-red-400 focus:border-red-400' : 'border-gray-300 focus:border-blue-400'}`}
        placeholder="Ej: Medicina"
        required
      />
      {#if $formData.errors.nombre}
        <p class="mt-1 text-xs text-red-500">{$formData.errors.nombre}</p>
      {/if}
    </div>

    <!-- Jornada + Sede -->
    <div class="grid grid-cols-2 gap-3 mb-4">
      <div>
        <label for="jornada" class="block text-sm font-medium text-gray-700 mb-1.5">Jornada</label>
        <input
          id="jornada"
          type="text"
          bind:value={$formData.jornada}
          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          placeholder="Ej: Diurna"
        />
      </div>
      <div>
        <label for="sede" class="block text-sm font-medium text-gray-700 mb-1.5">Sede</label>
        <input
          id="sede"
          type="text"
          bind:value={$formData.sede}
          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
          placeholder="Ej: Arica"
        />
      </div>
    </div>

    <!-- Modalidad -->
    <div class="mb-4">
      <label for="modalidad" class="block text-sm font-medium text-gray-700 mb-1.5">Modalidad</label>
      <input
        id="modalidad"
        type="text"
        bind:value={$formData.modalidad}
        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition"
        placeholder="Ej: Presencial"
      />
    </div>

    <!-- Facultad — read-only when editing -->
    <div class="mb-4">
      <label for="facultad" class="block text-sm font-medium text-gray-700 mb-1.5">
        Facultad <span class="text-red-500">*</span>
      </label>
      {#if uiState.editingCarrera}
        <div class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-between">
          <span>{uiState.editingCarrera.departamento?.facultad?.nombre ?? '—'}</span>
          <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded">Inmutable</span>
        </div>
      {:else}
        <select
          id="facultad"
          bind:value={$formData.id_facultad}
          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition bg-white"
          required
        >
          <option value={0}>Seleccione una facultad</option>
          {#each facultades as facultad}
            <option value={facultad.id_facultad}>{facultad.nombre}</option>
          {/each}
        </select>
      {/if}
    </div>

    <!-- Departamento — read-only when editing -->
    <div class="mb-2">
      <label for="departamento" class="block text-sm font-medium text-gray-700 mb-1.5">
        Departamento <span class="text-red-500">*</span>
      </label>
      {#if uiState.editingCarrera}
        <div class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-600 flex items-center justify-between">
          <span>{uiState.editingCarrera.departamento?.nombre ?? '—'}</span>
          <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded">Inmutable</span>
        </div>
        <p class="mt-1 text-[11px] text-gray-400">
          La jerarquía estructural no puede modificarse después de la creación para preservar la integridad RBAC.
        </p>
      {:else}
        <select
          id="departamento"
          bind:value={$formData.id_departamento}
          class={`w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition bg-white ${!$formData.id_facultad ? 'opacity-50 cursor-not-allowed' : ''}`}
          disabled={!$formData.id_facultad}
          required
        >
          <option value={0}>Seleccione un departamento</option>
          {#each departamentos as dep}
            <option value={dep.id_departamento} disabled={!!dep.fecha_eliminacion && !uiState.editingCarrera}>
              {dep.nombre}{dep.fecha_eliminacion ? ' (Eliminado)' : ''}
            </option>
          {/each}
        </select>
      {/if}
    </div>
  </FormModal>

  <!-- ── Discontinuar Modal ──────────────────────────────────────────────────── -->
  <ConfirmationModal
    isOpen={uiState.showConfirmDialog && uiState.discontinuingCarrera !== null}
    icon="warning"
    title="Discontinuar Carrera"
    message={uiState.discontinuingCarrera?.nombre || ''}
    isLoading={$formData.processing}
    isDangerous={true}
    confirmLabel="Confirmar Discontinuación"
    onCancel={() => {
      uiState.showConfirmDialog = false;
      uiState.discontinuingCarrera = null;
    }}
    onConfirm={handleDiscontinuar}
  >
    <div class="flex gap-3 bg-blue-50 border border-blue-100 rounded-lg p-4 text-[13px] text-blue-800 leading-relaxed">
      <svg
        class="shrink-0 mt-0.5 text-blue-500"
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg
      >
      <div>
        <p class="font-semibold mb-1">Impacto de esta acción</p>
        <p>
          Esta acción establecerá una <strong>fecha de eliminación (Soft Delete)</strong>. La carrera no admitirá nuevos planes, pero el historial
          académico de los estudiantes actuales se mantendrá intacto. La carrera seguirá visible en el sistema con estado
          <strong>Discontinuada</strong>.
        </p>
      </div>
    </div>
  </ConfirmationModal>

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
          ><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg
        >
      {/if}
      <span class="flex-1">{toast.msg}</span>
      <button onclick={() => (toast = null)} class="shrink-0 opacity-50 hover:opacity-100 transition cursor-pointer" aria-label="Cerrar">
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
