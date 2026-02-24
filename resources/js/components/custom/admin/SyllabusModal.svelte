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
	let step = $state<1 | 2 | 3 | 4 | 5>(1);
	let isGenerating = $state(false);
	let errorMsg = $state('');
	let descripcion = $state('');
	let metodologia = $state('');
	let evaluacion  = $state('');
	let objetivos = $state('');
	let competencias = $state('');
	let unidades = $state<{ titulo: string; descripcion: string }[]>([
		{ titulo: '', descripcion: '' }
	]);

	const STEPS = [
		{ id: 1, label: 'Información General', icon: '📚' },
		{ id: 2, label: 'Objetivos', icon: '🎯' },
		{ id: 3, label: 'Contenidos', icon: '📖' },
		{ id: 4, label: 'Evaluación', icon: '✓' },
		{ id: 5, label: 'Revisión', icon: '✓' },
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
		descripcion = metodologia = evaluacion = objetivos = competencias = '';
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
			{
				nombre_seccion: 'Objetivos de Aprendizaje',
				numeral_romano: 'II', orden: 2,
				contenidos: objetivos.trim()
					? [{ texto_contenido: objetivos.trim(), orden_item: 1 }]
					: [],
			},
			{
				nombre_seccion: 'Competencias',
				numeral_romano: 'III', orden: 3,
				contenidos: competencias.trim()
					? [{ texto_contenido: competencias.trim(), orden_item: 1 }]
					: [],
			},
		];
		let orden = 4;
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
			['Metodología', toRoman(orden)],
			['Evaluación', toRoman(orden + 1)],
		];
		for (const [nombre, roman] of standard) {
			const content = nombre === 'Metodología' ? metodologia.trim() : evaluacion.trim();
			secciones.push({
				nombre_seccion: nombre,
				numeral_romano: roman,
				orden: orden,
				contenidos: content
					? [{ texto_contenido: content, orden_item: 1 }]
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

	let step1Valid = $derived(descripcion.trim().length > 0);
	let step2Valid = $derived(objetivos.trim().length > 0);
	let step3Valid = $derived(unidades.some(u => u.titulo.trim().length > 0));
	let step4Valid = $derived(metodologia.trim().length > 0 && evaluacion.trim().length > 0);

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
		class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
		onclick={handleClose}
		onkeydown={handleKeydown}
		tabindex="-1"
		role="dialog"
		aria-modal="true"
		aria-labelledby="syllabus-modal-title"
	>
		<!-- svelte-ignore a11y_click_events_have_key_events -->
		<!-- svelte-ignore a11y_no_static_element_interactions -->
		<div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col" onclick={(e) => e.stopPropagation()}>

			<!-- ── Header ──────────────────────────────────────────────── -->
			<div class="flex items-center gap-3 px-6 py-5 border-b border-slate-100 flex-shrink-0">
				<div class="flex items-center justify-center w-9 h-9 bg-blue-50 rounded-lg text-blue-600 flex-shrink-0">
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
				</div>
				<div class="flex-1 min-w-0">
					<h2 id="syllabus-modal-title" class="text-lg font-bold text-slate-900">
						{mode === 'view' ? 'Programa de Cátedra' : 'Crear Programa'}
					</h2>
					<p class="text-sm text-slate-500 truncate">{curso.asignatura_nombre ?? `Curso ${curso.cod_curso}`}</p>
				</div>
				<div class="flex items-center gap-2 flex-shrink-0">
					{#if mode === 'view' && programaData}
						{#if programaData.estado === 'BORRADOR'}
							<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
								<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
								Borrador v{programaData.version_programa}
							</span>
						{:else}
							<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
								<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
								Aprobado v{programaData.version_programa}
							</span>
						{/if}
					{/if}
					<button
						type="button"
						onclick={handleClose}
						title="Cerrar"
						class="p-1 hover:bg-slate-100 rounded transition-colors"
					>
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
			</div>

			<!-- VIEW / EDIT MODE (existing programa) -->
			{#if mode === 'view'}
				<div class="flex-1 overflow-y-auto px-6 py-6">

					{#if loadingPrograma}
						<div class="flex flex-col items-center justify-center py-12 gap-2">
							<div class="w-4 h-4 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></div>
							<span class="text-sm text-slate-500">Cargando programa...</span>
						</div>

						{:else if viewError}
						<div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
							{viewError}
						</div>
						<button onclick={loadPrograma} class="mt-3 px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">Reintentar</button>

					{:else if editedSections.length === 0}
						<div class="text-center py-8 px-4">
							<p class="text-slate-600 mb-4">No se encontraron secciones en este programa.</p>
							<button type="button" onclick={() => { mode = 'wizard'; step = 1; }} class="px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
								Regenerar programa
							</button>
						</div>

			{:else}
					<!-- Editable sections -->
					<div class="space-y-3">
						{#each editedSections as sec, i}
							<div class="rounded-lg border border-slate-200 p-4 space-y-2">
								<div class="flex items-center gap-2 mb-3">
									{#if sec.numeral_romano}
										<span class="text-sm font-bold text-slate-400">{sec.numeral_romano}.</span>
									{/if}
									<span class="text-sm font-semibold text-slate-700">{sec.nombre_seccion}</span>
								</div>
								<textarea
									class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
				<div class="flex items-center justify-between px-6 py-4 border-t border-slate-100">
					<button
						type="button"
						onclick={() => { mode = 'wizard'; step = 1; }}
						class="px-4 py-2 text-slate-700 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors disabled:opacity-50"
						disabled={isSaving || isApproving}
					>
						Regenerar desde cero
					</button>
					<div class="flex gap-2">
						{#if programaData?.estado === 'BORRADOR'}
							<button
								type="button"
								onclick={handleApprove}
								disabled={isApproving || isSaving}
								class="px-4 py-2 text-green-700 bg-white border border-green-300 rounded-lg text-sm font-medium hover:bg-green-50 transition-colors disabled:opacity-50 inline-flex items-center gap-2"
							>
								{#if isApproving}
									<span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-green-600 rounded-full animate-spin"></span>
									Aprobando...
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
							class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:bg-blue-300 inline-flex items-center gap-2"
						>
							{#if isSaving}
								<span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-white rounded-full animate-spin"></span>
								Guardando...
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
			<div class="px-6 py-4 flex-shrink-0 border-b border-slate-100">
				<ol class="flex items-center list-none m-0 p-0 gap-0">
					{#each STEPS as s, i}
						{@const isComplete = step > s.id}
						{@const isActive   = step === s.id}
						<li class="flex items-center flex-1">
							<div class="flex items-center gap-2">
								<div class="{isComplete ? 'bg-blue-600 text-white' : isActive ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-slate-100 text-slate-400'} w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 transition-all">
									{#if isComplete}<span>✓</span>{:else}<span>{s.id}</span>{/if}
								</div>
								<span class="text-lg">{s.icon}</span>
							</div>
							<span class="text-xs {isActive ? 'text-blue-600 font-semibold' : 'text-slate-500'} ml-2">{s.label}</span>
							{#if i < STEPS.length - 1}
								<div class="{isComplete ? 'bg-blue-600' : 'bg-slate-200'} h-0.5 mx-2 flex-1 transition-colors"></div>
							{/if}
						</li>
					{/each}
					</ol>
				</div>

				<div class="flex-1 overflow-y-auto px-6 py-6">

					{#if step === 1}
					<div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
						<div>
							<h3 class="text-lg font-semibold text-slate-900">📚 Información General de la Asignatura</h3>
							<p class="text-sm text-slate-500 mt-0.5">Proporciona la descripción y contexto de la asignatura</p>
						</div>
						<div class="space-y-1.5">
							<label class="block text-sm font-semibold text-slate-700" for="syllabus-name">
								Asignatura <span class="text-red-500">*</span>
							</label>
							<input id="syllabus-name" type="text" value={curso.asignatura_nombre ?? ''} disabled class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed" />
						</div>
						<div class="space-y-1.5">
							<label class="block text-sm font-semibold text-slate-700" for="syllabus-desc">
								Descripción General <span class="text-red-500">*</span>
							</label>
							<p class="text-xs text-slate-500">Describa brevemente el propósito y contexto de la asignatura</p>
							<textarea id="syllabus-desc" bind:value={descripcion} rows="4" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Ej: Esta asignatura introduce los conceptos fundamentales de..."></textarea>
							<p class="text-xs text-slate-400">{descripcion.length} caracteres</p>
						</div>
					</div>

					{:else if step === 2}
						<div class="space-y-3">
							<div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
								<div>
									<h3 class="text-lg font-semibold text-slate-900">🎯 Objetivos de Aprendizaje</h3>
									<p class="text-sm text-slate-500 mt-0.5">Defina qué deben lograr los estudiantes al finalizar</p>
								</div>
								<div class="space-y-1.5">
									<label class="block text-sm font-semibold text-slate-700" for="syllabus-obj">
										Objetivos <span class="text-red-500">*</span>
									</label>
									<p class="text-xs text-slate-500">Liste los objetivos generales y específicos (uno por línea)</p>
									<textarea id="syllabus-obj" bind:value={objetivos} rows="4" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="• El estudiante will understand...&#10;• El estudiante will be able to..."></textarea>
								</div>
								<div class="space-y-1.5">
									<label class="block text-sm font-semibold text-slate-700" for="syllabus-comp">Competencias</label>
									<p class="text-xs text-slate-500">Competencias que se desarrollarán en la asignatura</p>
									<textarea id="syllabus-comp" bind:value={competencias} rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="• Pensamiento crítico&#10;• Resolución de problemas..."></textarea>
								</div>
							</div>
						</div>

					{:else if step === 3}
					<div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
						<div>
							<h3 class="text-lg font-semibold text-slate-900">📖 Contenidos y Unidades</h3>
							<p class="text-sm text-slate-500 mt-0.5">Organize los temas principales en unidades temáticas</p>
						</div>
						<div class="space-y-3">
							{#each unidades as u, i}
								<div class="rounded-lg border border-slate-200 p-4 space-y-2 border-l-4 border-l-blue-600">
									<div class="flex items-center justify-between">
										<div>
											<span class="text-sm font-semibold text-slate-700">Unidad {i + 1}</span>
										</div>
										{#if unidades.length > 1}
											<button type="button" onclick={() => removeUnidad(i)} title="Eliminar unidad" class="p-1 hover:bg-red-50 text-red-600 rounded transition-colors">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h16zM10 11v6m4-6v6"/></svg>
											</button>
										{/if}
									</div>
									<input type="text" bind:value={u.titulo} placeholder="Título de la unidad (ej: Fundamentos de Diseño)" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent mb-2" />
									<textarea bind:value={u.descripcion} rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Describe los temas y contenidos principales de esta unidad..."></textarea>
								</div>
							{/each}
						</div>
						<button type="button" onclick={addUnidad} class="w-full mt-3 px-4 py-2 border-2 border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
							Agregar Nueva Unidad
						</button>
					</div>

					{:else if step === 4}
					<div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
						<div>
							<h3 class="text-lg font-semibold text-slate-900">✓ Evaluación y Metodología</h3>
							<p class="text-sm text-slate-500 mt-0.5">Define cómo se enseña y cómo se evalúa</p>
						</div>
						<div class="space-y-1.5">
							<label class="block text-sm font-semibold text-slate-700" for="syllabus-met">
								Metodología de Enseñanza <span class="text-red-500">*</span>
							</label>
							<p class="text-xs text-slate-500">Describe las estrategias y métodos pedagógicos a usar</p>
							<textarea id="syllabus-met" bind:value={metodologia} rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="• Clases expositivas&#10;• Trabajo colaborativo&#10;• Casos de estudio..."></textarea>
						</div>
						<div class="space-y-1.5">
							<label class="block text-sm font-semibold text-slate-700" for="syllabus-eval">
								Sistema de Evaluación <span class="text-red-500">*</span>
							</label>
							<p class="text-xs text-slate-500">Especifica los criterios e instrumentos de evaluación</p>
							<textarea id="syllabus-eval" bind:value={evaluacion} rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="• Participación: 10%&#10;• Controles: 30%&#10;• Examen Final: 60%..."></textarea>
						</div>
					</div>

					{:else if step === 5}
					<div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
						<div>
							<h3 class="text-lg font-semibold text-slate-900">✅ Resumen del Programa</h3>
							<p class="text-sm text-slate-500 mt-0.5">Revisa la información antes de generar</p>
						</div>
						<dl class="space-y-3 mt-4">
							<div class="flex flex-col py-2 border-b border-slate-100 last:border-0">
								<dt class="text-sm font-medium text-slate-700">📚 Asignatura</dt>
								<dd class="text-sm text-slate-900 mt-1">{curso.asignatura_nombre ?? `Curso ${curso.cod_curso}`}</dd>
							</div>
							{#if descripcion.trim()}
								<div class="flex flex-col py-2 border-b border-slate-100 last:border-0">
									<dt class="text-sm font-medium text-slate-700">📝 Descripción</dt>
									<dd class="text-sm text-slate-900 mt-1">{descripcion.trim().slice(0, 100)}{descripcion.trim().length > 100 ? '…' : ''}</dd>
								</div>
							{/if}
							{#if objetivos.trim()}
								<div class="flex flex-col py-2 border-b border-slate-100 last:border-0">
									<dt class="text-sm font-medium text-slate-700">🎯 Objetivos</dt>
									<dd class="text-sm text-slate-900 mt-1">{objetivos.trim().slice(0, 100)}{objetivos.trim().length > 100 ? '…' : ''}</dd>
								</div>
							{/if}
							<div class="flex flex-col py-2 border-b border-slate-100 last:border-0">
								<dt class="text-sm font-medium text-slate-700">📖 Unidades</dt>
								<dd class="mt-1"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-medium">{unidades.filter(u => u.titulo.trim()).length} unidades</span></dd>
							</div>
							<div class="flex flex-col py-2 border-b border-slate-100 last:border-0">
								<dt class="text-sm font-medium text-slate-700">✓ Estado</dt>
								<dd class="mt-1"><span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-100 text-green-800 text-xs font-medium">Listo para generar</span></dd>
							</div>
						</dl>
					</div>

					{#if errorMsg}
						<div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm mt-4" role="alert">
							<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
							{errorMsg}
						</div>
					{/if}

					<button type="button" onclick={handleGenerate} disabled={isGenerating} class="w-full mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:bg-blue-300">
						{#if isGenerating}
							<span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-white rounded-full animate-spin mr-2"></span>Generando programa...
						{:else}
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
							Generar Programa
						{/if}
					</button>
					{/if}
				</div>

				<!-- Footer -->
			<div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-100 flex-shrink-0 gap-3">
				<button type="button" onclick={() => { if (step === 1 && curso?.has_programa) { mode = 'view'; } else { step = Math.max(1, step - 1) as 1|2|3|4|5; } }} disabled={isGenerating} class="px-3 py-2 rounded-lg text-sm font-medium bg-white border border-gray-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
					← {step === 1 && curso?.has_programa ? 'Ver existente' : 'Atrás'}
				</button>
				{#if step < 5}
					<button type="button" onclick={() => step = Math.min(5, step + 1) as 1|2|3|4|5} disabled={(step === 1 && !step1Valid) || (step === 2 && !step2Valid) || (step === 3 && !step3Valid) || (step === 4 && !step4Valid)} class="px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 border-none text-white hover:bg-blue-700 disabled:bg-blue-300 disabled:cursor-not-allowed transition-colors">
						Siguiente →
					</button>
				{:else}
					<button type="button" onclick={handleGenerate} disabled={isGenerating} class="px-3 py-2 rounded-lg text-sm font-medium bg-blue-600 border-none text-white hover:bg-blue-700 disabled:bg-blue-300 disabled:cursor-not-allowed transition-colors inline-flex items-center gap-2">
						{#if isGenerating}
							<span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-white rounded-full animate-spin"></span>
						{/if}
						Generar
					</button>
				{/if}
			</div>

		{/if}

	</div>
</div>
{/if}


