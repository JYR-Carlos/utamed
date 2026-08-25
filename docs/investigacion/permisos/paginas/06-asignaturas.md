# Reporte de Auditoría: Página de Asignaturas (`/admin/asignaturas`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/asignaturas`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Asignaturas.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Asignaturas.svelte#L1-L104)
- **Componentes Hijos y Modales**:
  - Catálogo de asignaturas con conteo de planes asociados: `AsignaturaList.svelte`
  - Modal Crear/Editar Asignatura (Código, Nombre, Créditos SCT, Horas pedagógicas): `AsignaturaForm.svelte`
  - Diálogo de Confirmación de Eliminación: `AsignaturaDeleteConfirm.svelte`
- **Servicios Frontend**: [`resources/js/modules/resources/asignatura`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/asignatura) (`createAsignatura`, `updateAsignatura`, `deleteAsignatura`).
- **Props de Permisos Recibidas por la Vista**:
  - Vista compartida entre Administradores y Jefes de Carrera (`routePrefix = '/admin' | '/docente/jefe-carrera'`). Sin flags booleanas en el componente padre; la autorización es validada en el controlador.

---

## 2. Endpoints Invocados desde la Página de Asignaturas

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/asignaturas` | [`AsignaturaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L34) | Inertia (`admin/Asignaturas`) | Carga de catálogo paginado de asignaturas activas y más recientes por código. |
| `POST` | `/admin/asignaturas` | [`AsignaturaController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L73) | Redirect (`admin.asignaturas.index`) | Registra una nueva asignatura en el catálogo global. |
| `GET` | `/admin/asignaturas/{asignatura}` | [`AsignaturaController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L103) | JSON | Consulta detalle de asignatura y sus vinculaciones a planes y carreras. |
| `PUT` | `/admin/asignaturas/{asignatura}` | [`AsignaturaController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L131) | Redirect (`admin.asignaturas.index`) | Actualiza mediante versionado inmutable (SoftDelete de anterior + creación de nueva fila). |
| `DELETE` | `/admin/asignaturas/{asignatura}` | [`AsignaturaController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L176) | Redirect (`admin.asignaturas.index`) | Elimina (SoftDelete) del catálogo si no está asignada a planes activos. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/asignaturas` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignaturaPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`ASIGNATURAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L155) | `$this->authorize('viewAny', Asignatura::class)` $\rightarrow$ `$user->can('viewAny', Asignatura::class)`. |
| `POST /admin/asignaturas` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignaturaPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`ASIGNATURAS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L152) | `$this->authorize('create', Asignatura::class)`. |
| `GET /admin/asignaturas/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignaturaPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`ASIGNATURAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L155) | `$this->authorize('view', $asignatura)` evalúa sobre la instancia. |
| `PUT /admin/asignaturas/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignaturaPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`ASIGNATURAS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L153) | `$this->authorize('update', $asignatura)` evalúa sobre la instancia. |
| `DELETE /admin/asignaturas/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignaturaPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`ASIGNATURAS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L154) | `$this->authorize('delete', $asignatura)` evalúa sobre la instancia. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`AsignaturaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L34-L68)
- **Autorización Backend**: Línea 36: `$this->authorize('viewAny', Asignatura::class);`
- **Recomendación según nuevo Estándar**:
  ```php
  abort_unless($request->user()->can('viewAny', Asignatura::class), 403, 'No autorizado para ver asignaturas.');
  ```
- **Integridad y Scoping**:
  - Filtra solo asignaturas activas (`active()`).
  - Utiliza `DISTINCT ON (cod_asignatura)` para entregar únicamente la última versión válida de cada código de ramo.

### 4.2. [`AsignaturaController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L73-L96)
- **Autorización Backend**: Línea 75: `$this->authorize('create', Asignatura::class);`
- **Validación de Inputs**: Valida unicidad global del código de asignatura y tipos numéricos positivos para créditos y horas.

### 4.3. [`AsignaturaController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L131-L166)
- **Autorización Backend**: Línea 133: `$this->authorize('update', $asignatura);`
- **Patrón de Inmutabilidad y Trazabilidad**:
  - En lugar de sobrescribir el registro, ejecuta un soft-delete de la versión anterior y crea una nueva fila. Garantiza que las actas y notas históricas sigan apuntando exactamente a la versión de la asignatura con la que cursaron los estudiantes en períodos pasados.

### 4.4. [`AsignaturaController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignaturaController.php#L176-L194)
- **Autorización Backend**: Línea 178: `$this->authorize('delete', $asignatura);`
- **Integridad Referencial**: Rechaza la eliminación si la asignatura se encuentra actualmente vinculada a algún plan curricular (`$asignatura->asignacionPlanes()->count() > 0`).

---

## 5. Auditoría de la Policy ([`AsignaturaPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/AsignaturaPolicy.php))

- **Clase**: [`App\Policies\AsignaturaPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/AsignaturaPolicy.php#L18) que extiende de [`BaseAsignaturaPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseAsignaturaPolicy.php#L18).
- **Mecanismo de Evaluación**:
  - Delega directamente a [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el recurso `asignaturas`:
    - `viewAny` $\rightarrow$ `asignaturas:ver` ([`ASIGNATURAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L155))
    - `view` $\rightarrow$ `asignaturas:ver` sobre `$contextId`
    - `create` $\rightarrow$ `asignaturas:crear` ([`ASIGNATURAS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L152))
    - `update` $\rightarrow$ `asignaturas:editar` ([`ASIGNATURAS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L153))
    - `delete` $\rightarrow$ `asignaturas:eliminar` ([`ASIGNATURAS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L154))
- **Bypass SuperAdmin**: Garantizado vía `before()` en [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20).

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware perimetral `is_admin` + Policy RelBAC `AsignaturaPolicy`.
2. **Desviaciones Menores**:
   - Uso de llamadas legacy `$this->authorize(...)` en lugar de la sintaxis unificada `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. El módulo cuenta con versionado inmutable y control estricto de acceso.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
