# Reporte de Auditoría: Módulo Syllabus y Programas de Asignatura (`/admin/programas/*`)

## 1. Identificación y Alcance de la Página / Módulo
- **Rutas URL en Navegador**:
  - Listado de versiones de programa por curso: `/admin/cursos/{curso}/programas`
  - Editor visual interactivo de 9 módulos: `/admin/programas/{programa}/edit`
  - Interfaz de revisión y aprobación: `/admin/programas/{programa}/review`
- **Archivos Svelte**:
  - `Programas_New.svelte`, `Programas.svelte`
  - `ProgramaEdit.svelte`
  - `Programas/ReviewPrograma.svelte`
  - `SyllabusModal.svelte`, `SyllabusTypeSelector.svelte`
- **Servicios de API Frontend**: Endpoints de `ProgramaController` y `ProgramaService`.
- **Props de Permisos Recibidas por la Vista**:
  - Permisos individuales por módulo (I a IX) y roles del usuario en el curso.

---

## 2. Endpoints Invocados desde el Módulo Syllabus

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/cursos/{curso}/programas` | [`ProgramaController@indexByCurso`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L41) | Inertia (`admin/Programas_New`) | Listado de versiones y estados de programas de un curso. |
| `GET` | `/admin/cursos/{curso}/programa/json` | [`ProgramaController@getJson`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L89) | JSON | Carga de datos JSONB del programa activo para modal rápido. |
| `POST` | `/admin/cursos/{curso}/programa/instanciar` | [`ProgramaController@instanciar`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L107) | JSON / Redirect | Genera borrador inicial de programa a partir del syllabus base. |
| `GET` | `/admin/programas/{programa}/edit` | [`ProgramaController@edit`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L162) | Inertia (`admin/ProgramaEdit`) | Editor interactivo de las 9 secciones del programa. |
| `PUT` | `/admin/programas/{programa}/draft` | [`ProgramaController@updateDraft`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L225) | JSON | Autoguardado de borrador JSONB. |
| `PUT` | `/admin/programas/{programa}/modulo/{modulo}` | [`ProgramaController@updateModulo`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L280) | JSON | Guardado y validación de una sección individual con permiso granular. |
| `POST` | `/admin/programas/{programa}/submit-review` | [`ProgramaController@submitReview`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L340) | JSON / Redirect | Envía el programa a revisión por Jefatura/Dirección. |
| `GET` | `/admin/programas/{programa}/review` | [`ProgramaController@review`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L390) | Inertia (`admin/Programas/ReviewPrograma`) | Interfaz de aprobación con vista comparativa. |
| `POST` | `/admin/programas/{programa}/approve` | [`ProgramaController@approve`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L440) | JSON / Redirect | Aprueba oficialmente el programa. |
| `POST` | `/admin/programas/{programa}/reject` | [`ProgramaController@reject`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L490) | JSON / Redirect | Rechaza el programa solicitando correcciones. |
| `POST` | `/admin/programas/{programa}/publish` | [`ProgramaController@publish`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php#L540) | JSON / Redirect | Publica el programa haciéndolo visible a los estudiantes. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/cursos/{curso}/programas` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@view`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('view', $curso)`. |
| `POST /admin/cursos/{curso}/programa/instanciar` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`ProgramaPolicy@create`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`PROGRAMAS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('create', [Programa::class, $curso])`. |
| `GET /admin/programas/{id}/edit` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`ProgramaPolicy@update`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`PROGRAMAS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('update', $programa)`. |
| `PUT /admin/programas/{id}/draft` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`ProgramaPolicy@update`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`PROGRAMAS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('update', $programa)`. |
| `PUT /admin/programas/{id}/modulo/{num}` | `auth, verified, is_admin` | No (RBAC) | **SÍ (Módulos 1 a 9)** | Sí (`ProgramaPolicy@update`) | **Sí** (Policy `update` + Permiso Granular de Módulo) | ✅ **CUMPLE** | [`Permissions::MODULO_1` al `MODULO_9`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$user->can($moduloPermission, $programa->curso)` valida individualmente la sección. |
| `POST /admin/programas/{id}/approve` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`ProgramaPolicy@approve`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`PROGRAMAS_APROBAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('approve', $programa)`. |
| `POST /admin/programas/{id}/publish` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`ProgramaPolicy@publish`) | **Sí** (Middleware + Policy) | ✅ **CUMPLE** | [`PROGRAMAS_PUBLICAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('publish', $programa)`. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. Autorización Granular de Secciones (`updateModulo`)
- **Implementación Ejemplar del Estándar**:
  ```php
  $moduloPermission = match((int)$modulo) {
      1 => Permissions::MODULO_1,
      2 => Permissions::MODULO_2,
      3 => Permissions::MODULO_3,
      4 => Permissions::MODULO_4,
      5 => Permissions::MODULO_5,
      6 => Permissions::MODULO_6,
      7 => Permissions::MODULO_7,
      8 => Permissions::MODULO_8,
      9 => Permissions::MODULO_9,
      default => null,
  };

  abort_unless($user->can($moduloPermission, $programa->curso), 403, "Sin permiso para editar la sección {$modulo}.");
  ```
  - Demuestra el poder de `$user->can($enum, $curso)` para resolver la jerarquía contextual institucional y otorgar acceso solo a docentes que tengan delegada la sección específica de la asignatura.

---

## 5. Auditoría de la Policy ([`ProgramaPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/ProgramaPolicy.php))

- **Clase**: [`App\Policies\ProgramaPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/ProgramaPolicy.php#L24).
- **Mecanismo de Evaluación**:
  - Incorpora lógica para validar tanto administradores, como docentes titulares del curso y directores de carrera ([`JefaturaCarreraResolver`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Authorization/JefaturaCarreraResolver.php)).
  - Métodos específicos de ciclo de vida: `view`, `create`, `update`, `approve`, `reject`, `publish`.

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware `is_admin` + Policy `ProgramaPolicy` + Permisos Granulares de Módulo (`Permissions::MODULO_*`).
2. **Desviaciones Menores**:
   - Llamadas `$this->authorize(...)` en operaciones de ciclo de vida general en lugar de la invocación unificada `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)**. El módulo de syllabus es uno de los componentes mejor estructurados y protegidos a nivel granular en UTAMED.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
