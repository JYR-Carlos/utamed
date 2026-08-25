# Reporte de Auditoría: Mensajería del Ayudante

- **Rutas Auditadas**:
  - `GET /ayudante/cursos/{curso}/mensajeria` (`ayudante.cursos.mensajeria.index`)
  - `POST /ayudante/cursos/{curso}/mensajeria/componentes/{componente}/difusion` (`ayudante.cursos.mensajeria.difusion`)
  - `POST /ayudante/cursos/{curso}/mensajeria/componentes/{componente}/alumnos/{alumno}/mensaje` (`ayudante.cursos.mensajeria.mensaje`)
- **Vista Frontend**:
  - [`resources/js/pages/ayudante/Mensajeria.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/ayudante/Mensajeria.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Ayudante/MensajeriaController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/MensajeriaController.php)
  - [`app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php)
- **Middlewares**: `['auth', 'verified', 'is_ayudante']`

---

## 1. Alcance y Flujo de Navegación

Permite al ayudante de curso participar en la atención de alumnos a través de los canales por componente y emitir avisos o aclaraciones como parte del staff docente de la asignatura.

```mermaid
flowchart TD
    A[Ayudante Autenticado] --> R1["GET /ayudante/cursos/{curso}/mensajeria"]
    R1 --> C1[Ayudante\\MensajeriaController@index]
    C1 --> SVC[MensajeriaService::componentesDeAyudante]
    C1 --> V1[Render ayudante/Mensajeria]

    V1 -->|Mensaje a Alumno| R2["POST .../alumnos/{alumno}/mensaje"]
    R2 --> G1{autorizarComponente}
    G1 --> G2{Alumno Inscrito en Componente?}
    G2 -->|Si| S1[Envia mensaje en canal compartido del componente]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vista**:
  - [`Mensajeria.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/ayudante/Mensajeria.svelte): Bandeja de mensajería del staff adaptada para ayudantes.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/ayudante/cursos/{curso}/mensajeria` | `ayudante.cursos.mensajeria.index` | `['auth', 'verified', 'is_ayudante']` | [`MensajeriaController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/MensajeriaController.php) |
| `POST` | `.../difusion` | `ayudante.cursos.mensajeria.difusion` | `['auth', 'verified', 'is_ayudante']` | [`GestionaMensajeriaStaff@enviarDifusion`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php#L85) |
| `POST` | `.../alumnos/{alumno}/mensaje` | `ayudante.cursos.mensajeria.mensaje` | `['auth', 'verified', 'is_ayudante']` | [`GestionaMensajeriaStaff@enviarMensaje`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Concerns/GestionaMensajeriaStaff.php#L108) |

---

## 4. Fase 3 & 4: Controlador Backend y Trait de Staff

- Utiliza el trait centralizado `GestionaMensajeriaStaff`.
- `componentesDeAyudante((int) Auth::id())` restringe la visibilidad estrictamente a los cursos donde el usuario tiene rol Ayudante activo en `usuario.usuario_rol_asignacion`.
- La mensajería comparte el canal `(componente, alumno)` con el docente titular, evitando la fragmentación de conversaciones.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Permisos involucrados:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Componente Visible | Validación Receptor | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET .../mensajeria` | `is_ayudante` | Scoped a ayudantías activas | - | ✅ **CUMPLE** |
| `POST .../difusion` | `is_ayudante` | `autorizarComponente` | Scoped al componente | ✅ **CUMPLE** |
| `POST .../mensaje` | `is_ayudante` | `autorizarComponente` | Valida estudiante inscrito | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO Y CUMPLE**.
