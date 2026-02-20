<script lang="ts">
	/**
	 * SyllabusModal — View/edit an existing Programa or generate a new one.
	 *
	 * Populated state (has_programa = true):
	 *   Loads sections via GET /admin/cursos/{id}/programa, renders them as editable
	 *   text areas. "Guardar cambios" POSTs updated sections (creates new version).
	 *
	 * Empty state (has_programa = false):
	 *   3-step wizard → generate.
	 */
	import axios, { AxiosError } from 'axios';
	import { onMount } from 'svelte';
	import type { Curso, Programa } from '@/types/admin.types';

	interface ContenidoPrograma {
		id_contenido_programa?: number;
		texto_contenido: string | null;
		valor_numerico?: number | null;
		orden_item: number;
	}

	interface SeccionPrograma {
		id_estructura_programa?: number;
		nombre_seccion: string;
		numeral_romano?: string;
		orden: number;
		es_lista?: boolean;
		es_actual?: boolean;
		contenidos_programa: ContenidoPrograma[];
	}

	interface ProgramaFull extends Programa {
		es_plantilla: boolean;
		version_programa: number;
		secciones: SeccionPrograma[];
	}

	interface WizardSection {
		nombre_seccion: string;
		numeral_romano: string;
		orden: number;
		contenidos: { texto_contenido: string; orden_item: number }[];
	}

	interface Props {
		isOpen: boolean;
		curso: Curso | null;
		onClose: () => void;
		onSuccess: (programa: Programa) => void;
	}

	let { isOpen = $bindable(), curso, onClose, onSuccess }: Props = $props();

	// ── Mode ────────────────────────────────────────────────────────────────────
	// 'view' = showing existing programa (editable)
	// 'wizard' = generating new programa (3-step wizard)
	let mode = $state<'view' | 'wizard'>('view');

	// ── View/edit state (populated) ─────────────────────────────────────────────
	let loadingPrograma = $state(false);
	let programaData = $state<ProgramaFull | null>(null);
	let editedSections = $state<SeccionPrograma[]>([]);
	let isSaving = $state(false);
	let isApproving = $state(false);
	let viewError = $state('');

	// ── Wizard state ─────────────────────────────────────────────────────────────
	let step = $state<1 | 2 | 3>(1);
	let isGenerating = $state(false);
	let errorMsg = $state('');
	let descripcion = $state('');
	let metodologia = $state('');
	let evaluacion  = $state('');
	let unidades = $state<{ titulo: string; descripcion: string }[]>([
		{ titulo: '', descripcion: '' }
	]);

	const STEPS = [
		{ id: 1, label: 'Info General' },
		{ id: 2, label: 'Contenidos'   },
		{ id: 3, label: 'Revisión'     },
	] as const;

	// ── Init on mount: decide mode and load data ─────────────────────────────────
	onMount(() => {
		if (curso?.has_programa) {
			mode = 'view';
			loadPrograma();
		} else {
			mode = 'wizard';
		}
	});

	async function loadPrograma() {
		if (!curso) return;
		loadingPrograma = true;
		viewError = '';
		try {
			const { data } = await axios.get(`/admin/cursos/${curso.id_curso}/programa`);
			programaData = data.programa;
			// Deep-clone to a mutable editing copy
			editedSections = (data.programa?.secciones ?? []).map((s: SeccionPrograma) => ({
				...s,
				contenidos_programa: s.contenidos_programa.map(c => ({ ...c })),
			}));
		} catch {
			viewError = 'Error cargando el programa. Intente de nuevo.';
		} finally {
			loadingPrograma = false;
		}
	}

	// ── Helpers ──────────────────────────────────────────────────────────────────
	function handleClose() {
		resetAll();
		onClose();
	}

	function resetAll() {
		mode = 'view';
		programaData = null;
		editedSections = [];
		isSaving = false;
		viewError = '';
		step = 1;
		descripcion = metodologia = evaluacion = '';
		unidades = [{ titulo: '', descripcion: '' }];
		isGenerating = false;
		errorMsg = '';
	}

	function handleKeydown(e: KeyboardEvent) {
		if (e.key === 'Escape') handleClose();
	}

	// ── Save edited sections (creates new version) ───────────────────────────────
	async function handleSaveEdits() {
		if (!curso) return;
		isSaving = true;
		viewError = '';
		try {
			const payload = editedSections.map((s, i) => ({
				nombre_seccion: s.nombre_seccion,
				numeral_romano: s.numeral_romano ?? '',
				orden: i + 1,
				contenidos: s.contenidos_programa.map((c, j) => ({
					texto_contenido: c.texto_contenido ?? '',
					orden_item: j + 1,
				})),
			}));

			const { data } = await axios.post(`/admin/cursos/${curso.id_curso}/programa`, {
				secciones: payload,
			});

			isSaving = false;
			resetAll();
			onSuccess(data.programa as Programa);
		} catch (err) {
			isSaving = false;
			viewError = err instanceof AxiosError
				? (err.response?.data?.error ?? err.message)
				: 'Error guardando los cambios.';
		}
	}

	// ── Approve (mark as definitivo) ─────────────────────────────────────────────
	async function handleApprove() {
		if (!curso) return;
		isApproving = true;
		viewError = '';
		try {
			await axios.put(`/admin/cursos/${curso.id_curso}/programa/aprobar`);
			// Flip local state so the badge updates instantly
			if (programaData) {
				programaData = { ...programaData, estado: 'APROBADO' };
			}
		} catch (err) {
			viewError = err instanceof AxiosError
				? (err.response?.data?.error ?? err.message)
				: 'Error al aprobar el programa.';
		} finally {
			isApproving = false;
		}
	}

	// ── Wizard helpers ───────────────────────────────────────────────────────────
	function addUnidad() {
		unidades = [...unidades, { titulo: '', descripcion: '' }];
	}

	function removeUnidad(i: number) {
		unidades = unidades.filter((_, idx) => idx !== i);
	}

	function toRoman(n: number): string {
		const map: [number, string][] = [
			[10,'X'],[9,'IX'],[5,'V'],[4,'IV'],[1,'I']
		];
		let result = '';
		for (const [val, sym] of map) {
			while (n >= val) { result += sym; n -= val; }
		}
		return result;
	}

	function buildSecciones(): WizardSection[] {
		const secciones: WizardSection[] = [
			{
				nombre_seccion: 'Descripción de la Asignatura',
				numeral_romano: 'I', orden: 1,
				contenidos: descripcion.trim()
					? [{ texto_contenido: descripcion.trim(), orden_item: 1 }]
					: [],
			},
		];
		let orden = 2;
		for (const u of unidades) {
			if (!u.titulo.trim()) continue;
			secciones.push({
				nombre_seccion: u.titulo.trim(),
				numeral_romano: toRoman(orden), orden,
				contenidos: u.descripcion.trim()
					? [{ texto_contenido: u.descripcion.trim(), orden_item: 1 }]
					: [],
			});
			orden++;
		}
		const standard: [string, string][] = [
			['Metodología', 'V'], ['Evaluación', 'VI'],
		];
		for (const [nombre, roman] of standard) {
			secciones.push({
				nombre_seccion: nombre, numeral_romano: roman, orden,
				contenidos: nombre === 'Metodología' && metodologia.trim()
					? [{ texto_contenido: metodologia.trim(), orden_item: 1 }]
					: nombre === 'Evaluación' && evaluacion.trim()
					? [{ texto_contenido: evaluacion.trim(),  orden_item: 1 }]
					: [],
			});
			orden++;
		}
		return secciones;
	}

	async function handleGenerate() {
		if (!curso) return;
		isGenerating = true;
		errorMsg = '';
		try {
			const { data } = await axios.post(`/admin/cursos/${curso.id_curso}/programa`, {
				secciones: buildSecciones(),
			});
			isGenerating = false;
			resetAll();
			onSuccess(data.programa as Programa);
		} catch (err) {
			isGenerating = false;
			errorMsg = err instanceof AxiosError
				? (err.response?.data?.error ?? err.message)
				: 'Error desconocido al generar el programa.';
		}
	}

	let step1Valid = $derived(descripcion.trim().length > 0 || metodologia.trim().length > 0);

	// Helper: get the first content text of a section (for display)
	function firstContent(sec: SeccionPrograma): string {
		return sec.contenidos_programa?.[0]?.texto_contenido ?? '';
	}

	// Helper: join all content texts
	function joinContents(sec: SeccionPrograma): string {
		return sec.contenidos_programa
			.map(c => c.texto_contenido ?? '')
			.filter(Boolean)
			.join('\n');
	}

	function updateSectionContent(secIdx: number, text: string) {
		const sec = editedSections[secIdx];
		if (sec.contenidos_programa.length === 0) {
			editedSections[secIdx] = {
				...sec,
				contenidos_programa: [{ texto_contenido: text, orden_item: 1 }],
			};
		} else {
			// Update the first / only content item; preserve any extra items
			editedSections[secIdx] = {
				...sec,
				contenidos_programa: sec.contenidos_programa.map((c, i) =>
					i === 0 ? { ...c, texto_contenido: text } : c
				),
			};
		}
	}
</script>

<svelte:window onkeydown={handleKeydown} />

{#if isOpen && curso}
	<div
		class="modal-backdrop"
		onclick={handleClose}
		onkeydown={handleKeydown}
		tabindex="-1"
		role="dialog"
		aria-modal="true"
		aria-labelledby="syllabus-modal-title"
	>
		<!-- svelte-ignore a11y_click_events_have_key_events -->
		<!-- svelte-ignore a11y_no_static_element_interactions -->
		<div class="modal-panel" onclick={(e) => e.stopPropagation()}>

			<!-- ── Header ──────────────────────────────────────────────── -->
			<div class="modal-header">
				<div class="header-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
				</div>
				<div class="header-text">
					<h2 id="syllabus-modal-title" class="modal-title">
						{mode === 'view' ? 'Programa' : 'Generar Programa'}
					</h2>
					<p class="modal-subtitle">{curso.asignatura_nombre ?? `Curso ${curso.cod_curso}`}</p>
				</div>
				<div class="header-actions">
					{#if mode === 'view' && programaData}
						{#if programaData.estado === 'BORRADOR'}
							<span class="status-badge status-draft">
								<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
								Borrador v{programaData.version_programa}
							</span>
						{:else}
							<span class="status-badge status-approved">
								<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
								Aprobado v{programaData.version_programa}
							</span>
						{/if}
					{/if}
					<button onclick={handleClose} class="modal-close" title="Cerrar">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
			</div>

			<!-- ══════════════════════════════════════════════════════════ -->
			<!-- VIEW / EDIT MODE (existing programa)                       -->
			<!-- ══════════════════════════════════════════════════════════ -->
			{#if mode === 'view'}
				<div class="modal-body">

					{#if loadingPrograma}
						<div class="loading-state">
							<span class="spinner spinner-dark"></span>
							<span>Cargando programa...</span>
						</div>

						{:else if viewError}
					<div class="error-banner" role="alert">
						{viewError}
						<button onclick={loadPrograma} class="retry-btn">Reintentar</button>
					</div>

				{:else if editedSections.length === 0}
					<div class="empty-sections">
						<p>No se encontraron secciones en este programa.</p>
						<button type="button" onclick={() => { mode = 'wizard'; step = 1; }} class="btn-nav btn-next" style="margin-top:0.75rem">
							Regenerar programa
						</button>
					</div>

				{:else}
					<!-- Editable sections -->
					<div class="sections-list">
						{#each editedSections as sec, i}
							<div class="section-item">
								<div class="section-header">
									{#if sec.numeral_romano}
										<span class="section-numeral">{sec.numeral_romano}.</span>
									{/if}
									<span class="section-name">{sec.nombre_seccion}</span>
								</div>
								<textarea
									class="section-textarea"
									rows="3"
									placeholder="Sin contenido — escribe aquí para agregar texto..."
									value={joinContents(sec)}
									oninput={(e) => updateSectionContent(i, (e.target as HTMLTextAreaElement).value)}
								></textarea>
							</div>
						{/each}
					</div>
				{/if}

				</div>

				<!-- View mode footer -->
				<div class="modal-footer">
					<button
						type="button"
						onclick={() => { mode = 'wizard'; step = 1; }}
						class="btn-nav btn-prev"
						disabled={isSaving || isApproving}
					>
						Regenerar desde cero
					</button>
					<div class="footer-actions-right">
						{#if programaData?.estado === 'BORRADOR'}
							<button
								type="button"
								onclick={handleApprove}
								disabled={isApproving || isSaving}
								class="btn-approve"
							>
								{#if isApproving}
									<span class="spinner"></span> Aprobando...
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
									Aprobar Programa
								{/if}
							</button>
						{/if}
						<button
							type="button"
							onclick={handleSaveEdits}
							disabled={isSaving || loadingPrograma || isApproving}
							class="btn-save"
						>
							{#if isSaving}
								<span class="spinner"></span> Guardando...
							{:else}
								<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
								Guardar cambios
							{/if}
						</button>
					</div>
				</div>

			<!-- ══════════════════════════════════════════════════════════ -->
			<!-- WIZARD MODE (create from scratch)                          -->
			<!-- ══════════════════════════════════════════════════════════ -->
			{:else}
				<!-- Step indicator -->
				<div class="step-indicator-wrap">
					<ol class="step-indicator">
						{#each STEPS as s, i}
							{@const isComplete = step > s.id}
							{@const isActive   = step === s.id}
							<li class="step-item">
								<div
									class="step-circle {isComplete ? 'complete' : isActive ? 'active' : 'pending'}"
									aria-current={isActive ? 'step' : undefined}
								>
									{#if isComplete}✓{:else}{s.id}{/if}
								</div>
								<span class="step-label {isActive ? 'label-active' : ''}">{s.label}</span>
								{#if i < STEPS.length - 1}
									<div class="step-connector {isComplete ? 'connector-done' : ''}"></div>
								{/if}
							</li>
						{/each}
					</ol>
				</div>

				<div class="modal-body wizard-body">

					{#if step === 1}
						<div class="step-content">
							<div class="form-group">
								<label class="form-label" for="syllabus-desc">
									Descripción de la Asignatura
									<span class="label-hint">Sección I del programa</span>
								</label>
								<textarea id="syllabus-desc" bind:value={descripcion} rows="3" class="form-textarea" placeholder="Descripción general de la asignatura..."></textarea>
							</div>
							<div class="form-group">
								<label class="form-label" for="syllabus-met">Metodología de Enseñanza</label>
								<textarea id="syllabus-met" bind:value={metodologia} rows="2" class="form-textarea" placeholder="Clases expositivas, trabajos prácticos, laboratorio..."></textarea>
							</div>
							<div class="form-group">
								<label class="form-label" for="syllabus-eval">Sistema de Evaluación</label>
								<textarea id="syllabus-eval" bind:value={evaluacion} rows="2" class="form-textarea" placeholder="Controles, examen final, proyecto..."></textarea>
							</div>
						</div>

					{:else if step === 2}
						<div class="step-content">
							<p class="step-description">Define las unidades o bloques de contenido del programa.</p>
							<div class="unidades-list">
								{#each unidades as u, i}
									<div class="unidad-item">
										<div class="unidad-header">
											<span class="unidad-num">Unidad {i + 1}</span>
											{#if unidades.length > 1}
												<button type="button" onclick={() => removeUnidad(i)} class="unidad-remove" title="Eliminar unidad">
													<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
												</button>
											{/if}
										</div>
										<input type="text" bind:value={u.titulo} placeholder="Título (ej: Fundamentos de Diseño)" class="form-input" />
										<textarea bind:value={u.descripcion} rows="2" class="form-textarea mt-2" placeholder="Contenidos de esta unidad..."></textarea>
									</div>
								{/each}
							</div>
							<button type="button" onclick={addUnidad} class="btn-add-unidad">
								<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
								Agregar Unidad
							</button>
						</div>

					{:else if step === 3}
						<div class="step-content">
							<div class="review-card">
								<p class="review-title">Resumen del Programa</p>
								<dl class="review-list">
									<div class="review-item">
										<dt>Asignatura</dt>
										<dd>{curso.asignatura_nombre ?? `Curso ${curso.cod_curso}`}</dd>
									</div>
									{#if descripcion.trim()}
										<div class="review-item">
											<dt>Descripción</dt>
											<dd class="review-excerpt">{descripcion.trim().slice(0, 120)}{descripcion.trim().length > 120 ? '…' : ''}</dd>
										</div>
									{/if}
									<div class="review-item">
										<dt>Unidades</dt>
										<dd>{unidades.filter(u => u.titulo.trim()).length} definidas</dd>
									</div>
								</dl>
							</div>

							{#if errorMsg}
								<div class="error-banner" role="alert">
									<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
									{errorMsg}
								</div>
							{/if}

							<button type="button" onclick={handleGenerate} disabled={isGenerating} class="btn-generate">
								{#if isGenerating}
									<span class="spinner"></span> Generando programa...
								{:else}
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
									Generar Programa
								{/if}
							</button>
						</div>
					{/if}
				</div>

				<!-- Wizard Footer -->
				<div class="modal-footer">
					<button
						type="button"
						onclick={() => {
							if (step === 1 && curso?.has_programa) { mode = 'view'; }
							else { step = Math.max(1, step - 1) as 1|2|3; }
						}}
						disabled={isGenerating}
						class="btn-nav btn-prev"
					>
						← {step === 1 && curso?.has_programa ? 'Ver existente' : 'Anterior'}
					</button>
					{#if step < 3}
						<button
							type="button"
							onclick={() => step = Math.min(3, step + 1) as 1|2|3}
							disabled={step === 1 && !step1Valid}
							class="btn-nav btn-next"
						>
							Siguiente →
						</button>
					{/if}
				</div>
			{/if}

		</div>
	</div>
{/if}

<style>
	/* ── Backdrop ───────────────────────────────────────────────── */
	.modal-backdrop {
		position: fixed;
		inset: 0;
		background: rgba(15, 23, 42, 0.45);
		backdrop-filter: blur(3px);
		z-index: 9000;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 1rem;
	}

	/* ── Panel ──────────────────────────────────────────────────── */
	.modal-panel {
		background: white;
		border-radius: 14px;
		box-shadow: 0 20px 60px rgba(0,0,0,0.18), 0 4px 16px rgba(0,0,0,0.1);
		width: 100%;
		max-width: 600px;
		max-height: 90vh;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}

	/* ── Header ─────────────────────────────────────────────────── */
	.modal-header {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		padding: 1.25rem 1.5rem 1rem;
		border-bottom: 1px solid #f1f5f9;
		flex-shrink: 0;
	}

	.header-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 38px;
		height: 38px;
		background: #eff6ff;
		border-radius: 10px;
		color: #2563eb;
		flex-shrink: 0;
	}

	.header-text {
		flex: 1;
		min-width: 0;
	}

	.modal-title {
		font-size: 1rem;
		font-weight: 700;
		color: #0f172a;
		margin: 0;
	}

	.modal-subtitle {
		font-size: 0.8rem;
		color: #64748b;
		margin: 0.1rem 0 0;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.header-actions {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		flex-shrink: 0;
	}

	.status-badge {
		display: flex;
		align-items: center;
		gap: 0.35rem;
		font-size: 0.68rem;
		font-weight: 700;
		border-radius: 20px;
		padding: 0.18rem 0.65rem;
		letter-spacing: 0.03em;
		text-transform: uppercase;
	}

	.status-draft {
		color: #b45309;
		background: #fffbeb;
		border: 1px solid #fcd34d;
	}

	.status-approved {
		color: #15803d;
		background: #f0fdf4;
		border: 1px solid #86efac;
	}

	.footer-actions-right {
		display: flex;
		align-items: center;
		gap: 0.75rem;
	}

	.btn-approve {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.5rem 1rem;
		background: white;
		color: #15803d;
		border: 1px solid #86efac;
		border-radius: 8px;
		font-size: 0.875rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.15s;
	}

	.btn-approve:hover:not(:disabled) {
		background: #f0fdf4;
		border-color: #4ade80;
	}

	.btn-approve:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.modal-close {
		padding: 0.375rem;
		border: none;
		background: none;
		color: #94a3b8;
		border-radius: 6px;
		cursor: pointer;
		display: flex;
		align-items: center;
		transition: background 0.15s, color 0.15s;
	}

	.modal-close:hover {
		background: #f1f5f9;
		color: #475569;
	}

	/* ── Body ────────────────────────────────────────────────────── */
	.modal-body {
		padding: 1.25rem 1.5rem;
		overflow-y: auto;
		flex: 1;
	}

	.wizard-body {
		padding-top: 1rem;
	}

	/* ── Loading ─────────────────────────────────────────────────── */
	.loading-state {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.75rem;
		padding: 3rem 1rem;
		color: #64748b;
		font-size: 0.875rem;
	}

	/* ── Editable Sections ───────────────────────────────────────── */
	.sections-list {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}

	.section-item {
		display: flex;
		flex-direction: column;
		gap: 0.375rem;
	}

	.section-header {
		display: flex;
		align-items: baseline;
		gap: 0.375rem;
	}

	.section-numeral {
		font-size: 0.72rem;
		font-weight: 800;
		color: #2563eb;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		flex-shrink: 0;
	}

	.section-name {
		font-size: 0.8rem;
		font-weight: 600;
		color: #1e293b;
	}

	.section-textarea {
		width: 100%;
		padding: 0.5rem 0.75rem;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		font-size: 0.82rem;
		color: #374151;
		resize: vertical;
		font-family: inherit;
		background: #f8fafc;
		transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
		box-sizing: border-box;
		min-height: 70px;
	}

	.section-textarea:focus {
		outline: none;
		border-color: #3b82f6;
		box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
		background: white;
	}

	/* ── Loading state ───────────────────────────────────────── */
	.loading-state {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.75rem;
		padding: 3rem 1rem;
		color: #64748b;
		font-size: 0.875rem;
	}

	/* ── Empty sections ──────────────────────────────────────── */
	.empty-sections {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 2.5rem 1rem;
		color: #64748b;
		font-size: 0.875rem;
		text-align: center;
	}

	.empty-sections p {
		margin: 0;
	}

	/* ── Step Indicator ─────────────────────────────────────────── */
	.step-indicator-wrap {
		padding: 1rem 1.5rem 0;
		flex-shrink: 0;
	}

	.step-indicator {
		display: flex;
		align-items: center;
		list-style: none;
		margin: 0;
		padding: 0;
		gap: 0;
	}

	.step-item {
		display: flex;
		align-items: center;
		flex: 1;
	}

	.step-circle {
		width: 28px;
		height: 28px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 0.7rem;
		font-weight: 700;
		flex-shrink: 0;
		transition: background 0.2s, color 0.2s;
	}

	.step-circle.complete {
		background: #2563eb;
		color: white;
	}

	.step-circle.active {
		background: #2563eb;
		color: white;
		box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
	}

	.step-circle.pending {
		background: #f1f5f9;
		color: #94a3b8;
	}

	.step-label {
		font-size: 0.7rem;
		color: #94a3b8;
		margin-left: 0.5rem;
		white-space: nowrap;
		display: none;
	}

	@media (min-width: 400px) {
		.step-label { display: block; }
	}

	.step-label.label-active {
		color: #2563eb;
		font-weight: 600;
	}

	.step-connector {
		flex: 1;
		height: 2px;
		background: #e2e8f0;
		margin: 0 0.5rem;
		transition: background 0.2s;
	}

	.step-connector.connector-done {
		background: #2563eb;
	}

	/* ── Wizard content ─────────────────────────────────────────── */
	.step-content {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}

	.step-description {
		font-size: 0.8rem;
		color: #64748b;
		margin: 0;
	}

	.form-group {
		display: flex;
		flex-direction: column;
		gap: 0.375rem;
	}

	.form-label {
		font-size: 0.8rem;
		font-weight: 600;
		color: #374151;
		display: flex;
		flex-direction: column;
		gap: 0.1rem;
	}

	.label-hint {
		font-size: 0.7rem;
		font-weight: 400;
		color: #9ca3af;
	}

	.form-input {
		width: 100%;
		padding: 0.5rem 0.75rem;
		border: 1px solid #d1d5db;
		border-radius: 7px;
		font-size: 0.85rem;
		color: #111827;
		transition: border-color 0.15s, box-shadow 0.15s;
		box-sizing: border-box;
	}

	.form-textarea {
		width: 100%;
		padding: 0.5rem 0.75rem;
		border: 1px solid #d1d5db;
		border-radius: 7px;
		font-size: 0.85rem;
		color: #111827;
		resize: none;
		transition: border-color 0.15s, box-shadow 0.15s;
		font-family: inherit;
		box-sizing: border-box;
	}

	.form-input:focus,
	.form-textarea:focus {
		outline: none;
		border-color: #3b82f6;
		box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
	}

	.mt-2 { margin-top: 0.5rem; }

	.unidades-list {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	.unidad-item {
		padding: 0.875rem 1rem;
		background: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.unidad-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.unidad-num {
		font-size: 0.7rem;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		color: #6366f1;
	}

	.unidad-remove {
		padding: 0.2rem;
		border: none;
		background: none;
		color: #9ca3af;
		border-radius: 4px;
		cursor: pointer;
		display: flex;
		align-items: center;
		transition: color 0.15s, background 0.15s;
	}

	.unidad-remove:hover {
		color: #dc2626;
		background: #fef2f2;
	}

	.btn-add-unidad {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.5rem;
		padding: 0.625rem;
		border: 2px dashed #cbd5e1;
		border-radius: 10px;
		background: transparent;
		color: #64748b;
		font-size: 0.8rem;
		font-weight: 500;
		cursor: pointer;
		transition: border-color 0.15s, background 0.15s, color 0.15s;
		width: 100%;
	}

	.btn-add-unidad:hover {
		border-color: #3b82f6;
		background: #eff6ff;
		color: #2563eb;
	}

	/* Review */
	.review-card {
		background: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		padding: 1rem 1.25rem;
	}

	.review-title {
		font-size: 0.8rem;
		font-weight: 700;
		color: #1e3a5f;
		margin: 0 0 0.75rem;
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}

	.review-list {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		margin: 0;
	}

	.review-item {
		display: grid;
		grid-template-columns: 120px 1fr;
		gap: 0.5rem;
		font-size: 0.8rem;
	}

	.review-item dt {
		color: #64748b;
		font-weight: 500;
	}

	.review-item dd {
		color: #0f172a;
		font-weight: 500;
		margin: 0;
	}

	.review-excerpt {
		color: #475569 !important;
		font-weight: 400 !important;
		font-style: italic;
	}

	/* Error banner */
	.error-banner {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.75rem 1rem;
		background: #fef2f2;
		border: 1px solid #fecaca;
		border-radius: 8px;
		font-size: 0.8rem;
		color: #dc2626;
	}

	.retry-btn {
		margin-left: auto;
		padding: 0.25rem 0.75rem;
		background: transparent;
		border: 1px solid #fca5a5;
		border-radius: 6px;
		color: #dc2626;
		font-size: 0.75rem;
		cursor: pointer;
		transition: background 0.15s;
	}

	.retry-btn:hover {
		background: #fee2e2;
	}

	/* Generate CTA */
	.btn-generate {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 0.5rem;
		width: 100%;
		padding: 0.75rem 1rem;
		background: #2563eb;
		color: white;
		border: none;
		border-radius: 10px;
		font-size: 0.9rem;
		font-weight: 700;
		cursor: pointer;
		transition: background 0.15s, box-shadow 0.15s;
		box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
	}

	.btn-generate:hover:not(:disabled) {
		background: #1d4ed8;
		box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
	}

	.btn-generate:disabled {
		background: #93c5fd;
		cursor: not-allowed;
		box-shadow: none;
	}

	/* Spinner */
	.spinner {
		display: inline-block;
		width: 15px;
		height: 15px;
		border: 2px solid rgba(255,255,255,0.4);
		border-top-color: white;
		border-radius: 50%;
		animation: spin 0.65s linear infinite;
	}

	.spinner-dark {
		border-color: rgba(37,99,235,0.15);
		border-top-color: #2563eb;
	}

	@keyframes spin {
		to { transform: rotate(360deg); }
	}

	/* ── Footer ─────────────────────────────────────────────────── */
	.modal-footer {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0.875rem 1.5rem;
		border-top: 1px solid #f1f5f9;
		flex-shrink: 0;
	}

	.btn-nav {
		padding: 0.5rem 1rem;
		border-radius: 8px;
		font-size: 0.85rem;
		font-weight: 500;
		cursor: pointer;
		transition: background 0.15s, border-color 0.15s;
	}

	.btn-prev {
		background: white;
		border: 1px solid #d1d5db;
		color: #374151;
	}

	.btn-prev:hover:not(:disabled) {
		background: #f9fafb;
		border-color: #9ca3af;
	}

	.btn-prev:disabled {
		opacity: 0.4;
		cursor: not-allowed;
	}

	.btn-next {
		background: #2563eb;
		border: 1px solid transparent;
		color: white;
	}

	.btn-next:hover:not(:disabled) {
		background: #1d4ed8;
	}

	.btn-next:disabled {
		background: #93c5fd;
		cursor: not-allowed;
	}

	.btn-save {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.5rem 1.25rem;
		background: #2563eb;
		color: white;
		border: none;
		border-radius: 8px;
		font-size: 0.875rem;
		font-weight: 600;
		cursor: pointer;
		transition: background 0.15s, box-shadow 0.15s;
		box-shadow: 0 1px 3px rgba(37,99,235,0.3);
	}

	.btn-save:hover:not(:disabled) {
		background: #1d4ed8;
		box-shadow: 0 3px 8px rgba(37,99,235,0.35);
	}

	.btn-save:disabled {
		background: #93c5fd;
		cursor: not-allowed;
		box-shadow: none;
	}
</style>
