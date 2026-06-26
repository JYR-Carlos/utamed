<script lang="ts">
  /**
   * Componente lista de usuarios.
   *
   * Muestra tabla paginada de usuarios con opciones para editar, eliminar, cambiar contraseña, toggle activo.
   * Soporta múltiples tipos: estudiante, docente, administrador.
   */
  import DataTable from '@/components/custom/admin/DataTable.svelte';
  import type { UsuarioItem, PaginatedResponse } from '@/types/admin.types';

  type UserType = 'estudiante' | 'docente' | 'administrador';

  interface Props {
    data: PaginatedResponse<UsuarioItem>;
    userType: UserType;
    onEdit: (usuario: UsuarioItem) => void;
    onPasswordChange: (usuario: UsuarioItem) => void;
    onToggleActive: (usuario: UsuarioItem) => void;
    onPermissions: (usuario: UsuarioItem) => void;
  }

  let { data, userType, onEdit, onPasswordChange, onToggleActive, onPermissions }: Props =
    $props();

  const COLUMN_CONFIGS: Record<UserType, Array<{ key: string; label: string; class?: string }>> = {
    estudiante: [
      // { key: 'usuario.id_usuario', label: 'ID Usuario'},
      // { key: 'estudiante.id_estudiante', label: 'ID Estudiante' },
      { key: 'usuario.rut', label: 'RUT', class: 'whitespace-nowrap' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'estudiante.agno_ingreso', label: 'Año Ingreso' },
      { key: 'estudiante.carrera.nombre', label: 'Carrera' },
    ],
    docente: [
      // { key: 'usuario.id_usuario', label: 'ID Usuario'},
      // { key: 'docente.id_docente', label: 'ID Docente' },
      { key: 'usuario.rut', label: 'RUT', class: 'whitespace-nowrap' },
      { key: 'usuario.nombre1', label: 'Nombre' },
      { key: 'usuario.apellido1', label: 'Apellido' },
      { key: 'docente.grado', label: 'Grado' },
      { key: 'docente.cargo', label: 'Cargo' },
    ],
    administrador: [
      // { key: 'usuario.id_usuario', label: 'ID' },
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
  {onPasswordChange}
  {onToggleActive}
  onCustomAction={onPermissions}
  customActionLabel="Permisos"
/>
