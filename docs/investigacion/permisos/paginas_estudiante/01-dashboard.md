# Reporte de Auditoría: Dashboard del Estudiante

- **Ruta Auditada**:
  - `GET /estudiante/dashboard` (`estudiante.dashboard`)
- **Vista Frontend**:
  - [`resources/js/pages/student/Dashboard.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Dashboard.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Student/DashboardController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/DashboardController.php)
- **Servicios Asociados**:
  - [`app/Services/MensajeriaService.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/MensajeriaService.php)
- **Middlewares**: `['auth', 'verified', 'is_estudiante']`

---

## 1. Alcance y Flujo de Navegación

Punto de entrada principal para alumnos matriculados en UTAMED. Muestra el resumen de asignaturas inscritas en el período académico activo, identificación del profesor titular de su sección y notificaciones de mensajes no leídos por curso.

```mermaid
flowchart TD
    A[Usuario Autenticado] --> M[Middleware is_estudiante]
    M -->|Sin rol Estudiante| D1[Redirect /dashboard con error]
    M -->|Estudiante Activo| CTRL[Student\\DashboardController@index]
    CTRL --> Q1[Query InscripcionCurso activa por id_estudiante]
    CTRL --> Q2[Query Mensajes no leidos via MensajeriaService]
    CTRL --> V1[Render student/Dashboard]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vista**:
  - [`Dashboard.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/student/Dashboard.svelte)
- **Props recibidas**:
  - `estudiante`: `{ id_estudiante, rut, id_usuario, nombre_carrera }`
  - `cursos`: `Array<{ id_curso, nombre, cod_curso, asignatura_nombre, carrera_nombre, fecha_inicio, fecha_fin, profesor }>`
  - `stats`: `{ total_cursos, nombre_completo }`
  - `mensajeria`: `{ no_leidos, cursos: [...] }`
  - `isAyudante`: `boolean` (indica si posee rol dual Estudiante/Ayudante)
  - `semestreActual`: `1 | 2`
- **Renderizado condicional**:
  - Tarjetas de cursos con acceso directo a `/estudiante/cursos/{curso.id_curso}`.
  - Alerta de rol ayudante si aplica.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/estudiante/dashboard` | `estudiante.dashboard` | `['auth', 'verified', 'is_estudiante']` | [`DashboardController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/DashboardController.php#L22) |

### Verificación del Middleware `is_estudiante`:
- [`\App\Http\Middleware\IsStudent`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Middleware/IsStudent.php) valida que el usuario posea el rol `Estudiante` y cuente con perfil asociado.

---

## 4. Fase 3: Controlador Backend y Autorización

### [`Student\DashboardController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Student/DashboardController.php)
- **Defensa en profundidad**:
  ```php
  if (!$user->estudiante) {
      return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
  }
  ```
- **Aislamiento Estricto de Datos (Anti-IDOR)**:
  - Las asignaturas se obtienen filtrando exclusivamente por la matrícula activa del alumno:
    ```php
    InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
        ->where('estado_inscripcion', 'INSCRITO')
    ```
  - La mensajería no leída se calcula filtrando los componentes de secciones donde el alumno está matriculado (`$mensajeria->componentesDeEstudiante($idUsuario)`).
  - No recibe parámetros de consulta externos, eliminando cualquier vector de manipulación de identificadores.

---

## 5. Fase 4: Policies y RelBAC

- Vista transversal de solo lectura; la autorización se basa en el perímetro del usuario autenticado y su matrícula institucional.

---

## 6. Fase 5: Mapeo al Catálogo de Permisos

- Permisos involucrados:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)

---

## 7. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro (Middleware) | Verificación Backend | Protección Anti-IDOR | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET /estudiante/dashboard` | `['auth', 'verified', 'is_estudiante']` | Verificación de perfil estudiante + query scoped | Total (100% scoped a `$user`) | ✅ **CUMPLE** |

**Veredicto**: Módulo **100% SEGURO Y CUMPLE**.
