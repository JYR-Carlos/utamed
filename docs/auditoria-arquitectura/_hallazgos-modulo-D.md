# Módulo D — Admin / Usuarios y Assignment Wizard

**Alcance auditado:** `Admin\UsuarioController` (1147 LOC, 24 métodos), `Admin\AssignmentWizardController`
(532 LOC, 10 métodos), `RoleAssignmentBuilder`, `IsAdmin`, frontend `pages/admin/Usuarios.svelte`.

---

## 🔴 Crítico

### D-1 · Escalada a SuperAdmin: `syncPermissions` esquiva el guard del builder
**Archivos:** `Admin/UsuarioController.php:971-1128` vs `Services/Authorization/RoleAssignmentBuilder.php:207-226`

El sistema tiene **dos caminos** para asignar roles. Uno está protegido y el otro no.

*Camino protegido* — `AssignmentWizardController::assignRole` usa `$usuario->giveRole($rol)` →
`RoleAssignmentBuilder::save()` → `validateActorAuthorization()`:

```php
if ($this->validator->isSuperAdmin($this->actor)) return;
throw new DontHavePermissionException(... 'Solo los administradores pueden asignar roles.');
```

*Camino sin proteger* — `UsuarioController::syncPermissions` escribe directo a las tablas:

```php
$idContexto = app(GlobalContextService::class)->getContextId();   // ← contexto GLOBAL
// …desactiva todas las asignaciones vigentes…
foreach ($validated['roles'] as $rolId) {
    UsuarioRolAsignacion::updateOrCreate(
        ['id_usuario' => $usuario->id_usuario, 'id_contexto' => $idContexto, 'id_rol' => $rolId],
        [...]
    );
}
```

Sin `validateActorAuthorization()`, sin comprobación de SuperAdmin, sin `authorize()`, y sin validar qué
roles son asignables (`'roles' => 'array'`, sin `exists:rol,id_rol`).

**Explotación:** `POST /admin/usuarios/{yo}/sync-permissions` con `roles: [<id_rol_SuperAdmin>]` concede
el rol en el contexto **global**. La misma operación que el builder rechaza.

### D-2 · `IsAdmin` acepta el rol "Administrador" de cualquier contexto
**Archivo:** `app/Http/Middleware/IsAdmin.php:41-43`

```php
$isAdmin = $user->hasRole('SuperAdmin') || $user->hasRole('Administrador') || $user->isSuperAdmin();
```

`Usuario::hasRole()` invoca `getAllRoles(null)` — **sin filtro de contexto** (`Usuario.php:263-280`). Un
usuario con el rol "Administrador" acotado a una sola facultad o carrera pasa el middleware y accede al
CRUD global de usuarios, al wizard y a `sync-permissions`.

Es el amplificador de D-1: convierte un administrador departamental en SuperAdmin global. Y es también
el único control de todo el módulo — **`grep -n "authorize\|Gate::\|policy("` sobre ambos controladores
devuelve 0 resultados**, pese a que existen `UsuarioPolicy`, `UsuarioRolAsignacionPolicy` y
`UsuarioPermisoEspecialPolicy` en `app/Policies/`.

### D-3 · Endpoint dedicado que devuelve el hash de contraseña por RUT
**Archivo:** `Admin/UsuarioController.php:885-897`

```php
public function buscarPorRut(Request $request)
{
    $usuario = Usuario::where('rut', $request->query('rut'))->first();
    return response()->json($usuario);        // modelo completo, sin $hidden (ver A-1)
}
```

Ruta: `GET /admin/usuarios/buscar-por-rut?rut=…`. Consumido en producción por
`resources/js/pages/admin/Usuarios.svelte:201`, así que el hash bcrypt llega al JS del navegador en cada
búsqueda del formulario de alta.

Peor que A-1 porque es **enumerable y dirigido**: el RUT chileno es predecible y validable, de modo que
permite cosechar hashes de usuarios concretos.

### D-4 · `changePassword` sin autorización, sin política de contraseña y sin invalidar sesiones
**Archivo:** `Admin/UsuarioController.php:850-860`

```php
$validated = $request->validate(['password' => 'required|string|min:6|confirmed']);
$usuario->update(['passhash' => Hash::make($validated['password'])]);
```

- Sin `authorize()`: cualquiera que pase `IsAdmin` (ver D-2) cambia la contraseña de **cualquier
  usuario, incluido un SuperAdmin** → toma de control total de la cuenta.
- Sin confirmación de la contraseña del propio administrador.
- `min:6` sin `Password::defaults()` ni comprobación de compromiso. Mismo criterio en las 6 rutas de
  creación (`:360, 400, 436, 555`).
- No se invalidan las sesiones activas del usuario afectado ni se registra la acción en auditoría.

---

## 🟠 Alto

### D-5 · Listados y detalle filtran el hash de todos los usuarios
**Archivo:** `Admin/UsuarioController.php:81, 129, 592-608`

```php
$query = Estudiante::with(['usuario', 'usuario.docente', 'carrera']);   // usuario completo
…
return response()->json($usuario);                                       // show(), modelo completo
```

Cada carga de `/admin/usuarios` entrega 15 modelos `Usuario` íntegros a los props de Inertia — 15 hashes
bcrypt por página. Es la explotación masiva de A-1.

### D-6 · `per_page` sin tope en todos los listados admin
`$request->input('per_page', 15)` sin límite superior en `UsuarioController:125`, `CursoController:67`,
`DepartamentoController:50`, `FacultadController:45`, `PlanController:50`, `InscripcionCursoController:62`.
`?per_page=1000000` fuerza a materializar la tabla completa; combinado con D-5, vuelca la base de
usuarios entera en una petición.

### D-7 · Importación masiva sin límites ni proceso en segundo plano
**Archivo:** `Admin/UsuarioController.php:270-320`

`Excel::toArray(new \stdClass, $request->file('file'))[0]` carga el archivo completo en memoria; el bucle
inserta fila a fila dentro de una única transacción, sin chunking, sin cola y sin límite de filas. Un
`.xlsx` de 5 MB (el máximo permitido) descomprime a cientos de miles de filas → agotamiento de memoria y
transacción de larga duración bloqueando la tabla `usuario`.

Las contraseñas se toman de la columna 7 del Excel (`:533`), en claro dentro del archivo subido.

### D-8 · Mensajes de excepción interna devueltos al cliente
- `UsuarioController:317` → `'Error al procesar el archivo: ' . $e->getMessage()`
- `AssignmentWizardController:~327` → `'Error de base de datos al asignar rol: ' . $e->getMessage()`
- `AssignmentWizardController:~340` → `'Error al asignar rol: ' . $e->getMessage()`

Con `APP_DEBUG=true` en `.env.example` (ver A-13), esto expone SQL, nombres de tabla y rutas del
servidor en la UI.

### D-9 · `syncPermissions` revienta si falta un campo opcional
**Archivo:** `Admin/UsuarioController.php:977-990`

`'special_permissions' => 'array'` no es `required`, pero la línea 985 hace
`array_filter($validated['special_permissions'], …)` sin comprobar existencia → `TypeError` en PHP 8 y
respuesta 500 si el cliente omite la clave.

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| D-10 | **Auditoría falsificable.** `$adminId = Auth::id() ?? 1; // Fallback only for dev/seeder` — si la sesión es nula, la acción se atribuye al usuario 1. | `UsuarioController:998` |
| D-11 | **Payload completo al log** en cada sincronización: `Log::info("Payload: " . print_r($request->all(), true))` y dos `Log::debug` más con datos de permisos. | `UsuarioController:975-976, 1058, 1087` |
| D-12 | `destroy()` captura toda excepción y reporta siempre "tiene registros asociados", enmascarando la causa real. Sin protección contra auto-borrado ni contra eliminar el último SuperAdmin. | `UsuarioController:797-839` |
| D-13 | Código muerto en `destroy()`: ternario con ramas idénticas (`:819`) y doble `delete()` sobre el mismo registro (`:809` y `:823`). | `UsuarioController:809-823` |
| D-14 | `toggleActive` sin autorización específica: permite desactivar a un SuperAdmin y provocar bloqueo administrativo. | `UsuarioController:870-880` |
| D-15 | `update()`/`destroy()` reciben `$id` polimórfico desde `$request->input('tipo')` sin route-model binding ni validación de `tipo`; un `tipo` desconocido cae siempre en la rama `administrador`. | `UsuarioController:624-647, 797-800` |

---

## ✅ Verificado correcto

- **`RoleAssignmentBuilder::validateActorAuthorization()` es el control correcto y está bien
  implementado** (`:207-226`): exige SuperAdmin real y lanza `DontHavePermissionException`. El problema
  de D-1 no es su diseño, es que `syncPermissions` no pasa por él.
- **Ordenamiento con whitelist real** en `index()`: `$estudianteSortWhitelist` / `$docenteSortWhitelist`
  mapean claves del frontend a columnas SQL, y `sort_dir` se normaliza con ternario
  (`:76`). No hay inyección SQL por parámetros de orden — a diferencia del patrón habitual en este tipo
  de listados.
- `revokeRole` y `revokePermission` (`AssignmentWizardController:488-531`) **sí** verifican que la
  asignación pertenezca al usuario indicado antes de invalidarla, y delegan en
  `invalidateRole`/`invalidatePermission`, que propagan `DontHavePermissionException`.
- La restricción de solapamiento de roles se apoya en una constraint de PostgreSQL
  (`uq_no_solapar_roles`, SQLSTATE 23P01) y el controlador la traduce a un 409 con mensaje útil
  (`AssignmentWizardController:~312`) — validación en la capa correcta.
- `import()` es transaccional con rollback y valida fila a fila reportando el número de fila del Excel.
- `formatRut()` (`:322-346`) valida el formato antes de normalizar.
- Frontend admin: 0 `$:` legacy en las 16 páginas.

---

## 🔁 Patrones transversales confirmados

- **Dos puertas para la misma operación, una endurecida y otra no** (D-1). Mismo patrón que B-1/B-2:
  el control existe, pero no cubre todas las rutas de entrada.
- **Ausencia total de Policies pese a existir en el proyecto**: 22 policies en `app/Policies/`, cero
  invocaciones en los dos controladores más sensibles del sistema.
