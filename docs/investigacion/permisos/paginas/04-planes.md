# Reporte de Auditoría: Página de Planes de Estudio (`/admin/planes`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/planes`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Planes.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Planes.svelte#L1-L287)
- **Componentes Hijos y Modales**:
  - Listado de planes con cálculo de créditos SCT: `PlanList.svelte`
  - Modal Crear/Editar Plan (Carrera, Año, Versión): `PlanForm.svelte`
  - Diálogo de Confirmación de Eliminación: `PlanDeleteConfirm.svelte`
  - Panel Slide-Over de Previsualización de Malla: `MallaSlideOver.svelte`
- **Servicios Frontend**: [`resources/js/modules/resources/plan`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/plan) (`createPlan`, `updatePlan`, `deletePlan`, `fetchMalla`).
- **Props de Permisos Recibidas por la Vista**:
  - La vista no recibe flags booleanas individuales, asumiendo control de acceso perimetral administrativo y delegando la autorización estricta en cada mutación HTTP.

---

## 2. Endpoints Invocados desde la Página de Planes

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/planes` | [`PlanController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L31) | Inertia (`admin/Planes`) | Carga de tabla de planes con suma de créditos SCT y filtros por carrera. |
| `GET` | `/admin/carreras/{carrera}/planes` | [`PlanController@byCarrera`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L70) | JSON | Obtiene versiones de planes para una carrera seleccionada. |
| `POST` | `/admin/planes` | [`PlanController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L85) | Redirect (`admin.planes.index`) | Registra un nuevo plan curricular para una carrera. |
| `GET` | `/admin/planes/{plan}` | [`PlanController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L112) | JSON | Obtiene el detalle de un plan con sus asignaciones de asignaturas. |
| `PUT` | `/admin/planes/{plan}` | [`PlanController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L124) | Redirect (`admin.planes.index`) | Actualiza año y versión de un plan. |
| `DELETE` | `/admin/planes/{plan}` | [`PlanController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L142) | Redirect (`admin.planes.index`) | Elimina un plan curricular. |
| `GET` | `/admin/planes/{plan}/asignaturas/json` | [`AsignacionPlanController@mallaJson`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/AsignacionPlanController.php#L50) | JSON | Carga diferida de la estructura de la malla para el slide-over "Ver Malla". |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/planes` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`PlanPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`PLANES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L127) | `$this->authorize('viewAny', Plan::class)` $\rightarrow$ `$user->can('viewAny', Plan::class)`. |
| `GET /admin/carreras/{id}/planes` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`PlanPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`PLANES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L127) | `$this->authorize('viewAny', Plan::class)`. |
| `POST /admin/planes` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`PlanPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`PLANES_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L124) | `$this->authorize('create', Plan::class)`. |
| `GET /admin/planes/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`PlanPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`PLANES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L127) | `$this->authorize('view', $plan)` evalúa sobre la instancia. |
| `PUT /admin/planes/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`PlanPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`PLANES_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L125) | `$this->authorize('update', $plan)` evalúa sobre la instancia. |
| `DELETE /admin/planes/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`PlanPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`PLANES_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L126) | `$this->authorize('delete', $plan)` evalúa sobre la instancia. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`PlanController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L31-L65)
- **Autorización Backend**: Línea 33: `$this->authorize('viewAny', Plan::class);`
- **Recomendación según nuevo Estándar**:
  ```php
  abort_unless($request->user()->can('viewAny', Plan::class), 403, 'No autorizado para ver planes.');
  ```
- **Lógica de Dominio**: Agrega la sumatoria de créditos SCT calculados a partir de las asignaturas asignadas (`withSum('asignaturas as creditos_sct_totales', 'creditos_sct')`).

### 4.2. [`PlanController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L85-L107)
- **Autorización Backend**: Línea 87: `$this->authorize('create', Plan::class);`
- **Validación de Inputs**: Valida `id_carrera`, `agno_plan` (rango 1900-2100) y `version_plan` (entero $\ge 1$).

### 4.3. [`PlanController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L124-L137)
- **Autorización Backend**: Línea 126: `$this->authorize('update', $plan);`
- **Validación de Inputs**: Valida correspondencia con la carrera y rangos temporales.

### 4.4. [`PlanController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/PlanController.php#L142-L160)
- **Autorización Backend**: Línea 144: `$this->authorize('delete', $plan);`
- **Trazabilidad**: Registra logs informativos y de error ante fallos de integridad referencial.

---

## 5. Auditoría de la Policy ([`PlanPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/PlanPolicy.php))

- **Clase**: [`App\Policies\PlanPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/PlanPolicy.php#L18) que extiende de [`BasePlanPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BasePlanPolicy.php#L18).
- **Mecanismo de Evaluación**:
  - Delega directamente a [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el recurso `planes`:
    - `viewAny` $\rightarrow$ `planes:ver` ([`PLANES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L127))
    - `view` $\rightarrow$ `planes:ver` sobre `$contextId`
    - `create` $\rightarrow$ `planes:crear` ([`PLANES_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L124))
    - `update` $\rightarrow$ `planes:editar` ([`PLANES_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L125))
    - `delete` $\rightarrow$ `planes:eliminar` ([`PLANES_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L126))
- **Bypass SuperAdmin**: Garantizado vía `before()` en [`HasBasePolicyMethods`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/Traits/HasBasePolicyMethods.php#L20).

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware perimetral `is_admin` + Policy RelBAC `PlanPolicy`.
2. **Desviaciones Menores**:
   - Uso de `$this->authorize(...)` en lugar de la invocación estandarizada `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. Endpoints principales y de cascada debidamente securizados.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
