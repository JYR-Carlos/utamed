<script lang="ts">
  /**
   * Página de administración de cursos ofertados (FASE 2 REFACTORIZADO)
   *
   * Gestión completa de cursos que se ofrecen en periodos académicos.
   * Refactorizado para usar componentes modulares:
   * - cursoList: Tabla de cursos
   * - cursoForm: Modal para crear/editar
   * - seccionForm: Modal para secciones
   * - cursoDeleteConfirm: Confirmación de eliminación
   *
   * Mantiene:
   * - CourseTeamModal: Gestión de equipos
   * - SyllabusModal: Gestión de programas
   * - CursoWizardModal: Wizard para crear cursos
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import SyllabusTypeSelector from '@/components/custom/admin/SyllabusTypeSelector.svelte';
  import CursoWizardModal from '@/components/custom/admin/CursoWizardModal.svelte';
  // ── Componentes modulares FASE 1 ──
  import CursoForm from '@/modules/resources/curso/components/cursoForm.svelte';
  import CursoDeleteConfirm from '@/modules/resources/curso/components/cursoDeleteConfirm.svelte';
  import CursoListAdmin from '@/modules/resources/curso/components/cursoListAdmin.svelte';
  import SeccionForm from '@/modules/resources/curso/components/seccionForm.svelte';
  import axios, { AxiosError } from 'axios';
  import type {
    Curso,
    Asignatura,
    Carrera,
    Plan,
    Docente,
    PaginatedResponse,
    CursoFormData,
    Seccion,
    TipoSeccion,
    Programa,
  } from '@/types/admin.types';

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Cursos paginados */
    cursos: PaginatedResponse<Curso>;
    /** Asignaturas disponibles para crear cursos */
    asignaturas: Asignatura[];
    /** Planes disponibles para filtrar/asignar cursos */
    planes: Plan[];
    /** Carreras disponibles (para el wizard) */
    carreras: Carrera[];
    /** Roles disponibles para asignar a miembros del equipo */
    availableRoles: any[];
    /** Permisos especiales disponibles */
    availablePermissions: Record<string, any[]>;
    /** Filtros aplicados */
    filters: { search?: string; id_asignatura?: number };
    /** Tipos de secciones disponibles (Cátedra, Problemas, etc.) */
    tipos_seccion: TipoSeccion[];
  }

  let {
    cursos,
    asignaturas,
    planes,
    carreras = [],
    filters,
    availableRoles = [],
    availablePermissions = {},
    tipos_seccion = [],
  }: Props = $props();

  let showModal = $state(false);
  let showWizardModal = $state(false);
  let showDeleteDialog = $state(false);
  let showTeamModal = $state(false);
  let showInscriptionModal = $state(false);
  let showSyllabusTypeSelector = $state(false);
  let showSyllabusModal = $state(false);
  let isLoading = $state(false);
  let editingCurso = $state<Curso | null>(null);
  let deletingCurso = $state<Curso | null>(null);
  let managingTeamCurso = $state<Curso | null>(null);
  let syllabusTargetCurso = $state<Curso | null>(null);
  let selectedSyllabusType = $state<'simplified' | 'combined' | 'complete' | null>(null);
  let selectedCursoForInscription = $state<Curso | null>(null);
  let docentes = $state<Docente[]>([]);
  let availableAsignaturas = $state<Asignatura[]>([]);
  let loadingAsignaturas = $state(false);
  let editingDocenteId = $state<number | undefined>(undefined);

  // ── Estado de CursoList ────────────────────────────────────────────────────
  let searchTerm = $state(getInitialSearch());
  let status = $state('active');
  let perPage = $state(15);

  function getInitialSearch() {
    return filters?.search || '';
  }

  // ── Quick-View Modal ──────────────────────────────────────────────────────
  let quickViewItem = $state<Curso | null>(null);
  let quickViewType = $state<'asignatura' | 'docente' | null>(null);

  function openQuickView(item: Curso, type: 'asignatura' | 'docente') {
    quickViewItem = item;
    quickViewType = type;
  }

  function closeQuickView() {
    quickViewItem = null;
    quickViewType = null;
  }

  // ── Derived: Detect existing syllabus type from curso program ────────────────
  let existingSyllabusType = $derived.by(() => {
    // Prefer programa_estado on the list row (simpler, always present)
    if (syllabusTargetCurso?.programa_estado === 'BASICO_COMPLETO') {
      return 'BASICO' as const;
    }
    if (
      syllabusTargetCurso?.programa_estado === 'COMPLETO' ||
      syllabusTargetCurso?.programa_estado === 'APROBADO' ||
      syllabusTargetCurso?.programa_estado === 'ENVIADO'
    ) {
      return 'COMPLETO' as const;
    }
    // Fallback: try to get tipo_syllabus from embedded program data
    const programa = (syllabusTargetCurso as any)?.programa;
    const tipoSyllabus = programa?.data_syllabus?.metadata?.tipo_syllabus;
    if (tipoSyllabus === 'BASICO' || tipoSyllabus === 'COMPLETO') {
      return tipoSyllabus as 'BASICO' | 'COMPLETO';
    }
    return null;
  });

  // Toast notification
  let toast = $state<{ msg: string; type: 'success' | 'error' } | null>(null);
  let toastTimeout: ReturnType<typeof setTimeout> | null = null;

  function showToast(msg: string, type: 'success' | 'error' = 'success') {
    if (toastTimeout) clearTimeout(toastTimeout);
    toast = { msg, type };
    toastTimeout = setTimeout(() => {
      toast = null;
    }, 4500);
  }

  let currentSecciones = $state<Seccion[]>([]);
  let newSeccionData = $state({
    id_tipo_seccion: undefined,
    id_docente: undefined,
  });
  let loadingSecciones = $state(false);
  let showEditDocente = $state(false);
  let editingSeccion = $state<Seccion | null>(null);

  let formData = $state<CursoFormData>({
    id_asignatura: 0,
    id_plan: 0,
    cod_curso: 0,
    nombre: '',
    fecha_inicio: '',
    numero_semestre: undefined,
    agno_real: new Date().getFullYear(),
    semestre_real: 1,
  });

  const columns = [
    { key: 'id_curso', label: 'ID' },
    { key: 'cod_curso', label: 'Código' },
    { key: 'asignatura_nombre', label: 'Asignatura' },
    { key: 'carrera_nombre', label: 'Carrera' },
    { key: 'numero_semestre', label: 'Semestre' },
    { key: 'docente_nombre', label: 'Docente' },
  ];

  // ═══════════════════════════════════════════════════════════════════════════
  // HELPERS
  // ═══════════════════════════════════════════════════════════════════════════

  async function loadDocentes() {
    try {
      const response = await fetch('/api/docentes', {
        headers: {
          Accept: 'application/json',
        },
      });

      if (!response.ok) {
        docentes = [];
        return;
      }

      const data = await response.json();
      if (!data.data) {
        if (Array.isArray(data)) {
          docentes = data.map((docente: any) => ({
            id_docente: docente.id_docente,
            nombre_completo: docente.nombre_completo || docente.usuario?.nombre1 || 'Sin nombre',
            email: docente.email,
            grado: docente.grado,
            titulo: docente.titulo,
            cargo: docente.cargo,
            usuario: docente.usuario,
          }));
        } else {
          docentes = [];
        }
      } else {
        docentes = (data.data || []).map((docente: any) => ({
          id_docente: docente.id_docente,
          nombre_completo: docente.nombre_completo || docente.usuario?.nombre1 || 'Sin nombre',
          email: docente.email,
          grado: docente.grado,
          titulo: docente.titulo,
          cargo: docente.cargo,
          usuario: docente.usuario,
        }));
      }
    } catch (error) {
      console.error('Error loading docentes:', error);
      docentes = [];
    }
  }

  async function loadAsignaturasByPlan(planId: number) {
    if (!planId || planId === 0) {
      availableAsignaturas = [];
      return;
    }
    loadingAsignaturas = true;
    try {
      const response = await fetch(`/admin/planes/${planId}/asignaturas-disponibles`);
      if (response.ok) {
        availableAsignaturas = await response.json();
      } else {
        availableAsignaturas = [];
      }
    } catch (error) {
      console.error('Error loading asignaturas:', error);
      availableAsignaturas = [];
    } finally {
      loadingAsignaturas = false;
    }
  }

  async function loadSecciones(cursoId: number) {
    loadingSecciones = true;
    try {
      const response = await axios.get(`/admin/cursos/${cursoId}`);
      if (response.data.secciones) {
        currentSecciones = response.data.secciones;
      }
    } catch (error) {
      console.error('Error loading secciones:', error);
    } finally {
      loadingSecciones = false;
    }
  }

  async function deleteSeccion(cursoId: number, seccionId: number) {
    if (!confirm('¿Estás seguro de eliminar esta sección?')) return;
    try {
      await axios.delete(`/admin/cursos/secciones/${seccionId}`);
      await loadSecciones(cursoId);
    } catch (error) {
      console.error('Error deleting seccion:', error);
      const message =
        error instanceof AxiosError
          ? error.response?.data?.error || error.message
          : 'Error desconocido';
      alert('Error al eliminar sección: ' + message);
    }
  }

  function openCreateModal() {
    showWizardModal = true;
  }

  function openEditModal(curso: Curso) {
    editingCurso = curso;
    const id_asignatura = curso.asignacionPlan?.id_asignatura || 0;
    const id_plan = curso.asignacionPlan?.id_plan || 0;

    formData = {
      id_asignatura: id_asignatura,
      id_plan: id_plan,
      cod_curso: curso.cod_curso,
      nombre: curso.nombre || '',
      fecha_inicio: curso.fecha_inicio || '',
      numero_semestre: curso.numero_semestre,
      agno_real: new Date().getFullYear(),
      semestre_real: 1,
    };

    if (id_plan) {
      loadAsignaturasByPlan(id_plan);
    }
    loadDocentes();
    loadSecciones(curso.id_curso);
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingCurso = null;
  }

  function handleSubmit() {
    if (formData.id_asignatura === 0 || formData.id_plan === 0 || !formData.cod_curso) {
      alert('Por favor complete los campos obligatorios (*)');
      return;
    }

    isLoading = true;
    if (editingCurso) {
      router.put(`/admin/cursos/${editingCurso.id_curso}`, formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
          showToast('Curso actualizado', 'success');
        },
        onError: () => {
          isLoading = false;
          showToast('Error al actualizar curso', 'error');
        },
      });
    } else {
      router.post('/admin/cursos', formData, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
          showToast('Curso creado', 'success');
        },
        onError: () => {
          isLoading = false;
          showToast('Error al crear curso', 'error');
        },
      });
    }
  }

  function openDeleteDialog(curso: Curso) {
    deletingCurso = curso;
    showDeleteDialog = true;
  }

  function closeDeleteDialog() {
    showDeleteDialog = false;
    deletingCurso = null;
  }

  function handleDelete() {
    if (!deletingCurso) return;
    isLoading = true;
    router.delete(`/admin/cursos/${deletingCurso.id_curso}`, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
        showToast('Curso eliminado', 'success');
      },
      onError: () => {
        isLoading = false;
        showToast('Error al eliminar curso', 'error');
      },
    });
  }

  function openTeamModal(curso: Curso) {
    managingTeamCurso = curso;
    showTeamModal = true;
  }

  function closeTeamModal() {
    showTeamModal = false;
    managingTeamCurso = null;
  }

  function handleWizardSubmit(data: CursoFormData & { id_docente_sugerido?: number }) {
    isLoading = true;
    router.post('/admin/cursos', data as any, {
      onSuccess: () => {
        showWizardModal = false;
        isLoading = false;
        showToast('Curso creado exitosamente', 'success');
      },
      onError: () => {
        isLoading = false;
        showToast('Error al crear curso', 'error');
      },
    });
  }

  function openSyllabusModal(curso: Curso) {
    if (curso.has_programa && curso.programa_estado && curso.programa_estado !== 'BORRADOR') {
      router.visit(`/admin/cursos/${curso.id_curso}/programa/revisar`, { method: 'get' });
    } else {
      syllabusTargetCurso = curso;
      showSyllabusTypeSelector = true;
    }
  }

  function handleSyllabusTypeSelect(type: 'simplified' | 'combined' | 'complete') {
    selectedSyllabusType = type;
    showSyllabusTypeSelector = false;
    showSyllabusModal = true;
  }

  function closeSyllabusTypeSelector() {
    showSyllabusTypeSelector = false;
  }

  function closeSyllabusModal() {
    showSyllabusModal = false;
    syllabusTargetCurso = null;
    selectedSyllabusType = null;
  }

  function handleSyllabusSuccess(programa: Programa) {
    if (syllabusTargetCurso) {
      const idx = cursos.data.findIndex((c) => c.id_curso === syllabusTargetCurso!.id_curso);
      if (idx !== -1) {
        cursos.data[idx] = {
          ...cursos.data[idx],
          has_programa: true,
          id_programa: programa.id_programa,
        };
      }
    }
    closeSyllabusModal();
    showToast('Programa generado exitosamente', 'success');
  }
</script>

<AdminLayout>
  <div>
    <div class="flex justify-between items-start mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Cursos</h1>
        <p class="text-sm text-gray-500">Gestión de cursos y asignación de docentes</p>
      </div>
      <button
        onclick={openCreateModal}
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Nuevo Curso
      </button>
    </div>

    <!-- Tabla de cursos usando componente modular -->
    <CursoListAdmin
      {cursos}
      {searchTerm}
      {status}
      {perPage}
      onSearchChange={(term: string) => {
        searchTerm = term;
      }}
      onSearch={() => {
        router.get('/admin/cursos', { search: searchTerm, status });
      }}
      onStatusChange={(newStatus: string) => {
        status = newStatus;
        router.get('/admin/cursos', { search: searchTerm, status: newStatus });
      }}
      onPerPageChange={(value: number) => {
        perPage = value;
        router.get('/admin/cursos', { search: searchTerm, status, per_page: value });
      }}
      onPageChange={(page: number) => {
        router.get('/admin/cursos', { search: searchTerm, status, per_page: perPage, page });
      }}
      onCreateNew={openCreateModal}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      onTeam={openTeamModal}
      onSyllabus={openSyllabusModal}
    />
  </div>

  <!-- ── Quick-View Modal ────────────────────────────────────────────────── -->
  {#if quickViewItem && quickViewType}
    <!-- Backdrop -->
    <button
      class="fixed inset-0 bg-black/40 z-40 cursor-default"
      onclick={closeQuickView}
      aria-label="Cerrar"
    ></button>

    <!-- Panel -->
    <div
      class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-50 bg-white rounded-2xl shadow-2xl w-full max-w-md p-6"
    >
      <div class="flex justify-between items-start mb-5">
        {#if quickViewType === 'asignatura'}
          <div>
            <p class="text-xs font-semibold text-blue-500 uppercase tracking-wider mb-0.5">
              Asignatura
            </p>
            <h3 class="text-lg font-bold text-gray-900">
              {quickViewItem.asignatura_nombre ?? '—'}
            </h3>
            <p class="text-sm text-gray-500">{quickViewItem.cod_asignatura ?? ''}</p>
          </div>
        {:else}
          <div>
            <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wider mb-0.5">
              Docente responsable
            </p>
            <h3 class="text-lg font-bold text-gray-900">{quickViewItem.docente_nombre ?? '—'}</h3>
            <p class="text-sm text-gray-500">{quickViewItem.docente_email ?? ''}</p>
          </div>
        {/if}
        <button
          onclick={closeQuickView}
          class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
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

      {#if quickViewType === 'asignatura'}
        <!-- Asignatura detail grid -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="bg-blue-50 rounded-xl p-3 text-center">
            <p class="text-2xl font-bold text-blue-700">{quickViewItem.creditos_sct ?? '—'}</p>
            <p class="text-xs text-blue-600 mt-0.5">Créditos SCT</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-3 text-center">
            <p class="text-2xl font-bold text-slate-700">{quickViewItem.numero_semestre ?? '—'}</p>
            <p class="text-xs text-slate-600 mt-0.5">Semestre</p>
          </div>
        </div>

        <div class="space-y-2">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Distribución de horas
          </p>
          {#each [{ label: 'Cátedra', value: quickViewItem.horas_catedra }, { label: 'Taller', value: quickViewItem.horas_taller }, { label: 'Laboratorio', value: quickViewItem.horas_laboratorio }, { label: 'Dirigidas', value: quickViewItem.horas_dirigidas }, { label: 'Autónomas', value: quickViewItem.horas_autonomas }] as hora}
            {#if hora.value != null}
              <div
                class="flex justify-between items-center text-sm py-1.5 border-b border-gray-100 last:border-0"
              >
                <span class="text-gray-600">{hora.label}</span>
                <span class="font-semibold text-gray-900">{hora.value} h</span>
              </div>
            {/if}
          {/each}
        </div>

        <div class="mt-4 pt-3 border-t border-gray-100">
          <p class="text-xs text-gray-500">
            Carrera: <span class="font-medium text-gray-700"
              >{quickViewItem.carrera_nombre ?? '—'}</span
            >
          </p>
        </div>
      {:else}
        <!-- Docente detail -->
        <div class="space-y-3">
          {#if quickViewItem.docente_cargo}
            <div class="flex justify-between items-center text-sm py-1.5 border-b border-gray-100">
              <span class="text-gray-600">Cargo</span>
              <span class="font-medium text-gray-900">{quickViewItem.docente_cargo}</span>
            </div>
          {/if}
          {#if quickViewItem.docente_email}
            <div class="flex justify-between items-center text-sm py-1.5 border-b border-gray-100">
              <span class="text-gray-600">Email</span>
              <a
                href="mailto:{quickViewItem.docente_email}"
                class="font-medium text-blue-600 hover:underline">{quickViewItem.docente_email}</a
              >
            </div>
          {/if}
          <div class="flex justify-between items-center text-sm py-1.5 border-b border-gray-100">
            <span class="text-gray-600">Asignatura</span>
            <span class="font-medium text-gray-900">{quickViewItem.asignatura_nombre ?? '—'}</span>
          </div>
          <div class="flex justify-between items-center text-sm py-1.5">
            <span class="text-gray-600">Carrera</span>
            <span class="font-medium text-gray-900">{quickViewItem.carrera_nombre ?? '—'}</span>
          </div>
        </div>
      {/if}

      <div class="mt-5 flex justify-end">
        <button
          onclick={closeQuickView}
          class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors"
        >
          Cerrar
        </button>
      </div>
    </div>
  {/if}

  <!-- Syllabus Type Selector Modal (Programa type selection) -->
  {#if showSyllabusTypeSelector && syllabusTargetCurso}
    <SyllabusTypeSelector
      bind:isOpen={showSyllabusTypeSelector}
      onClose={closeSyllabusTypeSelector}
      onSelect={handleSyllabusTypeSelect}
      {existingSyllabusType}
    />
  {/if}

  <!-- Syllabus Modal (Programa wizard) - use key block to remount on type change -->
  {#if showSyllabusModal && syllabusTargetCurso}
    {#key `${syllabusTargetCurso?.id_curso}-${selectedSyllabusType}`}
      <SyllabusModal
        bind:isOpen={showSyllabusModal}
        curso={syllabusTargetCurso}
        onClose={closeSyllabusModal}
        onSuccess={handleSyllabusSuccess}
        syllabusType={selectedSyllabusType}
      />
    {/key}
  {/if}

  <!-- Toast Notification -->
  {#if toast}
    <div
      role="status"
      aria-live="polite"
      class="fixed bottom-6 right-6 z-[10000] flex items-center gap-2.5 px-5 py-3 rounded-xl text-sm font-medium shadow-xl {toast.type ===
      'success'
        ? 'bg-green-50 border border-green-200 text-green-800'
        : 'bg-red-50 border border-red-200 text-red-700'}"
    >
      {#if toast.type === 'success'}
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
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
          width="16"
          height="16"
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
      {toast.msg}
    </div>
  {/if}

  {#if managingTeamCurso}
    <CourseTeamModal
      bind:isOpen={showTeamModal}
      onClose={closeTeamModal}
      curso={managingTeamCurso}
    />
  {/if}

  <!-- Wizard Modal -->
  <CursoWizardModal
    isOpen={showWizardModal}
    {carreras}
    {isLoading}
    onClose={() => {
      showWizardModal = false;
    }}
    onSubmit={handleWizardSubmit}
  />

  <!-- TODO: FASE 2 Refactorización - Componentes de formulario a implementar -->
  <!-- Reemplazar con: cursoForm, seccionForm, cursoDeleteConfirm -->
  <!-- FormModal y DeleteConfirmation removidos - Pendiente integración de componentes modulares -->

  <!-- CursoForm Modal - Crear/Editar cursos -->
  <CursoForm
    bind:isOpen={showModal}
    bind:editingCurso
    asignaturas={availableAsignaturas}
    {planes}
    {docentes}
    onSubmit={handleSubmit}
    onClose={closeModal}
  />

  <!-- Confirmación de eliminación de curso -->
  <CursoDeleteConfirm
    bind:isOpen={showDeleteDialog}
    bind:curso={deletingCurso}
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
  />

  <!-- SeccionForm Modal - Crear/Editar secciones -->
  {#if editingCurso}
    <SeccionForm
      bind:isOpen={showModal}
      cursoId={editingCurso.id_curso}
      bind:editingSeccion
      tiposSeccion={tipos_seccion}
      {docentes}
      onSubmit={(data) => {
        // Manejar submit de sección
        console.log('Sección:', data);
      }}
      onClose={() => {
        showModal = false;
      }}
    />
  {/if}
</AdminLayout>
