<script lang="ts">
	import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/custom/admin/DataTable.svelte';
	import FormModal from '@/components/custom/admin/FormModal.svelte';
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
	import type {
		Plan,
		Carrera,
		PaginatedResponse,
		PlanFormData
	} from '@/types/admin.types';

	interface Props {
		planes: PaginatedResponse<Plan>;
		carreras: Carrera[];
		filters: { search?: string; id_carrera?: number };
	}

	let { planes, carreras, filters }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
	let isLoading = $state(false);
	let editingPlan = $state<Plan | null>(null);
	let deletingPlan = $state<Plan | null>(null);

	let formData = $state<PlanFormData>({
		id_carrera: 0,
		agno: new Date().getFullYear(),
		version: 1
	});

	const columns = [
		{ key: 'id_plan', label: 'ID' },
		{ key: 'carrera.nombre', label: 'Carrera' },
		{ key: 'agno', label: 'Año' },
		{ key: 'version', label: 'Versión' },
		{ key: 'creditos_sct_totales', label: 'Créditos SCT' }
	];

	function openCreateModal() {
		editingPlan = null;
		formData = {
			id_carrera: 0,
			agno: new Date().getFullYear(),
			version: 1
		};
		showModal = true;
	}

	function openEditModal(plan: Plan) {
		editingPlan = plan;
		formData = {
			id_carrera: plan.id_carrera,
			agno: plan.agno,
			version: plan.version
		};
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		editingPlan = null;
	}

	function handleSubmit() {
		isLoading = true;

		if (editingPlan) {
			router.put(`/admin/planes/${editingPlan.id_plan}`, formData, {
				onSuccess: () => {
					closeModal();
					isLoading = false;
				},
				onError: () => {
					isLoading = false;
				}
			});
		} else {
			router.post('/admin/planes', formData, {
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

	function openDeleteDialog(plan: Plan) {
		deletingPlan = plan;
		showDeleteDialog = true;
	}

	function closeDeleteDialog() {
		showDeleteDialog = false;
		deletingPlan = null;
	}

	function handleDelete() {
		if (!deletingPlan) return;

		isLoading = true;
		router.delete(`/admin/planes/${deletingPlan.id_plan}`, {
			onSuccess: () => {
				closeDeleteDialog();
				isLoading = false;
			},
			onError: () => {
				isLoading = false;
			}
		});
	}

	function verMalla(plan: Plan) {
		router.visit(`/admin/planes/${plan.id_plan}/asignaturas`);
	}
</script>

<AdminLayout>
<div class="page-container">
	<div class="page-header">
		<div>
			<h1 class="page-title">Planes de Estudio</h1>
			<p class="page-description">Gestión de planes de estudio por carrera</p>
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
			Nuevo Plan
		</button>
	</div>

	<DataTable 
		data={planes} 
		{columns} 
		onEdit={openEditModal} 
		onDelete={openDeleteDialog}
		onCustomAction={verMalla}
		customActionLabel="Ver Malla"
	/>
</div>

<FormModal
	bind:isOpen={showModal}
	title={editingPlan ? 'Editar Plan' : 'Nuevo Plan'}
	onClose={closeModal}
	onSubmit={handleSubmit}
	{isLoading}
>
	<div class="form-group">
		<label for="carrera" class="form-label">Carrera *</label>
		<select id="carrera" bind:value={formData.id_carrera} class="form-input" required>
			<option value={0}>Seleccione una carrera</option>
			{#each carreras as carrera}
				<option value={carrera.id_carrera}>{carrera.nombre}</option>
			{/each}
		</select>
	</div>

	<div class="form-row">
		<div class="form-group">
			<label for="agno" class="form-label">Año *</label>
			<input
				id="agno"
				type="number"
				bind:value={formData.agno}
				class="form-input"
				min="1900"
				max="2100"
				placeholder="Ej: 2024"
				required
			/>
		</div>

		<div class="form-group">
			<label for="version" class="form-label">Versión *</label>
			<input
				id="version"
				type="number"
				bind:value={formData.version}
				class="form-input"
				min="1"
				placeholder="Ej: 1"
				required
			/>
		</div>
	</div>

	<div class="form-group">
		<label for="creditos" class="form-label">Créditos SCT Totales</label>
		<div class="credits-display">
			<span class="credits-value">{editingPlan?.creditos_sct_totales || 0}</span>
			<span class="credits-note">(Calculado automáticamente)</span>
		</div>
	</div>
</FormModal>

<DeleteConfirmation
	bind:isOpen={showDeleteDialog}
	title="¿Eliminar Plan?"
	message="Esta acción no se puede deshacer. Si el plan tiene asignaturas asignadas, no podrá ser eliminado."
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

	.credits-display {
		padding: 0.75rem 1rem;
		background: #f9fafb;
		border: 1px solid #e5e7eb;
		border-radius: 6px;
		display: flex;
		align-items: center;
		gap: 0.75rem;
	}

	.credits-value {
		font-size: 1.5rem;
		font-weight: 700;
		color: #3b82f6;
	}

	.credits-note {
		font-size: 0.75rem;
		color: #6b7280;
		font-style: italic;
	}
</style>
</AdminLayout>
