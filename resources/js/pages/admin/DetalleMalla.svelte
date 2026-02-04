<script lang="ts">
	import { router } from '@inertiajs/svelte';
	import type { Plan, Asignatura, AsignacionPlan, AsignacionPlanFormData, MallaData } from '@/types/admin.types';
	import AdminLayout from '@/layouts/AdminLayout.svelte';
	import FormModal from '@/components/custom/admin/FormModal.svelte';
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';

	interface Props {
		plan: Plan;
		malla: MallaData;
		asignaturas: Asignatura[];
	}

	let { plan, malla, asignaturas }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
	let isLoading = $state(false);
	let editingAsignacion = $state<AsignacionPlan | null>(null);
	let deletingAsignacion = $state<AsignacionPlan | null>(null);

	let formData = $state<AsignacionPlanFormData>({
		id_asignatura: 0,
		agno_planificado: 1,
		semestre_planificado: 1,
		tipo_ramo: ''
	});

	// Organize malla by year
	const mallaByYear = $derived(() => {
		const years: { [year: number]: { semestre1: AsignacionPlan[], semestre2: AsignacionPlan[] } } = {};
		
		Object.entries(malla).forEach(([key, asignaciones]) => {
			asignaciones.forEach(asig => {
				if (!years[asig.agno_planificado]) {
					years[asig.agno_planificado] = { semestre1: [], semestre2: [] };
				}
				if (asig.semestre_planificado === 1) {
					years[asig.agno_planificado].semestre1.push(asig);
				} else {
					years[asig.agno_planificado].semestre2.push(asig);
				}
			});
		});
		
		return years;
	});

	function openCreateModal() {
		editingAsignacion = null;
		formData = {
			id_asignatura: 0,
			agno_planificado: 1,
			semestre_planificado: 1,
			tipo_ramo: ''
		};
		showModal = true;
	}

	function openEditModal(asignacion: AsignacionPlan) {
		editingAsignacion = asignacion;
		formData = {
			id_asignatura: asignacion.id_asignatura,
			agno_planificado: asignacion.agno_planificado,
			semestre_planificado: asignacion.semestre_planificado,
			tipo_ramo: asignacion.tipo_ramo || ''
		};
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		editingAsignacion = null;
	}

	function handleSubmit() {
		if (formData.id_asignatura === 0) return;

		isLoading = true;

		if (editingAsignacion) {
			router.put(`/admin/planes/${plan.id_plan}/asignaturas/${editingAsignacion.id_asignatura}`, formData, {
				onSuccess: () => {
					closeModal();
					isLoading = false;
				},
				onError: () => {
					isLoading = false;
				}
			});
		} else {
			router.post(`/admin/planes/${plan.id_plan}/asignaturas`, formData, {
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

	function openDeleteDialog(asignacion: AsignacionPlan) {
		deletingAsignacion = asignacion;
		showDeleteDialog = true;
	}

	function closeDeleteDialog() {
		showDeleteDialog = false;
		deletingAsignacion = null;
	}

	function handleDelete() {
		if (!deletingAsignacion) return;

		isLoading = true;
		router.delete(`/admin/planes/${plan.id_plan}/asignaturas/${deletingAsignacion.id_asignatura}`, {
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
		<!-- Header -->
		<div class="page-header">
			<div>
				<h1 class="page-title">Malla Curricular</h1>
				<p class="page-description">
					{plan.carrera?.nombre} - Año {plan.agno} v{plan.version}
				</p>
			</div>
			<div class="header-actions">
				<div class="credits-badge">
					<span class="credits-label">Total Créditos SCT:</span>
					<span class="credits-value">{plan.creditos_sct_totales || 0}</span>
				</div>
				<button onclick={openCreateModal} class="btn-primary">
					+ Agregar Asignatura
				</button>
			</div>
		</div>

		<!-- Malla Grid -->
		<div class="malla-container">
			{#each Object.entries(mallaByYear()).sort(([a], [b]) => Number(a) - Number(b)) as [year, semesters]}
				<div class="year-section">
					<h2 class="year-title">Año {year}</h2>
					<div class="semesters-grid">
						<!-- Semestre 1 -->
						<div class="semester-column">
							<h3 class="semester-title">Semestre 1</h3>
							<div class="asignaturas-list">
								{#if semesters.semestre1.length === 0}
									<p class="empty-message">Sin asignaturas</p>
								{:else}
									{#each semesters.semestre1 as asignacion}
										<div class="asignatura-card">
											<div class="asignatura-header">
												<span class="asignatura-code">{asignacion.asignatura?.cod_asignatura}</span>
												<span class="asignatura-credits">{asignacion.asignatura?.creditos_sct || 0} SCT</span>
											</div>
											<p class="asignatura-name">{asignacion.asignatura?.nombre}</p>
											{#if asignacion.tipo_ramo}
												<span class="asignatura-type">{asignacion.tipo_ramo}</span>
											{/if}
											<div class="asignatura-actions">
												<button onclick={() => openEditModal(asignacion)} class="btn-edit">Editar</button>
												<button onclick={() => openDeleteDialog(asignacion)} class="btn-delete">Eliminar</button>
											</div>
										</div>
									{/each}
								{/if}
							</div>
						</div>

						<!-- Semestre 2 -->
						<div class="semester-column">
							<h3 class="semester-title">Semestre 2</h3>
							<div class="asignaturas-list">
								{#if semesters.semestre2.length === 0}
									<p class="empty-message">Sin asignaturas</p>
								{:else}
									{#each semesters.semestre2 as asignacion}
										<div class="asignatura-card">
											<div class="asignatura-header">
												<span class="asignatura-code">{asignacion.asignatura?.cod_asignatura}</span>
												<span class="asignatura-credits">{asignacion.asignatura?.creditos_sct || 0} SCT</span>
											</div>
											<p class="asignatura-name">{asignacion.asignatura?.nombre}</p>
											{#if asignacion.tipo_ramo}
												<span class="asignatura-type">{asignacion.tipo_ramo}</span>
											{/if}
											<div class="asignatura-actions">
												<button onclick={() => openEditModal(asignacion)} class="btn-edit">Editar</button>
												<button onclick={() => openDeleteDialog(asignacion)} class="btn-delete">Eliminar</button>
											</div>
										</div>
									{/each}
								{/if}
							</div>
						</div>
					</div>
				</div>
			{/each}

			{#if Object.keys(mallaByYear()).length === 0}
				<div class="empty-state">
					<p>No hay asignaturas asignadas a este plan.</p>
					<button onclick={openCreateModal} class="btn-primary">Agregar Primera Asignatura</button>
				</div>
			{/if}
		</div>
	</div>

	<!-- Add/Edit Modal -->
	<FormModal
		bind:isOpen={showModal}
		title={editingAsignacion ? 'Editar Asignación' : 'Agregar Asignatura'}
		onClose={closeModal}
		onSubmit={handleSubmit}
		{isLoading}
	>
		<div class="form-group">
			<label for="id_asignatura" class="form-label">Asignatura *</label>
			<select
				id="id_asignatura"
				bind:value={formData.id_asignatura}
				class="form-input"
				required
				disabled={!!editingAsignacion}
			>
				<option value={0}>Seleccione una asignatura</option>
				{#each asignaturas as asignatura}
					<option value={asignatura.id_asignatura}>
						{asignatura.cod_asignatura} - {asignatura.nombre}
					</option>
				{/each}
			</select>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="agno_planificado" class="form-label">Año *</label>
				<select
					id="agno_planificado"
					bind:value={formData.agno_planificado}
					class="form-input"
					required
				>
					{#each Array.from({ length: 10 }, (_, i) => i + 1) as year}
						<option value={year}>{year}</option>
					{/each}
				</select>
			</div>

			<div class="form-group">
				<label for="semestre_planificado" class="form-label">Semestre *</label>
				<select
					id="semestre_planificado"
					bind:value={formData.semestre_planificado}
					class="form-input"
					required
				>
					<option value={1}>1</option>
					<option value={2}>2</option>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="tipo_ramo" class="form-label">Tipo de Ramo</label>
			<select
				id="tipo_ramo"
				bind:value={formData.tipo_ramo}
				class="form-input"
			>
				<option value="">Seleccione un tipo (opcional)</option>
				<option value="Electivo Profesional">Electivo Profesional</option>
				<option value="Plan Común">Plan Común</option>
				<option value="Formación Profesional">Formación Profesional</option>
			</select>
		</div>
	</FormModal>

	<!-- Delete Confirmation -->
	<DeleteConfirmation
		bind:isOpen={showDeleteDialog}
		title="¿Eliminar Asignatura del Plan?"
		message="Esta acción no se puede deshacer. La asignatura será removida de este plan."
		onConfirm={handleDelete}
		onCancel={closeDeleteDialog}
		{isLoading}
	/>
</AdminLayout>

<style>
	.page-container {
		padding: 2rem;
		max-width: 1400px;
		margin: 0 auto;
	}

	.page-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 2rem;
		gap: 2rem;
	}

	.page-title {
		font-size: 1.875rem;
		font-weight: 700;
		color: #111827;
		margin: 0;
	}

	.page-description {
		color: #6b7280;
		margin-top: 0.5rem;
		font-size: 1rem;
	}

	.header-actions {
		display: flex;
		gap: 1rem;
		align-items: center;
	}

	.credits-badge {
		background: #eff6ff;
		padding: 0.75rem 1.5rem;
		border-radius: 8px;
		border: 2px solid #3b82f6;
	}

	.credits-label {
		color: #1e40af;
		font-size: 0.875rem;
		font-weight: 500;
		margin-right: 0.5rem;
	}

	.credits-value {
		color: #1e40af;
		font-size: 1.5rem;
		font-weight: 700;
	}

	.btn-primary {
		padding: 0.75rem 1.5rem;
		background: #3b82f6;
		color: white;
		border: none;
		border-radius: 6px;
		font-weight: 600;
		cursor: pointer;
		transition: background 0.2s;
		white-space: nowrap;
	}

	.btn-primary:hover {
		background: #2563eb;
	}

	.malla-container {
		display: flex;
		flex-direction: column;
		gap: 2rem;
	}

	.year-section {
		background: white;
		border-radius: 12px;
		padding: 1.5rem;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}

	.year-title {
		font-size: 1.5rem;
		font-weight: 700;
		color: #111827;
		margin: 0 0 1.5rem 0;
		padding-bottom: 0.75rem;
		border-bottom: 2px solid #e5e7eb;
	}

	.semesters-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 1.5rem;
	}

	.semester-column {
		min-height: 200px;
	}

	.semester-title {
		font-size: 1.125rem;
		font-weight: 600;
		color: #374151;
		margin: 0 0 1rem 0;
		padding: 0.5rem 0.75rem;
		background: #f9fafb;
		border-radius: 6px;
	}

	.asignaturas-list {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	.asignatura-card {
		background: #f9fafb;
		border: 1px solid #e5e7eb;
		border-radius: 8px;
		padding: 1rem;
		transition: all 0.2s;
	}

	.asignatura-card:hover {
		border-color: #3b82f6;
		box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
	}

	.asignatura-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 0.5rem;
	}

	.asignatura-code {
		font-family: 'Courier New', monospace;
		font-weight: 600;
		color: #3b82f6;
		font-size: 0.875rem;
	}

	.asignatura-credits {
		background: #dbeafe;
		color: #1e40af;
		padding: 0.25rem 0.75rem;
		border-radius: 12px;
		font-size: 0.75rem;
		font-weight: 600;
	}

	.asignatura-name {
		color: #111827;
		font-weight: 500;
		margin: 0 0 0.5rem 0;
		font-size: 0.875rem;
	}

	.asignatura-type {
		display: inline-block;
		background: #f3f4f6;
		color: #6b7280;
		padding: 0.25rem 0.5rem;
		border-radius: 4px;
		font-size: 0.75rem;
		margin-bottom: 0.5rem;
	}

	.asignatura-actions {
		display: flex;
		gap: 0.5rem;
		margin-top: 0.75rem;
	}

	.btn-edit,
	.btn-delete {
		padding: 0.375rem 0.75rem;
		border: none;
		border-radius: 4px;
		font-size: 0.75rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s;
	}

	.btn-edit {
		background: #eff6ff;
		color: #1d4ed8;
	}

	.btn-edit:hover {
		background: #dbeafe;
	}

	.btn-delete {
		background: #fef2f2;
		color: #dc2626;
	}

	.btn-delete:hover {
		background: #fee2e2;
	}

	.empty-message {
		color: #9ca3af;
		text-align: center;
		padding: 2rem 1rem;
		font-style: italic;
	}

	.empty-state {
		text-align: center;
		padding: 4rem 2rem;
		background: white;
		border-radius: 12px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}

	.empty-state p {
		color: #6b7280;
		margin-bottom: 1.5rem;
		font-size: 1.125rem;
	}

	.form-row {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 1rem;
	}

	.form-group {
		margin-bottom: 1rem;
	}

	.form-label {
		display: block;
		font-weight: 500;
		color: #374151;
		margin-bottom: 0.5rem;
		font-size: 0.875rem;
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

	@media (max-width: 768px) {
		.page-header {
			flex-direction: column;
		}

		.header-actions {
			width: 100%;
			flex-direction: column;
		}

		.semesters-grid {
			grid-template-columns: 1fr;
		}

		.form-row {
			grid-template-columns: 1fr;
		}
	}
</style>
