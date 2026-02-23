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
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
	import axios, { AxiosError } from 'axios';
	import type {
		Curso,
		Asignatura,
		Plan,
		Docente,
		PaginatedResponse,
		CursoFormData,
		Seccion,
		TipoSeccion,
		Programa
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
		/** Roles disponibles para asignar a miembros del equipo */
		availableRoles: any[];
		/** Permisos especiales disponibles */
		availablePermissions: Record<string, any[]>;
		/** Filtros aplicados */
		filters: { search?: string; id_asignatura?: number };
		/** Tipos de secciones disponibles (Cátedra, Problemas, etc.) */
		tipos_seccion: TipoSeccion[];
	}

	let { cursos, asignaturas, planes, filters, availableRoles = [], availablePermissions = {}, tipos_seccion = [] }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
    let showTeamModal = $state(false);
	let showInscriptionModal = $state(false);
	let showSyllabusModal = $state(false);
	let isLoading = $state(false);
	let editingCurso = $state<Curso | null>(null);
	let deletingCurso = $state<Curso | null>(null);
    let managingTeamCurso = $state<Curso | null>(null);
	let syllabusTargetCurso = $state<Curso | null>(null);
	let selectedCursoForInscription = $state<Curso | null>(null);
	let docentes = $state<Docente[]>([]);
	let availableAsignaturas = $state<Asignatura[]>([]);
	let loadingAsignaturas = $state(false);

	// Toast notification
	let toast = $state<{ msg: string; type: 'success' | 'error' } | null>(null);
	let toastTimeout: ReturnType<typeof setTimeout> | null = null;

	function showToast(msg: string, type: 'success' | 'error' = 'success') {
		if (toastTimeout) clearTimeout(toastTimeout);
		toast = { msg, type };
		toastTimeout = setTimeout(() => { toast = null; }, 4500);
	}

	let currentSecciones = $state<Seccion[]>([]);
	let newSeccionData = $state({
		id_tipo_seccion: undefined,
		id_docente: undefined
	});
	let loadingSecciones = $state(false);
	let showEditDocente = $state(false);
	let editingSeccion = $state<Seccion | null>(null);
	let editingDocenteId = $state<number | null>(null);

	let formData = $state<CursoFormData>({
		id_asignatura: 0,
		id_plan: 0,
		cod_curso: 0,
		nombre: '',
		fecha_inicio: '',
		numero_semestre: undefined,
		agno_real: new Date().getFullYear(),
		semestre_real: 1
	});

	const columns = [
		{ key: 'id_curso', label: 'ID' },
		{ key: 'cod_curso', label: 'Código' },
		{ key: 'asignatura_nombre', label: 'Asignatura' },
		{ key: 'carrera_nombre', label: 'Carrera' },
		{ key: 'numero_semestre', label: 'Semestre' },
		{ key: 'docente_nombre', label: 'Docente' }
	];

	async function loadDocentes() {
		try {
			console.log('📥 [loadDocentes] Iniciando carga de docentes');
			const response = await fetch('/api/docentes', {
				headers: {
					'Accept': 'application/json'
				}
			});
			
			console.log('✅ [loadDocentes] Respuesta recibida:', {
				status: response.status,
				statusText: response.statusText,
				url: response.url
			});
			
			// Check if not OK
			if (!response.ok) {
				const errorText = await response.text();
				console.error('❌ [loadDocentes] Response not OK:', {
					status: response.status,
					errorText: errorText
				});
				docentes = [];
				return;
			}
			
			const data = await response.json();
			console.log('✅ [loadDocentes] Datos recibidos:', data);
			
			// Verificar si data.data existe
			if (!data.data) {
				console.warn('⚠️ [loadDocentes] data.data no existe. Estructura recibida:', Object.keys(data));
				if (Array.isArray(data)) {
					console.log('⚠️ [loadDocentes] La respuesta es un array directo, procesando...');
					docentes = data.map((docente: any) => ({
						id_docente: docente.id_docente,
						nombre_completo: docente.nombre_completo || docente.usuario?.nombre1 || 'Sin nombre',
						email: docente.email,
						grado: docente.grado,
						titulo: docente.titulo,
						cargo: docente.cargo,
						usuario: docente.usuario
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
					usuario: docente.usuario
				}));
			}
			
			console.log(`✅ [loadDocentes] ${docentes.length} docentes cargados:`, docentes);
		} catch (error) {
			console.error('❌ [loadDocentes] Error CRÍTICO:', {
				message: error instanceof Error ? error.message : String(error),
				stack: error instanceof Error ? error.stack : 'No stack',
				error_object: error
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
		editingCurso = null;
		formData = {
			id_asignatura: 0,
			id_plan: 0,
			cod_curso: 0,
			nombre: '',
			fecha_inicio: '',
			numero_semestre: undefined,
			agno_real: new Date().getFullYear(),
			semestre_real: 1
		};
		availableAsignaturas = [];
		loadDocentes();
		showModal = true;
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
			semestre_real: 1
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
				response_data: response.data
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
			console.error(`❌ [loadSecciones] ERROR CRÍTICO:`, {
				message: error instanceof Error ? error.message : String(error),
				status: error?.response?.status,
				statusText: error?.response?.statusText,
				error_data: error?.response?.data,
				error_object: error
			});
			
			// Mostrar un alert con el error detallado
			const errorMessage = error?.response?.data?.error || error?.response?.data?.message || (error instanceof Error ? error.message : 'Error desconocido');
			const errorFile = error?.response?.data?.error_file || '';
			const errorTrace = error?.response?.data?.trace ? '\n\nStack Trace:\n' + error.response.data.trace : '';
			
			alert(`❌ Error al cargar secciones:\n\n${errorMessage}\n${errorFile}${errorTrace}`);
		} finally {
			loadingSecciones = false;
		}
	}
	
	// Computed: Tipos de sección disponibles (excluyendo los ya agregados)
	let availableTiposSeccion = $derived(
		tipos_seccion.filter(t => !currentSecciones.some(s => s.id_tipo_seccion === t.id_tipo_seccion))
	);

	async function addSeccion() {
		if (!editingCurso || !newSeccionData.id_tipo_seccion) return;
		
		try {
			const response = await axios.post(`/admin/cursos/${editingCurso.id_curso}/secciones`, newSeccionData);
			if (response.data.seccion) {
				currentSecciones = [...currentSecciones, response.data.seccion];
				// Reset form
				newSeccionData = { id_tipo_seccion: undefined, id_docente: undefined };
			}
		} catch (error) {
			console.error("Error adding seccion:", error);
			const message = error instanceof AxiosError 
				? (error.response?.data?.error || error.message)
				: 'Error desconocido';
			alert("Error al agregar sección: " + message);
		}
	}

	async function deleteSeccion(seccionId: number) {
		if (!confirm('¿Estás seguro de eliminar esta sección?')) return;
		
		try {
			await axios.delete(`/admin/cursos/secciones/${seccionId}`);
			currentSecciones = currentSecciones.filter(s => s.id_seccion !== seccionId);
		} catch (error) {
			console.error("Error deleting seccion:", error);
			const message = error instanceof AxiosError 
				? (error.response?.data?.error || error.message)
				: 'Error desconocido';
			alert("Error al eliminar sección: " + message);
		}
	}

	async function updateSeccionDocente(seccion: Seccion) {
		try {
			await axios.put(`/admin/cursos/secciones/${seccion.id_seccion}`, {
				id_tipo_seccion: seccion.id_tipo_seccion,
				id_docente: seccion.id_docente
			});
			// Optionally show success toast
		} catch (error) {
			console.error("Error updating seccion:", error);
			const message = error instanceof AxiosError 
				? (error.response?.data?.error || error.message)
				: 'Error desconocido';
			alert("Error al actualizar sección: " + message);
		}
	}

	function toggleEditDocente(seccion: Seccion) {
		if (showEditDocente && editingSeccion?.id_seccion === seccion.id_seccion) {
			// Close if clicking the same section
			showEditDocente = false;
			editingSeccion = null;
			editingDocenteId = null;
		} else {
			// Open edit mode
			editingSeccion = seccion;
			editingDocenteId = seccion.id_docente;
			showEditDocente = true;
		}
	}

	async function saveDocente() {
		if (!editingSeccion) return;
		
		editingSeccion.id_docente = editingDocenteId;
		await updateSeccionDocente(editingSeccion);
		showEditDocente = false;
		editingSeccion = null;
		editingDocenteId = null;
	}

	function cancelEditDocente() {
		showEditDocente = false;
		editingSeccion = null;
		editingDocenteId = null;
	}

	// Computed: get the currently selected docente details
	let selectedDocenteDetails = $derived.by(() => {
		if (!editingDocenteId) return null;
		return docentes.find(d => d.id_docente === editingDocenteId) || null;
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
				}
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
				}
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
			}
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
		// Si el programa existe y NO está en BORRADOR, navegar a revisar
		if (curso.has_programa && curso.programa_estado && curso.programa_estado !== 'BORRADOR') {
			router.visit(`/admin/cursos/${curso.id_curso}/programa/revisar`, { method: 'get' });
		} else {
			// Si no existe o está en BORRADOR, abrir modal de crear/editar
			syllabusTargetCurso = curso;
			showSyllabusModal = true;
		}
	}

	function closeSyllabusModal() {
		showSyllabusModal = false;
		syllabusTargetCurso = null;
	}

	function handleSyllabusSuccess(programa: Programa) {
		// Optimistic update — mutate the row in the local paginated list
		if (syllabusTargetCurso) {
			const idx = cursos.data.findIndex(c => c.id_curso === syllabusTargetCurso!.id_curso);
			if (idx !== -1) {
				cursos.data[idx] = {
					...cursos.data[idx],
					has_programa: true,
					id_programa: programa.id_programa
				};
			}
		}
		closeSyllabusModal();
		showToast('Programa generado exitosamente.');
	}
</script>

<AdminLayout>
<div class="page-container">
	<div class="page-header">
		<div>
			<h1 class="page-title">Cursos</h1>
			<p class="page-description">Gestión de cursos y asignación de docentes</p>
		</div>
		<button onclick={openCreateModal} class="btn-primary">
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
    />
</div>

<!-- Syllabus Modal (Programa wizard) -->
{#if showSyllabusModal}
	<SyllabusModal
		bind:isOpen={showSyllabusModal}
		curso={syllabusTargetCurso}
		onClose={closeSyllabusModal}
		onSuccess={handleSyllabusSuccess}
	/>
{/if}

<!-- Toast Notification -->
{#if toast}
	<div class="toast toast-{toast.type}" role="status" aria-live="polite">
		{#if toast.type === 'success'}
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
		{:else}
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
		{/if}
		{toast.msg}
	</div>
{/if}

{#if managingTeamCurso}
    <CourseTeamModal 
        bind:isOpen={showTeamModal}
        onClose={closeTeamModal}
        curso={managingTeamCurso}
        availableRoles={availableRoles}
        availablePermissions={availablePermissions}
    />
{/if}

<FormModal
	bind:isOpen={showModal}
	title={editingCurso ? 'Editar Curso' : 'Nuevo Curso'}
	onClose={closeModal}
	onSubmit={handleSubmit}
	{isLoading}
>
	<div class="form-group">
		<label for="plan" class="form-label">Plan (Malla) *</label>
		<select 
			id="plan" 
			bind:value={formData.id_plan} 
			class="form-input" 
			onchange={() => {
				formData.id_asignatura = 0; // Reset asignatura when plan changes
				loadAsignaturasByPlan(formData.id_plan);
			}}
			required
		>
			<option value={0}>Seleccione un plan</option>
			{#each planes as plan}
				<option value={plan.id_plan}>
					{plan.carrera?.nombre} - {plan.agno} v{plan.version}
				</option>
			{/each}
		</select>
	</div>

	<div class="form-group">
		<label for="asignatura" class="form-label">Asignatura *</label>
		<select 
			id="asignatura" 
			bind:value={formData.id_asignatura} 
			class="form-input" 
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

	<div class="form-row">
		<div class="form-group">
			<label for="cod_curso" class="form-label">Código de Curso *</label>
			<input
				id="cod_curso"
				type="number"
				bind:value={formData.cod_curso}
				class="form-input"
				placeholder="Ej: 12345"
				required
			/>
		</div>
	</div>

	<div class="form-group">
		<label for="nombre" class="form-label">Nombre del Curso</label>
		<input
			id="nombre"
			type="text"
			bind:value={formData.nombre}
			class="form-input"
			placeholder="Nombre personalizado (opcional)"
		/>
	</div>

	<div class="form-group">
		<label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
		<input
			id="fecha_inicio"
			type="date"
			bind:value={formData.fecha_inicio}
			class="form-input"
		/>
	</div>

	<div class="form-row">
		<div class="form-group">
			<label for="agno_real" class="form-label">Año Real *</label>
			<input
				id="agno_real"
				type="number"
				bind:value={formData.agno_real}
				class="form-input"
				min="2000"
				max="2100"
				required
			/>
		</div>
		<div class="form-group">
			<label for="semestre_real" class="form-label">Semestre Real *</label>
			<select
				id="semestre_real"
				bind:value={formData.semestre_real}
				class="form-input"
				required
			>
				<option value={1}>1</option>
				<option value={2}>2</option>
			</select>
		</div>
	</div>

	<div class="form-group">
		<label class="form-label">Secciones del Curso</label>
		{#if loadingSecciones}
			<p>Cargando secciones...</p>
		{:else}
			<div class="secciones-list">
				{#each currentSecciones as seccion, i (seccion.id_seccion || 'new-' + i)}
					<div class="seccion-item">
						<div class="seccion-info">
							<span class="badget">{seccion.tipo_seccion?.tipo || 'Sección'}</span>
						</div>
						<div class="seccion-docente">
							{#if seccion.id_docente}
								<div class="docente-display">
									<span class="docente-name">{seccion.docente?.nombre_completo || 'Sin nombre'}</span>
									<button type="button" class="btn-edit-docente" onclick={() => toggleEditDocente(seccion)}>
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
									</button>
								</div>
							{:else}
								<span class="docente-empty">Sin docente asignado</span>
							{/if}
						</div>
						<button type="button" class="btn-icon" onclick={() => deleteSeccion(seccion.id_seccion)} title="Eliminar Sección">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
						</button>
					</div>
				{/each}
			</div>

			{#if showEditDocente && editingSeccion}
				<div class="edit-docente-form">
					<div class="form-group">
						<label>Asignar Docente a {editingSeccion.tipo_seccion?.tipo || 'Sección'}</label>
						<select bind:value={editingDocenteId} class="form-input">
							<option value={null}>Sin docente</option>
							{#each docentes as docente}
								<option value={docente.id_docente}>{docente.nombre_completo}</option>
							{/each}
						</select>
					</div>

					{#if selectedDocenteDetails}
						<div class="docente-details-panel">
							<h5>Información del Docente</h5>
							<div class="details-grid">
								<div class="detail-item">
									<span class="detail-label">Nombre Completo:</span>
									<span class="detail-value">{selectedDocenteDetails.nombre_completo || 'Sin nombre'}</span>
								</div>
								{#if selectedDocenteDetails.email}
									<div class="detail-item">
										<span class="detail-label">Email:</span>
										<span class="detail-value">{selectedDocenteDetails.email}</span>
									</div>
								{/if}
								{#if selectedDocenteDetails.grado}
									<div class="detail-item">
										<span class="detail-label">Grado:</span>
										<span class="detail-value">{selectedDocenteDetails.grado}</span>
									</div>
								{/if}
								{#if selectedDocenteDetails.titulo}
									<div class="detail-item">
										<span class="detail-label">Título:</span>
										<span class="detail-value">{selectedDocenteDetails.titulo}</span>
									</div>
								{/if}
								{#if selectedDocenteDetails.cargo}
									<div class="detail-item">
										<span class="detail-label">Cargo:</span>
										<span class="detail-value">{selectedDocenteDetails.cargo}</span>
									</div>
								{/if}
							</div>
						</div>
					{/if}

					<div class="form-actions">
						<button type="button" class="btn-primary" onclick={saveDocente}>
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
							Guardar
						</button>
						<button type="button" class="btn-secondary" onclick={cancelEditDocente}>
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
							Cancelar
						</button>
					</div>
				</div>
			{/if}

			<div class="add-seccion-form">
				<h4>Agregar Sección</h4>
				<div class="form-row">
					<select bind:value={newSeccionData.id_tipo_seccion} class="form-input" disabled={availableTiposSeccion.length === 0 || currentSecciones.length >= 3}>
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
					<select bind:value={newSeccionData.id_docente} class="form-input" disabled={currentSecciones.length >= 3}>
						<option value={undefined}>Docente (Opcional)</option>
						{#each docentes as docente}
							<option value={docente.id_docente}>{docente.nombre_completo}</option>
						{/each}
					</select>
					<button 
						type="button" 
						class="btn-primary" 
						onclick={addSeccion}
						disabled={!newSeccionData.id_tipo_seccion || loadingSecciones || currentSecciones.length >= 3}
					>
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
						Agregar
					</button>
				</div>
				{#if currentSecciones.length >= 3}
					<p class="text-sm text-red-500 mt-2">Este curso ya tiene el máximo de 3 secciones permitidas.</p>
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

<style>
	.page-container {
		padding: 2rem;
		max-width: 1200px;
		margin: 0 auto;
	}

	.page-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 2rem;
	}

	.page-title {
		font-size: 1.875rem;
		font-weight: 700;
		color: #111827;
		margin: 0 0 0.25rem 0;
	}

	.page-description {
		color: #6b7280;
		font-size: 0.875rem;
		margin: 0;
	}

	.btn-primary {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.625rem 1.25rem;
		background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
		color: white;
		border: none;
		border-radius: 8px;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s;
		box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
	}

	.btn-primary:hover {
		transform: translateY(-1px);
		box-shadow: 0 4px 6px rgba(59, 130, 246, 0.4);
	}

	.form-group {
		margin-bottom: 1rem;
	}

	.form-row {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 1rem;
	}

	.form-label {
		display: block;
		font-size: 0.875rem;
		font-weight: 500;
		color: #374151;
		margin-bottom: 0.5rem;
	}

	.form-input {
		width: 100%;
		padding: 0.625rem 0.875rem;
		border: 1px solid #d1d5db;
		border-radius: 6px;
		font-size: 0.875rem;
		color: #111827;
		background-color: white;
		transition: all 0.2s;
	}

	.form-input:focus {
		outline: none;
		border-color: #3b82f6;
		box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
	}
	.secciones-list {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
		margin-bottom: 2rem;
	}

	.seccion-item {
		display: flex;
		align-items: center;
		gap: 1rem;
		padding: 0.75rem;
		background-color: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 0.5rem;
	}

	.seccion-info {
		min-width: 120px;
	}

	.badget { /* Correcting badge class name */
		display: inline-block;
		padding: 0.25rem 0.75rem;
		background-color: #e0e7ff;
		color: #4338ca;
		border-radius: 9999px;
		font-size: 0.75rem;
		font-weight: 600;
	}

	.seccion-docente {
		flex: 1;
	}

	.seccion-docente select {
		margin-bottom: 0;
	}

	.btn-icon {
		padding: 0.5rem;
		color: #ef4444;
		background: none;
		border: none;
		cursor: pointer;
		border-radius: 0.375rem;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.btn-icon:hover {
		background-color: #fee2e2;
	}

	.add-seccion-form {
		padding: 1rem;
		background-color: #f8fafc;
		border: 1px dashed #cbd5e1;
		border-radius: 0.5rem;
	}

	.add-seccion-form h4 {
		margin-top: 0;
		margin-bottom: 1rem;
		font-size: 0.875rem;
		font-weight: 600;
		color: #475569;
	}

	.btn-secondary {
		background-color: #ffffff;
		border: 1px solid #cbd5e1;
		color: #475569;
		padding: 0.5rem 1rem;
		border-radius: 0.375rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s;
	}

	.btn-secondary:hover:not(:disabled) {
		background-color: #f1f5f9;
		border-color: #94a3b8;
	}

	.btn-secondary:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.form-actions {
		display: flex;
		gap: 1rem;
		margin-top: 2rem;
		padding-top: 2rem;
		border-top: 1px solid #e5e7eb;
	}

	.btn-action {
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.625rem 1.25rem;
		border: 1px solid #d1d5db;
		border-radius: 0.5rem;
		font-size: 0.875rem;
		font-weight: 500;
		background-color: #ffffff;
		color: #374151;
		cursor: pointer;
		transition: all 0.2s;
	}

	.btn-action:hover {
		background-color: #f9fafb;
		border-color: #9ca3af;
	}

	.btn-inscriptions {
		background-color: #eff6ff;
		color: #1e40af;
		border-color: #93c5fd;
	}

	.btn-inscriptions:hover {
		background-color: #dbeafe;
		border-color: #60a5fa;
	}

	/* ── Toast notification ───────────────────────────────── */
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
		box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08);
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
		from { opacity: 0; transform: translateY(12px) scale(0.96); }
		to   { opacity: 1; transform: translateY(0)    scale(1);    }
	}

	/* ── Docente Display and Edit ──────────────────────── */
	.docente-display {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.75rem;
		background-color: #ffffff;
		border: 1px solid #e2e8f0;
		border-radius: 0.375rem;
	}

	.docente-name {
		flex: 1;
		font-size: 0.875rem;
		color: #1e293b;
		font-weight: 500;
	}

	.docente-empty {
		font-size: 0.875rem;
		color: #94a3b8;
		font-style: italic;
	}

	.btn-edit-docente {
		padding: 0.25rem;
		color: #0284c7;
		background: none;
		border: none;
		cursor: pointer;
		border-radius: 0.25rem;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: background-color 0.2s;
	}

	.btn-edit-docente:hover {
		background-color: #e0f2fe;
	}

	.edit-docente-form {
		padding: 1rem;
		background-color: #fafafa;
		border: 2px solid #3b82f6;
		border-radius: 0.5rem;
		margin: 1rem 0;
	}

	.form-group {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		margin-bottom: 1rem;
	}

	.form-group label {
		font-size: 0.875rem;
		font-weight: 600;
		color: #1e293b;
	}

	.form-group .form-input {
		padding: 0.625rem 0.75rem;
		border: 1px solid #cbd5e1;
		border-radius: 0.375rem;
		font-size: 0.875rem;
	}

	.form-actions {
		display: flex;
		gap: 0.75rem;
		margin-top: 1rem;
	}

	.btn-primary {
		background-color: #3b82f6;
		color: #ffffff;
		padding: 0.625rem 1.25rem;
		border: none;
		border-radius: 0.375rem;
		font-weight: 500;
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		transition: all 0.2s;
	}

	.btn-primary:hover:not(:disabled) {
		background-color: #2563eb;
	}

	.btn-primary:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.mr-2 {
		margin-right: 0.5rem;
	}

	/* ── Docente Details Panel ────────────────────────── */
	.docente-details-panel {
		padding: 1rem;
		background-color: #f0f9ff;
		border: 1px solid #bfdbfe;
		border-radius: 0.5rem;
		margin: 1rem 0;
	}

	.docente-details-panel h5 {
		margin: 0 0 1rem 0;
		font-size: 0.875rem;
		font-weight: 600;
		color: #1e40af;
	}

	.details-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 1rem;
	}

	.detail-item {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}

	.detail-label {
		font-size: 0.75rem;
		font-weight: 600;
		color: #64748b;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.detail-value {
		font-size: 0.875rem;
		color: #1e293b;
		font-weight: 500;
	}
</style>
</AdminLayout>
