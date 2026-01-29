<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/admin/DataTable.svelte';
	import FormModal from '@/components/admin/FormModal.svelte';
	import DeleteConfirmation from '@/components/admin/DeleteConfirmation.svelte';
	import type {
		Carrera,
		Facultad,
		Departamento,
		PaginatedResponse,
		CarreraFormData
	} from '@/types/admin.types';

	interface Props {
		carreras: PaginatedResponse<Carrera>;
		facultades: Facultad[];
		filters: { search?: string; id_facultad?: number; id_departamento?: number };
	}

	let { carreras, facultades, filters }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
	let isLoading = $state(false);
	let editingCarrera = $state<Carrera | null>(null);
	let deletingCarrera = $state<Carrera | null>(null);
	let departamentos = $state<Departamento[]>([]);

	let formData = $state<CarreraFormData>({
		nombre: '',
		jornada: '',
		sede: '',
		modalidad: '',
		id_departamento: 0,
		id_facultad: 0
	});

	const columns = [
		{ key: 'id_carrera', label: 'ID' },
		{ key: 'nombre', label: 'Nombre' },
		{ key: 'jornada', label: 'Jornada' },
		{ key: 'sede', label: 'Sede' },
		{ key: 'departamento.nombre', label: 'Departamento' }
	];

	async function loadDepartamentos(id_facultad: number) {
		if (!id_facultad) {
			departamentos = [];
			return;
		}

		try {
			const response = await fetch(`/admin/facultades/${id_facultad}/departamentos`);
			departamentos = await response.json();
		} catch (error) {
			console.error('Error loading departamentos:', error);
			departamentos = [];
		}
	}

	function openCreateModal() {
		editingCarrera = null;
		formData = {
			nombre: '',
			jornada: '',
			sede: '',
			modalidad: '',
			id_departamento: 0,
			id_facultad: 0
		};
		departamentos = [];
		showModal = true;
	}

	async function openEditModal(carrera: Carrera) {
		editingCarrera = carrera;
		formData = {
			nombre: carrera.nombre,
			jornada: carrera.jornada || '',
			sede: carrera.sede || '',
			modalidad: carrera.modalidad || '',
			id_departamento: carrera.id_departamento,
			id_facultad: carrera.id_facultad
		};
		await loadDepartamentos(carrera.id_facultad);
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		editingCarrera = null;
		departamentos = [];
	}

	function handleSubmit() {
		isLoading = true;

		if (editingCarrera) {
			router.put(`/admin/carreras/${editingCarrera.id_carrera}`, formData, {
				onSuccess: () => {
					closeModal();
					isLoading = false;
				},
				onError: () => {
					isLoading = false;
				}
			});
		} else {
			router.post('/admin/carreras', formData, {
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

	function openDeleteDialog(carrera: Carrera) {
		deletingCarrera = carrera;
		showDeleteDialog = true;
	}

	function closeDeleteDialog() {
		showDeleteDialog = false;
		deletingCarrera = null;
	}

	function handleDelete() {
		if (!deletingCarrera) return;

		isLoading = true;
		router.delete(`/admin/carreras/${deletingCarrera.id_carrera}`, {
			onSuccess: () => {
				closeDeleteDialog();
				isLoading = false;
			},
			onError: () => {
				isLoading = false;
			}
		});
	}

	$effect(() => {
		if (formData.id_facultad) {
			loadDepartamentos(formData.id_facultad);
		}
	});
</script>


<AdminLayout>
<div class="page-container">
	<div class="page-header">
		<div>
			<h1 class="page-title">Carreras</h1>
			<p class="page-description">Gestión de carreras por departamento</p>
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
			Nueva Carrera
		</button>
	</div>

	<DataTable data={carreras} {columns} onEdit={openEditModal} onDelete={openDeleteDialog} />
</div>

<FormModal
	bind:isOpen={showModal}
	title={editingCarrera ? 'Editar Carrera' : 'Nueva Carrera'}
	onClose={closeModal}
	onSubmit={handleSubmit}
	{isLoading}
>
	<div class="form-group">
		<label for="nombre" class="form-label">Nombre</label>
		<input
			id="nombre"
			type="text"
			bind:value={formData.nombre}
			class="form-input"
			placeholder="Ej: Medicina"
			required
		/>
	</div>

	<div class="form-row">
		<div class="form-group">
			<label for="jornada" class="form-label">Jornada</label>
			<input
				id="jornada"
				type="text"
				bind:value={formData.jornada}
				class="form-input"
				placeholder="Ej: Diurna"
			/>
		</div>

		<div class="form-group">
			<label for="sede" class="form-label">Sede</label>
			<input
				id="sede"
				type="text"
				bind:value={formData.sede}
				class="form-input"
				placeholder="Ej: Campus Central"
			/>
		</div>
	</div>

	<div class="form-group">
		<label for="modalidad" class="form-label">Modalidad</label>
		<input
			id="modalidad"
			type="text"
			bind:value={formData.modalidad}
			class="form-input"
			placeholder="Ej: Presencial"
		/>
	</div>

	<div class="form-group">
		<label for="facultad" class="form-label">Facultad</label>
		<select id="facultad" bind:value={formData.id_facultad} class="form-input" required>
			<option value={0}>Seleccione una facultad</option>
			{#each facultades as facultad}
				<option value={facultad.id_facultad}>{facultad.nombre}</option>
			{/each}
		</select>
	</div>

	<div class="form-group">
		<label for="departamento" class="form-label">Departamento</label>
		<select
			id="departamento"
			bind:value={formData.id_departamento}
			class="form-input"
			disabled={!formData.id_facultad}
			required
		>
			<option value={0}>Seleccione un departamento</option>
			{#each departamentos as departamento}
				<option value={departamento.id_departamento}>{departamento.nombre}</option>
			{/each}
		</select>
	</div>
</FormModal>

<DeleteConfirmation
	bind:isOpen={showDeleteDialog}
	title="¿Eliminar Carrera?"
	message="Esta acción no se puede deshacer. Si la carrera tiene planes o estudiantes asociados, no podrá ser eliminada."
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

	.form-input:disabled {
		background: #f3f4f6;
		cursor: not-allowed;
	}
</style>
</AdminLayout>
