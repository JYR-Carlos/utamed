<script>
	import { router } from '@inertiajs/svelte';
	import Button from '@/components/ui/button/button.svelte';
	import Alert from '@/components/ui/alert/alert.svelte';
	import { AlertCircle, CheckCircle, XCircle, ArrowLeft } from 'lucide-svelte';

	export let programa;
	export let curso;

	let isApproving = false;
	let isRejecting = false;
	let showRejectionReason = false;
	let rejectionReason = '';

	const handleApprove = async () => {
		isApproving = true;
		try {
			router.put(`/admin/cursos/${curso.id_curso}/programa/aprobar`, {});
		} catch (error) {
			console.error('Error al aprobar:', error);
		} finally {
			isApproving = false;
		}
	};

	const handleReject = async () => {
		if (!rejectionReason.trim() && showRejectionReason) {
			alert('Por favor ingresa un motivo de rechazo');
			return;
		}

		isRejecting = true;
		try {
			router.put(`/admin/cursos/${curso.id_curso}/programa/rechazar`, {
				razon: rejectionReason
			});
		} catch (error) {
			console.error('Error al rechazar:', error);
		} finally {
			isRejecting = false;
		}
	};

	const goBack = () => {
		router.get('/admin/cursos');
	};

	const getStateColor = (estado) => {
		const colors = {
			BORRADOR: 'bg-gray-100 text-gray-800',
			PENDIENTE: 'bg-yellow-100 text-yellow-800',
			APROBADO: 'bg-green-100 text-green-800',
			RECHAZADO: 'bg-red-100 text-red-800'
		};
		return colors[estado] || 'bg-gray-100 text-gray-800';
	};
</script>

<div class="min-h-screen bg-gray-50 p-6">
	<div class="max-w-4xl mx-auto">
		<!-- Header con navegación -->
		<div class="flex items-center justify-between mb-6">
			<div class="flex items-center gap-4">
				<Button
					onclick={goBack}
					variant="outline"
					class="inline-flex items-center gap-2"
				>
					<ArrowLeft class="w-4 h-4" />
					Volver
				</Button>
				<div>
					<h1 class="text-3xl font-bold text-gray-900">Revisión de Programa</h1>
					<p class="text-gray-600 mt-1">{curso.asignatura_nombre}</p>
				</div>
			</div>
		</div>

		<!-- Información del Curso -->
		<div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 mb-6">
			<h2 class="text-lg font-semibold text-gray-900 mb-4">Información del Curso</h2>
			<div class="grid grid-cols-2 gap-4">
				<div>
					<p class="text-sm text-gray-600">Asignatura</p>
					<p class="font-medium text-gray-900">{curso.asignatura_nombre}</p>
				</div>
				<div>
					<p class="text-sm text-gray-600">Carrera</p>
					<p class="font-medium text-gray-900">{curso.carrera_nombre}</p>
				</div>
				<div>
					<p class="text-sm text-gray-600">Código del Curso</p>
					<p class="font-medium text-gray-900">{curso.id_curso}</p>
				</div>
				<div>
					<p class="text-sm text-gray-600">Estado</p>
					<div class={`inline-block px-3 py-1 rounded-full text-sm font-medium ${getStateColor(programa.estado)}`}>
						{programa.estado}
					</div>
				</div>
			</div>
		</div>

		<!-- Contenido del Programa -->
		<div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 mb-6">
			<h2 class="text-lg font-semibold text-gray-900 mb-4">Contenido del Programa</h2>

			{#if programa.data_syllabus?.secciones}
				<div class="space-y-6">
					{#each programa.data_syllabus.secciones as seccion (seccion.nombre_seccion)}
						<div class="border-l-4 border-blue-500 pl-4">
							<h3 class="text-base font-semibold text-gray-900 mb-2">
								{seccion.numeral_romano}. {seccion.nombre_seccion}
							</h3>
							{#if seccion.contenidos && seccion.contenidos.length > 0}
								<div class="space-y-2">
									{#each seccion.contenidos as contenido (contenido.orden_item)}
										<p class="text-gray-700">
											{contenido.orden_item}. {contenido.texto_contenido || '(vacío)'}
										</p>
									{/each}
								</div>
							{:else}
								<p class="text-gray-500 italic">(Sin contenido)</p>
							{/if}
						</div>
					{/each}
				</div>
			{:else}
				<p class="text-gray-500">No hay contenido en el programa</p>
			{/if}
		</div>

		<!-- Metadatos -->
		<div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
			<p class="text-sm text-gray-600">
				<span class="font-medium">Versión:</span> {programa.version_programa}
			</p>
			<p class="text-sm text-gray-600">
				<span class="font-medium">Fecha de Creación:</span> {new Date(programa.fecha_creacion).toLocaleDateString('es-ES')}
			</p>
		</div>

		<!-- Panel de Acciones -->
		{#if programa.estado === 'PENDIENTE'}
			<div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200">
				<h2 class="text-lg font-semibold text-gray-900 mb-4">Acciones</h2>

				<div class="space-y-4">
					<Alert variant="default">
						<AlertCircle class="h-4 w-4" />
						<div>
							<p class="font-medium">Este programa está en revisión</p>
							<p class="text-sm">Puedes aprobarlo para que esté disponible para estudiantes, o rechazarlo para que el docente lo revise nuevamente.</p>
						</div>
					</Alert>

					{#if showRejectionReason}
						<div>
							<label for="reason" class="block text-sm font-medium text-gray-900 mb-2">
								Motivo de rechazo (opcional)
							</label>
							<textarea
								id="reason"
								bind:value={rejectionReason}
								rows="4"
								class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
								placeholder="Proporciona retroalimentación al docente sobre qué necesita mejorarse..."
							/>
						</div>
					{/if}

					<div class="flex gap-3 pt-4">
						<Button
							onclick={handleApprove}
							disabled={isApproving}
							class="flex-1 bg-green-600 hover:bg-green-700 text-white"
						>
							{#if isApproving}
								Aprobando...
							{:else}
								Aprobar Programa
							{/if}
						</Button>
						<Button
							onclick={() => {
								showRejectionReason = !showRejectionReason;
								if (!showRejectionReason) rejectionReason = '';
							}}
							variant="outline"
							class="px-6"
						>
							{showRejectionReason ? 'Cancelar Rechazo' : 'Rechazar'}
						</Button>
						{#if showRejectionReason}
							<Button
							onclick={handleReject}
								disabled={isRejecting}
								class="flex-1 bg-red-600 hover:bg-red-700 text-white"
							>
								{#if isRejecting}
									Rechazando...
								{:else}
									Confirmar Rechazo
								{/if}
							</Button>
						{/if}
					</div>
				</div>
			</div>
		{:else if programa.estado === 'RECHAZADO'}
			<Alert variant="destructive">
				<AlertCircle class="h-4 w-4" />
				<div>
					<p class="font-medium">Este programa ha sido rechazado</p>
					<p class="text-sm">El docente puede editarlo nuevamente y enviarlo para revisión.</p>
				</div>
			</Alert>
		{:else if programa.estado === 'APROBADO'}
			<Alert variant="default">
				<CheckCircle class="h-4 w-4" />
				<div>
					<p class="font-medium">Este programa ha sido aprobado</p>
					<p class="text-sm">Está disponible para que los estudiantes lo visualicen.</p>
				</div>
			</Alert>
		{/if}
	</div>
</div>

<style>
	:global(button) {
		transition: all 0.2s ease;
	}
</style>
