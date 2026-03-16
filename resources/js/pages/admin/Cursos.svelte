<script lang="ts">
  /**
   * Página de administración de cursos ofertados.
   *
   * Gestión completa de cursos que se ofrecen en periodos académicos.
   * Cada curso es una oferta específica de una asignatura en un plan.
   *
   * Características:
   * - CRUD de cursos (crear, leer, actualizar, eliminar)
   * - Creación de secciones (cátedra, problemas, laboratorio)
   * - Asignación de docentes a secciones
   * - Gestión de equipos de cátedra (docentes auxiliares, ayudantes)
   * - Asignación de roles y permisos a miembros del equipo
   * - Validación de reglas de negocio (máx 2 secciones por curso, tipos únicos)
   *
   * Tablas relacionadas:
   * - curso.curso: Información del curso (oferta específica)
   * - administrativo.asignatura: Asignatura que se cursa
   * - administrativo.plan: Plan al que pertenece el curso
   * - curso.seccion: Secciones del curso (cátedra, problemas, etc.)
   * - usuario.docente: Docentes responsables de secciones
   * - usuario.usuario_rol_asignación: Roles de miembros del equipo
   * - usuario.usuario_permiso_especial: Permisos especiales en contexto del curso
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import FormModal from '@/components/custom/admin/FormModal.svelte';
  import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
  import SyllabusModal from '@/components/custom/admin/SyllabusModal.svelte';
  import SyllabusTypeSelector from '@/components/custom/admin/SyllabusTypeSelector.svelte';
  import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
  import CursoWizardModal from '@/components/custom/admin/CursoWizardModal.svelte';
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

  async function loadDocentes() {
    try {
      console.log('📥 [loadDocentes] Iniciando carga de docentes');
      const response = await fetch('/api/docentes', {
        headers: {
          Accept: 'application/json',
        },
      });

      console.log('✅ [loadDocentes] Respuesta recibida:', {
        status: response.status,
        statusText: response.statusText,
        url: response.url,
      });

      // Check if not OK
      if (!response.ok) {
        const errorText = await response.text();
        console.error('❌ [loadDocentes] Response not OK:', {
          status: response.status,
          errorText: errorText,
        });
        docentes = [];
        return;
      }

      const data = await response.json();
      console.log('✅ [loadDocentes] Datos recibidos:', data);

      // Verificar si data.data existe
      if (!data.data) {
        console.warn(
          '⚠️ [loadDocentes] data.data no existe. Estructura recibida:',
          Object.keys(data),
        );
        if (Array.isArray(data)) {
          console.log('⚠️ [loadDocentes] La respuesta es un array directo, procesando...');
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
        // nombre_completo already computed by DocenteResource on the backend
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

      console.log(`✅ [loadDocentes] ${docentes.length} docentes cargados:`, docentes);
    } catch (error) {
      console.error('❌ [loadDocentes] Error CRÍTICO:', {
        message: error instanceof Error ? error.message : String(error),
        stack: error instanceof Error ? error.stack : 'No stack',
        error_object: error,
      });
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

  function handleWizardSubmit(data: CursoFormData & { id_docente_sugerido?: number }) {
    isLoading = true;
    router.post('/admin/cursos', data as any, {
      onSuccess: () => {
        showWizardModal = false;
        isLoading = false;
      },
      onError: (errors) => {
        console.error('Error creating curso via wizard:', errors);
        alert('Error al crear curso: ' + JSON.stringify(errors));
        isLoading = false;
      },
    });
  }

  function openEditModal(curso: Curso) {
    console.log('🔵 [openEditModal] ABRIENDO MODAL DE EDICIÓN para curso:', curso.nombre);
    editingCurso = curso;
    // Get id_asignatura and id_plan from asignacionPlan relationship
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
    // Load asignaturas for the selected plan
    if (id_plan) {
      loadAsignaturasByPlan(id_plan);
    }
    console.log('🔵 [openEditModal] LLAMANDO loadDocentes()');
    loadDocentes();

    // Load Secciones
    console.log('🔵 [openEditModal] LLAMANDO loadSecciones()');
    loadSecciones(curso.id_curso);

    console.log('🔵 [openEditModal] showModal = true');
    showModal = true;
  }

  async function loadSecciones(cursoId: number) {
    loadingSecciones = true;
    try {
      console.log(`📥 [loadSecciones] Iniciando carga para curso ID: ${cursoId}`);

      // Fetch fresh course data including sections
      const response = await axios.get(`/admin/cursos/${cursoId}`);

      console.log(`✅ [loadSecciones] Respuesta recibida:`, {
        status: response.status,
        data_keys: response.data ? Object.keys(response.data) : 'NO DATA',
        secciones_count: response.data?.secciones?.length,
        response_data: response.data,
      });

      // Assuming response.data.secciones exists due to my backend change
      if (response.data.secciones) {
        console.log(`✅ [loadSecciones] Secciones encontradas: ${response.data.secciones.length}`);
        currentSecciones = response.data.secciones;
        console.log(`✅ [loadSecciones] currentSecciones actualizado:`, currentSecciones);
      } else {
        console.warn(`⚠️ [loadSecciones] No se encontraron secciones en response.data.secciones`);
        console.warn(`⚠️ [loadSecciones] response.data:`, response.data);
      }
    } catch (error) {
      const axiosError = error as AxiosError;
      console.error(`❌ [loadSecciones] ERROR CRÍTICO:`, {
        message: error instanceof Error ? error.message : String(error),
        status: axiosError?.response?.status,
        statusText: axiosError?.response?.statusText,
        error_data: axiosError?.response?.data,
        error_object: error,
      });

      // Mostrar un alert con el error detallado
      const errorData = axiosError?.response?.data as any;
      const errorMessage =
        errorData?.error ||
        errorData?.message ||
        (error instanceof Error ? error.message : 'Error desconocido');
      const errorFile = errorData?.error_file || '';
      const errorTrace = errorData?.trace ? '\n\nStack Trace:\n' + errorData.trace : '';

      alert(`❌ Error al cargar secciones:\n\n${errorMessage}\n${errorFile}${errorTrace}`);
    } finally {
      loadingSecciones = false;
    }
  }

  // Computed: Tipos de sección disponibles (excluyendo los ya agregados)
  let availableTiposSeccion = $derived(
    tipos_seccion.filter(
      (t) => !currentSecciones.some((s) => s.id_tipo_seccion === t.id_tipo_seccion),
    ),
  );

  async function addSeccion() {
    if (!editingCurso || !newSeccionData.id_tipo_seccion) return;

    try {
      const response = await axios.post(
        `/admin/cursos/${editingCurso.id_curso}/secciones`,
        newSeccionData,
      );
      if (response.data.seccion) {
        currentSecciones = [...currentSecciones, response.data.seccion];
        // Reset form
        newSeccionData = { id_tipo_seccion: undefined, id_docente: undefined };
      }
    } catch (error) {
      console.error('Error adding seccion:', error);
      const message =
        error instanceof AxiosError
          ? error.response?.data?.error || error.message
          : 'Error desconocido';
      alert('Error al agregar sección: ' + message);
    }
  }

  async function deleteSeccion(seccionId: number) {
    if (!confirm('¿Estás seguro de eliminar esta sección?')) return;

    try {
      await axios.delete(`/admin/cursos/secciones/${seccionId}`);
      currentSecciones = currentSecciones.filter((s) => s.id_seccion !== seccionId);
    } catch (error) {
      console.error('Error deleting seccion:', error);
      const message =
        error instanceof AxiosError
          ? error.response?.data?.error || error.message
          : 'Error desconocido';
      alert('Error al eliminar sección: ' + message);
    }
  }

  async function updateSeccionDocente(seccion: Seccion) {
    try {
      await axios.put(`/admin/cursos/secciones/${seccion.id_seccion}`, {
        id_tipo_seccion: seccion.id_tipo_seccion,
        id_docente: seccion.id_docente,
      });
      // Optionally show success toast
    } catch (error) {
      console.error('Error updating seccion:', error);
      const message =
        error instanceof AxiosError
          ? error.response?.data?.error || error.message
          : 'Error desconocido';
      alert('Error al actualizar sección: ' + message);
    }
  }

  function toggleEditDocente(seccion: Seccion) {
    if (showEditDocente && editingSeccion?.id_seccion === seccion.id_seccion) {
      // Close if clicking the same section
      showEditDocente = false;
      editingSeccion = null;
      editingDocenteId = undefined;
    } else {
      // Open edit mode
      editingSeccion = seccion;
      editingDocenteId = seccion.id_docente;
      showEditDocente = true;
    }
  }

  async function saveDocente() {
    if (!editingSeccion) return;

    editingSeccion.id_docente = editingDocenteId === null ? undefined : editingDocenteId;
    await updateSeccionDocente(editingSeccion);
    showEditDocente = false;
    editingSeccion = null;
    editingDocenteId = undefined;
  }

  function cancelEditDocente() {
    showEditDocente = false;
    editingSeccion = null;
    editingDocenteId = undefined;
  }

  // Computed: get the currently selected docente details
  let selectedDocenteDetails = $derived.by(() => {
    if (!editingDocenteId) return null;
    return docentes.find((d) => d.id_docente === editingDocenteId) || null;
  });

  function closeModal() {
    showModal = false;
    editingCurso = null;
  }

  function openTeamModal(curso: Curso) {
    managingTeamCurso = curso;
    showTeamModal = true;
  }

  function closeTeamModal() {
    showTeamModal = false;
    managingTeamCurso = null;
  }

  function handleSubmit() {
    if (formData.id_asignatura === 0 || formData.id_plan === 0 || !formData.cod_curso) {
      alert('Por favor complete los campos obligatorios (*)');
      return;
    }

    console.log('Submitting curso with data:', formData);
    isLoading = true;

    if (editingCurso) {
      router.put(`/admin/cursos/${editingCurso.id_curso}`, formData, {
        onSuccess: () => {
          console.log('Curso updated successfully');
          closeModal();
          isLoading = false;
        },
        onError: (errors) => {
          console.error('Error updating curso:', errors);
          alert('Error al actualizar curso: ' + JSON.stringify(errors));
          isLoading = false;
        },
      });
    } else {
      router.post('/admin/cursos', formData, {
        onSuccess: () => {
          console.log('Curso created successfully');
          closeModal();
          isLoading = false;
        },
        onError: (errors) => {
          console.error('Error creating curso:', errors);
          alert('Error al crear curso: ' + JSON.stringify(errors));
          isLoading = false;
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
      },
      onError: () => {
        isLoading = false;
      },
    });
  }

  function openInscriptionsModal(curso: Curso) {
    selectedCursoForInscription = curso;
    showModal = false;
    showInscriptionModal = true;
  }

  function closeInscriptionsModal() {
    showInscriptionModal = false;
    selectedCursoForInscription = null;
  }

  function goToInscriptions(cursoId: number) {
    router.visit(`/admin/inscripciones_cursos?id_curso=${cursoId}`);
  }

  // ── Syllabus modal ────────────────────────────────────────────────────
  function openSyllabusModal(curso: Curso) {
    if (curso.has_programa && curso.programa_estado && curso.programa_estado !== 'BORRADOR') {
      // Navigate to view/review page for any existing program (including BASICO_COMPLETO)
      router.visit(`/admin/cursos/${curso.id_curso}/programa/revisar`, { method: 'get' });
    } else {
      // No program or BORRADOR → open the type selector
      syllabusTargetCurso = curso;
      showSyllabusTypeSelector = true;
    }
  }

  function handleSyllabusTypeSelect(type: 'simplified' | 'combined' | 'complete') {
    console.log('🎯 handleSyllabusTypeSelect called with type:', type);
    selectedSyllabusType = type;
    console.log('✅ selectedSyllabusType actualizado a:', selectedSyllabusType);
    showSyllabusTypeSelector = false;
    showSyllabusModal = true;
    console.log('🚀 Modal abierto con syllabusType:', selectedSyllabusType);
  }

  function closeSyllabusTypeSelector() {
    console.log(
      '❌ closeSyllabusTypeSelector - cerrando selector sin cambiar selectedSyllabusType',
    );
    showSyllabusTypeSelector = false;
    // NO resetear selectedSyllabusType aquí - solo se resetea en closeSyllabusModal
  }

  function closeSyllabusModal() {
    showSyllabusModal = false;
    syllabusTargetCurso = null;
    selectedSyllabusType = null;
  }

  function handleSyllabusSuccess(programa: Programa) {
    // Optimistic update — mutate the row in the local paginated list
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
    showToast('Programa generado exitosamente.');
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

    <DataTable
      data={cursos}
      {columns}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      onCustomAction={openTeamModal}
      customActionLabel="Equipo"
      onSyllabus={openSyllabusModal}
    >
      {#snippet cellSnippet({ item, column })}
        {#if column.key === 'asignatura_nombre'}
          <button
            onclick={() => openQuickView(item, 'asignatura')}
            class="text-blue-600 hover:text-blue-800 hover:underline text-left text-sm font-medium transition-colors"
            title="Ver detalle de asignatura"
          >
            {item.asignatura_nombre ?? '-'}
          </button>
        {:else if column.key === 'docente_nombre'}
          {#if item.docente_nombre}
            <button
              onclick={() => openQuickView(item, 'docente')}
              class="text-indigo-600 hover:text-indigo-800 hover:underline text-left text-sm font-medium transition-colors"
              title="Ver detalle del docente"
            >
              {item.docente_nombre}
            </button>
          {:else}
            <span class="text-xs text-gray-400 italic">Sin asignar</span>
          {/if}
        {:else}
          {item[column.key] ?? '-'}
        {/if}
      {/snippet}
    </DataTable>
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

  <!-- Wizard para creación de nuevo curso -->
  <CursoWizardModal
    isOpen={showWizardModal}
    {carreras}
    {isLoading}
    onClose={() => {
      showWizardModal = false;
    }}
    onSubmit={handleWizardSubmit}
  />

  <FormModal
    bind:isOpen={showModal}
    title="Editar Curso"
    onClose={closeModal}
    onSubmit={handleSubmit}
    {isLoading}
  >
    <div class="mb-4">
      <label for="plan" class="block text-sm font-medium text-gray-700 mb-2">Plan (Malla) *</label>
      <select
        id="plan"
        bind:value={formData.id_plan}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        onchange={() => {
          formData.id_asignatura = 0; // Reset asignatura when plan changes
          loadAsignaturasByPlan(formData.id_plan);
        }}
        required
      >
        <option value={0}>Seleccione un plan</option>
        {#each planes as plan}
          <option value={plan.id_plan}>
            {plan.carrera?.nombre} - {plan.agno} v{plan.version_plan}
          </option>
        {/each}
      </select>
    </div>

    <div class="mb-4">
      <label for="asignatura" class="block text-sm font-medium text-gray-700 mb-2"
        >Asignatura *</label
      >
      <select
        id="asignatura"
        bind:value={formData.id_asignatura}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        disabled={formData.id_plan === 0 || loadingAsignaturas}
        required
      >
        <option value={0}>
          {#if formData.id_plan === 0}
            Primero seleccione un plan
          {:else if loadingAsignaturas}
            Cargando asignaturas...
          {:else if availableAsignaturas.length === 0}
            No hay asignaturas en este plan
          {:else}
            Seleccione una asignatura
          {/if}
        </option>
        {#each availableAsignaturas as asignatura}
          <option value={asignatura.id_asignatura}>
            {asignatura.cod_asignatura} - {asignatura.nombre}
          </option>
        {/each}
      </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="mb-4">
        <label for="cod_curso" class="block text-sm font-medium text-gray-700 mb-2"
          >Código de Curso *</label
        >
        <input
          id="cod_curso"
          type="number"
          bind:value={formData.cod_curso}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          placeholder="Ej: 12345"
          required
        />
      </div>
    </div>

    <div class="mb-4">
      <label for="nombre" class="block text-sm font-medium text-gray-700 mb-2"
        >Nombre del Curso</label
      >
      <input
        id="nombre"
        type="text"
        bind:value={formData.nombre}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
        placeholder="Nombre personalizado (opcional)"
      />
    </div>

    <div class="mb-4">
      <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2"
        >Fecha de Inicio</label
      >
      <input
        id="fecha_inicio"
        type="date"
        bind:value={formData.fecha_inicio}
        class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
      />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="mb-4">
        <label for="agno_real" class="block text-sm font-medium text-gray-700 mb-2"
          >Año Real *</label
        >
        <input
          id="agno_real"
          type="number"
          bind:value={formData.agno_real}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          min="2000"
          max="2100"
          required
        />
      </div>
      <div class="mb-4">
        <label for="semestre_real" class="block text-sm font-medium text-gray-700 mb-2"
          >Semestre Real *</label
        >
        <select
          id="semestre_real"
          bind:value={formData.semestre_real}
          class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
          required
        >
          <option value={1}>1</option>
          <option value={2}>2</option>
        </select>
      </div>
    </div>

    <div class="mb-4">
      <div class="block text-sm font-medium text-gray-700 mb-2">Secciones del Curso</div>
      {#if loadingSecciones}
        <p>Cargando secciones...</p>
      {:else}
        <div class="flex flex-col gap-3 mb-8">
          {#each currentSecciones as seccion, i (seccion.id_seccion || 'new-' + i)}
            <div class="flex items-center gap-4 p-3 bg-slate-50 border border-slate-200 rounded-lg">
              <div class="min-w-[120px]">
                <span
                  class="inline-block px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-semibold"
                  >{seccion.tipo_seccion?.tipo || 'Sección'}</span
                >
              </div>
              <div class="flex-1">
                {#if seccion.id_docente}
                  <div
                    class="flex items-center gap-2 p-3 bg-white border border-slate-200 rounded-md"
                  >
                    <span class="flex-1 text-sm text-slate-800 font-medium"
                      >{seccion.docente?.nombre_completo || 'Sin nombre'}</span
                    >
                    <button
                      type="button"
                      class="p-1 text-sky-600 bg-transparent border-0 cursor-pointer rounded flex items-center justify-center hover:bg-sky-100 transition-colors"
                      aria-label="Editar docente"
                      onclick={() => toggleEditDocente(seccion)}
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        ><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                        ></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                        ></path></svg
                      >
                    </button>
                  </div>
                {:else}
                  <span class="text-sm text-slate-400 italic">Sin docente asignado</span>
                {/if}
              </div>
              <button
                type="button"
                class="p-2 text-red-500 bg-transparent border-0 cursor-pointer rounded-md flex items-center justify-center hover:bg-red-50"
                onclick={() => deleteSeccion(seccion.id_seccion)}
                title="Eliminar Sección"
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
                  stroke-linejoin="round"
                  ><polyline points="3 6 5 6 21 6"></polyline><path
                    d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                  ></path></svg
                >
              </button>
            </div>
          {/each}
        </div>

        {#if showEditDocente && editingSeccion}
          <div class="p-4 bg-gray-50 border-2 border-blue-500 rounded-lg my-4">
            <div class="mb-4">
              <label for="docente-select-{editingSeccion.id_seccion}"
                >Asignar Docente a {editingSeccion.tipo_seccion?.tipo || 'Sección'}</label
              >
              <select
                id="docente-select-{editingSeccion.id_seccion}"
                bind:value={editingDocenteId}
                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              >
                <option value={null}>Sin docente</option>
                {#each docentes as docente}
                  <option value={docente.id_docente}>{docente.nombre_completo}</option>
                {/each}
              </select>
            </div>

            {#if selectedDocenteDetails}
              <div class="p-4 bg-sky-50 border border-blue-200 rounded-lg my-4">
                <h5 class="text-sm font-semibold text-blue-800 mb-4 mt-0">
                  Información del Docente
                </h5>
                <div class="grid grid-cols-2 gap-4">
                  <div class="flex flex-col gap-1">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"
                      >Nombre Completo:</span
                    >
                    <span class="text-sm text-slate-800 font-medium"
                      >{selectedDocenteDetails.nombre_completo || 'Sin nombre'}</span
                    >
                  </div>
                  {#if selectedDocenteDetails.email}
                    <div class="flex flex-col gap-1">
                      <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"
                        >Email:</span
                      >
                      <span class="text-sm text-slate-800 font-medium"
                        >{selectedDocenteDetails.email}</span
                      >
                    </div>
                  {/if}
                  {#if selectedDocenteDetails.grado}
                    <div class="flex flex-col gap-1">
                      <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"
                        >Grado:</span
                      >
                      <span class="text-sm text-slate-800 font-medium"
                        >{selectedDocenteDetails.grado}</span
                      >
                    </div>
                  {/if}
                  {#if selectedDocenteDetails.titulo}
                    <div class="flex flex-col gap-1">
                      <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"
                        >Título:</span
                      >
                      <span class="text-sm text-slate-800 font-medium"
                        >{selectedDocenteDetails.titulo}</span
                      >
                    </div>
                  {/if}
                  {#if selectedDocenteDetails.cargo}
                    <div class="flex flex-col gap-1">
                      <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide"
                        >Cargo:</span
                      >
                      <span class="text-sm text-slate-800 font-medium"
                        >{selectedDocenteDetails.cargo}</span
                      >
                    </div>
                  {/if}
                </div>
              </div>
            {/if}

            <div class="flex gap-3 mt-4">
              <button
                type="button"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"
                onclick={saveDocente}
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
                  stroke-linejoin="round"
                  class="mr-2"
                  ><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
                  ></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline
                    points="7 3 7 8 15 8"
                  ></polyline></svg
                >
                Guardar
              </button>
              <button
                type="button"
                class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-md font-medium cursor-pointer transition-all hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed"
                onclick={cancelEditDocente}
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
                  stroke-linejoin="round"
                  class="mr-2"
                  ><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"
                  ></line><line x1="9" y1="9" x2="15" y2="15"></line></svg
                >
                Cancelar
              </button>
            </div>
          </div>
        {/if}

        <div class="p-4 bg-slate-50 border border-dashed border-slate-300 rounded-lg">
          <h4 class="text-sm font-semibold text-slate-600 mt-0 mb-4">Agregar Sección</h4>
          <div class="grid grid-cols-2 gap-4">
            <select
              bind:value={newSeccionData.id_tipo_seccion}
              class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              disabled={availableTiposSeccion.length === 0 || currentSecciones.length >= 3}
            >
              <option value={undefined}>
                {#if currentSecciones.length >= 3}
                  Máximo de secciones alcanzado
                {:else if availableTiposSeccion.length === 0}
                  Todos los tipos asignados
                {:else}
                  Seleccione Tipo...
                {/if}
              </option>
              {#each availableTiposSeccion as tipo}
                <option value={tipo.id_tipo_seccion}>{tipo.tipo}</option>
              {/each}
            </select>
            <select
              bind:value={newSeccionData.id_docente}
              class="w-full px-3.5 py-2.5 border border-gray-300 rounded-md text-sm text-gray-900 bg-white transition-all focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              disabled={currentSecciones.length >= 3}
            >
              <option value={undefined}>Docente (Opcional)</option>
              {#each docentes as docente}
                <option value={docente.id_docente}>{docente.nombre_completo}</option>
              {/each}
            </select>
            <button
              type="button"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"
              onclick={addSeccion}
              disabled={!newSeccionData.id_tipo_seccion ||
                loadingSecciones ||
                currentSecciones.length >= 3}
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
                stroke-linejoin="round"
                class="mr-2"
                ><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"
                ></line><line x1="8" y1="12" x2="16" y2="12"></line></svg
              >
              Agregar
            </button>
          </div>
          {#if currentSecciones.length >= 3}
            <p class="text-sm text-red-500 mt-2">
              Este curso ya tiene el máximo de 3 secciones permitidas.
            </p>
          {/if}
        </div>
      {/if}
    </div>
  </FormModal>

  <DeleteConfirmation
    bind:isOpen={showDeleteDialog}
    title="¿Eliminar Curso?"
    message="Esta acción no se puede deshacer. Si el curso tiene inscripciones asociadas, no podrá ser eliminado."
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
    {isLoading}
  />
</AdminLayout>
