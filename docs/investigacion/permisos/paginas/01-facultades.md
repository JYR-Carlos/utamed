# Reporte de Auditoría: Página de Facultades (`/admin/facultades`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/facultades`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Facultades.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Facultades.svelte#L1-L299)
- **Componentes Hijos y Modales**:
  - Listado con acordeón de departamentos: [`resources/js/modules/resources/facultad/components/facultadList.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/facultad/components/facultadList.svelte)
  - Modal Crear/Editar Facultad: [`resources/js/modules/resources/facultad/components/facultadForm.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/facultad/components/facultadForm.svelte)
  - Modal Crear Departamento: [`resources/js/modules/resources/facultad/components/departamentoModal.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/facultad/components/departamentoModal.svelte)
  - Diálogo de Confirmación de Eliminación: [`resources/js/modules/resources/facultad/components/facultadDeleteConfirm.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/facultad/components/facultadDeleteConfirm.svelte)
- **Servicio de API Frontend**: [`resources/js/modules/resources/facultad/services/facultadApi.ts`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/facultad/services/facultadApi.ts#L1-L73)
- **Props de Permisos Recibidas por la Vista**:
  - `canCreate: boolean` (Línea 51)
  - `canEdit: boolean` (Línea 52)
  - `canDelete: boolean` (Línea 53)
- **Protección en UI**:
  - El botón superior *"Nueva Facultad"* está condicionado a `{#if canCreate}`.
  - Los botones de *"Editar"* y *"Eliminar"* en la tabla están condicionados a `{#if canEdit}` y `{#if canDelete}`.

---

## 2. Endpoints Invocados desde la Página de Facultades

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/facultades` | [`FacultadController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L34) | Inertia (`admin/Facultades`) | Carga de tabla de facultades con departamentos paginada y búsqueda. |
| `POST` | `/admin/facultades` | [`FacultadController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L65) | Redirect (`admin.facultades.index`) | Envío de formulario para crear una nueva facultad. |
| `GET` | `/admin/facultades/{facultad}` | [`FacultadController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L86) | JSON | Carga de datos de una facultad para previsualización o edición. |
| `PUT` | `/admin/facultades/{facultad}` | [`FacultadController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L100) | Redirect (`admin.facultades.index`) | Envío de formulario para actualizar datos de la facultad. |
| `DELETE` | `/admin/facultades/{facultad}` | [`FacultadController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L121) | Redirect (`admin.facultades.index`) | Eliminación de facultad (rechazada si tiene departamentos activos). |
| `POST` | `/admin/departamentos` | [`DepartamentoController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L86) | Redirect (`admin.departamentos.index`) | Creación rápida de departamento anidado desde el modal de la página. |
| `DELETE` | `/admin/departamentos/{id}` | [`DepartamentoController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L135) | Redirect (`admin.departamentos.index`) | Eliminación rápida de departamento desde el acordeón de la facultad. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/facultades` | `auth, verified, is_admin` | No (Policy) | Vía Policy | Sí (`FacultadPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`FACULTADES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L148) | `$user->can('viewAny', Facultad::class)` delega a `PermissionValidator`. |
| `POST /admin/facultades` | `auth, verified, is_admin` | No (Policy) | Vía Policy | Sí (`FacultadPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`FACULTADES_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L145) | `$user->can('create', Facultad::class)`. |
| `GET /admin/facultades/{id}` | `auth, verified, is_admin` | No (Policy) | Vía Policy | Sí (`FacultadPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`FACULTADES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L148) | `$user->can('view', $facultad)` evalúa sobre la instancia. |
| `PUT /admin/facultades/{id}` | `auth, verified, is_admin` | No (Policy) | Vía Policy | Sí (`FacultadPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`FACULTADES_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L146) | `$user->can('update', $facultad)` evalúa sobre la instancia. |
| `DELETE /admin/facultades/{id}` | `auth, verified, is_admin` | No (Policy) | Vía Policy | Sí (`FacultadPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`FACULTADES_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L147) | `$user->can('delete', $facultad)` evalúa sobre la instancia. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`FacultadController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L34-L60)
- **Autorización Backend**: `$this->authorize('viewAny', Facultad::class);`
- **Recomendación según nuevo Estándar**:
  ```php
  abort_unless($request->user()->can('viewAny', Facultad::class), 403, 'No autorizado para ver facultades.');
  ```
- **Cálculo de Props de Permisos enviadas a Inertia (Líneas 56-58)**:
  ```php
  'canCreate'  => $user->can('create', Facultad::class),
  'canEdit'    => $user->can('update', new Facultad()),
  'canDelete'  => $user->can('delete', new Facultad()),
  ```
  - *Observación de Seguridad*: Se usa `new Facultad()` como modelo ficticio para calcular `canEdit` y `canDelete` generales. Al ser las facultades recursos institucionales raíz y su policy evaluar rol administrativo global, esta comprobación es consistente.

### 4.2. [`FacultadController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L65-L81)
- **Autorización Backend**: `$this->authorize('create', Facultad::class);`
- **Validación de Inputs**: Línea 70: `$request->validate(['nombre' => 'required|string|max:255'])`
- **Servicio Asociado**: Delega a [`FacultadService::create`](file:///c:/Users/dyri0n/Code/utamed/app/Services/FacultadService.php) que además autogenera el contexto institucional RBAC (`id_contexto`).

### 4.3. [`FacultadController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L100-L116)
- **Autorización Backend**: `$this->authorize('update', $facultad);`
- **Validación de Inputs**: Línea 105: `$request->validate(['nombre' => 'required|string|max:255'])`
- **Servicio Asociado**: [`FacultadService::update`](file:///c:/Users/dyri0n/Code/utamed/app/Services/FacultadService.php).

### 4.4. [`FacultadController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/FacultadController.php#L121-L134)
- **Autorización Backend**: `$this->authorize('delete', $facultad);`
- **Regla de Integridad Referencial**: `FacultadService::delete` rechaza la eliminación si la facultad cuenta con departamentos asociados (activos o soft-deleted).

---

## 5. Auditoría de la Policy ([`FacultadPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/FacultadPolicy.php))

- **Clase**: [`App\Policies\FacultadPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/FacultadPolicy.php#L19) que extiende de [`BaseFacultadPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseFacultadPolicy.php#L18).
- **Mecanismo de Evaluación**:
  - `FacultadPolicy` implementa un **Patrón Override 3**:
    ```php
    private function isAdmin(Usuario $user): bool {
        return $user->hasRole('Administrador') || $user->hasRole('Admin') || ...;
    }
    ```
  - *Diagnóstico*: Valida el rol `Administrador` directamente, ya que las facultades son administradas exclusivamente por administradores globales.
  - *Bypass SuperAdmin*: Garantizado por el trait [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20) mediante el método `before(Usuario $user, string $ability)`.

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Redundancia estándar (Defensa en Profundidad): El grupo de rutas exige `is_admin` y `FacultadPolicy` revalida `isAdmin($user)`. No existen guards manuales redundantes o desalineados en el cuerpo de los métodos del controlador.
2. **Desviaciones Menores**:
   - El controlador utiliza `$this->authorize(...)` en lugar de la llamada explícita y unificada con `$request->user()->can(...)` y `abort_unless(...)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. Los 5 endpoints principales y los 2 auxiliares de departamentos anidados están completamente protegidos por middleware y policy.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
