<script lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/custom/admin/DataTable.svelte';
	import FormModal from '@/components/custom/admin/FormModal.svelte';
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
	import type { Asignatura, PaginatedResponse, AsignaturaFormData } from '@/types/admin.types';

	interface Props {
		asignaturas: PaginatedResponse<Asignatura>;
		filters: { search?: string };
	}

	let { asignaturas, filters }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
	let isLoading = $state(false);
	let editingAsignatura = $state<Asignatura | null>(null);
	let deletingAsignatura = $state<Asignatura | null>(null);

	let formData = $state<AsignaturaFormData>({
		cod_asignatura: '',
		nombre: '',
		descripcion: '',
		creditos_sct: 0,
		horas_catedra: 0,
		horas_taller: 0,
		horas_laboratorio: 0,
		horas_dirigidas: 0,
		horas_autonomas: 0
	});

	const columns = [
		{ key: 'cod_asignatura', label: 'Código' },
		{ key: 'nombre', label: 'Nombre' },
		{ key: 'creditos_sct', label: 'Créditos SCT' }
	];

	function openCreateModal() {
		editingAsignatura = null;
		formData = {
			cod_asignatura: '',
			nombre: '',
			descripcion: '',
			creditos_sct: 0,
			horas_catedra: 0,
			horas_taller: 0,
			horas_laboratorio: 0,
			horas_dirigidas: 0,
			horas_autonomas: 0
		};
		showModal = true;
	}

	function openEditModal(asignatura: Asignatura) {
		editingAsignatura = asignatura;
		formData = {
			cod_asignatura: asignatura.cod_asignatura,
			nombre: asignatura.nombre,
			descripcion: asignatura.descripcion || '',
			creditos_sct: asignatura.creditos_sct || 0,
			horas_catedra: asignatura.horas_catedra || 0,
			horas_taller: asignatura.horas_taller || 0,
			horas_laboratorio: asignatura.horas_laboratorio || 0,
			horas_dirigidas: asignatura.horas_dirigidas || 0,
			horas_autonomas: asignatura.horas_autonomas || 0
		};
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		editingAsignatura = null;
	}

	function handleSubmit() {
		isLoading = true;

		if (editingAsignatura) {
			router.put(`/admin/asignaturas/${editingAsignatura.id_asignatura}`, formData, {
				onSuccess: () => {
					closeModal();
					isLoading = false;
				},
				onError: () => {
					isLoading = false;
				}
			});
		} else {
			router.post('/admin/asignaturas', formData, {
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

	function openDeleteDialog(asignatura: Asignatura) {
		deletingAsignatura = asignatura;
		showDeleteDialog = true;
	}

	function closeDeleteDialog() {
		showDeleteDialog = false;
		deletingAsignatura = null;
	}

	function handleDelete() {
		if (!deletingAsignatura) return;

		isLoading = true;
		router.delete(`/admin/asignaturas/${deletingAsignatura.id_asignatura}`, {
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
			<h1 class="page-title">Asignaturas</h1>
			<p class="page-description">Gestión del catálogo de asignaturas</p>
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
			Nueva Asignatura
		</button>
	</div>

	<DataTable data={asignaturas} {columns} onEdit={openEditModal} onDelete={openDeleteDialog} />
</div>

<FormModal
	bind:isOpen={showModal}
	title={editingAsignatura ? 'Editar Asignatura' : 'Nueva Asignatura'}
	onClose={closeModal}
	onSubmit={handleSubmit}
	{isLoading}
>
	<div class="form-row">
		<div class="form-group">
			<label for="cod_asignatura" class="form-label">Código</label>
			<input
				id="cod_asignatura"
				type="text"
				bind:value={formData.cod_asignatura}
				class="form-input"
				placeholder="Ej: MED101"
				required
			/>
		</div>

		<div class="form-group">
			<label for="creditos_sct" class="form-label">Créditos SCT</label>
			<input
				id="creditos_sct"
				type="number"
				bind:value={formData.creditos_sct}
				class="form-input"
				min="0"
			/>
		</div>
	</div>

	<div class="form-group">
		<label for="nombre" class="form-label">Nombre</label>
		<input
			id="nombre"
			type="text"
			bind:value={formData.nombre}
			class="form-input"
			placeholder="Ej: Anatomía Humana"
			required
		/>
	</div>

	<div class="form-group">
		<label for="descripcion" class="form-label">Descripción</label>
		<textarea
			id="descripcion"
			bind:value={formData.descripcion}
			class="form-input"
			rows="3"
			placeholder="Descripción de la asignatura"
		></textarea>
	</div>

	<div class="form-row">
		<div class="form-group">
			<label for="horas_catedra" class="form-label">Horas Cátedra</label>
			<input
				id="horas_catedra"
				type="number"
				bind:value={formData.horas_catedra}
				class="form-input"
				min="0"
			/>
		</div>

		<div class="form-group">
			<label for="horas_taller" class="form-label">Horas Taller</label>
			<input
				id="horas_taller"
				type="number"
				bind:value={formData.horas_taller}
				class="form-input"
				min="0"
			/>
		</div>
	</div>

	<div class="form-row">
		<div class="form-group">
			<label for="horas_laboratorio" class="form-label">Horas Laboratorio</label>
			<input
				id="horas_laboratorio"
				type="number"
				bind:value={formData.horas_laboratorio}
				class="form-input"
				min="0"
			/>
		</div>

		<div class="form-group">
			<label for="horas_dirigidas" class="form-label">Horas Dirigidas</label>
			<input
				id="horas_dirigidas"
				type="number"
				bind:value={formData.horas_dirigidas}
				class="form-input"
				min="0"
			/>
		</div>
	</div>

	<div class="form-group">
		<label for="horas_autonomas" class="form-label">Horas Autónomas</label>
		<input
			id="horas_autonomas"
			type="number"
			bind:value={formData.horas_autonomas}
			class="form-input"
			min="0"
		/>
	</div>
</FormModal>

<DeleteConfirmation
	bind:isOpen={showDeleteDialog}
	title="¿Eliminar Asignatura?"
	message="Esta acción no se puede deshacer. Si la asignatura está asignada a planes, no podrá ser eliminada."
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

	textarea.form-input {
		resize: vertical;
		font-family: inherit;
	}
</style>
</AdminLayout>
