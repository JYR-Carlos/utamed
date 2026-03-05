<script lang="ts">
    /**
     * Página de administración de asignaturas globales.
     * 
     * Permite crear, leer, actualizar y eliminar asignaturas que pueden
     * ser asignadas a múltiples planes de estudio.
     * 
     * Características:
     * - Tabla paginada con búsqueda por código y nombre
     * - Formulario modal para crear/editar asignaturas
     * - Validación de campos (horas, créditos, etc.)
     * - Confirmación antes de eliminación
     * 
     * Tabla relacionada:
     * - administrativo.asignatura: Almacena información global de asignaturas
     */
    import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/custom/admin/DataTable.svelte';
	import FormModal from '@/components/custom/admin/FormModal.svelte';
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
	import type { Asignatura, PaginatedResponse, AsignaturaFormData } from '@/types/admin.types';
	import { useForm } from '@inertiajs/svelte';
	/**
	 * Props recibidas del servidor.
	 */
	interface Props {
		/** Asignaturas paginadas del servidor */
		asignaturas: PaginatedResponse<Asignatura>;
		/** Filtros aplicados (búsqueda) */
		filters: { search?: string };
	}

	let { asignaturas, filters }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
	let editingAsignatura = $state<Asignatura | null>(null);
	let deletingAsignatura = $state<Asignatura | null>(null);
	

	// Uso de useForm para manejar el estado del formulario
	let formData = useForm({
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
		{ key: 'creditos_sct', label: 'Créditos SCT' },
		{ key: 'planes_count', label: 'Uso en Planes' }
	];

	function openCreateModal() {
		editingAsignatura = null;
		$formData.reset();
		$formData.clearErrors();
		showModal = true;
	}

	function openEditModal(asignatura: Asignatura) {
		editingAsignatura = asignatura;
		$formData.defaults({
			cod_asignatura: asignatura.cod_asignatura,
			nombre: asignatura.nombre,
			descripcion: asignatura.descripcion || '',
			creditos_sct: asignatura.creditos_sct || 0,
			horas_catedra: asignatura.horas_catedra || 0,
			horas_taller: asignatura.horas_taller || 0,
			horas_laboratorio: asignatura.horas_laboratorio || 0,
			horas_dirigidas: asignatura.horas_dirigidas || 0,
			horas_autonomas: asignatura.horas_autonomas || 0
		});
		$formData.reset();
		showModal = true;
	}



	function handleSubmit() {

		const url = editingAsignatura ? `/admin/asignaturas/{editingAsignatura.id_asignatura}` : '/admin/asignaturas';
		
		const method = editingAsignatura ? 'put' : 'post';

		$formData[method](url, {
			onSuccess: () => {
				showModal = false;
				editingAsignatura = null;
			},
			onError: () => {
				console.error('Error al guardar la asignatura:', formData.errors);
			}
		})
	}

	function openDeleteDialog(asignatura: Asignatura) {
		deletingAsignatura = asignatura;
		showDeleteDialog = true;
	}



	function handleDelete() {
		if (!deletingAsignatura) return;

		$formData.delete(`/admin/asignaturas/${deletingAsignatura.id_asignatura}`, {
			onSuccess: () => {
				showDeleteDialog = false;
				deletingAsignatura = null;
			},
			onError: () => {
				console.error('Error al eliminar la asignatura:', formData.errors.nombre);
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

	<DataTable data={asignaturas} {columns} onEdit={openEditModal} onDelete={openDeleteDialog}>
		{#snippet cellSnippet({ item, column })}
			{#if column.key === 'planes_count'}
				{@const count = item.planes_count ?? 0}
				<span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full
					{count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'}">
					{count > 0 ? `Utilizada en ${count} plan${count === 1 ? '' : 'es'}` : 'Sin asignar'}
				</span>
			{:else}
				{item[column.key] ?? '-'}
			{/if}
		{/snippet}
	</DataTable>
</div>

<FormModal
	bind:isOpen={showModal}
	title={editingAsignatura ? 'Editar Asignatura' : 'Nueva Asignatura'}
	onClose={() => showModal = false}
	onSubmit={handleSubmit}
	isLoading={$formData.processing}
>
	<!-- Advertencia de impacto cuando la asignatura ya está en uso -->
	{#if editingAsignatura && (editingAsignatura.planes_count ?? 0) > 0}
		<div class="mb-4 flex gap-3 items-start bg-amber-50 border border-amber-300 rounded-lg px-4 py-3">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-500 mt-0.5 shrink-0"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
			<div>
				<p class="text-sm font-semibold text-amber-800">Impacto en cascada</p>
				<p class="text-xs text-amber-700 mt-0.5">
					Esta asignatura está utilizada en <strong>{editingAsignatura.planes_count} plan{editingAsignatura.planes_count === 1 ? '' : 'es'} de estudio</strong>.
					Cualquier cambio en el nombre, código o créditos afectará directamente a todos los cursos derivados.
				</p>
			</div>
		</div>
	{/if}
	<div class="form-row">
		<div class="form-group">
			<label for="cod_asignatura" class="form-label">Código</label>
			<input
				id="cod_asignatura"
				type="text"
				bind:value={$formData.cod_asignatura}
				class="form-input"
				class:border-red-500={$formData.errors.cod_asignatura}
				placeholder="Ej: MED101"
				required
			/>
			{#if $formData.errors.cod_asignatura}
				<p class="text-red-500 text-sm">{$formData.errors.cod_asignatura}</p>
			{/if}
		</div>

		<div class="form-group">
			<label for="creditos_sct" class="form-label">Créditos SCT</label>
			<input
				id="creditos_sct"
				type="number"
				bind:value={$formData.creditos_sct}
				class="form-input"
				class:border-red-500={$formData.errors.creditos_sct}
				min="0"
			/>
			{#if $formData.errors.creditos_sct}
				<p class="text-red-500 text-sm">{$formData.errors.creditos_sct}</p>
			{/if}
		</div>
	</div>

	<div class="form-group">
		<label for="nombre" class="form-label">Nombre</label>
		<input
			id="nombre"
			type="text"
			bind:value={$formData.nombre}
			class="form-input"
			class:border-red-500={$formData.errors.nombre}
			placeholder="Ej: Anatomía Humana"
			required
		/>
		{#if $formData.errors.nombre}
			<p class="text-red-500 text-sm">{$formData.errors.nombre}</p>
		{/if}
	</div>

	<div class="form-group">
		<label for="descripcion" class="form-label">Descripción</label>
		<textarea
			id="descripcion"
			bind:value={$formData.descripcion}
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
				bind:value={$formData.horas_catedra}
				class="form-input"
				min="0"
			/>
		</div>

		<div class="form-group">
			<label for="horas_taller" class="form-label">Horas Taller</label>
			<input
				id="horas_taller"
				type="number"
				bind:value={$formData.horas_taller}
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
				bind:value={$formData.horas_laboratorio}
				class="form-input"
				min="0"
			/>
		</div>

		<div class="form-group">
			<label for="horas_dirigidas" class="form-label">Horas Dirigidas</label>
			<input
				id="horas_dirigidas"
				type="number"
				bind:value={$formData.horas_dirigidas}
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
			bind:value={$formData.horas_autonomas}
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
	onCancel={() => showDeleteDialog = false}
	isLoading={$formData.processing}
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
