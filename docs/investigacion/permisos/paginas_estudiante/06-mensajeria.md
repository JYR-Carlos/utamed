# Reporte de Auditoría: Mensajería por Componente del Estudiante

- **Rutas Auditadas**:
  - `GET /estudiante/cursos/{curso}/mensajeria` (`estudiante.cursos.mensajeria.index`)
  - `POST /estudiante/cursos/{curso}/mensajeria/componentes/{componente}/mensaje` (`estudiante.cursos.mensajeria.enviar`)
- **Vista Frontend**:
  - [`resources/js/pages/student/Mensajeria.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Mensajeria.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Student/MensajeriaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/MensajeriaController.php)
- **Servicio Asociado**:
  - [`app/Services/MensajeriaService.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/MensajeriaService.php)
- **Middlewares**: `['auth', 'verified', 'is_estudiante']`

---

## 1. Alcance y Flujo de Navegación

Permite a los estudiantes leer avisos y difusiones emitidas por el equipo docente de cada componente de su curso y mantener conversaciones directas y privadas con el profesor titular o ayudante de su sección.

```mermaid
flowchart TD
    A[Estudiante Autenticado] --> R1["GET /estudiante/cursos/{curso}/mensajeria"]
    R1 --> C1[Student\\MensajeriaController@index]
    C1 --> Q1[Query componentesDelCurso donde el alumno esta inscrito]
    C1 --> V1[Render student/Mensajeria con Pestañas por Componente]

    V1 -->|Enviar Mensaje a Docente| R2["POST .../componentes/{comp}/mensaje"]
    R2 --> G1{El estudiante esta inscrito en el componente?}
    G1 -->|No| E1[403 No estas inscrito en este componente]
    G1 -->|Si| G2[Resuelve Docente Receptor del Componente]
    G2 --> S1[MensajeriaService::enviarIndividual]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vista**:
  - [`Mensajeria.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Mensajeria.svelte): Visualizador de avisos docentes y panel de mensajería privada.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/estudiante/cursos/{curso}/mensajeria` | `estudiante.cursos.mensajeria.index` | `['auth', 'verified', 'is_estudiante']` | [`MensajeriaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/MensajeriaController.php#L38) |
| `POST` | `.../componentes/{comp}/mensaje` | `estudiante.cursos.mensajeria.enviar` | `['auth', 'verified', 'is_estudiante']` | [`MensajeriaController@enviar`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/MensajeriaController.php#L78) |

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### 4.1. Aislamiento por Inscripción en Componente (Anti-IDOR)
```php
$visible = $this->componentesDelCurso($curso)
    ->contains(fn($c) => (int) $c->id_componente === $componente);

if (!$visible) {
    abort(403, 'No estás inscrito en este componente.');
}
```
Un estudiante no puede enviar mensajes ni espiar canales de componentes a los que no pertenece.

### 4.2. Validación Estricta de Receptores
- El mensaje solo se puede destinar a un docente que efectivamente forme parte de la nómina docente del componente (`$docentes = $this->mensajeria->docentesDeComponente($componente)`).

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Permisos involucrados:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | IDOR Guard Componente | Validación Receptor | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET .../mensajeria` | `is_estudiante` | Scoped a componentes matriculados | - | ✅ **CUMPLE** |
| `POST .../mensaje` | `is_estudiante` | Valida `inscripcion_componente` | Scoped al equipo docente del componente | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO Y CUMPLE**.
