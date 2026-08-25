# Reporte de Auditoría: Mensajería Interna y Canales de Comunicación

- **Rutas Auditadas**:
  - `GET /docente/mensajes` (`docente.mensajes.index`)
  - `GET /docente/cursos/{curso}/mensajeria` (`docente.mensajeria.curso`)
  - `GET /docente/cursos/{curso}/mensajeria/componentes/{componente}` (`docente.mensajeria.componente`)
  - `POST /docente/cursos/{curso}/mensajeria/componentes/{componente}/broadcast` (`docente.mensajeria.enviarAviso`)
  - `POST /docente/cursos/{curso}/mensajeria/componentes/{componente}/estudiantes/{estudiante}` (`docente.mensajeria.enviarDirecto`)
  - `POST /docente/cursos/{curso}/mensajeria/componentes/{componente}/marcar-leido` (`docente.mensajeria.marcarLeido`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/Mensajeria.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Mensajeria.svelte)
  - [`resources/js/pages/docente/Mensajes.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Mensajes.svelte)
  - [`resources/js/pages/docente/components/CanalAlumno.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/CanalAlumno.svelte)
- **Controladores Backend**:
  - [`app/Http/Controllers/Docente/MensajeriaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/MensajeriaController.php)
  - [`app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php)
  - [`app/Http/Controllers/Docente/MensajesController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/MensajesController.php)
- **Servicio Asociado**:
  - [`app/Services/MensajeriaService.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/MensajeriaService.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Provee la comunicación bidireccional entre el equipo docente y los estudiantes a través de dos mecanismos:
1. **Avisos Generales por Componente (Broadcast)**: Notificaciones dirigidas a toda la sección (Cátedra o Laboratorio).
2. **Canales Privados por Alumno**: Hilos individuales compartidos entre el estudiante y el equipo docente del componente.

```mermaid
flowchart TD
    A[Docente] --> R1["GET /docente/cursos/{curso}/mensajeria"]
    R1 --> C1[MensajeriaController@index]
    C1 --> SVC1[MensajeriaService::componentesDeDocente]
    C1 --> V1[Render docente/Mensajeria con Pestañas por Componente]

    V1 -->|Enviar Difusion| R2["POST .../broadcast"]
    R2 --> G1{autorizarComponente}
    G1 -->|Componente No Asignado| E1[403 / 404 Forbidden]
    G1 -->|Autorizado| S1[MensajeriaService::enviarDifusion]

    V1 -->|Mensaje a Alumno| R3["POST .../estudiantes/{id}"]
    R3 --> G1
    R3 --> G2{Alumno Matriculado en Componente?}
    G2 -->|No| E2[404 Alumno no matriculado en componente]
    G2 -->|Si| S2[MensajeriaService::enviarIndividual]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas**:
  - [`Mensajeria.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/Mensajeria.svelte): Pestañas organizadas por componente curricular con badges de no leídos.
  - [`CanalAlumno.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/components/CanalAlumno.svelte): Chat interactivo con historial y formulario de respuesta.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/mensajes` | `docente.mensajeria.index` | `['auth', 'verified', 'is_docente']` | [`MensajeriaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/MensajeriaController.php) |
| `GET` | `/docente/cursos/{curso}/mensajeria` | `docente.mensajeria.curso` | `['auth', 'verified', 'is_docente']` | [`MensajeriaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/MensajeriaController.php) |
| `POST` | `.../componentes/{comp}/broadcast` | `docente.mensajeria.enviarAviso` | `['auth', 'verified', 'is_docente']` | [`GestionaMensajeriaStaff@enviarDifusion`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php#L85) |
| `POST` | `.../componentes/{comp}/estudiantes/{est}` | `docente.mensajeria.enviarDirecto` | `['auth', 'verified', 'is_docente']` | [`GestionaMensajeriaStaff@enviarMensaje`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php#L108) |

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### 4.1. Visibilidad Estricta por Componente
- `resolverComponentesVisibles()` consulta exclusivamente los componentes donde el docente figura en `curso.docente_componente` o en los cursos donde es titular general.

### 4.2. Validación de Matrícula del Receptor (Anti-IDOR)
- En `enviarMensaje`:
  ```php
  $esAlumnoDelComponente = $mensajeria
      ->canalesDeComponente($componente, $idUsuario)
      ->contains(fn($c) => $c['id_alumno'] === $alumno);

  if (!$esAlumnoDelComponente) {
      abort(404, 'El alumno no está inscrito en este componente.');
  }
  ```
  Esto bloquea cualquier intento de contactar alumnos de otras carreras o cursos ajenos mediante manipulación de parámetros HTTP.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)
  - [`Permissions::COMPONENTES_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L74) (`'componentes:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Componente Visible | Validación Receptor | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET .../mensajeria` | `is_docente` | Scoped a componentes del docente | - | ✅ **CUMPLE** |
| `POST .../broadcast` | `is_docente` | `autorizarComponente` | Scoped a matriculados | ✅ **CUMPLE** |
| `POST .../estudiantes/{id}` | `is_docente` | `autorizarComponente` | Valida estudiante inscrito en componente | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% CUMPLE**. Garantiza privacidad e impide el envío de mensajes cruzados o no autorizados.
