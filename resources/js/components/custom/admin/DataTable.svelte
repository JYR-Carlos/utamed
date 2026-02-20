<script lang="ts">
	import { router, page } from '@inertiajs/svelte';
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
		onSyllabus?: (item: any) => void;
		searchPlaceholder?: string;
	}

	let { data, columns, onEdit, onDelete, onPasswordChange, onToggleActive, onCustomAction, customActionLabel = 'Ver', onSyllabus, searchPlaceholder = 'Buscar...' }: Props = $props();

	let searchTerm = $state('');
	let currentPath = $derived($page.url);

	function handleSearch() {
		router.get(
			currentPath,
			{ search: searchTerm },
			{
				preserveState: true,
				preserveScroll: true
			}
		);
	}

	function goToPage(page: number) {
		router.get(
			currentPath,
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
					{#if onEdit || onDelete || onCustomAction || onSyllabus || onPasswordChange || onToggleActive}
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
							{#if onEdit || onDelete || onPasswordChange || onToggleActive || onCustomAction || onSyllabus}
								<td class="actions">
									<!-- P1: Manage Syllabus (Primary CTA — blue filled) -->
									{#if onSyllabus}
										<button
											onclick={() => onSyllabus?.(item)}
											class="btn-syllabus {item.has_programa ? 'has-programa' : ''}"
											title={item.has_programa ? 'Ver / Regenerar Programa' : 'Generar Programa'}
										>
											<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
											Programa
											{#if item.has_programa}
												<span class="programa-dot"></span>
											{/if}
										</button>
									{/if}

									<!-- P2: Manage Team / Custom Action (Secondary — indigo outline) -->
									{#if onCustomAction}
										<button onclick={() => onCustomAction?.(item)} class="btn-custom">{customActionLabel}</button>
									{/if}

									<!-- Separator between primary zone and edit/delete zone -->
									{#if (onSyllabus || onCustomAction) && (onEdit || onDelete)}
										<div class="action-sep"></div>
									{/if}

									<!-- P3: Edit (Tertiary — ghost with icon) -->
									{#if onEdit}
										<button onclick={() => onEdit?.(item)} class="btn-edit" title="Editar">
											<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
											Editar
										</button>
									{/if}

									<!-- P4: Delete (Destructive — icon-only, minimal prominence) -->
									{#if onDelete}
										<button onclick={() => onDelete?.(item)} class="btn-delete-icon" title="Eliminar">
											<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
										</button>
									{/if}

									<!-- Utility actions (other pages that use DataTable) -->
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
		padding: 0.75rem 1rem;
		border-bottom: 1px solid #f3f4f6;
		font-size: 0.875rem;
		color: #111827;
		vertical-align: middle;
	}

	.table tbody tr:hover {
		background: #f9fafb;
	}

	.empty-state {
		text-align: center;
		color: #9ca3af;
		padding: 3rem 1rem !important;
	}

	/* ─── Actions cell ─────────────────────────────────── */
	.actions {
		display: flex;
		align-items: center;
		gap: 0.375rem;
		white-space: nowrap;
	}

	/* Separator between primary zone and edit/delete zone */
	.action-sep {
		width: 1px;
		height: 20px;
		background: #e5e7eb;
		margin: 0 0.125rem;
		flex-shrink: 0;
	}

	/* ─── P1: Syllabus (Primary CTA) ────────────────────── */
	.btn-syllabus {
		display: inline-flex;
		align-items: center;
		gap: 0.3rem;
		padding: 0.3rem 0.65rem;
		background: #2563eb;
		color: white;
		border: none;
		border-radius: 5px;
		font-size: 0.73rem;
		font-weight: 600;
		cursor: pointer;
		transition: background 0.15s, box-shadow 0.15s;
		box-shadow: 0 1px 2px rgba(37, 99, 235, 0.35);
		position: relative;
	}

	.btn-syllabus:hover {
		background: #1d4ed8;
		box-shadow: 0 2px 6px rgba(37, 99, 235, 0.45);
	}

	/* green dot indicator when programa exists */
	.programa-dot {
		display: inline-block;
		width: 7px;
		height: 7px;
		background: #4ade80;
		border-radius: 50%;
		border: 1px solid rgba(255,255,255,0.6);
		flex-shrink: 0;
	}

	/* When already has a programa, shift to a slightly different shade */
	.btn-syllabus.has-programa {
		background: #1e40af;
	}

	/* ─── P2: Custom / Team (Secondary) ─────────────────── */
	.btn-custom {
		padding: 0.3rem 0.65rem;
		border: 1px solid #a5b4fc;
		border-radius: 5px;
		font-size: 0.73rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.15s;
		background: #eef2ff;
		color: #4338ca;
	}

	.btn-custom:hover {
		background: #e0e7ff;
		border-color: #818cf8;
	}

	/* ─── P3: Edit (Tertiary ghost) ──────────────────────── */
	.btn-edit {
		display: inline-flex;
		align-items: center;
		gap: 0.3rem;
		padding: 0.3rem 0.65rem;
		border: none;
		border-radius: 5px;
		font-size: 0.73rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.15s;
		background: transparent;
		color: #4b5563;
	}

	.btn-edit:hover {
		background: #f3f4f6;
		color: #111827;
	}

	/* ─── P4: Delete (Destructive icon-only) ────────────── */
	.btn-delete-icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 0.3rem;
		border: none;
		border-radius: 5px;
		font-size: 0.73rem;
		cursor: pointer;
		transition: all 0.15s;
		background: transparent;
		color: #9ca3af;
	}

	.btn-delete-icon:hover {
		background: #fef2f2;
		color: #dc2626;
	}

	/* ─── Utility (password, toggle) ─────────────────────── */
	.btn-password {
		padding: 0.3rem 0.65rem;
		border: none;
		border-radius: 5px;
		font-size: 0.73rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.15s;
		background: #f0fdf4;
		color: #16a34a;
	}

	.btn-password:hover {
		background: #dcfce7;
	}

	.btn-toggle {
		padding: 0.3rem 0.65rem;
		border: none;
		border-radius: 5px;
		font-size: 0.73rem;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.15s;
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

	/* ─── Pagination ─────────────────────────────────────── */
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
