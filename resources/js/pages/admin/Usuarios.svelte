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
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '@/layouts/AdminLayout.svelte';
  import PageHeader from '@/components/admin/PageHeader.svelte';
  import ConfirmationModal from '@/components/admin/ConfirmationModal.svelte';
  import PermissionsModal from '@/components/custom/admin/permissions-modal/PermissionsModal.svelte';
  import {
    UsuarioList,
    UsuarioForm,
    UsuarioImport,
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
  import {
    UserType
  } from '@/types/usuarios/tipos'
  import { untrack } from 'svelte';
  import type { BreadcrumbItem } from '@/types';



  type UserType = (typeof UserType)[keyof typeof UserType];
  type UserFormData = EstudianteFormData | DocenteFormData | AdministradorFormData;

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usuarios', href: '/admin/usuarios' },
  ];

  const USER_TYPE_LABELS: Record<UserType, string> = {
    [UserType.STUDENT]: 'Estudiante',
    [UserType.TEACHER]: 'Docente',
    [UserType.ADMIN]: 'Admin',
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
  interface ColumnaImportacion {
    campo: string;
    etiqueta: string;
    obligatorio: boolean;
    ejemplo: string;
  }

  interface Props {
    /** Usuarios paginados según tipo seleccionado */
    usuarios: PaginatedResponse<UsuarioItem>;
    /** Tipo de usuario a mostrar: estudiante, docente o administrador */
    tipo: 'estudiante' | 'docente' | 'administrador';
    /** Carreras disponibles (para asignar a estudiantes) */
    carreras: Carrera[];
    /** Formato del archivo de importación, declarado por el servidor. */
    columnasImportacion?: ColumnaImportacion[];
  }

  let { usuarios, tipo, carreras, columnasImportacion = [] }: Props = $props();

  let showModal = $state(false);
  let showImportModal = $state(false);
  let showDeleteDialog = $state(false);
  let showToggleDialog = $state(false);
  let showPasswordModal = $state(false);
  let showPermissionsModal = $state(false);
  let isLoading = $state(false);
  let fileToImport =$state<File | null>(null)
  let editingUsuario = $state<UsuarioItem | null>(null);
  let deletingUsuario = $state<UsuarioItem | null>(null);
  let togglingUsuario = $state<UsuarioItem | null>(null);
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
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  // efect de autocompletado de rut
  let searchTimeout: ReturnType<typeof setTimeout>;
  let deshabilitarCampos = $state(false);

  $effect(() => {
    // Al leer currentFormData.rut aquí, el $effect solo se re-ejecutará cuando el RUT cambie.
    const rutActual = currentFormData.rut;

    // Solo buscamos si NO estamos editando, el modal está abierto y el RUT tiene un largo razonable
    if (!editingUsuario && showModal && rutActual && rutActual.length >= 8) {
      clearTimeout(searchTimeout);
      
      // Debounce de 500ms para esperar a que el usuario termine de escribir
      searchTimeout = setTimeout(async () => {
        try {
          // Asegúrate de que esta URL coincida con tu ruta en web.php o api.php
          const response = await fetch(`/admin/usuarios/buscar-por-rut?rut=${rutActual}`);
          
          if (response.ok) {
            const usuarioDB = await response.json();
            
            if (usuarioDB) {
              // Usamos untrack para actualizar el estado sin re-disparar efectos accidentalmente
              deshabilitarCampos = true
              untrack(() => {
                currentFormData.nombre1 = usuarioDB.nombre1 || currentFormData.nombre1;
                currentFormData.nombre2 = usuarioDB.nombre2 || currentFormData.nombre2;
                currentFormData.apellido1 = usuarioDB.apellido1 || currentFormData.apellido1;
                currentFormData.apellido2 = usuarioDB.apellido2 || currentFormData.apellido2;
                currentFormData.email = usuarioDB.email || currentFormData.email;
                currentFormData.username = usuarioDB.username || currentFormData.username;
              });
            }
          }
        } catch (error) {
          console.error("Error al autocompletar usuario por RUT:", error);
        }
      }, 500); 
    }
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

  function openImportModal() {
    showImportModal = true;
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

  function handleAgregarSubmit() {
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
    deshabilitarCampos = false;
  }

  function handleImportarSubmit() {
    // El botón ya está deshabilitado hasta que la revisión da el archivo por
    // bueno; esto sólo cubre el caso de que llegue aquí sin archivo.
    if (!fileToImport || isLoading) return;

    isLoading = true;

    router.post(
      '/admin/usuarios/importar',
      {
        file: fileToImport,
        tipo: currentTipo,
      },
      {
        forceFormData: true,
        onSuccess: () => {
          showImportModal = false;
          isLoading = false;
          fileToImport = null;
        },
        onError: (errors: Record<string, string>) => {
          handleError('importar usuarios', errors);
          isLoading = false;
        },
      },
    );
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
    passwordFormData = { current_password: '', password: '', password_confirmation: '' };
    showPasswordModal = true;
  }

  function closePasswordModal() {
    showPasswordModal = false;
    changingPasswordUsuario = null;
    passwordFormData = { current_password: '', password: '', password_confirmation: '' };
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

  /**
   * Desactivar deja a la persona fuera del sistema, así que pasa por
   * confirmación igual que eliminar. Reactivar no destruye nada y se
   * aplica directamente.
   */
  function handleToggleActive(usuario: UsuarioItem) {
    if (usuario.usuario.esta_activo) {
      togglingUsuario = usuario;
      showToggleDialog = true;
      return;
    }
    toggleActive(usuario.usuario.id_usuario);
  }

  function closeToggleDialog() {
    showToggleDialog = false;
    togglingUsuario = null;
  }

  function confirmToggleActive() {
    if (!togglingUsuario) return;
    toggleActive(togglingUsuario.usuario.id_usuario);
    closeToggleDialog();
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

<AdminLayout {breadcrumbs}>
  <div>
    <PageHeader title="Usuarios" subtitle="Gestión de estudiantes, docentes y administradores">
      {#snippet secondaryActions()}
        <!-- Importar es secundaria y va etiquetada: el círculo verde con
             flecha de descarga sugería exportar, justo lo contrario. -->
        <button onclick={openImportModal} class="btn btn-neutral">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3m0 0L7.5 7.5M12 3l4.5 4.5M3.75 16.5v2.25A2.25 2.25 0 0 0 6 21h12a2.25 2.25 0 0 0 2.25-2.25V16.5" />
          </svg>
          Importar desde archivo
        </button>
      {/snippet}
      {#snippet primaryAction()}
        <button onclick={openCreateModal} class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Nuevo {USER_TYPE_LABELS[currentTipo].toLowerCase()}
        </button>
      {/snippet}
    </PageHeader>

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
    {deshabilitarCampos}
    onClose={closeModal}
    onSubmit={handleAgregarSubmit}
  />

  <UsuarioImport
    isOpen={showImportModal}
    userType={currentTipo}
    columnas={columnasImportacion}
    isLoading={isLoading}
    bind:file={fileToImport}
    onClose={() => {
      showImportModal = false;
      fileToImport = null;
    }}
    onSubmit={handleImportarSubmit}
  />


  <UsuarioDeleteConfirm
    isOpen={showDeleteDialog}
    userType={currentTipo}
    usuario={deletingUsuario}
    {isLoading}
    onConfirm={handleDelete}
    onCancel={closeDeleteDialog}
  />

  <ConfirmationModal
    isOpen={showToggleDialog}
    tone="warning"
    title="Desactivar cuenta"
    recordName={togglingUsuario
      ? `${togglingUsuario.usuario.nombre1 ?? ''} ${togglingUsuario.usuario.apellido1 ?? ''}`.trim()
      : null}
    recordMeta={[togglingUsuario?.usuario.rut ?? '', togglingUsuario?.usuario.username ?? '']}
    message="La persona deja de poder iniciar sesión de inmediato y sus sesiones abiertas se cierran. Sus datos e historial se conservan y puedes volver a activarla cuando quieras."
    confirmLabel="Desactivar cuenta"
    onConfirm={confirmToggleActive}
    onCancel={closeToggleDialog}
  />

  <PasswordChangeModal
    isOpen={showPasswordModal}
    bind:passwordFormData
    {isLoading}
    onClose={closePasswordModal}
    onSubmit={handlePasswordChange}
  />
</AdminLayout>
