<script lang="ts">
	import { router } from '@inertiajs/svelte';
	import type { PaginatedResponse } from '@/types/admin.types';

	interface Props {
		data: PaginatedResponse<any>;
		columns: { key: string; label: string; sortable?: boolean }[];
		onEdit?: (item: any) => void;
		onDelete?: (item: any) => void;
		onPasswordChange?: (item: any) => void;
		onToggleActive?: (item: any) => void;
		onCustomAction?: (item: any) => void;
		customActionLabel?: string;
		searchPlaceholder?: string;
	}

	let { data, columns, onEdit, onDelete, onPasswordChange, onToggleActive, onCustomAction, customActionLabel = 'Ver', searchPlaceholder = 'Buscar...' }: Props = $props();

	let searchTerm = $state('');

	function handleSearch() {
		router.get(
			window.location.pathname,
			{ search: searchTerm },
			{
				preserveState: true,
				preserveScroll: true
			}
		);
	}

	function goToPage(page: number) {
		router.get(
			window.location.pathname,
			{ page },
			{
				preserveState: true,
				preserveScroll: true
			}
		);
	}

	function getValue(item: any, key: string) {
		const keys = key.split('.');
		let value = item;
		for (const k of keys) {
			value = value?.[k];
		}
		return value ?? '-';
	}
</script>

<div class="data-table">
	<!-- Search Bar -->
	<div class="search-bar">
		<input
			type="text"
			bind:value={searchTerm}
			placeholder={searchPlaceholder}
			onkeydown={(e) => e.key === 'Enter' && handleSearch()}
			class="search-input"
		/>
		<button onclick={handleSearch} class="search-button">Buscar</button>
	</div>

	<!-- Table -->
	<div class="table-container">
		<table class="table">
			<thead>
				<tr>
					{#each columns as column}
						<th>{column.label}</th>
					{/each}
					{#if onEdit || onDelete}
						<th>Acciones</th>
					{/if}
				</tr>
			</thead>
			<tbody>
				{#if data.data.length === 0}
					<tr>
						<td colspan={columns.length + (onEdit || onDelete ? 1 : 0)} class="empty-state">
							No se encontraron resultados
						</td>
					</tr>
				{:else}
					{#each data.data as item}
						<tr>
							{#each columns as column}
								<td>{getValue(item, column.key)}</td>
							{/each}
							{#if onEdit || onDelete || onPasswordChange || onToggleActive || onCustomAction}
								<td class="actions">
									{#if onCustomAction}
										<button onclick={() => onCustomAction?.(item)} class="btn-custom">{customActionLabel}</button>
									{/if}
									{#if onEdit}
										<button onclick={() => onEdit?.(item)} class="btn-edit">Editar</button>
									{/if}
									{#if onDelete}
										<button onclick={() => onDelete?.(item)} class="btn-delete">Eliminar</button>
									{/if}
									{#if onPasswordChange}
										<button onclick={() => onPasswordChange?.(item)} class="btn-password">Contraseña</button>
									{/if}
									{#if onToggleActive}
										<button
											onclick={() => onToggleActive?.(item)}
											class="btn-toggle {item.esta_activo ? 'active' : 'inactive'}"
										>
											{item.esta_activo ? 'Activo' : 'Inactivo'}
										</button>
									{/if}
								</td>
							{/if}
						</tr>
					{/each}
				{/if}
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	{#if data.last_page > 1}
		<div class="pagination">
			<button
				onclick={() => goToPage(data.current_page - 1)}
				disabled={data.current_page === 1}
				class="pagination-button"
			>
				Anterior
			</button>

			<span class="pagination-info">
				Página {data.current_page} de {data.last_page}
			</span>

			<button
				onclick={() => goToPage(data.current_page + 1)}
				disabled={data.current_page === data.last_page}
				class="pagination-button"
			>
				Siguiente
			</button>
		</div>
	{/if}
</div>

<style>
	.data-table {
		background: white;
		border-radius: 8px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		overflow: hidden;
	}

	.search-bar {
		padding: 1rem;
		border-bottom: 1px solid #e5e7eb;
		display: flex;
		gap: 0.5rem;
	}

	.search-input {
		flex: 1;
		padding: 0.5rem 1rem;
		border: 1px solid #d1d5db;
		border-radius: 6px;
		font-size: 0.875rem;
	}

	.search-input:focus {
		outline: none;
		border-color: #3b82f6;
		box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
	}

	.search-button {
		padding: 0.5rem 1.5rem;
		background: #3b82f6;
		color: white;
		border: none;
		border-radius: 6px;
		font-weight: 500;
		cursor: pointer;
		transition: background 0.2s;
	}

	.search-button:hover {
		background: #2563eb;
	}

	.table-container {
		overflow-x: auto;
	}

	.table {
		width: 100%;
		border-collapse: collapse;
	}

	.table thead {
		background: #f9fafb;
		border-bottom: 1px solid #e5e7eb;
	}

	.table th {
		padding: 0.75rem 1rem;
		text-align: left;
		font-size: 0.75rem;
		font-weight: 600;
		text-transform: uppercase;
		color: #6b7280;
		letter-spacing: 0.05em;
	}

	.table td {
		padding: 1rem;
		border-bottom: 1px solid #f3f4f6;
		font-size: 0.875rem;
		color: #111827;
	}

	.table tbody tr:hover {
		background: #f9fafb;
	}

	.empty-state {
		text-align: center;
		color: #9ca3af;
		padding: 3rem 1rem !important;
	}

	.actions {
		display: flex;
		gap: 0.5rem;
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

	.btn-password {
		background: #f0fdf4;
		color: #16a34a;
	}

	.btn-password:hover {
		background: #dcfce7;
	}

	.btn-toggle {
		padding: 0.375rem 0.75rem;
		border: none;
		border-radius: 4px;
		font-size: 0.75rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s;
	}

	.btn-toggle.active {
		background: #dbeafe;
		color: #1d4ed8;
	}

	.btn-toggle.active:hover {
		background: #bfdbfe;
	}

	.btn-toggle.inactive {
		background: #fee2e2;
		color: #dc2626;
	}

	.btn-toggle.inactive:hover {
		background: #fecaca;
	}

	.btn-custom {
		padding: 0.375rem 0.75rem;
		border: none;
		border-radius: 4px;
		font-size: 0.75rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s;
		background: #f5f3ff;
		color: #7c3aed;
	}

	.btn-custom:hover {
		background: #ede9fe;
	}

	.pagination {
		padding: 1rem;
		display: flex;
		align-items: center;
		justify-content: space-between;
		border-top: 1px solid #e5e7eb;
	}

	.pagination-button {
		padding: 0.5rem 1rem;
		background: white;
		border: 1px solid #d1d5db;
		border-radius: 6px;
		font-size: 0.875rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s;
	}

	.pagination-button:hover:not(:disabled) {
		background: #f9fafb;
		border-color: #9ca3af;
	}

	.pagination-button:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.pagination-info {
		font-size: 0.875rem;
		color: #6b7280;
	}
</style>
