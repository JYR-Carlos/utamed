# Reporte de Auditoría: Página de Inscripciones a Cursos (`/admin/inscripciones_cursos`)

## 1. Identificación y Alcance de la Página
- **Rutas URL en Navegador**:
  - Nómina principal: `/admin/inscripciones_cursos`
  - Formulario individual de inscripción: `/admin/inscripciones_cursos/create`
  - Formulario de edición de estado: `/admin/inscripciones_cursos/{id}/edit`
- **Archivos Svelte**:
  - Principal: [`resources/js/pages/admin/InscripcionesCursos.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/InscripcionesCursos.svelte)
  - Crear: [`resources/js/pages/admin/CreateInscripcionCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/CreateInscripcionCurso.svelte)
  - Editar: [`resources/js/pages/admin/EditInscripcionCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/EditInscripcionCurso.svelte)
- **Componentes y Modales**:
  - Modal de inscripción masiva (`BulkInscriptionModal.svelte`)
  - Selector de cambio de estado rápido en fila de tabla
- **Servicios de API Frontend**: Endpoints en `InscripcionCursoController` (incluyendo AJAX y exportación CSV).

---

## 2. Endpoints Invocados desde la Página de Inscripciones

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/inscripciones_cursos` | [`InscripcionCursoController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L54) | Inertia (`admin/InscripcionesCursos`) | Carga de nómina de inscritos con filtros de estado y curso. |
| `GET` | `/admin/inscripciones_cursos/create` | [`InscripcionCursoController@create`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L99) | Inertia (`admin/CreateInscripcionCurso`) | Formulario para seleccionar curso y alumno. |
| `POST` | `/admin/inscripciones_cursos` | [`InscripcionCursoController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L147) | Redirect (`admin.inscripciones_cursos.index`) | Registra inscripción individual de alumno. |
| `GET` | `/admin/inscripciones_cursos/{id}/edit` | [`InscripcionCursoController@edit`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L191) | Inertia (`admin/EditInscripcionCurso`) | Formulario para editar estado o número de intento. |
| `PUT` | `/admin/inscripciones_cursos/{idCurso},{idEstudiante}` | [`InscripcionCursoController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L210) | Redirect (`admin.inscripciones_cursos.index`) | Actualiza datos de la inscripción en BD. |
| `DELETE` | `/admin/inscripciones_cursos/{idCurso},{idEstudiante}` | [`InscripcionCursoController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L234) | Redirect (`admin.inscripciones_cursos.index`) | Elimina la inscripción de un estudiante. |
| `PATCH` | `/admin/inscripciones_cursos/{id}/estado` | [`InscripcionCursoController@updateEstado`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L257) | JSON | Cambio directo de estado (INSCRITO, APROBADO, etc.) desde la tabla. |
| `POST` | `/admin/inscripciones_cursos/bulk` | [`InscripcionCursoController@storeBulk`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L278) | JSON | Inscripción por lote de múltiples estudiantes. |
| `GET` | `/admin/inscripciones_cursos/ajax/disponibles` | [`InscripcionCursoController@getEstudiantesDisponibles`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L356) | JSON | Carga estudiantes no inscritos para autocompletado. |
| `GET` | `/admin/inscripciones_cursos/ajax/by-curso` | [`InscripcionCursoController@getByCurso`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L376) | JSON | Obtiene lista de inscritos por curso en llamadas asíncronas. |
| `GET` | `/admin/inscripciones_cursos/export/csv` | [`InscripcionCursoController@exportCsv`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L398) | Stream (CSV) | Descarga CSV sanitizado contra fórmulas maliciosas. |
| `POST` | `/admin/cursos/{curso}/inscripcion-automatica` | [`InscripcionCursoController@inscripcionAutomatica`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/InscripcionCursoController.php#L476) | Redirect / JSON | Ejecuta sincronización masiva desde servicio Intranet externo. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/inscripciones_cursos` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L170) | `$this->authorize('viewAny', InscripcionCurso::class)`. |
| `GET /admin/inscripciones_cursos/create` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@create`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L167) | `$this->authorize('create', InscripcionCurso::class)`. |
| `POST /admin/inscripciones_cursos` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@create`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L167) | `$this->authorize('create', InscripcionCurso::class)`. |
| `GET /admin/inscripciones_cursos/{id}/edit` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@update`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L168) | `$this->authorize('update', $inscripcion)`. |
| `PUT /admin/inscripciones_cursos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@update`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L168) | `$this->authorize('update', $inscripcion)`. |
| `DELETE /admin/inscripciones_cursos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@delete`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L169) | `$this->authorize('delete', $inscripcion)`. |
| `PATCH /admin/inscripciones_cursos/{id}/estado` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@update`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L168) | `$this->authorize('update', $inscripcion)`. |
| `POST /admin/inscripciones_cursos/bulk` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@create`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L167) | `$this->authorize('create', InscripcionCurso::class)`. |
| `GET /admin/inscripciones_cursos/ajax/disponibles` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE (REMEDIADO)** | [`INSCRIPCIONES_CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L170) | `abort_unless($request->user()->can('viewAny', InscripcionCurso::class), 403)`. |
| `GET /admin/inscripciones_cursos/ajax/by-curso` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@viewAny`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE (REMEDIADO)** | [`INSCRIPCIONES_CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L170) | `abort_unless($request->user()->can('viewAny', InscripcionCurso::class), 403)`. |
| `GET /admin/inscripciones_cursos/export/csv` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`InscripcionCursoPolicy@export`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`INSCRIPCIONES_CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L170) | `$this->authorize('export', InscripcionCurso::class)`. |
| `POST /admin/cursos/{curso}/inscripcion-automatica` | `auth, verified, is_admin` | No (RBAC) | **SÍ (Granular sobre Curso)** | Sí (`InscripcionCursoPolicy@create`) | **Sí** (Middleware + Permiso Granular de Curso + Policy) | ✅ **CUMPLE (REMEDIADO)** | [`CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L39) | `abort_unless($request->user()->can(Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS, $curso) || $request->user()->can('create', ...), 403)`. |

---

## 4. Análisis Detallado del Backend y Remediaciones Aplicadas

### 4.1. Remediación en Endpoints AJAX (`getEstudiantesDisponibles` y `getByCurso`)
- **Implementación**:
  ```php
  $user = $request->user();
  abort_unless($user && $user->can('viewAny', InscripcionCurso::class), 403, 'No autorizado para consultar estudiantes disponibles.');
  ```
  - Se eliminó la exposición pública y la dependencia exclusiva en middleware perimetral.

### 4.2. Remediación en Sincronización Masiva (`inscripcionAutomatica`)
- **Implementación**:
  ```php
  $user = $request->user();
  abort_unless(
      $user && (
          $user->can(Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS, $curso)
          || $user->can('create', [InscripcionCurso::class, $curso])
          || $user->can('create', InscripcionCurso::class)
      ),
      403,
      'No autorizado para realizar sincronización e inscripción automática en este curso.'
  );
  ```
  - Vincula la operación con el permiso granular tipado en el enum [`Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L39), asegurando la verificación contextual sobre el `$curso`.

---

## 5. Auditoría de la Policy ([`InscripcionCursoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/InscripcionCursoPolicy.php))

- **Clase**: [`App\Policies\InscripcionCursoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/InscripcionCursoPolicy.php#L18) que extiende de [`BaseInscripcionCursoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseInscripcionCursoPolicy.php#L18).
- **Mecanismo de Evaluación**: Delega en [`PermissionValidator`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/PermissionValidator.php) con el recurso `inscripciones_cursos`.

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias**:
   - Middleware `is_admin` + Policy `InscripcionCursoPolicy` en los 12 endpoints.
2. **Remediaciones Aplicadas**:
   - Se aseguraron los 3 endpoints que carecían de verificación explícita.
3. **Brechas de Seguridad**:
   - **0 (Todas remediadas)**.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR (REMEDIADO)**.
