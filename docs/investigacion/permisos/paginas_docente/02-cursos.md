# Reporte de Auditoría: Cursos y Detalle de Curso Docente

- **Rutas Auditadas**:
  - `GET /docente/cursos` (`docente.cursos.index`)
  - `GET /docente/cursos/{curso}` (`docente.cursos.show`)
  - `GET /docente/cursos/{curso}/docentes` (`docente.cursos.docentes`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/Cursos.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Cursos.svelte)
  - [`resources/js/pages/docente/CursoDetalle.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/CursoDetalle.svelte)
  - [`resources/js/pages/docente/DocentesCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DocentesCurso.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Docente/DocenteCursoController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php)
- **Policy Asociada**:
  - [`app/Policies/CursoPolicy.php`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Este módulo permite al docente explorar sus asignaturas asignadas, acceder al detalle del curso (perspectiva de titular y colegiado), revisar la nómina de estudiantes, consultar el estado de componentes y gestionar el equipo docente del curso.

```mermaid
flowchart TD
    A[Docente Autenticado] --> R1["GET /docente/cursos"]
    R1 --> CTRL1[DocenteCursoController@index]
    CTRL1 --> Q1[Filtro por id_docente_titular o docente_componente]
    CTRL1 --> V1[Render docente/Cursos]

    V1 -->|Click en Curso| R2["GET /docente/cursos/{curso}"]
    R2 --> POL1["CursoPolicy@viewPrograma($curso)"]
    POL1 -->|Es Titular o Colegiado| CTRL2[DocenteCursoController@show]
    POL1 -->|Sin Asignacion| DENY1[403 Forbidden + Log Seguridad]
    CTRL2 --> V2[Render docente/CursoDetalle]

    V2 -->|Gestionar Docentes| R3["GET /docente/cursos/{curso}/docentes"]
    R3 --> POL2["CursoPolicy@manageTeam($curso)"]
    POL2 -->|Solo Docente Titular| CTRL3[DocenteCursoController@docentes]
    POL2 -->|No es Titular| DENY2[403 Forbidden + Log IDOR]
    CTRL3 --> V3[Render docente/DocentesCurso]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

### 2.1. [`Cursos.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Cursos.svelte)
- **Props recibidas**:
  - `cursosSemestre1`: `Array<Curso>`
  - `cursosSemestre2`: `Array<Curso>`
  - `availableRoles`: `Array<Rol>` (ayudante, estudiante para modales de delegación)
  - `availablePermissions`: `Record<string, Array<Permiso>>`
- **Renderizado condicional**:
  - Cada tarjeta de curso refleja la bandera `es_titular_curso` para habilitar acciones administrativas exclusivas del titular.

### 2.2. [`CursoDetalle.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/CursoDetalle.svelte)
- **Props recibidas**:
  - `curso`: Datos completos, fechas, semestres, `es_titular_curso`, `userPermissions`.
  - `mis_componentes`: Componentes donde el docente tiene carga académica directa.
  - `mis_estudiantes`: Alumnos inscritos en sus componentes.
  - `todos_componentes`: Vista global (solo poblada para el titular).
  - `actividades`: Lista de evaluaciones asociadas a los componentes del curso.
- **Renderizado condicional**:
  - Pestañas y botones de administración de equipo (`DocentesCurso`), delegación de permisos y configuración global solo visibles si `curso.es_titular_curso === true`.

### 2.3. [`DocentesCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DocentesCurso.svelte)
- **Props recibidas**:
  - `curso`, `todos_componentes`, `colegiados`, `permisos_syllabus`.
- **Renderizado**: Matriz de titulares de componente y asignación de permisos sobre el programa.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/cursos` | `docente.cursos.index` | `['auth', 'verified', 'is_docente']` | [`DocenteCursoController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php#L44) |
| `GET` | `/docente/cursos/{curso}` | `docente.cursos.show` | `['auth', 'verified', 'is_docente']` | [`DocenteCursoController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php#L137) |
| `GET` | `/docente/cursos/{curso}/docentes` | `docente.cursos.docentes` | `['auth', 'verified', 'is_docente']` | [`DocenteCursoController@docentes`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php#L352) |

---

## 4. Fase 3 & 4: Controlador Backend, Policies y RelBAC

### 4.1. `index()`: Listado de Cursos
- **Aislamiento por identidad**:
  ```php
  $cursos = Curso::where(function ($q) use ($user) {
      $q->where('id_docente_titular', $user->docente->id_docente)
        ->orWhereHas('componentes.docenteComponentes', function ($dq) use ($user) {
            $dq->where('id_docente', $user->docente->id_docente);
        });
  })
  ```
- No es vulnerable a IDOR: solo recupera cursos vinculados directamente al perfil autenticado.

### 4.2. `show(Curso $curso)`: Detalle del Curso
- **Autorización por Policy**:
  - Invoca `$this->authorize('viewPrograma', $curso);`.
  - [`CursoPolicy@viewPrograma`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php#L103) verifica:
    1. Administradores: Acceso total.
    2. Docente Titular (`$curso->id_docente_titular === $user->docente->id_docente`): Acceso permitido.
    3. Docente Colegiado (en `componentes.docenteComponentes`): Acceso permitido.
    4. Otros: Denegación con 403 y log de auditoría en canal `seguridad` (`ACCESO_DENEGADO_VIEWPROGRAMA`).
- **Seguridad en lazy-loading de mensajes**:
  - Valida que los grupos del estudiante pertenezcan estrictamente al curso (`c.id_curso = $curso->id_curso`).

### 4.3. `docentes(Curso $curso)`: Gestión de Docentes
- **Autorización estricta por Policy**:
  - Invoca `$this->authorize('manageTeam', $curso);`.
  - [`CursoPolicy@manageTeam`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php#L42) verifica:
    - **Solo el Docente Titular actual** del curso puede acceder.
    - Si un docente fue removido de la titularidad e intenta ingresar por URL directa, se bloquea con 403 y se emite alerta de seguridad `ACCESO_DENEGADO_MANAGEAM_TEAM`.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)
  - [`Permissions::COMPONENTES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L74) (`'componentes:ver'`)
  - [`Permissions::COMPONENTES_DOCENTESCOLEGIADOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L87) (`'componentes/docentesColegiados:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro (Middleware) | Policy / RelBAC | Protección Anti-IDOR | Auditoría | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET /docente/cursos` | `is_docente` | Query Scoped al usuario | Total (automático) | - | ✅ **CUMPLE** |
| `GET /docente/cursos/{curso}` | `is_docente` | `CursoPolicy@viewPrograma` | Total (valida titularidad o componente) | Log `ACCESO_DENEGADO_VIEWPROGRAMA` | ✅ **CUMPLE** |
| `GET /docente/cursos/{curso}/docentes` | `is_docente` | `CursoPolicy@manageTeam` | Total (exclusivo para Docente Titular actual) | Log `ACCESO_DENEGADO_MANAGEAM_TEAM` | ✅ **CUMPLE** |

**Veredicto**: Módulo **100% SEGURO Y AUDITADO**. La segregación entre docente titular y colegiado está rígidamente implementada tanto a nivel de Policy como de controlador y UI.
