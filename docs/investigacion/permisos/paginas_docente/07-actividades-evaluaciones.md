# Reporte de Auditoría: Actividades, Rúbricas y Calificaciones

- **Rutas Auditadas**:
  - `GET /docente/cursos/{curso}/actividades` (`docente.cursos.actividades.index`)
  - `GET /docente/cursos/{curso}/actividades/json` (`docente.cursos.actividades.json`)
  - `POST /docente/cursos/{curso}/actividades` (`docente.cursos.actividades.store`)
  - `PUT /docente/cursos/{curso}/actividades/{actividad}` (`docente.cursos.actividades.update`)
  - `PATCH /docente/cursos/{curso}/actividades/{actividad}/visibilidad` (`docente.cursos.actividades.visibilidad.toggle`)
  - `DELETE /docente/cursos/{curso}/actividades/{actividad}` (`docente.cursos.actividades.destroy`)
  - `GET /docente/calificaciones` (`docente.calificaciones.centro`)
  - `GET /docente/cursos/{curso}/actividades/{actividad}/evaluacion` (`docente.cursos.actividades.evaluacion`)
  - `POST /docente/cursos/{curso}/rubrica` (`docente.cursos.rubrica.store`)
  - `PUT /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/integrantes/{asignado}` (`docente.cursos.actividades.integrantes.update`)
  - `POST /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/recalcular-notas` (`docente.cursos.actividades.grupos.recalcular`)
  - `POST /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/evaluacion` (`docente.cursos.actividades.grupos.evaluacion`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/Actividades.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Actividades.svelte)
  - [`resources/js/pages/docente/Calificaciones.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Calificaciones.svelte)
  - [`resources/js/pages/docente/Activities/MatrizEvaluacion.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Activities/MatrizEvaluacion.svelte)
  - [`resources/js/pages/docente/Activities/RubricaEditor.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Activities/RubricaEditor.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Docente/DocenteActivityController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php)
- **Guards de Seguridad**:
  - `assertActividadDeCurso` en [`DocenteActivityController.php:L51`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php#L51)
  - `assertPuedeEditarEvaluacion` en [`DocenteActivityController.php:L73`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php#L73)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Gobierna el ciclo de vida de las evaluaciones del curso: creación de tareas/controles, definición de rúbricas analíticas, visibilidad para alumnos, corrección de entregas, ajuste de notas individuales y recálculo ponderado.

```mermaid
flowchart TD
    A[Docente] --> R1["GET /docente/cursos/{c}/actividades"]
    R1 --> P1["CursoPolicy@viewPrograma($curso)"]
    P1 --> C1[DocenteActivityController@show]
    C1 --> V1[Render docente/Actividades]

    V1 -->|Crear/Editar Evaluacion| R2["POST / PUT / DELETE .../actividades/{a}"]
    R2 --> G1{Guard: assertActividadDeCurso}
    G1 -->|Actividad ajena al curso| E1[404 Not Found]
    G1 -->|Actividad valida| G2{Guard: assertPuedeEditarEvaluacion}
    G2 -->|Es DT Curso o Docente del Componente| S1[Permitir Mutacion]
    G2 -->|Docente de otro componente| E2[403 + Log ESCRITURA_EVALUACION_FUERA_DE_COMPONENTE]

    V1 -->|Calificar Grupo/Rubrica| R3["POST .../grupos/{g}/evaluacion"]
    R3 --> G1
    R3 --> G2
    G2 --> S2[Guarda Puntajes + Recalcula Nota Final]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas**:
  - [`Actividades.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Actividades.svelte): Tablero Kanban y lista por estado.
  - [`MatrizEvaluacion.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Activities/MatrizEvaluacion.svelte): Matriz de corrección con niveles de rúbrica, notas grupales e individuales.
  - [`RubricaEditor.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Activities/RubricaEditor.svelte): Creador de rúbricas con descriptores.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/cursos/{curso}/actividades` | `docente.cursos.actividades.index` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php#L154) |
| `GET` | `/docente/cursos/{curso}/actividades/json` | `docente.cursos.actividades.json` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@actividadesJson`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `POST` | `/docente/cursos/{curso}/actividades` | `docente.cursos.actividades.store` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `PUT` | `/docente/cursos/{curso}/actividades/{actividad}` | `docente.cursos.actividades.update` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `PATCH` | `/docente/cursos/{curso}/actividades/{actividad}/visibilidad` | `docente.cursos.actividades.visibilidad.toggle` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@toggleVisibilidad`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `DELETE` | `/docente/cursos/{curso}/actividades/{actividad}` | `docente.cursos.actividades.destroy` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `GET` | `/docente/calificaciones` | `docente.calificaciones.centro` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@centroCalificaciones`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `GET` | `/docente/cursos/{curso}/actividades/{actividad}/evaluacion` | `docente.cursos.actividades.evaluacion` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@showEvaluacion`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `POST` | `/docente/cursos/{curso}/rubrica` | `docente.cursos.rubrica.store` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@storeRubrica`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `PUT` | `.../grupos/{grupo}/integrantes/{asignado}` | `docente.cursos.actividades.integrantes.update` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@updateIntegrante`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `POST` | `.../grupos/{grupo}/recalcular-notas` | `docente.cursos.actividades.grupos.recalcular` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@recalcularNotasIndividuales`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |
| `POST` | `.../grupos/{grupo}/evaluacion` | `docente.cursos.actividades.grupos.evaluacion` | `['auth', 'verified', 'is_docente']` | [`DocenteActivityController@storeEvaluacion`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteActivityController.php) |

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### 4.1. Guard `assertActividadDeCurso`: Prevención de IDOR Vertical
- Verifica estrictamente que `$actividad->componente->id_curso === $curso->id_curso`. Si se intenta enviar un `id_actividad` perteneciente a otro curso en la URL, se interrumpe con `404 Not Found`.

### 4.2. Guard `assertPuedeEditarEvaluacion`: Segregación por Componente
- **Docente Titular del Curso**: Tiene permisos completos sobre todas las evaluaciones de todos los componentes.
- **Docente de Componente (Colegiado)**: **Solo puede modificar y evaluar actividades del componente específico que imparte** (`$actividad->componente->docenteComponentes()->where('id_docente', ...)`).
- Si un docente de Laboratorio intenta calificar la Cátedra o borrar una actividad ajena, se emite una alerta de seguridad (`ESCRITURA_EVALUACION_FUERA_DE_COMPONENTE`) y se aborta con 403.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::ACTIVIDADES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L33) (`'actividades:ver'`)
  - [`Permissions::ACTIVIDADES_CREAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L24) (`'actividades:crear'`)
  - [`Permissions::ACTIVIDADES_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L28) (`'actividades:editar'`)
  - [`Permissions::ACTIVIDADES_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L29) (`'actividades:eliminar'`)
  - [`Permissions::ACTIVIDADES_EVALUAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L31) (`'actividades:evaluar'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Guard Anti-IDOR | Segregación de Componente | Auditoría | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET .../actividades` | `is_docente` | `viewPrograma` | Scoped al curso | - | ✅ **CUMPLE** |
| `POST .../actividades` | `is_docente` | `viewPrograma` | Valida componente del curso | - | ✅ **CUMPLE** |
| `PUT .../actividades/{a}` | `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | Log Seguridad | ✅ **CUMPLE** |
| `PATCH .../visibilidad` | `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | Log Seguridad | ✅ **CUMPLE** |
| `DELETE .../actividades/{a}` | `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | Log Seguridad | ✅ **CUMPLE** |
| `GET /calificaciones` | `is_docente` | Query acotada al docente | Scoped a cursos impartidos | - | ✅ **CUMPLE** |
| `GET .../evaluacion` | `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | - | ✅ **CUMPLE** |
| `POST .../evaluacion` | `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | Log Seguridad | ✅ **CUMPLE** |
| `PUT .../integrantes/{asig}`| `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | - | ✅ **CUMPLE** |
| `POST .../recalcular-notas` | `is_docente` | `assertActividadDeCurso` | `assertPuedeEditarEvaluacion` | - | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% CUMPLE**. Ofrece uno de los esquemas de autorización más robustos del sistema al impedir que docentes colegiados interfieran en evaluaciones de otros componentes.
