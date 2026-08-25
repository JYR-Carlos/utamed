# Reporte de Auditoría: Página de Departamentos (`/admin/departamentos`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/departamentos`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Departamentos.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Departamentos.svelte#L1-L236)
- **Componentes Hijos y Modales**:
  - Listado con acordeón de carreras anidadas: [`resources/js/modules/resources/departamento/components/departamentoList.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/departamento/components/departamentoList.svelte)
  - Modal Crear/Editar Departamento: [`resources/js/modules/resources/departamento/components/departamentoForm.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/departamento/components/departamentoForm.svelte)
  - Diálogo de Confirmación de Eliminación: [`resources/js/modules/resources/departamento/components/departamentoDeleteConfirm.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/departamento/components/departamentoDeleteConfirm.svelte)
- **Servicio de API Frontend**: [`resources/js/modules/resources/departamento/services/departamentoApi.ts`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/departamento/services/departamentoApi.ts#L1-L50)
- **Props de Permisos Recibidas por la Vista**:
  - `canCreate: boolean`
  - `canEdit: boolean`
  - `canDelete: boolean`
- **Protección en UI**:
  - El botón superior *"Nuevo Departamento"* está condicionado a `{#if canCreate}`.
  - Los botones de *"Editar"* y *"Eliminar"* en la tabla están condicionados a `{#if canEdit}` y `{#if canDelete}`.

---

## 2. Endpoints Invocados desde la Página de Departamentos

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/departamentos` | [`DepartamentoController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L30) | Inertia (`admin/Departamentos`) | Carga de listado paginado de departamentos con facultad y conteo de carreras. |
| `POST` | `/admin/departamentos` | [`DepartamentoController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L86) | Redirect (`admin.departamentos.index`) | Envío de formulario para crear un nuevo departamento. |
| `GET` | `/admin/departamentos/{departamento}` | [`DepartamentoController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L107) | JSON | Consulta detalle de departamento y carreras asociadas. |
| `PUT` | `/admin/departamentos/{departamento}` | [`DepartamentoController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L118) | Redirect (`admin.departamentos.index`) | Envío de formulario para actualizar datos de un departamento. |
| `DELETE` | `/admin/departamentos/{departamento}` | [`DepartamentoController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L135) | Redirect (`admin.departamentos.index`) | Eliminación de departamento (rechazada si tiene carreras activas). |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/departamentos` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`DepartamentoPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`DEPARTAMENTOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L141) | `$user->can('viewAny', Departamento::class)`. |
| `POST /admin/departamentos` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`DepartamentoPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`DEPARTAMENTOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L138) | `$this->authorize('create', Departamento::class)`. |
| `GET /admin/departamentos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`DepartamentoPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`DEPARTAMENTOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L141) | `$this->authorize('view', $departamento)` evalúa sobre la instancia. |
| `PUT /admin/departamentos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`DepartamentoPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`DEPARTAMENTOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L139) | `$this->authorize('update', $departamento)` evalúa sobre la instancia. |
| `DELETE /admin/departamentos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`DepartamentoPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`DEPARTAMENTOS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L140) | `$this->authorize('delete', $departamento)` evalúa sobre la instancia. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`DepartamentoController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L30-L68)
- **Autorización Backend**: Línea 32: `$this->authorize('viewAny', Departamento::class);`
- **Cálculo de Props de Permisos enviadas a Inertia (REMEDIADO)**:
  ```php
  $user = Auth::user();

  'canCreate' => $user?->can('create', Departamento::class) ?? false,
  'canEdit'   => $user?->can('update', new Departamento()) ?? false,
  'canDelete' => $user?->can('delete', new Departamento()) ?? false,
  ```
  - *Remediación Aplicada*: Se corrigió la asignación errónea anterior que usaba `auth()->check()`, alineando las props visuales con las capacidades efectivas del usuario autenticado vía `$user->can(...)`.

### 4.2. [`DepartamentoController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L86-L102)
- **Autorización Backend**: Línea 88: `$this->authorize('create', Departamento::class);`
- **Validación de Inputs**: Valida nombre y existencia de la facultad padre.

### 4.3. [`DepartamentoController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L118-L130)
- **Autorización Backend**: Línea 120: `$this->authorize('update', $departamento);`

### 4.4. [`DepartamentoController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L135-L147)
- **Autorización Backend**: Línea 137: `$this->authorize('delete', $departamento);`
- **Integridad Referencial**: Rechaza la eliminación si el departamento tiene carreras asociadas.

---

## 5. Auditoría de la Policy ([`DepartamentoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/DepartamentoPolicy.php))

- **Clase**: [`App\Policies\DepartamentoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/DepartamentoPolicy.php#L18) que extiende de [`BaseDepartamentoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseDepartamentoPolicy.php#L18).
- **Mecanismo de Evaluación**: Delega directamente a [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el slug `departamentos:*`.
- **Bypass SuperAdmin**: Garantizado vía `before()` en [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20).

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias**:
   - Middleware perimetral `is_admin` + Policy RelBAC `DepartamentoPolicy`.
2. **Remediaciones Aplicadas**:
   - Se reemplazó el uso de `auth()->check()` en `index()` por `$user->can(...)` para `canCreate`, `canEdit` y `canDelete`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR (REMEDIADO)**.
