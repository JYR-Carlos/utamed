# Reporte de Auditoría: Dashboard del Ayudante

- **Ruta Auditada**:
  - `GET /ayudante/dashboard` (`ayudante.dashboard`)
- **Vista Frontend**:
  - [`resources/js/pages/Dashboard.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/Dashboard.svelte) (subcomponente `DashboardAyudante`)
- **Controlador Backend**:
  - [`app/Http/Controllers/Ayudante/DashboardController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/DashboardController.php)
- **Middlewares**: `['auth', 'verified', 'is_ayudante']`

---

## 1. Alcance y Flujo de Navegación

Permite a los ayudantes docentes acceder a su panel de control para revisar métricas institucionales y el resumen de asignaturas asignadas a su ayudantía.

```mermaid
flowchart TD
    A[Usuario Autenticado] --> M[Middleware is_ayudante]
    M -->|Sin rol Ayudante| D1[Redirect /dashboard con error]
    M -->|Rol Ayudante Activo| CTRL[Ayudante\\DashboardController@index]
    CTRL --> Q1[Query UsuarioRolAsignacion en contexto curso]
    CTRL --> V1[Render Dashboard con subcomponente Ayudante]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vista**:
  - [`Dashboard.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/Dashboard.svelte): Detecta la bandera de rol `Ayudante` e inyecta la vista adaptada para ayudantes.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/ayudante/dashboard` | `ayudante.dashboard` | `['auth', 'verified', 'is_ayudante']` | [`DashboardController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/DashboardController.php#L29) |

### Verificación del Middleware `is_ayudante`:
- [`\App\Http\Middleware\IsAyudante`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Middleware/IsAyudante.php) valida que el usuario posea el rol `Ayudante` asignado y activo.

---

## 4. Fase 3 & 4: Controlador Backend y Autorización

### [`Ayudante\DashboardController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Ayudante/DashboardController.php)
- Resuelve los contextos asignados mediante `UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)->where('id_rol', $rolAyudante->id_rol)`.
- No recibe inputs del cliente, evitando manipulación de identificadores (Anti-IDOR).

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Permisos involucrados:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro (Middleware) | Verificación Backend | Protección Anti-IDOR | Estado |
|---|:---:|:---:|:---:|:---:|
| `GET /ayudante/dashboard` | `['auth', 'verified', 'is_ayudante']` | Verificación de rol ayudante activo | Total (100% scoped a `$user`) | ✅ **CUMPLE** |

**Veredicto**: Submódulo **100% SEGURO Y CUMPLE**.
