# Reporte de Auditoría: Wizard de Asignación de Roles y Permisos (`/admin/assignment/*`)

## 1. Identificación y Alcance de la Página / Módulo
- **Ruta URL en Navegador**: Invocado desde modales contextuales y wizards de asignación en `/admin/usuarios` y administración RBAC.
- **Controlador Principal**: [`AssignmentWizardController`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php)
- **Servicios Asociados**:
  - `RoleAssignmentBuilder` / `PermissionAssignmentBuilder`
  - `GlobalContextService`
  - `PermissionContextConstraints` (validador de restricciones de nivel de contexto por permiso)
- **Props de Permisos Recibidas por la Vista**:
  - Catálogo de tipos de contexto (`global`, `facultad`, `departamento`, `carrera`, `curso`, `actividad`), roles permitidos y permisos asignables.

---

## 2. Endpoints Invocados desde el Wizard de Asignación

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/assignment/context-types` | [`AssignmentWizardController@getContextTypes`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php#L55) | JSON | Lista categorías de contexto para segmentar la asignación. |
| `GET` | `/admin/assignment/context-objects` | [`AssignmentWizardController@getContextObjects`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php#L128) | JSON | Lista entidades (cursos, facultades, etc.) para vincular el rol. |
| `POST` | `/admin/assignment/assign-role` | [`AssignmentWizardController@assignRole`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php#L201) | JSON | Asigna un rol a un usuario en un contexto específico (URA). |
| `POST` | `/admin/assignment/assign-permission` | [`AssignmentWizardController@assignPermission`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php#L324) | JSON | Otorga o deniega permiso especial directo a un usuario (UPE). |
| `GET` | `/admin/assignment/contexts-for-role` | [`AssignmentWizardController@getContextsForRole`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php#L484) | JSON | Filtra contextos compatibles según el tipo de rol seleccionado. |
| `GET` | `/admin/assignment/contexts-for-permission` | [`AssignmentWizardController@getContextsForPermission`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AssignmentWizardController.php#L535) | JSON | Filtra contextos compatibles según restricciones del permiso. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/assignment/context-types` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioRolAsignacionPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('viewAny', UsuarioRolAsignacion::class)`. |
| `GET /admin/assignment/context-objects` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioRolAsignacionPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('viewAny', UsuarioRolAsignacion::class)`. |
| `POST /admin/assignment/assign-role` | `auth, verified, is_admin` | Sí (Guard Anti-Autoasignación) | Vía Policy | Sí (`UsuarioRolAsignacionPolicy@create`) | **Sí** (Policy `create` + Guard `assertNoEsAutoAsignacion`) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('create', UsuarioRolAsignacion::class)` + guard anti-escalada. |
| `POST /admin/assignment/assign-permission` | `auth, verified, is_admin` | Sí (Guard Anti-Autoasignación) | Vía Policy | Sí (`UsuarioPermisoEspecialPolicy@create`) | **Sí** (Policy `create` + Guard `assertNoEsAutoAsignacion` + Constraints) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('create', UsuarioPermisoEspecial::class)` + validación contextual. |
| `GET /admin/assignment/contexts-for-role` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioRolAsignacionPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('viewAny', UsuarioRolAsignacion::class)`. |
| `GET /admin/assignment/contexts-for-permission` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`UsuarioPermisoEspecialPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`ROLES_PERMISOS_ASIGNAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('viewAny', UsuarioPermisoEspecial::class)`. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. Guard Anti-Autoasignación y Escalada de Privilegios
- **Líneas 45-53**:
  ```php
  private function assertNoEsAutoAsignacion(Usuario $objetivo): void {
      $actor = Auth::user();
      if ($actor && (int) $actor->id_usuario === (int) $objetivo->id_usuario) {
          abort(422, 'No puedes asignarte roles ni permisos a ti mismo.');
      }
  }
  ```
  - Bloquea cualquier vector de ataque donde un administrador con acceso al wizard intente elevar sus propios privilegios asignándose roles superiores o permisos globales no autorizados.

### 4.2. Validación de Restricciones Contextuales (`PermissionContextConstraints`)
- Verifica que el tipo de permiso asignado sea contextualmente válido (p. ej. un permiso a nivel de `Curso` no puede ser asignado en un contexto de tipo `Facultad` si la regla de negocio lo restringe).

---

## 5. Auditoría de las Policies

- **`UsuarioRolAsignacionPolicy`** y **`UsuarioPermisoEspecialPolicy`**:
  - Ambas extienden de las bases generadas delegando en [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con bypass de `SuperAdmin` en `before()`.

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware `is_admin` + Policy de asignación + Guard `assertNoEsAutoAsignacion`. Es una **Defensa en Profundidad** crítica para el núcleo de seguridad RBAC.
2. **Desviaciones Menores**:
   - Uso de llamadas legacy `$this->authorize(...)` en lugar de la invocación estándar `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. El wizard implementa validaciones estrictas en todas las operaciones de asignación.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
