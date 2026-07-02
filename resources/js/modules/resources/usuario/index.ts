/**
 * Módulo Usuario — barrel principal.
 *
 * CRUD de usuarios del admin con tres perfiles (estudiante, docente,
 * administrador), importación masiva desde Excel/CSV, cambio de contraseña
 * y activación/desactivación.
 */
export { UsuarioList, UsuarioForm, UsuarioDeleteConfirm, UsuarioImport, PasswordChangeModal } from './components';
export * from './services/usuarioApi';
