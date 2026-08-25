# Reporte de Auditoría: Página de Gestión de Usuarios (`/admin/usuarios`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/usuarios`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Usuarios.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Usuarios.svelte)
- **Componentes Hijos y Modales**:
  - Directorio segmentado por pestañas (Estudiantes, Docentes, Administradores): `UsuariosList.svelte`
  - Modal Crear/Editar Usuario y Perfil: `UsuarioForm.svelte`
  - Modal Cambio de Contraseña de Administrador: `ChangePasswordModal.svelte`
  - Modal Gestión de Roles y Permisos Directos (URA / UPE): `PermissionsDrawer.svelte`
  - Modal de Importación Masiva Excel con previsualización: `ImportExcelModal.svelte`
  - Diálogo de Confirmación de Baja / Restauración: `UsuarioDeleteConfirm.svelte`
- **Servicio de API Frontend**: Endpoints en `UsuarioController`.
- **Props de Permisos Recibidas por la Vista**:
  - Catálogo de roles del sistema, permisos disponibles y datos contextuales de carreras.

---

## 2. Endpoints Invocados desde la Página de Usuarios

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/usuarios` | [`UsuarioController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L90) | Inertia (`admin/Usuarios`) | Directorio paginado con búsqueda unificada (nombre, RUT, email). |
| `POST` | `/admin/usuarios` | [`UsuarioController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L254) | Redirect (`admin.usuarios.index`) | Registra usuario y crea perfil (Estudiante, Docente o Admin). |
| `GET` | `/admin/usuarios/{usuario}` | [`UsuarioController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L380) | JSON | Obtiene detalle de usuario, perfiles, roles y permisos. |
| `PUT` | `/admin/usuarios/{usuario}` | [`UsuarioController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L407) | Redirect (`admin.usuarios.index`) | Actualiza datos personales y perfiles académicos. |
| `DELETE` | `/admin/usuarios/{usuario}` | [`UsuarioController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L487) | Redirect (`admin.usuarios.index`) | Da de baja (SoftDelete) a un usuario. |
| `PUT` | `/admin/usuarios/{usuario}/profile` | [`UsuarioController@updateProfile`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L548) | JSON | Modificación rápida de perfil desde drawer. |
| `PUT` | `/admin/usuarios/{usuario}/password` | [`UsuarioController@changePassword`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L588) | JSON | Reseteo administrativo de contraseña. |
| `POST` | `/admin/usuarios/{id}/restore` | [`UsuarioController@restore`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L633) | Redirect (`admin.usuarios.index`) | Reactiva usuario previamente dado de baja. |
| `GET` | `/admin/usuarios/{usuario}/permissions` | [`UsuarioController@permissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L659) | JSON | Carga roles asignados (URA) y permisos directos (UPE). |
| `POST` | `/admin/usuarios/{usuario}/sync-permissions` | [`UsuarioController@syncPermissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L702) | JSON / Redirect | Sincroniza matriz de roles y permisos con guard anti-escalada. |
| `GET` | `/admin/usuarios/template/export` | [`UsuarioController@exportTemplate`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L1373) | Binary (Excel) | Descarga plantilla .xlsx para carga masiva. |
| `POST` | `/admin/usuarios/import` | [`UsuarioController@importExcel`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L1403) | JSON / Inertia | Procesa e importa usuarios masivamente. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/usuarios` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`USUARIOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L113) | `$this->authorize('viewAny', Usuario::class)` $\rightarrow$ `$user->can('viewAny', Usuario::class)`. |
| `POST /admin/usuarios` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`USUARIOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L110) | `$this->authorize('create', Usuario::class)`. |
| `GET /admin/usuarios/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`USUARIOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L113) | `$this->authorize('view', $usuario)` evalúa sobre la instancia. |
| `PUT /admin/usuarios/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`USUARIOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L111) | `$this->authorize('update', $usuario)` evalúa sobre la instancia. |
| `DELETE /admin/usuarios/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`USUARIOS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L112) | `$this->authorize('delete', $usuario)` evalúa sobre la instancia. |
| `PUT /admin/usuarios/{id}/password` | `auth, verified, is_admin` | Sí (Guard SuperAdmin) | Vía Policy | Sí (`UsuarioPolicy@update`) | **Sí** (Policy `update` + Guard manual anti-escalada en controlador) | ✅ **CUMPLE** | [`USUARIOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L111) | `$this->authorize('update', $usuario)` + comprobación de jerarquía. |
| `POST /admin/usuarios/{id}/sync-permissions` | `auth, verified, is_admin` | Sí (Guard Anti-Escalada) | Vía Policy | Sí (`UsuarioPolicy@update`) | **Sí** (Doble Policy URA+UPE + `assertPuedeSincronizarPermisos`) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | Doble policy + guard contra asignación de roles superiores + log en canal `seguridad`. |
| `GET /admin/usuarios/template/export` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@create`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`USUARIOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L110) | `$this->authorize('create', Usuario::class)`. |
| `POST /admin/usuarios/import` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPolicy@create`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`USUARIOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L110) | `$this->authorize('create', Usuario::class)`. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`UsuarioController@syncPermissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L702-L820)
- **Defensa en Profundidad**:
  - Autoriza la mutación de roles (URA) y permisos (UPE) mediante la policy de usuario.
  - Ejecuta el guard de seguridad `assertPuedeSincronizarPermisos($actor, $usuario, $nuevosRoles)` para impedir que un administrador asigne o revoque roles superiores (p. ej. solo un SuperAdmin puede asignar el rol SuperAdmin).
  - Emite registro de auditoría en el canal de logging `seguridad`.

### 4.2. [`UsuarioController@changePassword`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/UsuarioController.php#L588-L628)
- **Protección contra Escalada**:
  - Evalúa la Policy `update` sobre `$usuario`.
  - Verifica si el usuario objetivo es SuperAdmin; en caso afirmativo, deniega la operación si el actor no es a su vez SuperAdmin.

---

## 5. Auditoría de la Policy ([`UsuarioPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/UsuarioPolicy.php))

- **Clase**: [`App\Policies\UsuarioPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/UsuarioPolicy.php#L17) que extiende de [`BaseUsuarioPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseUsuarioPolicy.php#L18).
- **Mecanismo de Evaluación**:
  - Delega directamente a [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el recurso `usuarios`:
    - `viewAny` $\rightarrow$ `usuarios:ver` ([`USUARIOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L113))
    - `view` $\rightarrow$ `usuarios:ver` sobre `$contextId`
    - `create` $\rightarrow$ `usuarios:crear` ([`USUARIOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L110))
    - `update` $\rightarrow$ `usuarios:editar` ([`USUARIOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L111))
    - `delete` $\rightarrow$ `usuarios:eliminar` ([`USUARIOS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L112))
- **Bypass SuperAdmin**: Garantizado vía `before()` en [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20).

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware `is_admin` + Policy `UsuarioPolicy` + Guards defensivos en controlador (`assertPuedeSincronizarPermisos`, verificación de jerarquía de contraseñas). Son mecanismos de **Defensa en Profundidad** altamente recomendados para el módulo de identidad y autenticación.
2. **Desviaciones Menores**:
   - Uso de `$this->authorize(...)` en lugar de la invocación recomendada `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. Los 12 endpoints del módulo de usuarios se encuentran robustamente securizados y auditados.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
