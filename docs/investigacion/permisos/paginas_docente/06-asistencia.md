# Reporte de Auditoría: Control de Asistencia Docente

- **Rutas Auditadas**:
  - `GET /docente/asistencia` (`docente.asistencia.centro`)
  - `GET /docente/cursos/{curso}/componentes/{componente}/asistencia` (`docente.cursos.componentes.asistencia.index`)
  - `POST /docente/cursos/{curso}/componentes/{componente}/asistencia` (`docente.cursos.componentes.asistencia.store`)
  - `PUT /docente/cursos/{curso}/componentes/{componente}/asistencia` (`docente.cursos.componentes.asistencia.update`)
  - `DELETE /docente/cursos/{curso}/componentes/{componente}/asistencia` (`docente.cursos.componentes.asistencia.destroy`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/Asistencia.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Asistencia.svelte)
  - [`resources/js/pages/docente/components/AsistenciaPanel.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/AsistenciaPanel.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Docente/AsistenciaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/AsistenciaController.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Permite a los docentes registrar, editar y supervisar la asistencia de estudiantes por componente curricular (Cátedra, Taller, Laboratorio).

```mermaid
flowchart TD
    A[Docente] --> R1["GET /docente/asistencia"]
    R1 --> C1[AsistenciaController@centro]
    C1 --> Q1[Query Cursos y Componentes autorizados]
    C1 --> V1[Render docente/Asistencia]

    V1 -->|Seleccionar Componente| R2["GET .../componentes/{comp}/asistencia"]
    R2 --> G1{Guard: autorizarComponente}
    G1 -->|No es DT ni Imparte| ERR1[403 Forbidden]
    G1 -->|Comp no es del Curso| ERR2[404 Not Found]
    G1 -->|Autorizado| C2[AsistenciaController@index]
    C2 --> JSON1[JSON: Grilla Estudiantes + Sesiones agrupadas]

    V1 -->|Guardar Sesion| R3["POST / PUT / DELETE .../asistencia"]
    R3 --> G1
    R3 --> V2[Validar Horarios + Filtrar Inscripciones Validas]
    V2 --> TX[DB::transaction]
    TX --> S1[Insert / Update / Delete en curso.asistencia]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas y Componentes**:
  - [`Asistencia.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Asistencia.svelte): Tablero transversal de selección rápida de curso y componente.
  - [`AsistenciaPanel.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/AsistenciaPanel.svelte): Grilla de asistencia con marcas de Presente/Ausente y cálculo automático de porcentajes.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/asistencia` | `docente.asistencia.centro` | `['auth', 'verified', 'is_docente']` | [`AsistenciaController@centro`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/AsistenciaController.php#L43) |
| `GET` | `/docente/cursos/{curso}/componentes/{componente}/asistencia` | `docente.cursos.componentes.asistencia.index` | `['auth', 'verified', 'is_docente']` | [`AsistenciaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/AsistenciaController.php#L108) |
| `POST` | `/docente/cursos/{curso}/componentes/{componente}/asistencia` | `docente.cursos.componentes.asistencia.store` | `['auth', 'verified', 'is_docente']` | [`AsistenciaController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/AsistenciaController.php#L155) |
| `PUT` | `/docente/cursos/{curso}/componentes/{componente}/asistencia` | `docente.cursos.componentes.asistencia.update` | `['auth', 'verified', 'is_docente']` | [`AsistenciaController@update`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/AsistenciaController.php#L187) |
| `DELETE` | `/docente/cursos/{curso}/componentes/{componente}/asistencia` | `docente.cursos.componentes.asistencia.destroy` | `['auth', 'verified', 'is_docente']` | [`AsistenciaController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/AsistenciaController.php#L214) |

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### 4.1. Guard `autorizarComponente` (Anti-IDOR y Titularidad)
En todas las operaciones sobre un componente específico se verifica:
```php
if ($componente->id_curso !== $curso->id_curso) {
    abort(404, 'El componente no pertenece a este curso.');
}

$esTitular = $curso->id_docente_titular === $docente->id_docente;
$esAsignado = $componente->docenteComponentes()
    ->where('id_docente', $docente->id_docente)
    ->exists();

if (!$esTitular && !$esAsignado) {
    abort(403, 'No tienes acceso a la asistencia de este componente.');
}
```

### 4.2. Blindaje Anti-Inyección de Estudiantes Ajenos
- Al guardar o actualizar la sesión, el controlador consulta los `idsInscripcionValidos($componente)` reales desde la base de datos.
- Cualquier ID de inscripción enviado por el cliente que no corresponda al componente es descartado (`if (!isset($validas[$idIc])) continue;`), impidiendo modificar asistencias de otros cursos o secciones.
- Transacciones protegidas con `DB::transaction()`.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::COMPONENTES_ASISTENCIA_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L81) (`'componentes/asistencia:ver'`)
  - [`Permissions::COMPONENTES_ASISTENCIA_REGISTRAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L80) (`'componentes/asistencia:registrar'`)
  - [`Permissions::COMPONENTES_ASISTENCIA_EDITAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L78) (`'componentes/asistencia:editar'`)
  - [`Permissions::COMPONENTES_ASISTENCIA_ELIMINAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L79) (`'componentes/asistencia:eliminar'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Guard / Autorización | Anti-IDOR (Componente & Inscripciones) | Integridad DB | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET /asistencia` | `is_docente` | Query acotada al docente | Scoped a cursos impartidos | - | ✅ **CUMPLE** |
| `GET .../asistencia` | `is_docente` | `autorizarComponente` | Valida `$componente->id_curso` | - | ✅ **CUMPLE** |
| `POST .../asistencia` | `is_docente` | `autorizarComponente` | Filtra IDs de inscripción con whitelist | `DB::transaction` | ✅ **CUMPLE** |
| `PUT .../asistencia` | `is_docente` | `autorizarComponente` | Filtra IDs de inscripción con whitelist | `DB::transaction` | ✅ **CUMPLE** |
| `DELETE .../asistencia` | `is_docente` | `autorizarComponente` | Delete acotado a inscripciones válidas | `DB::transaction` | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO Y CUMPLE**. Presenta doble capa de protección anti-IDOR (curso-componente y componente-inscripción).
