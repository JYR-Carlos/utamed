<script lang="ts">
  /**
   * Página de administración de cursos ofertados (FASE 2 REFACTORIZADO)
   *
   * Gestión completa de cursos que se ofrecen en periodos académicos.
   * Refactorizado para usar componentes modulares:
   * - cursoList: Tabla de cursos
   * - cursoForm: Modal para crear/editar
   * - componenteForm: Modal para componentes
   * - cursoDeleteConfirm: Confirmación de eliminación
   *
   * Mantiene:
   * - CourseTeamModal: Gestión de equipos
   * - SyllabusModal: Gestión de programas
   * - CursoWizardModal: Wizard para crear cursos
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import SyllabusModal from '@/modules/resources/programa/components/SyllabusModal.svelte';
  import SyllabusTypeSelector from '@/modules/resources/programa/components/SyllabusTypeSelector.svelte';
  import { CourseTeamModal, CursoWizardModal } from '@/modules/resources/curso/components';
  // ── Componentes modulares FASE 1 ──
  import CursoForm from '@/modules/resources/curso/components/cursoForm.svelte';
  import CursoDeleteConfirm from '@/modules/resources/curso/components/cursoDeleteConfirm.svelte';
  import CursoListAdmin from '@/modules/resources/curso/components/cursoListAdmin.svelte';
  import CursoSlideOver from '@/modules/resources/curso/components/cursoSlideOver.svelte';
  import ComponenteForm from '@/modules/resources/curso/components/componenteForm.svelte';
  import {
    createCurso,
    updateCurso,
    deleteCurso,
    createComponente,
    updateComponente,
    deleteComponente,
  } from '@/modules/resources/curso/services/cursoApi';
  import type {
    Curso,
    Asignatura,
    Carrera,
    Plan,
    Docente,
    PaginatedResponse,
    CursoFormData,
    TipoComponente,
    Programa,
  } from '@/types/admin.types';
  import type { ComponenteFormState } from '@/modules/resources/curso/types/curso.types';

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
    /** Tipos de componente disponibles (Cátedra, Problemas, Laboratorio, etc.) */
    tipos_componente: TipoComponente[];
  }

  let {
    cursos,
    asignaturas,
    planes,
    carreras = [],
    filters,
    availableRoles = [],
    availablePermissions = {},
    tipos_componente = [],
  }: Props = $props();

  let showModal = $state(false);
  let showWizardModal = $state(false);
  let showComponenteModal = $state(false);
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
  // ── Slide-over ────────────────────────────────────────────────────────────
  let showSlideOver = $state(false);
  let slideOverCurso = $state<Curso | null>(null);

  function openSlideOver(curso: Curso) {
    slideOverCurso = curso;
    showSlideOver = true;
  }

  function closeSlideOver() {
    showSlideOver = false;
    slideOverCurso = null;
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

  import type { Componente } from '@/modules/resources/curso/types/curso.types';

  let editingComponente = $state<Componente | null>(null);

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

  function openCreateModal() {
    showWizardModal = true;
  }

  function openComponenteModal(curso: Curso) {
    editingCurso = curso;
    editingComponente = null;
    loadDocentes();
    showComponenteModal = true;
  }

  function openEditComponenteModal(curso: Curso, componente: Componente) {
    editingCurso = curso;
    editingComponente = componente;
    loadDocentes();
    showComponenteModal = true;
  }

  function handleDeleteComponente(curso: Curso, componente: Componente) {
    if (
      !confirm(
        `¿Eliminar el componente "${componente.tipo_componente?.tipo ?? 'este'}" del curso ${curso.cod_curso}?`,
      )
    )
      return;
    isLoading = true;
    deleteComponente(curso.id_curso, componente.id_componente, {
      onSuccess: () => {
        isLoading = false;
        showToast('Componente eliminado', 'success');
      },
      onError: () => {
        isLoading = false;
        showToast('Error al eliminar componente', 'error');
      },
    });
  }

  function openEditModal(curso: Curso) {
    editingCurso = curso;
    const id_plan = curso.asignacionPlan?.id_plan || 0;

    if (id_plan) {
      loadAsignaturasByPlan(id_plan);
    }
    loadDocentes();
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingCurso = null;
  }

  function handleSubmit(data: CursoFormData) {
    if (data.id_asignatura === 0 || data.id_plan === 0 || !data.cod_curso) {
      alert('Por favor complete los campos obligatorios (*)');
      return;
    }

    isLoading = true;
    if (editingCurso) {
      updateCurso(editingCurso.id_curso, data, {
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
      createCurso(data, {
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
    deleteCurso(deletingCurso.id_curso, {
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
    createCurso(data, {
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
    if (curso.has_programa || curso.id_programa || curso.programa_estado) {
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
          programa_estado: programa.estado,
        };

        if (slideOverCurso?.id_curso === syllabusTargetCurso.id_curso) {
          slideOverCurso = cursos.data[idx];
        }
      }
    }
    closeSyllabusModal();
    showToast('Programa generado exitosamente', 'success');
  }
</script>

<AdminLayout>
  <div>
    <!-- Tabla de cursos usando componente modular -->
    <CursoListAdmin
      {cursos}
      {searchTerm}
      {status}
      {perPage}
      onSearchChange={(term: string) => {
        searchTerm = term;
        router.get('/admin/cursos', { search: term, status });
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
      onManage={openSlideOver}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
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

  <!-- Slide-over de gestión de curso -->
  <CursoSlideOver
    bind:isOpen={showSlideOver}
    curso={slideOverCurso}
    onClose={closeSlideOver}
    onEdit={(curso) => {
      closeSlideOver();
      openEditModal(curso);
    }}
    onDelete={(curso) => {
      closeSlideOver();
      openDeleteDialog(curso);
    }}
    onTeam={(curso) => {
      closeSlideOver();
      openTeamModal(curso);
    }}
    onSyllabus={(curso) => {
      closeSlideOver();
      openSyllabusModal(curso);
    }}
    onAddComponente={(curso) => {
      closeSlideOver();
      openComponenteModal(curso);
    }}
    onEditComponente={(curso, comp) => {
      closeSlideOver();
      openEditComponenteModal(curso, comp);
    }}
    onDeleteComponente={handleDeleteComponente}
  />

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
    tiposComponente={tipos_componente}
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

  <!-- ComponenteForm Modal - Crear/Editar componentes -->
  {#if editingCurso}
    <ComponenteForm
      bind:isOpen={showComponenteModal}
      cursoId={editingCurso.id_curso}
      bind:editingComponente
      tiposComponente={tipos_componente}
      {docentes}
      onSubmit={(data: ComponenteFormState) => {
        if (!editingCurso) return;
        isLoading = true;
        if (editingComponente) {
          updateComponente(editingCurso.id_curso, editingComponente.id_componente, data, {
            onSuccess: () => {
              showComponenteModal = false;
              editingComponente = null;
              isLoading = false;
              showToast('Componente actualizado', 'success');
            },
            onError: () => {
              isLoading = false;
              showToast('Error al actualizar componente', 'error');
            },
          });
        } else {
          createComponente(editingCurso.id_curso, data, {
            onSuccess: () => {
              showComponenteModal = false;
              isLoading = false;
              showToast('Componente creado', 'success');
            },
            onError: () => {
              isLoading = false;
              showToast('Error al crear componente', 'error');
            },
          });
        }
      }}
      onClose={() => {
        showComponenteModal = false;
        if (!showModal) editingCurso = null;
      }}
    />
  {/if}
</AdminLayout>
