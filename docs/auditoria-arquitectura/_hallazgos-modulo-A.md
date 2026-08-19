# Módulo A — Núcleo transversal

**Alcance auditado:** `HandleInertiaRequests`, los 6 middlewares de rol, `PermissionValidator` (649 LOC),
`ContextResolver`, `GlobalContextService`, `UserCoursesService`, `Usuario`/`BaseUsuario`,
`FortifyServiceProvider`, `AppServiceProvider`, `usePermissions`, `useFilteredList`, layouts,
`config/session.php`, `config/inertia.php`.

---

## 🔴 Crítico

### A-1 · Fuga del hash de contraseña y del token "recordarme" en cada respuesta Inertia
**Archivos:** `app/Http/Middleware/HandleInertiaRequests.php:214-217`, `app/Models/Base/Usuario/BaseUsuario.php:30-42`

```php
'auth' => [
    'user'       => $user,        // modelo Eloquent completo
    'docente'    => $docente,
    'estudiante' => $estudiante,
```

`$fillable` incluye `passhash` y `token_recuerdame_sesion`, y **no existe `$hidden` en ningún modelo**
(`grep -rn '\$hidden' app/Models/` → 0 resultados). `toArray()` serializa todas las columnas: el hash
bcrypt y el remember-token viajan en el atributo `data-page` del HTML inicial y en cada JSON de
navegación. Quedan en el DOM, en el historial y en cualquier proxy intermedio.

El tipo TS `resources/js/types/index.d.ts:79-93` declara solo campos benignos, lo que oculta el problema
desde el frontend.

**Fix:** `protected $hidden = ['passhash', 'token_recuerdame_sesion'];` en `Usuario`; a medio plazo,
sustituir por `UsuarioDataResource` (ya existe en `app/Http/Resources/`).

### A-2 · Bug de autorización: el GRANT/DENY efectivo se elige por orden de filas de la BD
**Archivo:** `app/Services/Authorization/PermissionValidator.php:511-533`

```php
foreach ($results as $result) {              // ①
    $transformedSlugs[] = $castedPermission;
}
foreach ($transformedSlugs as $slug) {       // ②
    $priority = WildcardMatcher::getPriority($permission, $slug);
    if ($priority < $bestPriority) {
        $bestPriority = $priority;
        $bestResult = $result;               // ← $result es residuo del bucle ①
    }
}
```

`$bestResult` recibe siempre la última fila devuelta por PostgreSQL, sin relación con el `$slug` cuya
prioridad se evalúa. La línea 540 decide el permiso a partir de esa fila arbitraria.

**Escenario de fallo:** usuario con `cursos:*` (GRANT) y `cursos/notas:*` (DENY) en el mismo contexto.
La intención documentada (`:476-478`) es que gane el DENY por especificidad; el código deja que gane la
fila que devuelva el motor sin `ORDER BY`.

### A-3 · `getContextsFromPermission()` lanza TypeError siempre que se invoca
**Archivo:** `app/Services/Authorization/PermissionValidator.php:154`

```php
$allGranted = array_unique(...$grantedContextsUPE, ...$grantedContextsURA);
```

`array_unique(array $array, int $flags)`. El spread de dos arrays de enteros invoca
`array_unique(1, 5, 7, …)` → `TypeError: Argument #1 must be of type array, int given`. Con ambos
arrays vacíos → `ArgumentCountError`. Expuesto en `Usuario::getContextsFromPermission()`
(`app/Models/Usuario/Usuario.php:188`), documentado como la vía para acotar listados con `whereContext`.

**Fix:** `array_unique(array_merge($grantedContextsUPE, $grantedContextsURA))`.

---

## 🟠 Alto

### A-4 · Motor de permisos sin caché: ~13 queries por comprobación
**Archivo:** `PermissionValidator.php` (TODOs en `:42-43, 142, 157, 486, 503, 536, 542, 560, 573`)

Cada `validate()`: 1 query `isSuperAdmin` (`:274`) + 1 llamada a `fn_obtener_ids_contexto_ancestros` por
contexto (`ContextResolver.php:201`, sin caché) + 1 query UPE (`:494`) y 1 URA (`:565`) por cada contexto
de la cadena. Un permiso sobre Curso expande a curso→carrera→plan→global ≈ 13 queries.

### A-5 · El `share()` global consulta la BD en cada navegación
**Archivo:** `app/Services/UserCoursesService.php`

```php
'tiene_programa' => Programa::where('id_curso', $curso->id_curso)->exists(),           // N+1
'permisos'       => $this->getPermissionsForContext($docente->usuario, $curso->id_contexto), // N+1
```

Docente con 8 cursos = 16 queries solo para pintar el sidebar, en cada GET. `getEstudianteCourses()`
repite el patrón y trae modelos completos sin `select()`. `getAyudanteCourses()` resolvió el N+1 de
permisos vía `allPermsGrouped` pero conserva el de `Programa::exists()`. Ninguno usa
`Inertia::optional()`, así que viajan íntegros incluso en partial reloads que no los piden.

### A-6 · `hasRole()` ignora el eager loading y consulta en cada llamada
**Archivo:** `app/Models/Usuario/Usuario.php:263`

`getAllRoles()` usa `$this->rolesAsignados()` (query builder), no la relación cargada por
`HandleInertiaRequests.php:164`. La ruta `/dashboard` (`routes/web.php:34-49`) encadena `hasAnyRole` +
`hasRole`×4 → 5 queries; los middlewares `IsDocente`/`IsAdmin` suman 1-3 más.

### A-7 · `auth.permissions` es global, no contextual
**Archivo:** `HandleInertiaRequests.php:180-184` (con el comentario del equipo `// FIX: esto esta mal debe ser contextual`)

Aplana los permisos de todos los roles en todos los contextos. `can('cursos:editar')` devuelve `true` en
la ficha de un curso donde el usuario no tiene ese permiso, si lo tiene en cualquier otro.

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| A-8 | **`usePermissions()` no es reactivo.** `get(pageStore)` se evalúa una vez al montar; el JSDoc promete reactividad. Con `preserveState: true` (que usa todo listado vía `useFilteredList.ts:57`) los permisos quedan congelados. Debe ser `$derived`. | `lib/composables/usePermissions.ts:48-66` |
| A-9 | **PII en logs.** RUT en claro + IP en cada intento de login, más `username` y `passhash_length`. Canal `single`, sin rotación. RUT es dato personal bajo Ley 19.628. | `FortifyServiceProvider.php:57-61, 76-80, 91-94` |
| A-10 | **SSR activo pero nunca construido.** `'enabled' => true` hardcoded sin `env()`; `bootstrap/ssr/` no existe → cada request intenta POST a `127.0.0.1:13714` y cae al fallback. Si se activara, `window.location.origin` en `useFilteredList.ts:23` rompería el render (`useAppearance.svelte.ts:6` sí protege con `typeof window`). | `config/inertia.php:22-27` |
| A-11 | **Conexión a BD forzada en cada request.** `DB::connection()->getPdo()->exec("SET search_path TO {$searchPath}")` anula la conexión perezosa e interpola config en SQL crudo. El driver pgsql soporta `search_path` en `config/database.php`. | `AppServiceProvider.php:44-45` |
| A-12 | **Endpoint que devuelve un modelo crudo.** `fn() => TipoComponente::all()`. | `routes/web.php:103` |
| A-13 | **Cookies de sesión sin `Secure`.** `'secure' => env('SESSION_SECURE_COOKIE')` → null → false; ni `.env` ni `.env.example` la definen. `.env.example` se distribuye con `APP_DEBUG=true`. | `config/session.php:172`, `.env.example:2-4` |

---

## ✅ Verificado correcto

- **CSRF intacto.** Cero `validateCsrfTokens(except:)` y cero `$except` en el proyecto; todas las
  mutaciones pasan por el grupo `web`. Inertia adjunta `X-XSRF-TOKEN` automáticamente.
- **Superficie XSS casi nula.** Un único `{@html}` en 465 archivos Svelte
  (`components/custom/auth/TwoFactorSetupModal.svelte:136`), con el SVG del QR generado por Fortify en
  servidor — no es entrada de usuario.
- **Rate limiting** de login (5/min en prod, por rut+ip) y de 2FA correctamente configurados
  (`FortifyServiceProvider.php:150-161`).
- `HandleAppearance.php:31` valida la cookie contra allowlist antes de `View::share()`.
- `HandleInertiaRequests::handle()` (`:112-118`) fuerza `no-store` en rutas de invitado — mitigación
  correcta del bfcache tras logout.
- **0 `console.log`** en 757 archivos frontend; solo 2 archivos usan `svelte/store` (el resto se apoya en
  props de Inertia + runes, que es el patrón correcto).
- `CursoPolicy::manageTeam` registra intentos de acceso no autorizado en el canal `seguridad`.
