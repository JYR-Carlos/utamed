# Reporte de Auditoría: Programa / Syllabus del Curso (Perspectiva Docente)

- **Rutas Auditadas**:
  - `GET /docente/cursos/{curso}/programa` (`docente.cursos.programa.show`)
  - `POST /docente/cursos/{curso}/programa` (`docente.cursos.programa.store`)
  - `PUT /docente/cursos/{curso}/programa/completar-basico` (`docente.cursos.programa.completar-basico`)
  - `PUT /docente/cursos/{curso}/programa/enviar` (`docente.cursos.programa.enviar`)
  - `DELETE /docente/cursos/{curso}/programa` (`docente.cursos.programa.destroy`)
  - `GET /docente/cursos/{curso}/programa/json` (`docente.cursos.programa.json`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/Programa.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Programa.svelte)
  - Editores modulares de secciones del Syllabus (Módulos I a IX).
- **Controladores Backend**:
  - [`app/Http/Controllers/Administrativo/ProgramaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Administrativo/ProgramaController.php)
  - [`app/Http/Controllers/Admin/ProgramaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php)
- **Policies Asociadas**:
  - [`app/Policies/CursoPolicy.php`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php)
  - [`app/Policies/ProgramaPolicy.php`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/ProgramaPolicy.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Permite a los docentes diseñar y editar el syllabus del curso (unidades, resultados de aprendizaje, bibliografía, ponderaciones), generar borradores, marcar la versión básica como completa y remitir el programa a revisión y aprobación formal por la Jefatura de Carrera.

```mermaid
flowchart TD
    A[Docente] --> R1["GET /docente/cursos/{curso}/programa"]
    R1 --> P1["CursoPolicy@viewPrograma($curso)"]
    P1 --> P2["ProgramaPolicy@view($programa)"]
    P2 --> C1[Administrativo\\ProgramaController@show]
    C1 --> V1[Render docente/Programa]

    V1 -->|Generar Syllabus| R2["POST .../programa"]
    R2 --> P1
    R2 --> P3["ProgramaPolicy@create"]
    P3 --> S1[ProgramaService::generateProgramaWithSyllabus]

    V1 -->|Marcar Basico Completo| R3["PUT .../completar-basico"]
    R3 --> P1
    R3 --> P4["ProgramaPolicy@update($programa)"]
    P4 --> S2[BORRADOR -> BASICO_COMPLETO]

    V1 -->|Enviar a Revision| R4["PUT .../enviar"]
    R4 --> P1
    R4 --> P4
    P4 --> S3[BASICO_COMPLETO -> COMPLETO]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas**:
  - [`Programa.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Programa.svelte): Visualizador y editor modular estructurado en 9 secciones romanas.
  - Indicadores de estado: Badges según el estado (`BORRADOR`, `BASICO_COMPLETO`, `COMPLETO`, `APROBADO`).
  - Permisos por sección: Los campos de edición se habilitan según los permisos granulares `cursos/programas/modificar:modulo_X` recibidos en `userPermissions`.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/cursos/{curso}/programa` | `docente.cursos.programa.show` | `['auth', 'verified', 'is_docente']` | [`ProgramaController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Administrativo/ProgramaController.php#L97) |
| `POST` | `/docente/cursos/{curso}/programa` | `docente.cursos.programa.store` | `['auth', 'verified', 'is_docente']` | [`AdminProgramaController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/ProgramaController.php) |
| `PUT` | `.../completar-basico` | `docente.cursos.programa.completar-basico` | `['auth', 'verified', 'is_docente']` | [`ProgramaController@completarBasico`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Administrativo/ProgramaController.php#L277) |
| `PUT` | `.../enviar` | `docente.cursos.programa.enviar` | `['auth', 'verified', 'is_docente']` | [`ProgramaController@enviarParaRevision`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Administrativo/ProgramaController.php#L306) |
| `DELETE` | `/docente/cursos/{curso}/programa` | `docente.cursos.programa.destroy` | `['auth', 'verified', 'is_docente']` | [`ProgramaController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Administrativo/ProgramaController.php#L330) |

---

## 4. Fase 3 & 4: Controlador Backend, Policies y RelBAC

### 4.1. Doble Nivel de Autorización (Curso + Programa)
- **Nivel 1 (Curso)**: `$this->authorize('viewPrograma', $curso)` garantiza que solo docentes asignados al curso accedan.
- **Nivel 2 (Programa)**:
  - `ProgramaPolicy@update`: Valida que el programa no se encuentre bloqueado en estado `APROBADO`.
  - `ProgramaPolicy@create`: Valida permisos para instanciar programas.

### 4.2. Flujo de Estados Seguro
- El docente solo puede transicionar el programa hacia adelante (`BORRADOR` $\rightarrow$ `BASICO_COMPLETO` $\rightarrow$ `COMPLETO`).
- Los métodos `approve` y `reject` están estrictamente reservados para Administradores o Jefes de Carrera con `ProgramaPolicy@approve` y `ProgramaPolicy@reject`.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::CURSOS_PROGRAMAS_VER_TODOS`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L131) (`'cursos/programas:ver_todos'`)
  - [`Permissions::CURSOS_PROGRAMAS_AGREGAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L117) (`'cursos/programas:agregar'`)
  - [`Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L121) a [`MODULO_9`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L129) (`'cursos/programas/modificar:modulo_*'`)
  - [`Permissions::CURSOS_PROGRAMAS_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L118) (`'cursos/programas:eliminar'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Policy Curso | Policy Programa | Máquina de Estados | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET .../programa` | `is_docente` | `viewPrograma` | `view` | Lectura segura | ✅ **CUMPLE** |
| `POST .../programa` | `is_docente` | `viewPrograma` | `create` | Crea estructura inicial | ✅ **CUMPLE** |
| `PUT .../completar-basico` | `is_docente` | `viewPrograma` | `update` | Valida estado editable | ✅ **CUMPLE** |
| `PUT .../enviar` | `is_docente` | `viewPrograma` | `update` | Valida estado editable | ✅ **CUMPLE** |
| `DELETE .../programa` | `is_docente` | `viewPrograma` | `delete` | Soft-delete contextual | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO Y CUMPLE**. Ofrece protección en dos capas de policies y control estricto de la máquina de estados del syllabus.
