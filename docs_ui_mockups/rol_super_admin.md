# Super Admin

> **Nombres en BD:** `Super Admin`, `SuperAdmin` (`Usuario::ROLES_ADMINISTRATIVOS`)
> **Puerta de entrada:** permiso wildcard global (`Permissions::GLOBAL_WILDCARD`)
> **Middleware:** `is_admin` (`app/Http/Middleware/IsAdmin.php`)
> **Usuario semilla:** `system_admin` / `admin@utamed.local` (`RoleAndPermissionSeeder.php:41`)

---

## Descripción y Objetivo del Rol

Rol de **operación del sistema**, no de gestión académica. Su seña de identidad
no es una lista de permisos sino un **cortocircuito**: `PermissionValidator::isSuperAdmin()`
devuelve `true` ante cualquier comprobación y todas las policies salen antes de
evaluar contexto (`ProgramaPolicy::puedeResolverPrograma()` línea 1;
`CursoPolicy::manageTeam()`; `Usuario::can()`).

Consecuencia de diseño para la UI: **el Super Admin ve exactamente las mismas
pantallas que el Administrador**, pero sin ninguna casilla deshabilitada y sin
ningún recorte por contexto. Es el único rol que puede resolver un syllabus de
*cualquier* carrera, gestionar el equipo de *cualquier* curso y asignar roles
que ningún otro puede delegar.

**Objetivo funcional:** dejar el sistema en marcha (estructura académica base,
primer administrador, permisos raíz) y desbloquear situaciones que la
autorización contextual bloquea legítimamente para todos los demás.

---

## Flujos de Usuario (User Flows)

### F1. Acceso e ingreso al panel

```
Login (RUT + contraseña)
  → GET /dashboard  [auth, verified]
  → hasAnyRole(['Docente Titular','Docente Titular Restringido','Docente Componente'])? NO
  → hasRole('Estudiante')? NO  → hasRole('Ayudante')? NO
  → hasRole('Administrador') || hasRole('SuperAdmin') → render Dashboard.svelte
```
`routes/web.php:51-102`.
⚠️ **Bug de routing a documentar:** el dashboard prioriza Docente > Estudiante >
Ayudante > Admin. Un Super Admin que además sea docente **nunca** ve el
dashboard administrativo por defecto; debe conmutar con las pestañas de rol del
sidebar. El mockup debe mostrar ese conmutador siempre visible para este rol.

### F2. Concesión de un rol o permiso no delegable (flujo exclusivo)

```
/admin/usuarios → fila de usuario → botón "Permisos" (icono escudo)
  → PermissionsModal
     Paso 1: elegir tipo de contexto        GET /admin/assignment/context-types
     Paso 2: elegir objeto de ese contexto  GET /admin/assignment/context-types/{type}/objects
     Paso 3a: elegir Rol                    GET /admin/assignment/roles
              (ver permisos que trae)       GET /admin/assignment/roles/{roleId}/detail
     Paso 3b: o elegir Permiso individual   GET /admin/assignment/permissions
     Paso 4: fechas de vigencia + confirmar
  → POST /admin/usuarios/{usuario}/assign-role   |  .../assign-permission
  → 200 JSON {success} → refresco de la matriz de permisos del usuario
```
`AssignmentWizardController`. **Sólo aquí** se pueden conceder roles fuera de la
lista delegable (`Rol::whereIn('nombre', ['ayudante','estudiante'])` es el techo
de todos los demás roles: `CourseTeamController.php:345`).
Un intento de escalada por un no-superadmin queda registrado como
`CONCESION_ROL_NO_DELEGABLE` (`Admin/UsuarioController.php:1502`).

### F3. Revocación

```
/admin/usuarios → Permisos → pestaña "Asignaciones activas"
  → DELETE /admin/usuarios/{usuario}/roles/{ura}
  → DELETE /admin/usuarios/{usuario}/permissions/{upe}
  → confirmación destructiva → toast de éxito
```

### F4. Desbloqueo de syllabus fuera de carrera

```
/admin/syllabus  (o /admin/programas)
  → fila de curso de CUALQUIER carrera → "Revisar"
  → GET /admin/cursos/{curso}/programa/revisar
  → PUT .../aprobar   |   PUT .../rechazar (con razón obligatoria)
```
Para Jefe de Carrera esta acción está limitada a su carrera
(`ProgramaPolicy::puedeResolverPrograma`); el Super Admin no tiene ese filtro.

---

## Vistas Exclusivas del Rol

> El Super Admin **no tiene pantallas propias**: comparte todas las de
> `rol_administrador.md`. Lo exclusivo son **estados de UI** dentro de ellas.

### V1. Wizard de Asignación RBAC — modo sin restricciones
**Ruta:** modal `PermissionsModal.svelte` desde `/admin/usuarios`

- **Objetivo:** conceder cualquier rol o permiso en cualquier contexto del árbol.
- **Datos consumidos:** `Rol` (catálogo completo, sin filtro de delegables),
  `Permiso` (los ~90 slugs de `Permissions.php`, agrupados por módulo),
  `Contexto` + `TipoContexto` (categorías `global`, `facultad`, `departamento`,
  `carrera`, `curso`, `componente`, `actividad`), `UsuarioRolAsignacion`,
  `UsuarioPermisoEspecial`.
- **Componentes UI clave inferidos:**
  - Wizard de 4 pasos con barra de progreso.
  - Selector en cascada tipo-de-contexto → objeto (combo con búsqueda).
  - Panel de dos columnas: catálogo de permisos agrupado por familia
    (accordion `Actividades`, `Cursos`, `Programas`, `Usuarios`…) con checkboxes.
  - Vista previa "este rol otorga:" al seleccionar un rol (`getRoleDetail`).
  - `DatePickerCL` doble para `fecha_inicio_planificada` / `fecha_fin_planificada`.
  - **Distintivo Super Admin:** ausencia del banner "sólo puedes delegar
    Ayudante/Estudiante" que sí ven docentes y administradores acotados.

### V2. Indicador de identidad wildcard
- **Objetivo:** que el operador sepa que está actuando sin red de seguridad.
- **Datos consumidos:** prop global `auth.is_super_admin` (booleano, disponible
  en TODA página vía `HandleInertiaRequests::share`).
- **Componentes UI clave:** badge permanente en `NavUser` / cabecera del sidebar
  (p. ej. pill roja `SUPER ADMIN`). Hoy el código expone el flag pero **no lo
  pinta** → ⚠️ oportunidad de diseño, no funcionalidad existente.

---

## Vistas Compartidas (Modificadas)

| Vista | Compartida con | Qué añade el Super Admin |
|---|---|---|
| `/admin/*` (las 9 secciones) | Administrador | Nada visualmente; todos los botones activos por definición |
| `/admin/syllabus`, `/admin/programas` | Administrador, Jefe de Carrera | Aprobar/Rechazar **sin filtro de carrera** |
| `/admin/cursos/{curso}/team` | Administrador, Docente Titular | Puede añadir miembros con roles fuera de `[ayudante, estudiante]` |
| `/admin/usuarios` | Administrador | Modal de permisos con catálogo completo; puede tocar a otros administradores |
| Delegación de permisos de curso | Docente Titular | Sin la guarda IDOR "el objetivo debe ser miembro del curso y no el DT" (`DelegacionPermisosController::assertIsMiembroCurso`) |

Ver detalle de cada pantalla en `rol_administrador.md` y `vistas_compartidas.md`.

---

## Interacciones y Estados

| Elemento | Comportamiento en código |
|---|---|
| Confirmación de revocación de rol | `ConfirmationModal.svelte`; devuelve `{success:true,message:'Rol revocado correctamente.'}` |
| Error de contexto inválido | `400 {error:'Tipo de contexto no válido'}` (`AssignmentWizardController:151,311`) |
| Asignación fuera del propio usuario | `403 {success:false,message:'La asignación no pertenece a este usuario.'}` |
| Vigencia de asignación | Toda `UsuarioRolAsignacion` lleva `fecha_inicio_planificada`/`fecha_fin_planificada`; el seeder usa +100 años para el rol raíz → la UI debe ofrecer un atajo "sin caducidad" |
| Auditoría | Los intentos denegados escriben en el canal `seguridad` con IP y user-agent (`CursoPolicy::manageTeam`). Un panel de auditoría **no existe** todavía → ⚠️ HUECO |
| Feedback general | `flash.success` / `flash.error` como banner superior de `AdminLayout` |
