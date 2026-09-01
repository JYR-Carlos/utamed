# Reporte de Auditoría: Cursos y Detalle para Ayudantes

- **Rutas Auditadas**:
  - `GET /ayudante/cursos` (`ayudante.cursos.index`)
  - `GET /ayudante/cursos/{curso}` (`ayudante.cursos.show`)
- **Vistas Frontend**:
  - [`resources/js/pages/ayudante/Courses/Index.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/ayudante/Courses/Index.svelte)
  - [`resources/js/pages/ayudante/Courses/Show.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/ayudante/Courses/Show.svelte)
- **Controlador Backend**:
  - [`app/Http/Controllers/Ayudante/CourseController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/CourseController.php)
- **Middlewares**: `['auth', 'verified', 'is_ayudante']`

---

## 1. Alcance y Flujo de Navegación

Permite a los ayudantes de cátedra y laboratorio consultar la lista de cursos en los que han sido asignados por el docente titular, ingresar al detalle del curso y verificar los permisos contextuales delegados sobre el curso.

```mermaid
flowchart TD
    A[Ayudante Autenticado] --> R1["GET /ayudante/cursos"]
    R1 --> C1[Ayudante\\CourseController@index]
    C1 --> Q1[Query UsuarioRolAsignacion id_rol = Ayudante]
    C1 --> V1[Render ayudante/Courses/Index]

    V1 -->|Click en Curso| R2["GET /ayudante/cursos/{curso}"]
    R2 --> G1{Guard: UsuarioRolAsignacion activa en contexto curso}
    G1 -->|No es Ayudante de este Curso| E1[403 No tienes permiso para ver este curso]
    G1 -->|Asignacion Valida| C2[Ayudante\\CourseController@show]
    C2 --> Q2[Resuelve userPermissions contextuales]
    C2 --> V2[Render ayudante/Courses/Show]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas**:
  - [`Index.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/ayudante/Courses/Index.svelte): Muestra tarjetas de asignaturas con permisos asignados.
  - [`Show.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/ayudante/Courses/Show.svelte): Panel de navegación condicionado por el arreglo `userPermissions`.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/ayudante/cursos` | `ayudante.cursos.index` | `['auth', 'verified', 'is_ayudante']` | [`CourseController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/CourseController.php#L29) |
| `GET` | `/ayudante/cursos/{curso}` | `ayudante.cursos.show` | `['auth', 'verified', 'is_ayudante']` | [`CourseController@show`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/CourseController.php#L88) |

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### 4.1. Verificación Contextual de Rol (Anti-IDOR)
En `show(int $id)`:
```php
$esAyudante = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
    ->where('id_contexto', $curso->id_contexto)
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->whereHas('rol', fn($q) => $q->whereRaw('LOWER(nombre) = ?', ['ayudante']))
    ->exists();

if (!$esAyudante) {
    abort(403, 'No tienes permiso para ver este curso');
}
```
Garantiza que un ayudante de "Química" no pueda acceder a los cursos de "Anatomía" aún teniendo el rol global de Ayudante.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Permisos involucrados:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro | Guard Anti-IDOR (Contexto) | Inyección de Permisos | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET /ayudante/cursos` | `is_ayudante` | Scoped a `id_contexto` asignados | Permisos por curso | ✅ **CUMPLE** |
| `GET /ayudante/cursos/{c}` | `is_ayudante` | Valida asignación activa en el curso | Inyecta `userPermissions` | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO Y CUMPLE**.
