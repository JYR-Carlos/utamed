# Reporte de Auditoría: Página de Carreras (`/admin/carreras`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/carreras`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Carreras.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Carreras.svelte#L1-L326)
- **Componentes Hijos y Modales**:
  - Listado con toolbar y filtros: [`resources/js/modules/resources/carrera/components/carreraList.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/carrera/components/carreraList.svelte)
  - Modal Crear/Editar Carrera (con selects en cascada Facultad $\rightarrow$ Departamento): [`resources/js/modules/resources/carrera/components/carreraForm.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/carrera/components/carreraForm.svelte)
  - Diálogo de Confirmación de Discontinuación: [`resources/js/modules/resources/carrera/components/carreraDiscontinueConfirm.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/carrera/components/carreraDiscontinueConfirm.svelte)
- **Servicio de API Frontend**: [`resources/js/modules/resources/carrera/services/carreraApi.ts`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/carrera/services/carreraApi.ts)
- **Props de Permisos Recibidas por la Vista**:
  - La vista no recibe props booleanas de permisos explícitas (`canCreate`, `canEdit`, `canDelete`), asumiendo rol administrativo implícito vía middleware `is_admin`.

---

## 2. Endpoints Invocados desde la Página de Carreras

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/carreras` | [`CarreraController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L39) | Inertia (`admin/Carreras`) | Carga del listado paginado de carreras con filtros de facultad, departamento y estado. |
| `GET` | `/admin/departamentos/{departamento}/carreras` | [`CarreraController@byDepartamento`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L105) | JSON | Obtiene carreras de un departamento para selects en cascada. |
| `POST` | `/admin/carreras` | [`CarreraController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L118) | Redirect (`admin.carreras.index`) | Registra una nueva carrera académica y autogenera su contexto RBAC institucional. |
| `GET` | `/admin/carreras/{carrera}` | [`CarreraController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L145) | JSON | Obtiene el detalle de la carrera con sus planes y jerarquía organizacional. |
| `PUT` | `/admin/carreras/{carrera}` | [`CarreraController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L164) | Redirect (`admin.carreras.index`) | Actualiza atributos mutables de una carrera (bloqueando cambios en `id_departamento`). |
| `DELETE` | `/admin/carreras/{carrera}` | [`CarreraController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L185) | Redirect (`admin.carreras.index`) | Discontinúa (SoftDelete) la carrera académica preservando su historial académico. |
| `GET` | `/admin/facultades/{facultad}/departamentos` | [`DepartamentoController@byFacultad`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/DepartamentoController.php#L73) | JSON | Carga departamentos dependientes al cambiar la facultad en el modal de formulario. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/carreras` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CarreraPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`CARRERAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L134) | `$this->authorize('viewAny', Carrera::class)` $\rightarrow$ `$user->can('viewAny', Carrera::class)`. |
| `GET /admin/departamentos/{id}/carreras` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CarreraPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`CARRERAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L134) | `$this->authorize('viewAny', Carrera::class)`. |
| `POST /admin/carreras` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CarreraPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`CARRERAS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L131) | `$this->authorize('create', Carrera::class)`. |
| `GET /admin/carreras/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CarreraPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`CARRERAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L134) | `$this->authorize('view', $carrera)` evalúa sobre la instancia. |
| `PUT /admin/carreras/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CarreraPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`CARRERAS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L132) | `$this->authorize('update', $carrera)` evalúa sobre la instancia. |
| `DELETE /admin/carreras/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CarreraPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`CARRERAS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L133) | `$this->authorize('delete', $carrera)` evalúa sobre la instancia. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`CarreraController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L39-L100)
- **Autorización Backend**: Línea 41: `$this->authorize('viewAny', Carrera::class);`
- **Recomendación según nuevo Estándar**:
  ```php
  abort_unless($request->user()->can('viewAny', Carrera::class), 403, 'No autorizado para ver carreras.');
  ```
- **Integridad y Scoping de Datos**:
  - Excluye soft-deletes por defecto (`$status === 'all' ? Carrera::withTrashed() : Carrera::query()`).
  - Realiza un conteo contextual de Jefes de Carrera activos asignados vía RBAC (`withCount(['jefesDeCarreraActivos as has_director'])`).

### 4.2. [`CarreraController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L118-L140)
- **Autorización Backend**: Línea 120: `$this->authorize('create', Carrera::class);`
- **Validación de Inputs**: Valida campos obligatorios y la existencia de foreign keys (`id_departamento` y `id_facultad`).
- **Servicio Asociado**: Delega en [`CarreraService::create`](file:///c:/Users/dyri0n/Code/utamed/app/Services/CarreraService.php) que genera la carrera y registra su contexto institucional en el árbol RBAC.

### 4.3. [`CarreraController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L164-L179)
- **Autorización Backend**: Línea 166: `$this->authorize('update', $carrera);`
- **Protección de Inmutabilidad Contextual**:
  - Bloquea intencionalmente la modificación de `id_departamento` e `id_facultad` para evitar romper la jerarquía del contexto RBAC y las constraints de unicidad institucionales.

### 4.4. [`CarreraController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CarreraController.php#L185-L197)
- **Autorización Backend**: Línea 187: `$this->authorize('delete', $carrera);`
- **Mecanismo**: Aplica SoftDelete preservando el historial académico y las notas de los alumnos.

---

## 5. Auditoría de la Policy ([`CarreraPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CarreraPolicy.php))

- **Clase**: [`App\Policies\CarreraPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CarreraPolicy.php#L18) que extiende de [`BaseCarreraPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseCarreraPolicy.php#L18).
- **Mecanismo de Evaluación**:
  - Delega directamente a [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el recurso `carreras`:
    - `viewAny` $\rightarrow$ `carreras:ver` ([`CARRERAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L134))
    - `view` $\rightarrow$ `carreras:ver` sobre `$contextId`
    - `create` $\rightarrow$ `carreras:crear` ([`CARRERAS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L131))
    - `update` $\rightarrow$ `carreras:editar` ([`CARRERAS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L132))
    - `delete` $\rightarrow$ `carreras:eliminar` ([`CARRERAS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L133))
- **Bypass SuperAdmin**: Garantizado vía `before()` en [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20).

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware perimetral `is_admin` + Policy RelBAC `CarreraPolicy`.
2. **Desviaciones Menores**:
   - El controlador utiliza llamadas legacy `$this->authorize(...)` en lugar del estándar unificado `abort_unless($request->user()->can(...), 403)`.
   - No se envían props booleanas de permisos a la vista (`canCreate`, etc.), aunque al ser una página protegida integralmente por `is_admin`, no genera riesgo de elevación de privilegios en el backend.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. Todos los endpoints están blindados por middleware y policy.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
