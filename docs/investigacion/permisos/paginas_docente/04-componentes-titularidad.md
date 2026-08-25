# Reporte de Auditoría: Componentes, Titularidad y Permisos de Componente

- **Rutas Auditadas**:
  - `GET /docente/cursos/{curso}/componentes` (`docente.cursos.componentes.index`)
  - `PUT /docente/cursos/{curso}/componentes/{componente}/titular` (`docente.cursos.componentes.titular.docente`)
  - `GET /docente/cursos/{curso}/componentes/{componente}/permisos` (`docente.cursos.componentes.permisos.index`)
  - `POST /docente/cursos/{curso}/componentes/{componente}/permisos` (`docente.cursos.componentes.permisos.sync`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/DocentesCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DocentesCurso.svelte)
  - [`resources/js/pages/docente/components/ColegiadoPermisosModal.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/ColegiadoPermisosModal.svelte)
- **Controladores Backend**:
  - [`app/Http/Controllers/Admin/ComponenteController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php)
  - [`app/Http/Controllers/Docente/CursoPermisosController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php)
- **Policies / Guards**:
  - [`app/Policies/CursoPolicy.php`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php)
  - `authorizeEsTitularComponente` en [`CursoPermisosController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php#L208)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Este submódulo permite al **Docente Titular del Curso** designar a los titulares de cada componente (Cátedra, Laboratorio, Taller) y al **Docente Titular de cada Componente Colegiado** delegar permisos de evaluación y registro de asistencia a los demás docentes asignados al componente.

```mermaid
flowchart TD
    A[Docente Titular Curso] -->|PUT /titular| C1[ComponenteController@setTitularByDt]
    C1 --> V1{Es DT del Curso y Componente es del Curso?}
    V1 -->|No| ERR1[403 Forbidden]
    V1 -->|Si| S1[Actualiza Titularidad + Sincroniza Roles Contextuales]

    B[Docente Titular Componente] -->|POST /permisos| C2[CursoPermisosController@componenteSync]
    C2 --> V2{Es DT del Componente?}
    V2 -->|No| ERR2[403 Solo titular de componente]
    V2 -->|Si| S2[Sincroniza UPE en contexto componente]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Componentes**:
  - [`ColegiadoPermisosModal.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/ColegiadoPermisosModal.svelte): Renderiza matriz de switches por docente colegiado (`actividades:evaluar`, `componentes/asistencia:registrar`, etc.).
  - Selectores de Titular en [`DocentesCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DocentesCurso.svelte): Habilitados únicamente si el usuario autenticado es el titular general del curso.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/cursos/{curso}/componentes` | `docente.cursos.componentes.index` | `['auth', 'verified', 'is_docente']` | [`AdminSeccionController@indexByCurso`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php) |
| `PUT` | `/docente/cursos/{curso}/componentes/{componente}/titular` | `docente.cursos.componentes.titular.docente` | `['auth', 'verified', 'is_docente']` | [`ComponenteController@setTitularByDt`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ComponenteController.php#L425) |
| `GET` | `/docente/cursos/{curso}/componentes/{componente}/permisos` | `docente.cursos.componentes.permisos.index` | `['auth', 'verified', 'is_docente']` | [`CursoPermisosController@componenteIndex`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php#L145) |
| `POST` | `/docente/cursos/{curso}/componentes/{componente}/permisos` | `docente.cursos.componentes.permisos.sync` | `['auth', 'verified', 'is_docente']` | [`CursoPermisosController@componenteSync`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php#L181) |

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### 4.1. `setTitularByDt`: Blindaje Anti-IDOR y Titularidad
- **Verificación de Titularidad del Curso**:
  ```php
  if (!$user->docente || $curso->id_docente_titular !== $user->docente->id_docente) {
      abort(403, 'Solo el Docente Titular del curso puede cambiar el titular de un componente.');
  }
  ```
- **Verificación de Pertenencia Jerárquica (Anti-IDOR)**:
  ```php
  if ($componente->id_curso !== $curso->id_curso) {
      abort(403, 'El componente no pertenece al curso indicado.');
  }
  ```
- **Sincronización Contextual RBAC**:
  - Promueve al docente seleccionado con rol `Docente Titular` en el contexto del curso y degrada a los ex-titulares con `downgradeDocenteRolToComponente()`.

### 4.2. `componenteIndex` y `componenteSync`: Permisos Colegiados
- **Verificación de Titularidad de Componente**:
  - `authorizeEsTitularComponente($componente)` consulta la tabla `curso.docente_componente` validando `id_docente = $user->docente->id_docente AND es_titular = true`.
- **Whitelisting Estricto de Slugs**:
  - Solo permite los slugs definidos en `COMPONENTE_SLUGS`:
    - `actividades:evaluar`
    - `actividades:editar`
    - `componentes/asistencia:registrar`
    - `componentes/asistencia:editar`
- **Ámbito Contextual**:
  - Los permisos se aplican en el contexto del componente (`$componente->id_contexto`), manteniendo estricto aislamiento entre secciones (ej. un docente del Lab 1 no obtiene permisos sobre el Lab 2).

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::COMPONENTES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L74) (`'componentes:ver'`)
  - [`Permissions::COMPONENTES_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L72) (`'componentes:editar'`)
  - [`Permissions::COMPONENTES_DOCENTESCOLEGIADOS_ALL`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L84) (`'componentes/docentesColegiados:*'`)
  - [`Permissions::ACTIVIDADES_EVALUAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L31) (`'actividades:evaluar'`)
  - [`Permissions::COMPONENTES_ASISTENCIA_REGISTRAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L80) (`'componentes/asistencia:registrar'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro (Middleware) | Comprobación de Autorización | Protección Anti-IDOR | Ámbito RBAC | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET .../componentes` | `is_docente` | Vinculación a `$curso` | Scoped a `$curso` | Lectura componentes | ✅ **CUMPLE** |
| `PUT .../titular` | `is_docente` | DT de curso obligatorio | Valida `$componente->id_curso` | Sincroniza Roles RBAC | ✅ **CUMPLE** |
| `GET .../permisos` | `is_docente` | DT de componente obligatorio | Scoped a `$componente` | Contexto Componente | ✅ **CUMPLE** |
| `POST .../permisos` | `is_docente` | DT de componente obligatorio | Slugs whitelisted en `COMPONENTE_SLUGS` | Contexto Componente | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% CUMPLE**. Demuestra excelente granularidad contextual respetando los dos niveles de titularidad (Titular de Curso vs Titular de Componente).
