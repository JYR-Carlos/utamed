<script lang="ts">
	/**
	 * Página de administración integral de usuarios.
	 * 
	 * Gestión CRUD de Estudiantes, Docentes y Administradores del sistema.
	 * Permite crear, editar, eliminar usuarios y asignar roles/permisos especiales.
	 * 
	 * SEGURIDAD: Este componente maneja datos sensibles. Valida que usuario
	 * autenticado sea administrador antes de permitir cualquier operación.
	 * 
	 * Características:
	 * - Filtrado por tipo de usuario (estudiante/docente/admin)
	 * - Búsqueda por RUT, nombre, username
	 * - Modal para crear/editar con campos específicos por tipo
	 * - Modal para cambiar contraseña
	 * - Modal para asignar roles y permisos especiales (RBAC)
	 * - Confirmación antes de eliminación
	 * 
	 * Tablas relacionadas:
	 * - usuario.usuario: Datos base de usuarios
	 * - usuario.estudiante: Perfil de estudiante con carrera
	 * - usuario.docente: Perfil de docente con grado/título/cargo
	 * - usuario.rol: Roles disponibles del sistema
	 * - usuario.permiso: Permisos especiales asignables
	 * - usuario.usuario_rol_asignación: Asignaciones de roles
	 * - usuario.usuario_permiso_especial: Asignaciones de permisos
	 */
	import AdminLayout from '@/layouts/AdminLayout.svelte';
	import { router } from '@inertiajs/svelte';
	import DataTable from '@/components/custom/admin/DataTable.svelte';
	import FormModal from '@/components/custom/admin/FormModal.svelte';
	import PermissionsModal from '@/components/custom/admin/PermissionsModal.svelte';
	import DeleteConfirmation from '@/components/custom/admin/DeleteConfirmation.svelte';
	import type {
		Estudiante,
		Docente,
		Administrador,
		Carrera,
		PaginatedResponse,
		EstudianteFormData,
		DocenteFormData,
		AdministradorFormData
	} from '@/types/admin.types';

	/**
	 * Props recibidas del servidor.
	 */
	interface Props {
		/** Usuarios paginados según tipo seleccionado */
		usuarios: PaginatedResponse<Estudiante | Docente | Administrador>;
		/** Tipo de usuario a mostrar: estudiante, docente o administrador */
		tipo: 'estudiante' | 'docente' | 'administrador';
		/** Carreras disponibles (para asignar a estudiantes) */
		carreras: Carrera[];
		/** Roles disponibles para asignar a usuarios */
		availableRoles: any[];
		/** Permisos especiales disponibles por módulo */
		availablePermissions: Record<string, any[]>;
		/** Filtros de búsqueda/tipo */
		filters: { search?: string; tipo?: string };
	}

	let { usuarios, tipo, carreras, filters, availableRoles = [], availablePermissions = {} }: Props = $props();

	let showModal = $state(false);
	let showDeleteDialog = $state(false);
	let showPasswordModal = $state(false);
	let isLoading = $state(false);
	let editingUsuario = $state<Estudiante | Docente | Administrador | null>(null);
	let deletingUsuario = $state<Estudiante | Docente | Administrador | null>(null);
	let changingPasswordUsuario = $state<Estudiante | Docente | Administrador | null>(null);

	// Use $derived to fix lint warning about tipo reference
	let currentTipo = $derived(tipo);

	let estudianteFormData = $state<EstudianteFormData>({
		rut: '',
		nombre1: '',
		nombre2: '',
		apellido1: '',
		apellido2: '',
		email: '',
		agno_ingreso: undefined,
		id_carrera: undefined,
		username: '',
		password: ''
	});

	let docenteFormData = $state<DocenteFormData>({
		rut: '',
		nombre1: '',
		nombre2: '',
		apellido1: '',
		apellido2: '',
		email: '',
		grado: '',
		titulo: '',
		cargo: '',
		username: '',
		password: ''
	});

	let administradorFormData = $state<AdministradorFormData>({
		rut: '',
		nombre1: '',
		nombre2: '',
		apellido1: '',
		apellido2: '',
		email: '',
		username: '',
		password: ''
	});

	const estudianteColumns = [
		{ key: 'id_estudiante', label: 'ID' },
		{ key: 'rut', label: 'RUT' },
		{ key: 'nombre1', label: 'Nombre' },
		{ key: 'apellido1', label: 'Apellido' },
		{ key: 'agno_ingreso', label: 'Año Ingreso' },
		{ key: 'carrera.nombre', label: 'Carrera' }
	];

	const docenteColumns = [
		{ key: 'id_docente', label: 'ID' },
		{ key: 'rut', label: 'RUT' },
		{ key: 'nombre1', label: 'Nombre' },
		{ key: 'apellido1', label: 'Apellido' },
		{ key: 'grado', label: 'Grado' },
		{ key: 'cargo', label: 'Cargo' }
	];

	const administradorColumns = [
		{ key: 'id_usuario', label: 'ID' },
		{ key: 'rut', label: 'RUT' },
		{ key: 'username', label: 'Usuario' },
		{ key: 'nombre1', label: 'Nombre' },
		{ key: 'apellido1', label: 'Apellido' },
		{ key: 'email', label: 'Email' }
	];

	function switchTipo(newTipo: 'estudiante' | 'docente' | 'administrador') {
		router.get('/admin/usuarios', { tipo: newTipo }, { preserveState: false });
	}

	function openCreateModal() {
		editingUsuario = null;
		if (currentTipo === 'estudiante') {
			estudianteFormData = {
				rut: '',
				nombre1: '',
				nombre2: '',
				apellido1: '',
				apellido2: '',
				email: '',
				agno_ingreso: undefined,
				id_carrera: undefined,
				username: '',
				password: ''
			};
		} else if (currentTipo === 'docente') {
			docenteFormData = {
				rut: '',
				nombre1: '',
				nombre2: '',
				apellido1: '',
				apellido2: '',
				email: '',
				grado: '',
				titulo: '',
				cargo: '',
				username: '',
				password: ''
			};
		} else {
			administradorFormData = {
				rut: '',
				nombre1: '',
				nombre2: '',
				apellido1: '',
				apellido2: '',
				email: '',
				username: '',
				password: ''
			};
		}
		showModal = true;
	}

	function openEditModal(usuario: Estudiante | Docente | Administrador) {
		editingUsuario = usuario;
		if (currentTipo === 'estudiante') {
			const estudiante = usuario as Estudiante;
			estudianteFormData = {
				rut: estudiante.rut,
				nombre1: estudiante.nombre1,
				nombre2: estudiante.nombre2 || '',
				apellido1: estudiante.apellido1,
				apellido2: estudiante.apellido2 || '',
				email: estudiante.email || '',
				agno_ingreso: estudiante.agno_ingreso,
				id_carrera: estudiante.id_carrera,
				username: '',
				password: ''
			};
		} else if (currentTipo === 'docente') {
			const docente = usuario as Docente;
			docenteFormData = {
				rut: docente.rut,
				nombre1: docente.nombre1,
				nombre2: docente.nombre2 || '',
				apellido1: docente.apellido1,
				apellido2: docente.apellido2 || '',
				email: docente.email || '',
				grado: docente.grado || '',
				titulo: docente.titulo || '',
				cargo: docente.cargo || '',
				username: '',
				password: ''
			};
		} else {
			const admin = usuario as Administrador;
			administradorFormData = {
				rut: admin.rut,
				nombre1: admin.nombre1,
				nombre2: admin.nombre2 || '',
				apellido1: admin.apellido1,
				apellido2: admin.apellido2 || '',
				email: admin.email || '',
				username: '',
				password: ''
			};
		}
		showModal = true;
	}

	function closeModal() {
		showModal = false;
		editingUsuario = null;
	}

	function handleSubmit() {
		isLoading = true;

		const formData = currentTipo === 'estudiante' 
			? estudianteFormData 
			: currentTipo === 'docente' 
				? docenteFormData 
				: administradorFormData;
		const dataToSend = { ...formData, tipo: currentTipo };

		if (editingUsuario) {
			const id = currentTipo === 'estudiante' 
				? (editingUsuario as Estudiante).id_estudiante 
				: currentTipo === 'docente'
					? (editingUsuario as Docente).id_docente
					: (editingUsuario as Administrador).id_usuario;

			router.put(`/admin/usuarios/${id}`, dataToSend, {
				onSuccess: () => {
					console.log('Usuario updated successfully');
					closeModal();
					isLoading = false;
				},
				onError: (errors) => {
					console.error('Error updating usuario:', errors);
					alert('Error al actualizar usuario: ' + JSON.stringify(errors));
					isLoading = false;
				}
			});
		} else {
			router.post('/admin/usuarios', dataToSend, {
				onSuccess: () => {
					console.log('Usuario created successfully');
					closeModal();
					isLoading = false;
				},
				onError: (errors) => {
					console.error('Error creating usuario:', errors);
					alert('Error al crear usuario: ' + JSON.stringify(errors));
					isLoading = false;
				}
			});
		}
	}

	function openDeleteDialog(usuario: Estudiante | Docente | Administrador) {
		deletingUsuario = usuario;
		showDeleteDialog = true;
	}

	function closeDeleteDialog() {
		showDeleteDialog = false;
		deletingUsuario = null;
	}

	function handleDelete() {
		if (!deletingUsuario) return;

		isLoading = true;
		const id = currentTipo === 'estudiante' 
			? (deletingUsuario as Estudiante).id_estudiante 
			: currentTipo === 'docente'
				? (deletingUsuario as Docente).id_docente
				: (deletingUsuario as Administrador).id_usuario;

		router.delete(`/admin/usuarios/${id}`, {
			data: { tipo: currentTipo },
			onSuccess: () => {
				closeDeleteDialog();
				isLoading = false;
			},
			onError: (errors) => {
				console.error('Error deleting usuario:', errors);
				alert('Error al eliminar usuario: ' + JSON.stringify(errors));
				isLoading = false;
			}
		});
	}

	let passwordFormData = $state({
		password: '',
		password_confirmation: ''
	});

	function openPasswordModal(usuario: Estudiante | Docente | Administrador) {
		changingPasswordUsuario = usuario;
		passwordFormData = {
			password: '',
			password_confirmation: ''
		};
		showPasswordModal = true;
	}

	function closePasswordModal() {
		showPasswordModal = false;
		changingPasswordUsuario = null;
		passwordFormData = {
			password: '',
			password_confirmation: ''
		};
	}

	function handlePasswordChange() {
		if (!changingPasswordUsuario) return;

		isLoading = true;
		const id = (changingPasswordUsuario as any).id_usuario;

		router.post(`/admin/usuarios/${id}/change-password`, passwordFormData, {
			onSuccess: () => {
				closePasswordModal();
				isLoading = false;
			},
			onError: (errors) => {
				console.error('Error changing password:', errors);
				alert('Error al cambiar contraseña: ' + JSON.stringify(errors));
				isLoading = false;
			}
		});
	}

	function handleToggleActive(usuario: Estudiante | Docente | Administrador) {
		const id = (usuario as any).id_usuario;

		router.post(`/admin/usuarios/${id}/toggle-active`, {}, {
			preserveScroll: true
		});
	}

	// Permissions Modal Logic
	let showPermissionsModal = $state(false);
	let permissionsUser = $state<any>(null);

	function openPermissionsModal(usuario: Estudiante | Docente | Administrador) {
		// Extract base user object depending on type, or just pass the specialized object 
		// if the backend handles extracting id_usuario correctly (it does).
		// However, for display purposes (username), we might need to access the inner 'usuario' relation 
		// for Docente/Estudiante if 'username' is not top-level.
		// Checking types: Admin has username. Docente/Estudiante have 'usuario' relation with username.
		// Let's normalize or pass the whole object and let Modal handle it.
		// The Modal expects 'usuario' with 'id_usuario' and 'username'.
		
		// Since we use JOINs in the controller and select fields into the main object,
		// the user data (id_usuario, username) is at the top level.
		permissionsUser = usuario;
		showPermissionsModal = true;
	}

	function closePermissionsModal() {
		showPermissionsModal = false;
		permissionsUser = null;
	}
</script>

<AdminLayout>
<div class="page-container">
	<div class="page-header">
		<div>
			<h1 class="page-title">Usuarios</h1>
			<p class="page-description">Gestión de estudiantes, docentes y administradores</p>
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
			Nuevo {currentTipo === 'estudiante' ? 'Estudiante' : currentTipo === 'docente' ? 'Docente' : 'Administrador'}
		</button>
	</div>

	<div class="tipo-selector">
		<button
			class="tipo-btn"
			class:active={currentTipo === 'estudiante'}
			onclick={() => switchTipo('estudiante')}
		>
			Estudiantes
		</button>
		<button
			class="tipo-btn"
			class:active={currentTipo === 'docente'}
			onclick={() => switchTipo('docente')}
		>
			Docentes
		</button>
		<button
			class="tipo-btn"
			class:active={currentTipo === 'administrador'}
			onclick={() => switchTipo('administrador')}
		>
			Administradores
		</button>
	</div>

	<DataTable
		data={usuarios}
		columns={currentTipo === 'estudiante' ? estudianteColumns : currentTipo === 'docente' ? docenteColumns : administradorColumns}
		onEdit={openEditModal}
		onDelete={openDeleteDialog}
		onPasswordChange={openPasswordModal}
		onToggleActive={handleToggleActive}
		onCustomAction={openPermissionsModal}
		customActionLabel="Permisos"
	/>
</div>

<PermissionsModal
	bind:isOpen={showPermissionsModal}
	onClose={closePermissionsModal}
	usuario={permissionsUser}
	{availableRoles}
	{availablePermissions}
/>

<FormModal
	bind:isOpen={showModal}
	title={editingUsuario
		? `Editar ${currentTipo === 'estudiante' ? 'Estudiante' : currentTipo === 'docente' ? 'Docente' : 'Administrador'}`
		: `Nuevo ${currentTipo === 'estudiante' ? 'Estudiante' : currentTipo === 'docente' ? 'Docente' : 'Administrador'}`}
	onClose={closeModal}
	onSubmit={handleSubmit}
	{isLoading}
>
	{#if currentTipo === 'estudiante'}
		<!-- Estudiante Form -->
		<div class="form-group">
			<label for="rut" class="form-label">RUT *</label>
			<input
				id="rut"
				type="text"
				bind:value={estudianteFormData.rut}
				class="form-input"
				placeholder="Ej: 12345678-9"
				required
			/>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="nombre1" class="form-label">Primer Nombre *</label>
				<input
					id="nombre1"
					type="text"
					bind:value={estudianteFormData.nombre1}
					class="form-input"
					placeholder="Ej: Juan"
					required
				/>
			</div>

			<div class="form-group">
				<label for="nombre2" class="form-label">Segundo Nombre</label>
				<input
					id="nombre2"
					type="text"
					bind:value={estudianteFormData.nombre2}
					class="form-input"
					placeholder="Ej: Carlos"
				/>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="apellido1" class="form-label">Primer Apellido *</label>
				<input
					id="apellido1"
					type="text"
					bind:value={estudianteFormData.apellido1}
					class="form-input"
					placeholder="Ej: González"
					required
				/>
			</div>

			<div class="form-group">
				<label for="apellido2" class="form-label">Segundo Apellido</label>
				<input
					id="apellido2"
					type="text"
					bind:value={estudianteFormData.apellido2}
					class="form-input"
					placeholder="Ej: Pérez"
				/>
			</div>
		</div>

		<div class="form-group">
			<label for="email_estudiante" class="form-label">Email</label>
			<input
				id="email_estudiante"
				type="email"
				bind:value={estudianteFormData.email}
				class="form-input"
				placeholder="Ej: juan.gonzalez@ejemplo.com"
			/>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="agno_ingreso" class="form-label">Año de Ingreso</label>
				<input
					id="agno_ingreso"
					type="number"
					bind:value={estudianteFormData.agno_ingreso}
					class="form-input"
					min="1900"
					max="2100"
					placeholder="Ej: 2024"
				/>
			</div>

			<div class="form-group">
				<label for="carrera" class="form-label">Carrera</label>
				<select id="carrera" bind:value={estudianteFormData.id_carrera} class="form-input">
					<option value={undefined}>Sin carrera</option>
					{#each carreras as carrera}
						<option value={carrera.id_carrera}>{carrera.nombre}</option>
					{/each}
				</select>
			</div>
		</div>

		{#if !editingUsuario}
			<div class="form-divider"></div>
			<p class="form-section-title">Credenciales de Acceso</p>

			<div class="form-row">
				<div class="form-group">
					<label for="username" class="form-label">Usuario *</label>
					<input
						id="username"
						type="text"
						bind:value={estudianteFormData.username}
						class="form-input"
						placeholder="Máx. 10 caracteres"
						maxlength="10"
						required
					/>
				</div>

				<div class="form-group">
					<label for="password" class="form-label">Contraseña *</label>
					<input
						id="password"
						type="password"
						bind:value={estudianteFormData.password}
						class="form-input"
						placeholder="Mín. 6 caracteres"
						minlength="6"
						required
					/>
				</div>
			</div>
		{/if}
	{:else if currentTipo === 'docente'}
		<!-- Docente Form -->
		<div class="form-group">
			<label for="rut_docente" class="form-label">RUT *</label>
			<input
				id="rut_docente"
				type="text"
				bind:value={docenteFormData.rut}
				class="form-input"
				placeholder="Ej: 12345678-9"
				required
			/>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="nombre1_docente" class="form-label">Primer Nombre *</label>
				<input
					id="nombre1_docente"
					type="text"
					bind:value={docenteFormData.nombre1}
					class="form-input"
					placeholder="Ej: Juan"
					required
				/>
			</div>

			<div class="form-group">
				<label for="nombre2_docente" class="form-label">Segundo Nombre</label>
				<input
					id="nombre2_docente"
					type="text"
					bind:value={docenteFormData.nombre2}
					class="form-input"
					placeholder="Ej: Carlos"
				/>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="apellido1_docente" class="form-label">Primer Apellido *</label>
				<input
					id="apellido1_docente"
					type="text"
					bind:value={docenteFormData.apellido1}
					class="form-input"
					placeholder="Ej: González"
					required
				/>
			</div>

			<div class="form-group">
				<label for="apellido2_docente" class="form-label">Segundo Apellido</label>
				<input
					id="apellido2_docente"
					type="text"
					bind:value={docenteFormData.apellido2}
					class="form-input"
					placeholder="Ej: Pérez"
				/>
			</div>
		</div>

		<div class="form-group">
			<label for="email_docente" class="form-label">Email</label>
			<input
				id="email_docente"
				type="email"
				bind:value={docenteFormData.email}
				class="form-input"
				placeholder="Ej: juan.gonzalez@ejemplo.com"
			/>
		</div>

		<div class="form-group">
			<label for="titulo_docente" class="form-label">Título</label>
			<input
				id="titulo_docente"
				type="text"
				bind:value={docenteFormData.titulo}
				class="form-input"
				placeholder="Ej: Ingeniero Civil"
			/>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="grado_docente" class="form-label">Grado Académico</label>
				<input
					id="grado_docente"
					type="text"
					bind:value={docenteFormData.grado}
					class="form-input"
					placeholder="Ej: Doctor en Medicina"
				/>
			</div>

			<div class="form-group">
				<label for="cargo_docente" class="form-label">Cargo</label>
				<input
					id="cargo_docente"
					type="text"
					bind:value={docenteFormData.cargo}
					class="form-input"
					placeholder="Ej: Profesor Titular"
				/>
			</div>
		</div>

		{#if !editingUsuario}
			<div class="form-divider"></div>
			<p class="form-section-title">Credenciales de Acceso</p>

			<div class="form-row">
				<div class="form-group">
					<label for="username_docente" class="form-label">Usuario *</label>
					<input
						id="username_docente"
						type="text"
						bind:value={docenteFormData.username}
						class="form-input"
						placeholder="Máx. 10 caracteres"
						maxlength="10"
						required
					/>
				</div>

				<div class="form-group">
					<label for="password_docente" class="form-label">Contraseña *</label>
					<input
						id="password_docente"
						type="password"
						bind:value={docenteFormData.password}
						class="form-input"
						placeholder="Mín. 6 caracteres"
						minlength="6"
						required
					/>
				</div>
			</div>
		{/if}
	{:else if currentTipo === 'administrador'}
		<!-- Administrador Form -->
		<div class="form-row">
			<div class="form-group">
				<label for="rut" class="form-label">RUT *</label>
				<input
					id="rut"
					type="text"
					bind:value={administradorFormData.rut}
					class="form-input"
					placeholder="Ej: 12345678-9"
					required
				/>
			</div>

			<div class="form-group">
				<label for="email" class="form-label">Email</label>
				<input
					id="email"
					type="email"
					bind:value={administradorFormData.email}
					class="form-input"
					placeholder="Ej: admin@uta.cl"
				/>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="nombre1" class="form-label">Primer Nombre *</label>
				<input
					id="nombre1"
					type="text"
					bind:value={administradorFormData.nombre1}
					class="form-input"
					placeholder="Ej: Juan"
					required
				/>
			</div>

			<div class="form-group">
				<label for="nombre2" class="form-label">Segundo Nombre</label>
				<input
					id="nombre2"
					type="text"
					bind:value={administradorFormData.nombre2}
					class="form-input"
					placeholder="Ej: Carlos"
				/>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="apellido1" class="form-label">Primer Apellido *</label>
				<input
					id="apellido1"
					type="text"
					bind:value={administradorFormData.apellido1}
					class="form-input"
					placeholder="Ej: Pérez"
					required
				/>
			</div>

			<div class="form-group">
				<label for="apellido2" class="form-label">Segundo Apellido</label>
				<input
					id="apellido2"
					type="text"
					bind:value={administradorFormData.apellido2}
					class="form-input"
					placeholder="Ej: García"
				/>
			</div>
		</div>

		{#if !editingUsuario}
			<div class="form-divider"></div>
			<p class="form-section-title">Credenciales de Acceso</p>

			<div class="form-row">
				<div class="form-group">
					<label for="username" class="form-label">Usuario *</label>
					<input
						id="username"
						type="text"
						bind:value={administradorFormData.username}
						class="form-input"
						placeholder="Máx. 30 caracteres"
						maxlength="30"
						required
					/>
				</div>

				<div class="form-group">
					<label for="password" class="form-label">Contraseña *</label>
					<input
						id="password"
						type="password"
						bind:value={administradorFormData.password}
						class="form-input"
						placeholder="Mín. 6 caracteres"
						minlength="6"
						required
					/>
				</div>
			</div>
		{/if}
	{:else}
		<!-- Docente Form -->
		<div class="form-group">
			<label for="rut" class="form-label">RUT *</label>
			<input
				id="rut"
				type="text"
				bind:value={docenteFormData.rut}
				class="form-input"
				placeholder="Ej: 12345678-9"
				required
			/>
		</div>


		<div class="form-row">
			<div class="form-group">
				<label for="nombre1_docente_2" class="form-label">Primer Nombre *</label>
				<input
					id="nombre1_docente_2"
					type="text"
					bind:value={docenteFormData.nombre1}
					class="form-input"
					placeholder="Ej: Juan"
					required
				/>
			</div>

			<div class="form-group">
				<label for="nombre2_docente_2" class="form-label">Segundo Nombre</label>
				<input
					id="nombre2_docente_2"
					type="text"
					bind:value={docenteFormData.nombre2}
					class="form-input"
					placeholder="Ej: Carlos"
				/>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="apellido1_docente_2" class="form-label">Primer Apellido *</label>
				<input
					id="apellido1_docente_2"
					type="text"
					bind:value={docenteFormData.apellido1}
					class="form-input"
					placeholder="Ej: González"
					required
				/>
			</div>

			<div class="form-group">
				<label for="apellido2_docente_2" class="form-label">Segundo Apellido</label>
				<input
					id="apellido2_docente_2"
					type="text"
					bind:value={docenteFormData.apellido2}
					class="form-input"
					placeholder="Ej: Pérez"
				/>
			</div>
		</div>

		<div class="form-group">
			<label for="email_docente_2" class="form-label">Email</label>
			<input
				id="email_docente_2"
				type="email"
				bind:value={docenteFormData.email}
				class="form-input"
				placeholder="Ej: juan.gonzalez@ejemplo.com"
			/>
		</div>

		<div class="form-group">
			<label for="titulo_2" class="form-label">Título</label>
			<input
				id="titulo_2"
				type="text"
				bind:value={docenteFormData.titulo}
				class="form-input"
				placeholder="Ej: Ingeniero Civil"
			/>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="grado" class="form-label">Grado Académico</label>
				<input
					id="grado"
					type="text"
					bind:value={docenteFormData.grado}
					class="form-input"
					placeholder="Ej: Doctor en Medicina"
				/>
			</div>

			<div class="form-group">
				<label for="cargo" class="form-label">Cargo</label>
				<input
					id="cargo"
					type="text"
					bind:value={docenteFormData.cargo}
					class="form-input"
					placeholder="Ej: Profesor Titular"
				/>
			</div>
		</div>

		{#if !editingUsuario}
			<div class="form-divider"></div>
			<p class="form-section-title">Credenciales de Acceso</p>

			<div class="form-row">
				<div class="form-group">
					<label for="username" class="form-label">Usuario *</label>
					<input
						id="username"
						type="text"
						bind:value={docenteFormData.username}
						class="form-input"
						placeholder="Máx. 10 caracteres"
						maxlength="10"
						required
					/>
				</div>

				<div class="form-group">
					<label for="password" class="form-label">Contraseña *</label>
					<input
						id="password"
						type="password"
						bind:value={docenteFormData.password}
						class="form-input"
						placeholder="Mín. 6 caracteres"
						minlength="6"
						required
					/>
				</div>
			</div>
		{/if}
	{/if}
</FormModal>

<DeleteConfirmation
	bind:isOpen={showDeleteDialog}
	title="¿Eliminar {currentTipo === 'estudiante' ? 'Estudiante' : currentTipo === 'docente' ? 'Docente' : 'Administrador'}?"
	message="Esta acción no se puede deshacer. Si el usuario tiene registros asociados, no podrá ser eliminado."
	onConfirm={handleDelete}
	onCancel={closeDeleteDialog}
	{isLoading}
/>

<!-- Password Change Modal -->
<FormModal
	bind:isOpen={showPasswordModal}
	title="Cambiar Contraseña"
	onClose={closePasswordModal}
	onSubmit={handlePasswordChange}
	{isLoading}
>
	<div class="form-group">
		<label for="new_password" class="form-label">Nueva Contraseña *</label>
		<input
			id="new_password"
			type="password"
			bind:value={passwordFormData.password}
			class="form-input"
			placeholder="Mín. 6 caracteres"
			minlength="6"
			required
		/>
	</div>

	<div class="form-group">
		<label for="password_confirmation" class="form-label">Confirmar Contraseña *</label>
		<input
			id="password_confirmation"
			type="password"
			bind:value={passwordFormData.password_confirmation}
			class="form-input"
			placeholder="Repita la contraseña"
			minlength="6"
			required
		/>
	</div>
</FormModal>


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
		margin-bottom: 1.5rem;
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

	.tipo-selector {
		display: flex;
		gap: 0.5rem;
		margin-bottom: 1.5rem;
		padding: 0.25rem;
		background: #f3f4f6;
		border-radius: 8px;
		width: fit-content;
	}

	.tipo-btn {
		padding: 0.5rem 1.25rem;
		background: transparent;
		border: none;
		border-radius: 6px;
		font-weight: 500;
		color: #6b7280;
		cursor: pointer;
		transition: all 0.2s;
	}

	.tipo-btn.active {
		background: white;
		color: #3b82f6;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}

	.tipo-btn:hover:not(.active) {
		color: #374151;
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

	.form-divider {
		height: 1px;
		background: #e5e7eb;
		margin: 1.5rem 0 1rem 0;
	}

	.form-section-title {
		font-size: 0.875rem;
		font-weight: 600;
		color: #374151;
		margin: 0 0 1rem 0;
	}
</style>
</AdminLayout>
