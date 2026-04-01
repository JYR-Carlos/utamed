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
   */
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import PermissionsModal from '@/components/custom/admin/permissions-modal/PermissionsModal.svelte';
  import {
    UsuarioList,
    UsuarioForm,
    UsuarioDeleteConfirm,
    PasswordChangeModal,
    switchTipo as apiSwitchTipo,
    createUsuario,
    updateUsuario,
    deleteUsuario,
    changePassword,
    toggleActive,
  } from '@/modules/resources/usuario';
  import type {
    UsuarioItem,
    Carrera,
    PaginatedResponse,
    EstudianteFormData,
    DocenteFormData,
    AdministradorFormData,
    UsuarioData,
  } from '@/types/admin.types';

  // ====== CONSTANTES Y TIPOS ======
  const UserType = {
    STUDENT: 'estudiante',
    TEACHER: 'docente',
    ADMIN: 'administrador',
  } as const;

  type UserType = (typeof UserType)[keyof typeof UserType];
  type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

  const USER_TYPE_LABELS: Record<UserType, string> = {
    [UserType.STUDENT]: 'Estudiante',
    [UserType.TEACHER]: 'Docente',
    [UserType.ADMIN]: 'Administrador',
  };

  const COLUMN_CONFIGS: Record<UserType, Array<{ key: string; label: string }>> = {
    [UserType.STUDENT]: [
      { key: 'estudiante.id_estudiante', label: 'ID' },
      { key: 'usuario.rut', label: 'RUT' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'estudiante.agno_ingreso', label: 'Año Ingreso' },
      { key: 'estudiante.carrera.nombre', label: 'Carrera' },
    ],
    [UserType.TEACHER]: [
      { key: 'docente.id_docente', label: 'ID' },
      { key: 'usuario.rut', label: 'RUT' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'docente.grado', label: 'Grado' },
      { key: 'docente.cargo', label: 'Cargo' },
    ],
    [UserType.ADMIN]: [
      { key: 'usuario.id_usuario', label: 'ID' },
      { key: 'usuario.rut', label: 'RUT' },
      { key: 'usuario.username', label: 'Usuario' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'usuario.email', label: 'Email' },
    ],
  };

  // ====== FUNCIONES AUXILIARES ======
  function getUsuarioId(item: UsuarioItem, tipo: UserType): number {
    switch (tipo) {
      case UserType.STUDENT:
        return item.estudiante?.id_estudiante || 0;
      case UserType.TEACHER:
        return item.docente?.id_docente || 0;
      case UserType.ADMIN:
        return item.usuario.id_usuario;
    }
  }

  function createEmptyFormData(tipo: UserType): UserFormData {
    const baseData = {
      rut: '',
      nombre1: '',
      nombre2: '',
      apellido1: '',
      apellido2: '',
      email: '',
      username: '',
      password: '',
    };

    if (tipo === UserType.STUDENT) {
      return {
        ...baseData,
        agno_ingreso: undefined,
        id_carrera: undefined,
      } as EstudianteFormData;
    } else if (tipo === UserType.TEACHER) {
      return {
        ...baseData,
        grado: '',
        titulo: '',
        cargo: '',
      } as DocenteFormData;
    }
    return baseData as AdministradorFormData;
  }

  function loadFormDataFromItem(item: UsuarioItem, tipo: UserType): UserFormData {
    const baseData = {
      rut: item.usuario.rut || '',
      nombre1: item.usuario.nombre1 || '',
      nombre2: item.usuario.nombre2 || '',
      apellido1: item.usuario.apellido1 || '',
      apellido2: item.usuario.apellido2 || '',
      email: item.usuario.email || '',
      username: '',
      password: '',
    };

    if (tipo === UserType.STUDENT) {
      return {
        ...baseData,
        agno_ingreso: item.estudiante?.agno_ingreso,
        id_carrera: item.estudiante?.id_carrera,
      } as EstudianteFormData;
    } else if (tipo === UserType.TEACHER) {
      return {
        ...baseData,
        grado: item.docente?.grado || '',
        titulo: item.docente?.titulo || '',
        cargo: item.docente?.cargo || '',
      } as DocenteFormData;
    }
    return baseData as AdministradorFormData;
  }

  function handleError(context: string, errors: any) {
    console.error(`Error ${context}:`, errors);
    alert(`Error al ${context}: ` + JSON.stringify(errors));
  }

  /**
   * Props recibidas del servidor.
   */
  interface Props {
    /** Usuarios paginados según tipo seleccionado */
    usuarios: PaginatedResponse<UsuarioItem>;
    /** Tipo de usuario a mostrar: estudiante, docente o administrador */
    tipo: 'estudiante' | 'docente' | 'administrador';
    /** Carreras disponibles (para asignar a estudiantes) */
    carreras: Carrera[];
  }

  let { usuarios, tipo, carreras }: Props = $props();

  let showModal = $state(false);
  let showDeleteDialog = $state(false);
  let showPasswordModal = $state(false);
  let showPermissionsModal = $state(false);
  let isLoading = $state(false);
  let editingUsuario = $state<UsuarioItem | null>(null);
  let deletingUsuario = $state<UsuarioItem | null>(null);
  let changingPasswordUsuario = $state<UsuarioItem | null>(null);
  let permissionsUser = $state<UsuarioData | null>(null);

  let currentTipo = $derived(tipo as UserType);
  let currentFormData = $state<UserFormData>(createEmptyFormData('estudiante' as UserType));

  // Actualizar formulario cuando cambia el tipo
  $effect(() => {
    if (!editingUsuario && showModal) {
      currentFormData = createEmptyFormData(currentTipo);
    }
  });

  let passwordFormData = $state({
    password: '',
    password_confirmation: '',
  });

  // ====== FUNCIONES DE MANEJO DE EVENTOS ======
  function switchTipo(newTipo: UserType) {
    apiSwitchTipo(newTipo);
  }

  function openCreateModal() {
    editingUsuario = null;
    currentFormData = createEmptyFormData(currentTipo);
    showModal = true;
  }

  function openEditModal(item: UsuarioItem) {
    editingUsuario = item;
    currentFormData = loadFormDataFromItem(item, currentTipo);
    showModal = true;
  }

  function closeModal() {
    showModal = false;
    editingUsuario = null;
  }

  function handleSubmit() {
    isLoading = true;
    const dataToSend = { ...currentFormData, tipo: currentTipo };

    if (editingUsuario) {
      const id = getUsuarioId(editingUsuario, currentTipo);
      updateUsuario(id, dataToSend, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: (errors) => {
          handleError('actualizar usuario', errors);
          isLoading = false;
        },
      });
    } else {
      createUsuario(dataToSend, {
        onSuccess: () => {
          closeModal();
          isLoading = false;
        },
        onError: (errors) => {
          handleError('crear usuario', errors);
          isLoading = false;
        },
      });
    }
  }

  function openDeleteDialog(usuario: UsuarioItem) {
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
    const id = getUsuarioId(deletingUsuario, currentTipo);

    deleteUsuario(id, currentTipo, {
      onSuccess: () => {
        closeDeleteDialog();
        isLoading = false;
      },
      onError: (errors) => {
        handleError('eliminar usuario', errors);
        isLoading = false;
      },
    });
  }

  function openPasswordModal(usuario: UsuarioItem) {
    changingPasswordUsuario = usuario;
    passwordFormData = { password: '', password_confirmation: '' };
    showPasswordModal = true;
  }

  function closePasswordModal() {
    showPasswordModal = false;
    changingPasswordUsuario = null;
    passwordFormData = { password: '', password_confirmation: '' };
  }

  function handlePasswordChange() {
    if (!changingPasswordUsuario) return;

    isLoading = true;
    const id = changingPasswordUsuario.usuario.id_usuario;

    changePassword(id, passwordFormData, {
      onSuccess: () => {
        closePasswordModal();
        isLoading = false;
      },
      onError: (errors) => {
        handleError('cambiar contraseña', errors);
        isLoading = false;
      },
    });
  }

  function handleToggleActive(usuario: UsuarioItem) {
    toggleActive(usuario.usuario.id_usuario);
  }

  function openPermissionsModal(item: UsuarioItem) {
    permissionsUser = item.usuario;
    showPermissionsModal = true;
  }

  function closePermissionsModal() {
    showPermissionsModal = false;
    permissionsUser = null;
  }
</script>

<AdminLayout>
  <div>
    <div class="flex justify-between items-start mb-6">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 mb-1">Usuarios</h1>
        <p class="text-sm text-gray-500">Gestión de estudiantes, docentes y administradores</p>
      </div>
      <button
        onclick={openCreateModal}
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-linear-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white border-0 rounded-lg font-medium cursor-pointer transition-all shadow-sm active:scale-95"
      >
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
        Nuevo {USER_TYPE_LABELS[currentTipo]}
      </button>
    </div>

    <div class="flex gap-2 p-1 bg-gray-100 rounded-lg w-fit mb-6">
      <button
        onclick={() => switchTipo(UserType.STUDENT)}
        class={currentTipo === UserType.STUDENT
          ? 'px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-white text-blue-500 shadow-sm'
          : 'px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-transparent text-gray-500 hover:text-gray-700'}
      >
        Estudiantes
      </button>
      <button
        onclick={() => switchTipo(UserType.TEACHER)}
        class={currentTipo === UserType.TEACHER
          ? 'px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-white text-blue-500 shadow-sm'
          : 'px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-transparent text-gray-500 hover:text-gray-700'}
      >
        Docentes
      </button>
      <button
        onclick={() => switchTipo(UserType.ADMIN)}
        class={currentTipo === UserType.ADMIN
          ? 'px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-white text-blue-500 shadow-sm'
          : 'px-5 py-2 border-0 rounded-md font-medium cursor-pointer transition-all bg-transparent text-gray-500 hover:text-gray-700'}
      >
        Administradores
      </button>
    </div>

    <UsuarioList
      data={usuarios}
      userType={currentTipo}
      onEdit={openEditModal}
      onDelete={openDeleteDialog}
      onPasswordChange={openPasswordModal}
      onToggleActive={handleToggleActive}
      onPermissions={openPermissionsModal}
    />
  </div>

  {#if showPermissionsModal && permissionsUser}
    <PermissionsModal
      isOpen={showPermissionsModal}
      onClose={closePermissionsModal}
      usuario={permissionsUser}
    />
  {/if}

  <UsuarioForm
    isOpen={showModal}
    {editingUsuario}
    userType={currentTipo}
    bind:formData={currentFormData}
    {carreras}
    {isLoading}
    onClose={closeModal}
    onSubmit={handleSubmit}
  />

  <UsuarioDeleteConfirm
    isOpen={showDeleteDialog}
    userType={currentTipo}
    {isLoading}
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
  />

  <PasswordChangeModal
    isOpen={showPasswordModal}
    bind:passwordFormData
    {isLoading}
    onClose={closePasswordModal}
    onSubmit={handlePasswordChange}
  />
</AdminLayout>
