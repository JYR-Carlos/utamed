<script lang="ts">
  /**
   * usuarioList — Tabla paginada de usuarios con acciones de editar,
   * eliminar, cambiar contraseña, activar/desactivar y permisos.
   *
   * Presentacional: delega toda acción en el padre. Las columnas cambian
   * según el tipo de usuario (COLUMN_CONFIGS); las claves con puntos
   * ('usuario.rut') las resuelve DataTable sobre el objeto anidado.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { UsuarioItem, PaginatedResponse } from '@/types/admin.types';

  type UserType = 'estudiante' | 'docente' | 'administrador';

  interface Props {
    data: PaginatedResponse<UsuarioItem>;
    /** Define qué columnas se muestran. */
    userType: UserType;
    onEdit: (usuario: UsuarioItem) => void;
    onDelete?: (usuario: UsuarioItem) => void;
    onPasswordChange: (usuario: UsuarioItem) => void;
    onToggleActive: (usuario: UsuarioItem) => void;
    /** Abre el modal de permisos (acción custom del DataTable). */
    onPermissions: (usuario: UsuarioItem) => void;
  }

  let { data, userType, onEdit, onDelete, onPasswordChange, onToggleActive, onPermissions }: Props =
    $props();

  /** Columnas por tipo de usuario. */
  const COLUMN_CONFIGS: Record<UserType, Array<{ key: string; label: string; class?: string }>> = {
    estudiante: [
      { key: 'usuario.rut', label: 'RUT', class: 'whitespace-nowrap' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'estudiante.agno_ingreso', label: 'Año Ingreso' },
      { key: 'estudiante.carrera.nombre', label: 'Carrera' },
    ],
    docente: [
      { key: 'usuario.rut', label: 'RUT', class: 'whitespace-nowrap' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'docente.grado', label: 'Grado' },
      { key: 'docente.cargo', label: 'Cargo' },
    ],
    administrador: [
      { key: 'usuario.rut', label: 'RUT', class: 'whitespace-nowrap' },
      { key: 'usuario.username', label: 'Usuario' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'usuario.email', label: 'Email' },
    ],
  };

  const columns = $derived(COLUMN_CONFIGS[userType]);
</script>

<DataTable
  {data}
  {columns}
  {onEdit}
  {onDelete}
  {onPasswordChange}
  {onToggleActive}
  onCustomAction={onPermissions}
  customActionLabel="Permisos"
/>
