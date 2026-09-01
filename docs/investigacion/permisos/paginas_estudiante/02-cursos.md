# Reporte de Auditoría: Cursos y Detalle de Asignatura del Estudiante

- **Rutas Auditadas**:
  - `GET /estudiante/cursos` (`estudiante.cursos.index`)
  - `GET /estudiante/cursos/{curso}` (`estudiante.cursos.show`)
- **Vistas Frontend**:
  - [`resources/js/pages/student/Courses/Index.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Courses/Index.svelte)
  - [`resources/js/pages/student/Courses/Show.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Courses/Show.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Student/CourseController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/CourseController.php)
- **Presentador de Syllabus**:
  - [`app/Services/Student/StudentSyllabusPresenter.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/Student/StudentSyllabusPresenter.php)
- **Middlewares**: `['auth', 'verified', 'is_estudiante']`

---

## 1. Alcance y Flujo de Navegación

Permite a los estudiantes matriculados consultar el catálogo histórico y vigente de sus asignaturas, ingresar al detalle del curso para revisar su ficha curricular, visualizar las actividades evaluativas visibles y examinar el syllabus oficial de su sección.

```mermaid
flowchart TD
    A[Estudiante Autenticado] --> R1["GET /estudiante/cursos"]
    R1 --> C1[Student\\CourseController@index]
    C1 --> Q1[Query inscripcionCursos() del Estudiante]
    C1 --> V1[Render student/Courses/Index]

    V1 -->|Click en Curso| R2["GET /estudiante/cursos/{curso}"]
    R2 --> G1{Guard: Verifica Matricula Activa en Curso}
    G1 -->|No Matriculado / Estado Inactivo| E1[403 No estas inscrito en este curso]
    G1 -->|Matricula Valida| C2[Student\\CourseController@show]
    C2 --> Q2[Filtro Componentes del Alumno]
    C2 --> Q3[Filtro Actividades visible = true]
    C2 --> V2[Render student/Courses/Show]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas**:
  - [`Courses/Index.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Courses/Index.svelte): Grilla de cursos con filtros de año y semestre.
  - [`Courses/Show.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Courses/Show.svelte): Pestañas de syllabus, actividades evaluativas, ponderaciones y cuerpo docente.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/estudiante/cursos` | `estudiante.cursos.index` | `['auth', 'verified', 'is_estudiante']` | [`CourseController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/CourseController.php#L36) |
| `GET` | `/estudiante/cursos/{curso}` | `estudiante.cursos.show` | `['auth', 'verified', 'is_estudiante']` | [`CourseController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/CourseController.php#L105) |

---

## 4. Fase 3 & 4: Controlador Backend y Aislamiento por Matrícula

### 4.1. Verificación de Matrícula Activa (Anti-IDOR)
En `show(Curso $curso)`:
```php
$inscripcion = $estudiante->inscripcionCursos()
    ->where('id_curso', $curso->id_curso)
    ->where('estado_inscripcion', 'INSCRITO')
    ->first();

if (!$inscripcion) {
    abort(403, 'No estás inscrito en este curso');
}
```
Si un estudiante altera el `{curso}` en la URL para consultar un curso donde no está inscrito, se bloquea inmediatamente con 403.

### 4.2. Aislamiento por Componente Curricular
- El controlador consulta únicamente los componentes donde el alumno tiene inscripción (`inscripcion_componente`).
- Las actividades se restringen mediante `whereIn('id_componente', $componenteIds)->where('visible', true)`. Las actividades ocultas/borradores creadas por los docentes nunca se exponen en la API ni en los props de Inertia.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Permisos involucrados:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)
  - [`Permissions::ACTIVIDADES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L33) (`'actividades:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Guard Anti-IDOR | Filtrado de Visibilidad | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET /estudiante/cursos` | `is_estudiante` | Scoped a `inscripcionCursos()` | Solo cursos inscritos | ✅ **CUMPLE** |
| `GET /estudiante/cursos/{curso}` | `is_estudiante` | Valida `estado_inscripcion = 'INSCRITO'` | `visible = true` obligatorio | ✅ **CUMPLE** |

**Veredicto**: Módulo **100% CUMPLE**. Presenta estricto control de acceso basado en matrícula institucional y aislamiento de componentes.
