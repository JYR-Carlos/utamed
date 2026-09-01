# Reporte de Auditoría: Delegación Granular de Permisos en Cursos

- **Rutas Auditadas**:
  - `GET /docente/cursos/{curso}/delegacion-permisos` (`docente.cursos.delegacion-permisos.index`)
  - `POST /docente/cursos/{curso}/delegacion-permisos/toggle` (`docente.cursos.delegacion-permisos.toggle`)
  - `GET /docente/cursos/{curso}/permisos-syllabus` (`docente.cursos.permisos-syllabus.index`)
  - `POST /docente/cursos/{curso}/permisos-syllabus` (`docente.cursos.permisos-syllabus.sync`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/DelegacionPermisosCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DelegacionPermisosCurso.svelte)
  - [`resources/js/pages/docente/components/PermisosSyllabusMatriz.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/PermisosSyllabusMatriz.svelte)
- **Controladores Backend**:
  - [`app/Http/Controllers/Docente/DelegacionPermisosController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DelegacionPermisosController.php)
  - [`app/Http/Controllers/Docente/CursoPermisosController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php)
- **Servicio de Autorización**:
  - [`app/Services/Docente/PermisosCursoService.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Docente/PermisosCursoService.php)
- **Policy Asociada**:
  - [`app/Policies/CursoPolicy.php`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Permite al **Docente Titular** delegar de forma quirúrgica permisos específicos (edición de syllabus, creación de actividades, evaluación, gestión de grupos) a integrantes específicos de su equipo docente dentro del contexto del curso.

```mermaid
flowchart TD
    A[Docente Titular] --> R1["GET /docente/cursos/{curso}/delegacion-permisos"]
    R1 --> P1["CursoPolicy@manageTeam($curso)"]
    P1 --> C1[DelegacionPermisosController@index]
    C1 --> V1[Render DelegacionPermisosCurso con Matriz]

    A --> R2["POST .../delegacion-permisos/toggle"]
    R2 --> P2["CursoPolicy@manageTeam($curso)"]
    P2 --> C2[DelegacionPermisosController@toggle]
    C2 --> G1{Guard: assertIsMiembroCurso}
    G1 -->|Target = Self| BLK1[422: Anti-Auto-Delegacion]
    G1 -->|Target no es del curso| LOG1[403 + Log IDOR_DELEGACION_USUARIO_EXTERNO]
    G1 -->|Target Valido| SVC[PermisosCursoService::syncPermiso]
    SVC --> DB[(usuario.usuario_permiso_especial)]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas y Componentes**:
  - [`DelegacionPermisosCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DelegacionPermisosCurso.svelte): Renderiza matriz interactiva con acordeones por área (`Curso`, `Inscripciones`, `Unidades`, `Programas`, `Componentes`, `Actividades`, `Grupos`).
  - Swiches reactivos que disparan peticiones POST hacia `/toggle`.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/cursos/{curso}/delegacion-permisos` | `docente.cursos.delegacion-permisos.index` | `['auth', 'verified', 'is_docente']` | [`DelegacionPermisosController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DelegacionPermisosController.php#L116) |
| `POST` | `/docente/cursos/{curso}/delegacion-permisos/toggle` | `docente.cursos.delegacion-permisos.toggle` | `['auth', 'verified', 'is_docente']` | [`DelegacionPermisosController@toggle`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DelegacionPermisosController.php#L160) |
| `GET` | `/docente/cursos/{curso}/permisos-syllabus` | `docente.cursos.permisos-syllabus.index` | `['auth', 'verified', 'is_docente']` | [`CursoPermisosController@syllabusIndex`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php#L73) |
| `POST` | `/docente/cursos/{curso}/permisos-syllabus` | `docente.cursos.permisos-syllabus.sync` | `['auth', 'verified', 'is_docente']` | [`CursoPermisosController@syllabusSync`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/CursoPermisosController.php#L110) |

---

## 4. Fase 3 & 4: Controlador Backend, Policies y RelBAC

### 4.1. `CursoPolicy@manageTeam`
- Todos los métodos exigen que el solicitante sea el Docente Titular actual del curso.

### 4.2. Whitelist Estricta de Slugs Delegables (`DELEGABLE_MATRIX`)
- Solo los 33 slugs declarados en la constante `DELEGABLE_MATRIX` son aceptados en la validación HTTP (`in:...`). No es posible delegar permisos de administración global, facultades o usuarios.

### 4.3. IDOR Guard: `assertIsMiembroCurso`
- **Previene Auto-Delegación**: Un docente no puede auto-otorgarse permisos especiales para evadir restricciones.
- **Previene Asignación Cruzada**: Verifica que el usuario objetivo figure como docente de algún componente del curso (`$curso->componentes()->whereHas('docenteComponentes.docente'...)`).
- **Auditoría de Intentos Fraudulentos**: Registra en `Log::channel('seguridad')` cualquier intento con evento `IDOR_DELEGACION_USUARIO_EXTERNO`.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Slugs administrados:
  - `cursos:ver`, `cursos:editar`, `cursos:eliminar`
  - `cursos/programas/modificar:modulo_1` a `modulo_9`
  - `actividades:*` (`crear`, `editar`, `eliminar`, `evaluar`, `dar_feedback`, `descargar_entregas`)
  - `actividades/grupos:*` (`ver`, `crear`, `editar`, `eliminar`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Policy / RelBAC | IDOR Guard | Auditoría | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET .../delegacion-permisos` | `is_docente` | `CursoPolicy@manageTeam` | Scoped a miembros del curso | - | ✅ **CUMPLE** |
| `POST .../delegacion-permisos/toggle` | `is_docente` | `CursoPolicy@manageTeam` | `assertIsMiembroCurso` + Anti-self | Log `IDOR_DELEGACION_USUARIO_EXTERNO` | ✅ **CUMPLE** |
| `GET .../permisos-syllabus` | `is_docente` | `CursoPolicy@manageTeam` | Scoped a docentes del curso | - | ✅ **CUMPLE** |
| `POST .../permisos-syllabus` | `is_docente` | `CursoPolicy@manageTeam` | Whitelist `SYLLABUS_SLUGS` | - | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO**. Excelente implementación de principio de mínimo privilegio y detección de IDOR.
