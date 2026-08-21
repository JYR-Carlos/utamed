# Reporte de Auditoría: Página de Detalle de Malla Curricular (`/admin/planes/{plan}/asignaturas`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/planes/{plan}/asignaturas`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/DetalleMalla.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/DetalleMalla.svelte#L1-L213)
- **Componentes Hijos y Modales**:
  - Matriz visual de la malla organizada por año y semestre: `MallaGrid.svelte`
  - Catálogo lateral de asignaturas disponibles: `AsignaturasCatalogo.svelte`
  - Modal Editar Asignación (Año, Semestre planificado, Tipo de ramo): `EditAsignacionModal.svelte`
  - Diálogo de Confirmación de Desvinculación de Asignatura: `AsignacionDeleteConfirm.svelte`
- **Servicio de API Frontend**: [`resources/js/modules/resources/detalle-malla/services/mallaApi.ts`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/detalle-malla/services/mallaApi.ts)
- **Props de Permisos Recibidas por la Vista**:
  - La vista es compartida entre Administradores y Jefes de Carrera vía `routePrefix` (`/admin` vs `/docente/jefe-carrera`). La autorización se verifica estrictamente en backend.

---

## 2. Endpoints Invocados desde la Página de Detalle Malla

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/planes/{plan}/asignaturas` | [`AsignacionPlanController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L65) | Inertia (`admin/DetalleMalla`) | Carga de matriz curricular de asignaturas agrupadas por período y catálogo activo. |
| `POST` | `/admin/planes/{plan}/asignaturas` | [`AsignacionPlanController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L84) | Redirect back | Asigna una asignatura del catálogo al plan curricular. |
| `PUT` | `/admin/planes/{plan}/asignaturas/{asignatura}` | [`AsignacionPlanController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L154) | Redirect back | Modifica año/semestre planificado o tipo de ramo en la malla. |
| `DELETE` | `/admin/planes/{plan}/asignaturas/{asignatura}` | [`AsignacionPlanController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L196) | Redirect back | Desvincula una asignatura de la malla del plan. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/planes/{plan}/asignaturas` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignacionPlanPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`ASIGNACIONES_PLAN_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L120) | `$this->authorize('viewAny', AsignacionPlan::class)` $\rightarrow$ `$user->can('viewAny', AsignacionPlan::class)`. |
| `POST /admin/planes/{plan}/asignaturas` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignacionPlanPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`ASIGNACIONES_PLAN_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L117) | `$this->authorize('create', AsignacionPlan::class)`. |
| `PUT /admin/planes/{plan}/asignaturas/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignacionPlanPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`ASIGNACIONES_PLAN_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L118) | `$this->authorize('update', $asignacion)` evalúa sobre la instancia. |
| `DELETE /admin/planes/{plan}/asignaturas/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`AsignacionPlanPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`ASIGNACIONES_PLAN_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L119) | `$this->authorize('delete', $asignacion)` evalúa sobre la instancia. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`AsignacionPlanController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L65-L77)
- **Autorización Backend**: Línea 67: `$this->authorize('viewAny', AsignacionPlan::class);`
- **Recomendación según nuevo Estándar**:
  ```php
  abort_unless($request->user()->can('viewAny', AsignacionPlan::class), 403, 'No autorizado para ver asignaciones de plan.');
  ```
- **Scoping y Filtrado de Datos**: Agrupa asignaciones en formato clave `"año-semestre"` y suministra exclusivamente asignaturas activas (`Asignatura::active()`).

### 4.2. [`AsignacionPlanController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L84-L149)
- **Autorización Backend**: Línea 86: `$this->authorize('create', AsignacionPlan::class);`
- **Validación de Integridad y Versión**:
  - Valida mediante closure que la asignatura pertenezca al catálogo activo y no a una versión obsoleta/soft-deleted.
  - Verifica que no exista una asignación previa para evitar duplicados en el mismo plan.

### 4.3. [`AsignacionPlanController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L154-L191)
- **Autorización Backend**: Línea 178: `$this->authorize('update', $asignacion);`
- **Protección IDOR y Contexto**: Localiza la asignación explícitamente cruzando `id_plan` y `id_asignatura` mediante `where('id_plan', $plan->id_plan)->where('id_asignatura', $asignatura->id_asignatura)->firstOrFail()` antes de autorizar.

### 4.4. [`AsignacionPlanController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L196-L207)
- **Autorización Backend**: Línea 202: `$this->authorize('delete', $asignacion);`
- **Protección IDOR**: Localiza el registro exacto validando pertenencia al plan antes de proceder a la eliminación.

---

## 5. Auditoría de la Policy ([`AsignacionPlanPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/AsignacionPlanPolicy.php))

- **Clase**: [`App\Policies\AsignacionPlanPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/AsignacionPlanPolicy.php#L18) que extiende de [`BaseAsignacionPlanPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseAsignacionPlanPolicy.php#L18).
- **Mecanismo de Evaluación**:
  - Delega directamente a [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el recurso `asignaciones_plan`:
    - `viewAny` $\rightarrow$ `asignaciones_plan:ver` ([`ASIGNACIONES_PLAN_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L120))
    - `view` $\rightarrow$ `asignaciones_plan:ver` sobre `$contextId`
    - `create` $\rightarrow$ `asignaciones_plan:crear` ([`ASIGNACIONES_PLAN_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L117))
    - `update` $\rightarrow$ `asignaciones_plan:editar` ([`ASIGNACIONES_PLAN_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L118))
    - `delete` $\rightarrow$ `asignaciones_plan:eliminar` ([`ASIGNACIONES_PLAN_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L119))
- **Bypass SuperAdmin**: Garantizado vía `before()` en [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20).

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware perimetral `is_admin` + Policy RelBAC `AsignacionPlanPolicy`.
2. **Desviaciones Menores**:
   - Uso de llamadas legacy `$this->authorize(...)` en lugar de la invocación recomendada `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. El enlace bidireccional entre `Plan` y `Asignatura` cuenta con validaciones anti-IDOR en base de datos y autorización por policy en cada operación.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
