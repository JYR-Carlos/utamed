# Reporte de Auditoría: Submódulo Jefatura de Carrera

- **Rutas Auditadas**:
  - `GET /docente/jefe-carrera/dashboard` (`docente.jefe-carrera.dashboard`)
  - `GET /docente/jefe-carrera/actividades` (`docente.jefe-carrera.actividades`)
  - `GET /docente/jefe-carrera/metricas` (`docente.jefe-carrera.metricas`)
  - `GET /docente/jefe-carrera/programas/{programaId}/preview` (`docente.jefe-carrera.programas.preview`)
  - `POST /docente/jefe-carrera/programas/{programaId}/aprobar` (`docente.jefe-carrera.programas.aprobar`)
  - `POST /docente/jefe-carrera/programas/{programaId}/rechazar` (`docente.jefe-carrera.programas.rechazar`)
  - `GET /docente/jefe-carrera/planes` (`docente.jefe-carrera.planes.index`)
  - `POST /docente/jefe-carrera/planes` (`docente.jefe-carrera.planes.store`)
  - `GET /docente/jefe-carrera/planes/{plan}` (`docente.jefe-carrera.planes.show`)
  - `PUT /docente/jefe-carrera/planes/{plan}` (`docente.jefe-carrera.planes.update`)
  - `DELETE /docente/jefe-carrera/planes/{plan}` (`docente.jefe-carrera.planes.destroy`)
  - `POST /docente/jefe-carrera/planes/{plan}/asignaturas` (`docente.jefe-carrera.asignacion-planes.store`)
  - `DELETE /docente/jefe-carrera/planes/{plan}/asignaturas/{asignatura}` (`docente.jefe-carrera.asignacion-planes.destroy`)
  - `GET /docente/jefe-carrera/asignaturas` (`docente.jefe-carrera.asignaturas.index`)
  - `GET /docente/jefe-carrera/carreras/{carrera}/malla` (`docente.jefe-carrera.carreras.malla`)
- **Vistas Frontend**:
  - [`resources/js/pages/jefe-carrera/Dashboard.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/jefe-carrera/Dashboard.svelte)
  - [`resources/js/pages/jefe-carrera/ActividadesSeguimiento.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/jefe-carrera/ActividadesSeguimiento.svelte)
  - [`resources/js/pages/jefe-carrera/Metricas.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/jefe-carrera/Metricas.svelte)
  - [`resources/js/pages/admin/Planes.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Planes.svelte)
  - [`resources/js/pages/admin/Asignaturas.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Asignaturas.svelte)
- **Controladores Backend**:
  - [`app/Http/Controllers/Docente/JefeCarreraController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php)
  - [`app/Http/Controllers/Docente/JefeCarrera/PlanController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/PlanController.php)
  - [`app/Http/Controllers/Docente/JefeCarrera/AsignacionPlanController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/AsignacionPlanController.php)
  - [`app/Http/Controllers/Docente/JefeCarrera/AsignaturaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/AsignaturaController.php)
  - [`app/Http/Controllers/Docente/JefeCarrera/CarreraController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/CarreraController.php)
- **Trait de Autorización**:
  - [`app/Http/Controllers/Docente/JefeCarrera/ResolvesJefaturaCarrera.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/ResolvesJefaturaCarrera.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Brinda herramientas de gobierno académico al **Jefe de Carrera** para supervisar la entrega y avance de syllabus, evaluar métricas de rendimiento y riesgo de alumnos, aprobar/rechazar programas oficiales y gestionar los planes de estudio y malla curricular de su propia carrera.

```mermaid
flowchart TD
    A[Docente con Rol Jefe de Carrera] --> R1["GET /docente/jefe-carrera/dashboard"]
    R1 --> TR[Trait: ResolvesJefaturaCarrera]
    TR -->|Sin Asignacion Activa de Jefe de Carrera| D1[Redirect /docente/dashboard con error]
    TR -->|Jefatura Activa en Carrera X| C1[JefeCarreraController@dashboard]
    C1 --> Q1[Query Cursos y Metricas acotadas a Carrera X]
    C1 --> V1[Render jefe-carrera/Dashboard]

    V1 -->|Aprobar / Rechazar Syllabus| R2["POST .../programas/{id}/aprobar"]
    R2 --> TR
    TR --> G1{El programa pertenece a un curso de Carrera X?}
    G1 -->|No| ERR1[403 Forbidden]
    G1 -->|Si| S1[Actualiza estado APROBADO / BORRADOR + Trigger Auditoria]

    V1 -->|Gestionar Planes / Malla| R3["POST / PUT / DELETE .../planes"]
    R3 --> TR
    TR --> G2[Fuerza id_carrera = Carrera X e impide mutar otras carreras]
    G2 --> S2[Persistencia acotada a la carrera]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas Especializadas**:
  - [`Dashboard.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/jefe-carrera/Dashboard.svelte): Tablero de control con métricas agregadas de asistencia, alumnos en riesgo y cumplimiento de syllabus.
  - [`ActividadesSeguimiento.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/jefe-carrera/ActividadesSeguimiento.svelte): Vista de auditoría por curso y docente.
  - Modales de Aprobación y Rechazo de Programa con captura de observaciones obligatorias.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/jefe-carrera/dashboard` | `docente.jefe-carrera.dashboard` | `['auth', 'verified', 'is_docente']` | [`JefeCarreraController@dashboard`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php#L43) |
| `GET` | `/docente/jefe-carrera/actividades` | `docente.jefe-carrera.actividades` | `['auth', 'verified', 'is_docente']` | [`JefeCarreraController@actividades`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php) |
| `GET` | `/docente/jefe-carrera/metricas` | `docente.jefe-carrera.metricas` | `['auth', 'verified', 'is_docente']` | [`JefeCarreraController@metricas`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php) |
| `GET` | `.../programas/{id}/preview` | `docente.jefe-carrera.programas.preview` | `['auth', 'verified', 'is_docente']` | [`JefeCarreraController@programaPreview`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php) |
| `POST` | `.../programas/{id}/aprobar` | `docente.jefe-carrera.programas.aprobar` | `['auth', 'verified', 'is_docente']` | [`JefeCarreraController@aprobarPrograma`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php) |
| `POST` | `.../programas/{id}/rechazar` | `docente.jefe-carrera.programas.rechazar` | `['auth', 'verified', 'is_docente']` | [`JefeCarreraController@rechazarPrograma`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarreraController.php) |
| `GET` | `/docente/jefe-carrera/planes` | `docente.jefe-carrera.planes.index` | `['auth', 'verified', 'is_docente']` | [`PlanController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/PlanController.php#L27) |
| `POST` | `.../planes/{plan}/asignaturas` | `docente.jefe-carrera.asignacion-planes.store` | `['auth', 'verified', 'is_docente']` | [`AsignacionPlanController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/AsignacionPlanController.php) |
| `GET` | `.../carreras/{c}/malla` | `docente.jefe-carrera.carreras.malla` | `['auth', 'verified', 'is_docente']` | [`CarreraController@malla`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/JefeCarrera/CarreraController.php) |

---

## 4. Fase 3 & 4: Controlador Backend, Trait y Aislamiento por Carrera

### 4.1. Trait `ResolvesJefaturaCarrera`
- Resuelve dinámicamente mediante `JefaturaCarreraResolver` la carrera exacta asociada a la asignación activa del rol `Jefe de Carrera` en el contexto correspondiente (`categoria = 'carrera'`).
- Si el usuario no tiene jefatura activa, `jefaturaOrAbort()` interrumpe con 403 y `jefaturaOrRedirect()` redirige al dashboard docente.

### 4.2. Inyección y Blindaje Forzado en Mutaciones
- En la creación de planes:
  ```php
  $carreraId = $this->carreraIdOrAbort();
  $validated['id_carrera'] = $carreraId; // Forzado: se sobreescribe cualquier input malicioso
  Plan::create($validated);
  ```
- En aprobación de programas:
  - Valida que el programa pertenezca a un curso cuya asignación curricular corresponda al `$carreraId` del jefe autenticado.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::CARRERAS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L58) (`'carreras:ver'`)
  - [`Permissions::PLANES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L143) (`'planes:ver'`)
  - [`Permissions::PLANES_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L140) (`'planes:crear'`)
  - [`Permissions::CURSOS_PROGRAMAS_APROBAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L119) (`'cursos/programas:aprobar'`)
  - [`Permissions::CURSOS_PROGRAMAS_RECHAZAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L120) (`'cursos/programas:rechazar'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Trait de Aislamiento | Blindaje Anti-IDOR | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET .../dashboard` | `is_docente` | `jefaturaOrRedirect` | Scoped a su `$carreraId` | ✅ **CUMPLE** |
| `POST .../aprobar` | `is_docente` | `jefaturaOrAbort` | Valida programa en su carrera | ✅ **CUMPLE** |
| `POST .../rechazar` | `is_docente` | `jefaturaOrAbort` | Valida programa en su carrera | ✅ **CUMPLE** |
| `GET .../planes` | `is_docente` | `carreraIdOrAbort` | Solo planes de su carrera | ✅ **CUMPLE** |
| `POST .../planes` | `is_docente` | `carreraIdOrAbort` | Fuerza `id_carrera = $carreraId` | ✅ **CUMPLE** |
| `PUT .../planes/{p}` | `is_docente` | `assertPlanDeCarrera` | Bloquea cambio de carrera | ✅ **CUMPLE** |
| `GET .../carreras/{c}/malla`| `is_docente` | `carreraIdOrAbort` | Bloquea carreras ajenas | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% CUMPLE**. Demuestra un blindaje de seguridad ejemplar mediante el trait `ResolvesJefaturaCarrera` y la asignación forzada de claves foráneas.
