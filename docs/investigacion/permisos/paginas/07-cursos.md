# Reporte de Auditoría: Página de Cursos Ofertados (`/admin/cursos`)

## 1. Identificación y Alcance de la Página
- **Ruta URL en Navegador**: `/admin/cursos`
- **Archivo Principal Svelte**: [`resources/js/pages/admin/Cursos.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Cursos.svelte#L1-L893)
- **Componentes Hijos y Modales**:
  - Tabla de cursos ofertados con acordeón: `CursoListAdmin.svelte`
  - Modal Formulario Curso: `CursoForm.svelte`
  - Modal Wizard de Creación Paso a Paso: `CursoWizardModal.svelte`
  - Modal Gestión de Componentes (Cátedra / Laboratorio): `ComponenteForm.svelte`
  - Modal Gestión de Equipos de Curso (Roles y Permisos RBAC): `CourseTeamModal.svelte`
  - Modal Previsualización y Duplicación de Curso: `CursoCopyPreviewModal.svelte`
  - Modales de Programas / Syllabus: `SyllabusModal.svelte`, `SyllabusTypeSelector.svelte`
  - Diálogo de Confirmación de Eliminación: `CursoDeleteConfirm.svelte`
- **Servicios de API Frontend**: [`resources/js/modules/resources/curso/services/cursoApi.ts`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/curso/services/cursoApi.ts)
- **Props de Permisos Recibidas por la Vista**:
  - Recibe `availableRoles` y `availablePermissions` para alimentar el modal de administración de equipos de curso.

---

## 2. Endpoints Invocados desde la Página de Cursos

| Método HTTP | Endpoint (URI) | Controlador / Método | Tipo Retorno | Propósito en UI |
|---|---|---|---|---|
| `GET` | `/admin/cursos` | [`CursoController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L53) | Inertia (`admin/Cursos`) | Carga de cursos con componentes, docentes y estadísticas. |
| `POST` | `/admin/cursos` | [`CursoController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L94) | Redirect (`admin.cursos.index`) | Registra curso ofertado y componentes iniciales. |
| `GET` | `/admin/cursos/{curso}` | [`CursoController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L151) | JSON | Detalle de curso, componentes y equipo docente. |
| `PUT` | `/admin/cursos/{curso}` | [`CursoController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L194) | Redirect (`admin.cursos.index`) | Actualiza datos y profesor jefe del curso. |
| `DELETE` | `/admin/cursos/{curso}` | [`CursoController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L528) | Redirect (`admin.cursos.index`) | Elimina un curso ofertado. |
| `GET` | `/admin/planes/{plan}/asignaturas-disponibles` | [`CursoController@getAsignaturasByPlan`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L219) | JSON | Asignaturas disponibles para wizard de curso. |
| `GET` | `/admin/asignaturas/{asignatura}/cursos-anteriores` | [`CursoController@getCursosAnteriores`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L252) | JSON | Cursos pasados para clonación de estructura. |
| `GET` | `/admin/asignaturas/{asignatura}/docentes-sugeridos` | [`CursoController@getDocentesSugeridos`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L289) | JSON | Sugerencias de docentes históricos para la asignatura. |
| `GET` | `/admin/docentes` | [`CursoController@getDocentes`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L362) | JSON | Listado global de docentes activos. |
| `GET` | `/admin/cursos/proxima-letra` | [`CursoController@getProximaLetra`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L386) | JSON | Autocalcula la siguiente sección (A, B, C...). |
| `GET` | `/admin/cursos/{curso}/preview-copia` | [`CursoController@previewCopia`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L430) | JSON | Previsualiza estructura clonada antes de confirmar. |
| `POST` | `/admin/cursos/{curso}/copiar` | [`CursoController@copiar`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php#L502) | Redirect (`admin.cursos.index`) | Duplica estructura de curso y componentes. |
| `GET` | `/admin/cursos/{curso}/componentes` | [`ComponenteController@indexByCurso`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L62) | JSON | Componentes y ponderaciones del curso. |
| `POST` | `/admin/cursos/{curso}/componentes` | [`ComponenteController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L96) | JSON / Redirect | Agrega componente (Cátedra/Lab) al curso. |
| `PUT` | `/admin/cursos/{curso}/componentes/{componente}` | [`ComponenteController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L169) | JSON / Redirect | Modifica tipo o docente asignado al componente. |
| `DELETE` | `/admin/cursos/{curso}/componentes/{componente}` | [`ComponenteController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L230) | JSON / Redirect | Elimina componente del curso con validación anti-IDOR. |
| `POST` | `/admin/cursos/{curso}/componentes/{componente}/docentes` | [`ComponenteController@addDocente`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L253) | JSON | Vincula docente adicional al componente. |
| `DELETE` | `/admin/cursos/{curso}/componentes/{componente}/docentes/{id}` | [`ComponenteController@removeDocente`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L304) | JSON | Desvincula docente del componente. |
| `PUT` | `/admin/cursos/{curso}/componentes/{componente}/titular` | [`ComponenteController@setTitular`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L357) | JSON | Asigna titularidad a docente del componente. |
| `PUT` | `/admin/cursos/{curso}/componentes/{componente}/genera-acta` | [`ComponenteController@toggleGeneraActa`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L449) | JSON | Alterna flag de generación de acta final. |
| `GET` | `/admin/cursos/{curso}/team` | [`CourseTeamController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L57) | JSON | Nómina del equipo de curso (docentes/ayudantes). |
| `GET` | `/admin/cursos/{curso}/team/search-assistants` | [`CourseTeamController@searchAssistants`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L594) | JSON | Búsqueda de ayudantes disponibles. |
| `POST` | `/admin/cursos/{curso}/team` | [`CourseTeamController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L130) | Redirect back | Asigna nuevo miembro/rol al equipo del curso. |
| `DELETE` | `/admin/cursos/{curso}/team/{usuario}` | [`CourseTeamController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L218) | Redirect back | Revoca miembro del equipo del curso. |
| `GET` | `/admin/cursos/{curso}/team/{usuario}/permissions` | [`CourseTeamController@getMemberPermissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L259) | JSON | Permisos individuales y asignaciones RBAC en curso. |
| `POST` | `/admin/cursos/{curso}/team/{usuario}/sync-permissions` | [`CourseTeamController@syncMemberPermissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L423) | Redirect back | Sincroniza roles y permisos granulares en el curso. |

---

## 3. Matriz de Autorización y Seguridad

| Endpoint & Método | Middleware | Valida Rol | Valida Permiso Granular | Valida Policy | ¿Tiene Verificación Redundante? | ¿Sigue el Estándar? | Permiso Granular (Enum) | Cómo está asegurado con `$user->can(...)` |
|---|---|---|---|---|---|---|---|---|
| `GET /admin/cursos` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@viewAny`) | **Sí** (Middleware `is_admin` + Policy `viewAny`) | ✅ **CUMPLE** | [`CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L162) | `$this->authorize('viewAny', Curso::class)` $\rightarrow$ `$user->can('viewAny', Curso::class)`. |
| `POST /admin/cursos` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`CURSOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L159) | `$this->authorize('create', Curso::class)`. |
| `GET /admin/cursos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L162) | `$this->authorize('view', $curso)` evalúa sobre la instancia. |
| `PUT /admin/cursos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@update`) | **Sí** (Middleware `is_admin` + Policy `update`) | ✅ **CUMPLE** | [`CURSOS_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L160) | `$this->authorize('update', $curso)` evalúa sobre la instancia. |
| `DELETE /admin/cursos/{id}` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@delete`) | **Sí** (Middleware `is_admin` + Policy `delete`) | ✅ **CUMPLE** | [`CURSOS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L161) | `$this->authorize('delete', $curso)` evalúa sobre la instancia. |
| `GET /admin/cursos/{id}/preview-copia` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@view`) | **Sí** (Middleware `is_admin` + Policy `view`) | ✅ **CUMPLE** | [`CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L162) | `$this->authorize('view', $curso)`. |
| `POST /admin/cursos/{id}/copiar` | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`CursoPolicy@create`) | **Sí** (Middleware `is_admin` + Policy `create`) | ✅ **CUMPLE** | [`CURSOS_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L159) | `$this->authorize('create', Curso::class)`. |
| Endpoints de Componentes (7) | `auth, verified, is_admin` | No (RBAC) | Vía Policy | Sí (`ComponentePolicy`) | **Sí** (Middleware + Policy + Guard IDOR en controlador) | ✅ **CUMPLE** | [`COMPONENTES_*`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('update'/'delete', $componente)` + `assertComponenteDeCurso`. |
| Endpoints de CourseTeam (6) | `auth, verified, is_admin` | Sí (Docente Jefe) | Vía Policy | Sí (`CursoPolicy@manageTeam`) | **Sí** (Middleware `is_admin` + Policy `manageTeam` + Guard Docente) | ✅ **CUMPLE** | [`CURSOS_EQUIPO_GESTIONAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php) | `$this->authorize('manageTeam', $curso)` con auditoría en canal `seguridad`. |

---

## 4. Análisis Detallado del Backend y Controladores

### 4.1. [`CursoController`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php)
- **Autorización de Listado y Creación**: Todas las operaciones CRUD invocan `$this->authorize(...)` sobre `Curso::class` o `$curso`.
- **Servicios Auxiliares**: `CursoService::create` autogenera los componentes obligatorios y registra el contexto institucional del curso en el árbol RBAC.

### 4.2. [`ComponenteController`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php)
- **Defensa en Profundidad Anti-IDOR**:
  - En `update` y `destroy`, además de validar la Policy `$this->authorize('update/delete', $componente)`, ejecuta el guard defensivo:
    ```php
    private function assertComponenteDeCurso(Curso $curso, Componente $componente): void {
        if ($componente->id_curso !== $curso->id_curso) {
            abort(404, 'El componente no pertenece al curso especificado.');
        }
    }
    ```

### 4.3. [`CourseTeamController`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php)
- **Autorización de Equipo**: Invoca `$this->authorize('manageTeam', $curso)`.
- **Auditoría de Seguridad**: Registra intentos de acceso denegados de ex-profesores jefe en el canal `seguridad` para prevenir y detectar ataques de retención indebida de credenciales o IDOR.

---

## 5. Auditoría de las Policies ([`CursoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php) y [`ComponentePolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/ComponentePolicy.php))

- **`CursoPolicy`**:
  - Hereda de [`BaseCursoPolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseCursoPolicy.php) e incorpora el método `manageTeam(Usuario $user, Curso $curso)`.
  - Admins acceden siempre; docentes únicamente si son el profesor jefe activo del curso en cuestión.
- **`ComponentePolicy`**:
  - Delega en [`BaseComponentePolicy`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/Base/BaseComponentePolicy.php) resolviendo el contexto institucional a través del curso padre.

---

## 6. Hallazgos, Redundancias y Veredicto de la Página

1. **Redundancias Identificadas**:
   - Middleware `is_admin` + Policy `manageTeam` + Guard de verificación de rol docente en `CourseTeamController@store`.
   - Middleware `is_admin` + Policy `update/delete` + Guard manual `assertComponenteDeCurso` en `ComponenteController`. (Ambas son redundancias de **Defensa en Profundidad** saludables).
2. **Desviaciones Menores**:
   - Uso de llamadas legacy `$this->authorize(...)` en lugar del estándar recomendado `abort_unless($request->user()->can(...), 403)`.
3. **Brechas de Seguridad**:
   - **Ninguna (0)** en los 26 endpoints del ecosistema de cursos.
4. **Veredicto Global**: ✅ **SEGURO Y CUMPLE EL ESTÁNDAR**.
