<script lang="ts">
	import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/custom/admin/DataTable.svelte';
	import FormModal from '@/components/custom/admin/FormModal.svelte';
	import CourseTeamModal from '@/components/custom/admin/CourseTeamModal.svelte';
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
	import axios from 'axios';
	import type {
		Curso,
		Asignatura,
		Plan,
		Docente,
		PaginatedResponse,
		CursoFormData,
		Seccion, // Assuming type exists or I need to add it
		TipoSeccion
	} from '@/types/admin.types';

	interface Props {
		cursos: PaginatedResponse<Curso>;
		asignaturas: Asignatura[];
		planes: Plan[];
		availableRoles: any[];
		availablePermissions: Record<string, any[]>;
		filters: { search?: string; id_asignatura?: number };
		tipos_seccion: TipoSeccion[];
	}

	let { cursos, asignaturas, planes, filters, availableRoles = [], availablePermissions = {}, tipos_seccion = [] }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
    let showTeamModal = $state(false);
	let isLoading = $state(false);
	let editingCurso = $state<Curso | null>(null);
	let deletingCurso = $state<Curso | null>(null);
    let managingTeamCurso = $state<Curso | null>(null);
	let docentes = $state<Docente[]>([]);
	let availableAsignaturas = $state<Asignatura[]>([]);
	let loadingAsignaturas = $state(false);

	let currentSecciones = $state<Seccion[]>([]);
	let newSeccionData = $state({
		id_tipo_seccion: undefined,
		id_docente: undefined
	});
	let loadingSecciones = $state(false);

	let formData = $state<CursoFormData>({
		id_asignatura: 0,
		id_plan: 0,
		cod_curso: 0,
		nombre: '',
		fecha_inicio: '',
		id_docente: undefined // Kept for legacy compatibility or removal
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
			const response = await fetch('/admin/usuarios?tipo=docente', {
				headers: {
					'Accept': 'application/json'
				}
			});
			const data = await response.json();
			docentes = data.data || [];
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
		editingCurso = null;
		formData = {
			id_asignatura: 0,
			id_plan: 0,
			cod_curso: 0,
			nombre: '',
			fecha_inicio: '',
			numero_semestre: undefined,
			id_docente: undefined
		};
		availableAsignaturas = [];
		loadDocentes();
		showModal = true;
	}

	function openEditModal(curso: Curso) {
		editingCurso = curso;
		formData = {
			id_asignatura: curso.id_asignatura,
			id_plan: curso.id_plan,
			cod_curso: curso.cod_curso,
			nombre: curso.nombre || '',
			fecha_inicio: curso.fecha_inicio || '',
			numero_semestre: curso.numero_semestre,
			id_docente: curso.id_docente
		};
		// Load asignaturas for the selected plan
		if (curso.id_plan) {
			loadAsignaturasByPlan(curso.id_plan);
		}
		loadDocentes();
		
		// Load Secciones
		loadSecciones(curso.id_curso);
		
		showModal = true;
	}

	async function loadSecciones(cursoId: number) {
		loadingSecciones = true;
		try {
			// Fetch fresh course data including sections
			const response = await axios.get(`/admin/cursos/${cursoId}`);
			// Assuming response.data.secciones exists due to my backend change
			if (response.data.secciones) {
				currentSecciones = response.data.secciones;
			}
		} catch (error) {
			console.error("Error loading secciones:", error);
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
			alert("Error al agregar sección: " + (error.response?.data?.error || error.message));
		}
	}

	async function deleteSeccion(seccionId: number) {
		if (!confirm('¿Estás seguro de eliminar esta sección?')) return;
		
		try {
			await axios.delete(`/admin/cursos/secciones/${seccionId}`);
			currentSecciones = currentSecciones.filter(s => s.id_seccion !== seccionId);
		} catch (error) {
			console.error("Error deleting seccion:", error);
			alert("Error al eliminar sección: " + (error.response?.data?.error || error.message));
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
			alert("Error al actualizar sección: " + (error.response?.data?.error || error.message));
		}
	}

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
    />
</div>

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
							<select 
								bind:value={seccion.id_docente} 
								onchange={() => updateSeccionDocente(seccion)}
								class="form-input"
							>
								<option value={null}>Sin docente</option>
								{#each docentes as docente}
									<option value={docente.id_docente}>{docente.nombre_completo}</option>
								{/each}
							</select>
						</div>
						<button type="button" class="btn-icon" onclick={() => deleteSeccion(seccion.id_seccion)} title="Eliminar Sección">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
						</button>
					</div>
				{/each}
			</div>

			<div class="add-seccion-form">
				<h4>Agregar Sección</h4>
				<div class="form-row">
					<select bind:value={newSeccionData.id_tipo_seccion} class="form-input" disabled={availableTiposSeccion.length === 0 || currentSecciones.length >= 2}>
						<option value={undefined}>
							{#if currentSecciones.length >= 2}
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
					<select bind:value={newSeccionData.id_docente} class="form-input" disabled={currentSecciones.length >= 2}>
						<option value={undefined}>Docente (Opcional)</option>
						{#each docentes as docente}
							<option value={docente.id_docente}>{docente.nombre_completo}</option>
						{/each}
					</select>
					<button 
						type="button" 
						class="btn-primary" 
						onclick={addSeccion}
						disabled={!newSeccionData.id_tipo_seccion || loadingSecciones || currentSecciones.length >= 2}
					>
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
						Agregar
					</button>
				</div>
				{#if currentSecciones.length >= 2}
					<p class="text-sm text-red-500 mt-2">Este curso ya tiene el máximo de 2 secciones permitidas.</p>
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
</style>
</AdminLayout>
