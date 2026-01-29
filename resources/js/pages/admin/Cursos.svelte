<script lang="ts">
	import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/admin/DataTable.svelte';
	import FormModal from '@/components/admin/FormModal.svelte';
	import CourseTeamModal from '@/components/admin/CourseTeamModal.svelte';
	import DeleteConfirmation from '@/components/admin/DeleteConfirmation.svelte';
	import type {
		Curso,
		Asignatura,
		Plan,
		Docente,
		PaginatedResponse,
		CursoFormData
	} from '@/types/admin.types';

	interface Props {
		cursos: PaginatedResponse<Curso>;
		asignaturas: Asignatura[];
		planes: Plan[];
		filters: { search?: string; id_asignatura?: number };
	}

	let { cursos, asignaturas, planes, filters }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
    let showTeamModal = $state(false);
	let isLoading = $state(false);
	let editingCurso = $state<Curso | null>(null);
	let deletingCurso = $state<Curso | null>(null);
    let managingTeamCurso = $state<Curso | null>(null);
	let docentes = $state<Docente[]>([]);

	let formData = $state<CursoFormData>({
		id_asignatura: 0,
		id_plan: 0,
		cod_curso: '',
		nombre: '',
		fecha_inicio: '',
		numero_semestre: undefined,
		id_docente: undefined
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

	function openCreateModal() {
		editingCurso = null;
		formData = {
			id_asignatura: 0,
			id_plan: 0,
			cod_curso: '',
			nombre: '',
			fecha_inicio: '',
			numero_semestre: undefined,
			id_docente: undefined
		};
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
		loadDocentes();
		showModal = true;
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

		isLoading = true;

		if (editingCurso) {
			router.put(`/admin/cursos/${editingCurso.id_curso}`, formData, {
				onSuccess: () => {
					closeModal();
					isLoading = false;
				},
				onError: () => {
					isLoading = false;
				}
			});
		} else {
			router.post('/admin/cursos', formData, {
				onSuccess: () => {
					closeModal();
					isLoading = false;
				},
				onError: () => {
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
		<label for="asignatura" class="form-label">Asignatura *</label>
		<select id="asignatura" bind:value={formData.id_asignatura} class="form-input" required>
			<option value={0}>Seleccione una asignatura</option>
			{#each asignaturas as asignatura}
				<option value={asignatura.id_asignatura}>
					{asignatura.cod_asignatura} - {asignatura.nombre}
				</option>
			{/each}
		</select>
	</div>

	<div class="form-group">
		<label for="plan" class="form-label">Plan *</label>
		<select id="plan" bind:value={formData.id_plan} class="form-input" required>
			<option value={0}>Seleccione un plan</option>
			{#each planes as plan}
				<option value={plan.id_plan}>
					{plan.carrera?.nombre} - {plan.agno} v{plan.version}
				</option>
			{/each}
		</select>
	</div>

	<div class="form-row">
		<div class="form-group">
			<label for="cod_curso" class="form-label">Código de Curso *</label>
			<input
				id="cod_curso"
				type="text"
				bind:value={formData.cod_curso}
				class="form-input"
				placeholder="Ej: MED-2024-1"
				required
			/>
		</div>

		<div class="form-group">
			<label for="numero_semestre" class="form-label">Semestre</label>
			<input
				id="numero_semestre"
				type="number"
				bind:value={formData.numero_semestre}
				class="form-input"
				min="1"
				placeholder="Ej: 1"
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
		<label for="docente" class="form-label">Docente Asignado</label>
		<select id="docente" bind:value={formData.id_docente} class="form-input">
			<option value={undefined}>Sin asignar</option>
			{#each docentes as docente}
				<option value={docente.id_docente}>
					{docente.nombre_completo}
				</option>
			{/each}
		</select>
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
</style>
</AdminLayout>
