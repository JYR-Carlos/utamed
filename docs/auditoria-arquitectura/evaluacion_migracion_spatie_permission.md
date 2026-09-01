# Evaluación: reemplazar RelBAC por `spatie/laravel-permission`

> Método: documentación oficial de `spatie/laravel-permission` v7 (vía Context7, `/websites/spatie_be_laravel-permission_v7`
> — Teams, sync*, caché, wildcard permissions) contrastada contra el código real de RelBAC ya auditado en
> [`auditoria_relbac_autorizacion.md`](./auditoria_relbac_autorizacion.md) y contra el comportamiento verificado de
> `Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithPivotTable::sync()` en el `vendor/` del proyecto
> (Laravel 12). No se instaló el paquete ni se hizo una prueba de concepto ejecutable — esto es un análisis de
> viabilidad sobre el papel, con las citas de origen indicadas en cada sección para que se pueda verificar.

## Veredicto ejecutivo

**No se recomienda reemplazar RelBAC por `spatie/laravel-permission` como motor único.** La razón no es que el
paquete sea malo — es sólido y resuelve de raíz un problema real (ver §2) — sino que **el 70% de lo que hace
complejo a RelBAC no es "cómo se guardan roles y permisos" (lo que Spatie reemplaza), sino tres reglas de dominio
que Spatie no modela en absoluto**: herencia automática por árbol de contextos, DENY explícito que gana sobre
GRANT heredado, y delegación acotada por `puede_delegar` en un contexto exacto. Las tres seguirían siendo código
100% propio encima de Spatie — es decir, la migración no elimina esa complejidad, la reubica, y de paso introduce
una pieza de estado global mutable (`setPermissionsTeamId()`) que RelBAC hoy no tiene.

| Se resuelve de raíz migrando | Sigue siendo trabajo propio, migre o no |
|---|---|
| El anti-patrón `__destruct()` de los builders (H-1/H-3 del informe previo) — Spatie no tiene builders fluidos con auto-guardado | Herencia jerárquica con expansión de ancestros (`ContextResolver`, `fn_obtener_ids_contexto_ancestros`) |
| Invalidación manual de caché en escrituras masivas (P-2/P-3) — el caché de Spatie se resetea solo con los métodos *built-in* | DENY explícito que revoca un GRANT heredado (semántica de `UsuarioPermisoEspecial.esta_permitido=false`) |
| Falta de índices en el storage plano (parcialmente — ver §2) | Delegación acotada por `puede_delegar` y contexto exacto (`validateActorAuthorization`) |
| — | Vigencia temporal de asignaciones (`->for($dias)`, `->waitFor($dias)`) — Spatie no expira roles solo |
| — | Trazabilidad de quién asignó/revocó cada permiso (`creado_por`, regla "sólo el asignador original revoca") |

---

## 1. Mapeo de datos y contextos: ¿alcanza "Teams"?

Con `'teams' => true` en `config/permission.php`, Spatie agrega una columna `team_id` a `roles`,
`model_has_roles` y `model_has_permissions`. Es **una partición plana de un solo nivel**, no un árbol:

```php
// Rol global (aplica a cualquier team, único en el sistema)
Role::create(['name' => 'writer', 'team_id' => null]);

// Rol específico de un team — puede repetirse el mismo nombre en otro team
Role::create(['name' => 'reader', 'team_id' => 1]);
```
*(fuente: `spatie.be/docs/laravel-permission/v7/basic-usage/teams-permissions`)*

Para consultar contra un team distinto al activo hay que **cambiar un estado global mutable** y descartar
relaciones cacheadas en el modelo:

```php
setPermissionsTeamId($new_team_id);
$user->unsetRelation('roles')->unsetRelation('permissions');
$user->hasPermissionTo('foo');
```

No existe ningún concepto de "team padre" ni de "este permiso en el team 7 también aplica a los teams 12, 13 y
14 que cuelgan de él" en el paquete. Comparado con RelBAC, donde un permiso otorgado en el contexto de una
Facultad se propaga automáticamente a todas sus Carreras y Cursos vía `fn_obtener_ids_contexto_ancestros()`, Teams
no ofrece nada equivalente. Para lograr el mismo efecto hay exactamente dos caminos, y ninguno es gratis:

**Opción A — materializar la herencia en escritura.** Al otorgar un permiso "a nivel Facultad", crear una fila
`model_has_permissions` por cada Carrera/Curso descendiente en ese momento (igual que `onAllCurrentInstances()`
hoy, pero sin el `_valid_context`/expansión dinámica). Problema: si se crea una Carrera **nueva** después de
otorgado el permiso, esa carrera no hereda nada — hay que volver a correr el materializador cada vez que cambia el
árbol académico. RelBAC hoy resuelve esto en tiempo de **lectura** (la CTE de ancestros), no de escritura; esa
propiedad se pierde.

**Opción B — loop de `setPermissionsTeamId()` por cada contexto ancestro.** Reutilizar `ContextResolver` para
obtener la cadena de ancestros (exactamente como hoy) y, por cada uno, cambiar el team activo y preguntar a
Spatie. Esto preserva la semántica de herencia, pero: (a) reintroduce casi toda la lógica que hoy vive en
`PermissionValidator::resolveTargetContexts()`/`validateInAnyContext()`, sólo que llamando a Spatie N veces en vez
de a `vw_permisos_usuario` N veces — no hay reducción neta de complejidad; y (b) **anula el beneficio de caché**
que se gana en §2, porque el caché de Spatie no sabe nada de cadenas de contexto — cada nivel de la jerarquía es
una clave de caché distinta, y aun así hay que resolver la cadena de ancestros en cada validación (con o sin
caché propio de `ContextResolver`, que seguiría haciendo falta).

**Riesgo adicional no presente en RelBAC hoy:** `setPermissionsTeamId()` es estado **global mutable** del proceso
PHP, no un parámetro explícito por llamada. En workers de vida larga (colas, Octane) que no reseteen ese valor al
final de cada job/request, una petición puede terminar evaluando permisos con el `team_id` que dejó la petición
anterior. RelBAC nunca tiene este riesgo porque el contexto siempre se pasa explícito (`$contextId` como
parámetro de `validate()`), nunca como variable de paquete.

**Conclusión de esta sección:** Teams sirve bien para multi-tenancy plano (SaaS con "cuentas" independientes sin
jerarquía entre ellas). No es la herramienta para un árbol de 5 niveles con herencia automática — usarlo para eso
es forzar la pieza equivocada.

---

## 2. Resolución de vulnerabilidades: ¿qué arregla `syncRoles`/`syncPermissions` automáticamente?

### Caché — sí, de raíz

> "Role and permission data are cached to improve application performance. When using built-in methods to
> manipulate roles and permissions, **the cache is automatically reset and relations are reloaded**. If data is
> modified directly in the database, the cache must be manually cleared."
> *(`spatie.be/docs/laravel-permission/v7/advanced-usage/cache`)*

Esto cierra de raíz el patrón que hoy exige acordarse de llamar `PermissionCache::olvidarUsuario()` a mano después
de cada `where()->update()` masivo (la nota explícita en `syncPermissions()` línea 1414-1417 del código actual).
También evita el problema de P-3 del informe previo (filas de caché huérfanas por generación): el caché de Spatie
es una sola entrada global de "todos los `role_has_permissions`" (TTL configurable, 24h por defecto), no N
entradas por usuario×permiso×contexto — no hay nada que podar.

### Atomicidad — parcialmente, y no por lo que el enunciado asume

Verificado en `vendor/laravel/framework/.../InteractsWithPivotTable.php:98` (Laravel 12, el mismo que usa este
proyecto): `sync()` **no** envuelve sus operaciones en un `DB::transaction()` explícito. Lo que sí hace, y por lo
que en la práctica sí evita el problema de H-1 del informe previo, es agrupar todos los `attach` pendientes en
**una sola sentencia `INSERT`** (`formatAttachRecords()` + un único `insert()`) y todos los `detach` en **una
sola sentencia `DELETE`** — en vez del `foreach` de N llamadas a `::create()` independientes que hacen
`RoleAssignmentBuilder::save()`/`PermissionAssignmentBuilder::save()` hoy. Una única sentencia SQL es atómica por
definición del motor de base de datos, sin necesidad de una transacción explícita alrededor. Es una diferencia de
diseño real y verificable, no una transacción "mágica" — vale la pena decirlo así de preciso en vez de repetir la
premisa de que Spatie "envuelve todo en transacciones".

**Lo que esto NO cubre:** `sync()` opera sobre una sola relación (roles, o permisos directos, no ambas a la vez).
Si la operación de negocio es "reemplazar roles Y permisos especiales como una sola unidad" — que es exactamente
lo que hace `syncPermissions()` hoy — seguiría haciendo falta un `DB::transaction()` propio alrededor de las dos
llamadas a `sync()`, igual que hoy. Migrar a Spatie resuelve el bug concreto de H-1 (el loop no atómico dentro de
una sola tabla), pero no vuelve innecesaria la disciplina transaccional a nivel de caso de uso.

### Índices — sin cambio de fondo

El *schema* estándar de Spatie (`model_has_roles`, `model_has_permissions`) sí trae índices compuestos
`(role_id/permission_id, model_id, model_type)` de fábrica en su migración de publicación — sería una mejora
concreta respecto al P-1 actual (URA/UPE sin índice en `(id_usuario, id_contexto)`) **si se usa el schema
estándar tal cual**. Pero en cuanto se agrega la columna `team_id` para intentar resolver §1, hay que decidir el
índice compuesto igual que hoy — el paquete no adivina qué combinación de columnas se va a filtrar en cada
`WHERE`.

---

## 3. Reglas de negocio de delegación: dónde vive la lógica que Spatie no tiene

Spatie no tiene ninguna columna ni concepto equivalente a `puede_delegar`, ni a un DENY explícito que revoque un
permiso heredado de un rol. Su modelo es estrictamente *allow-list*: un usuario tiene un permiso (directo o vía
rol) o no lo tiene; no hay un tercer estado de "explícitamente denegado a pesar de heredarlo". Estas dos reglas
—que son el corazón de por qué RelBAC existe como sistema propio en primer lugar— tendrían que reconstruirse
completas, sin ayuda del paquete:

```php
namespace App\Services\Authorization;

/**
 * Capa de negocio sobre Spatie: ni la delegación ni el DENY explícito
 * tienen equivalente nativo en el paquete — viven acá, no en el storage.
 */
class DelegationAuthorizer
{
    public function actorPuedeDelegar(Usuario $actor, Role $role, array $teamIds): bool
    {
        if ($actor->hasRole('Super Admin')) {
            return true;
        }

        // "¿Qué permisos otorga este rol?" sí lo resuelve Spatie:
        $permissions = $role->permissions;

        // "¿El actor tiene ESTO delegable en ESTE contexto exacto?" — tabla propia,
        // sin equivalente en Spatie (no existe columna puede_delegar en el paquete).
        foreach ($permissions as $permission) {
            $delegableTeams = PermissionDelegation::query()
                ->where('actor_id', $actor->id)
                ->where('permission_id', $permission->id)
                ->where('puede_delegar', true)
                ->pluck('team_id');

            if (array_diff($teamIds, $delegableTeams->all())) {
                return false;
            }
        }

        return true;
    }
}

/**
 * DENY explícito ("hijo gana sobre padre") — se evalúa ANTES de dejar
 * que Spatie conteste, en un Gate::before() propio.
 */
Gate::before(function (Usuario $user, string $ability, array $arguments = []) {
    $resource = $arguments[0] ?? null;
    $teamIds = app(ContextResolver::class)->getAncestorContextIds($resource?->getContextId() ?? []);

    if (PermissionDenial::query()
            ->where('id_usuario', $user->id)
            ->where('permission_slug', $ability)
            ->whereIn('team_id', $teamIds)
            ->exists()) {
        return false; // corta antes de que Spatie evalúe el rol heredado
    }

    return null; // sin veredicto — Spatie sigue evaluando normalmente
});
```

Este diseño es correcto en el sentido de que `DelegationAuthorizer` queda desacoplado del storage (no le importa
si por debajo hay Spatie o las tablas actuales) — es la misma propuesta R-5 del informe de auditoría previo,
aplicable sin cambios a cualquiera de los dos backends. Pero es importante ser honesto sobre el tamaño: esto **no
es una capa fina de adaptación**, es prácticamente todo el contenido hoy en
`RoleAssignmentBuilder::validateActorAuthorization()` y `PermissionValidator::checkSpecialPermission()`, sólo que
apuntando a tablas nuevas en vez de a `usuario_rol_asignacion`/`usuario_permiso_especial`.

---

## 4. Impacto en el código

| Componente actual | Con Spatie |
|---|---|
| `RoleAssignmentBuilder` / `PermissionAssignmentBuilder` (builders + `__destruct`) | **Obsoletos.** Reemplazados por `$user->assignRole()`/`syncRoles()`/`givePermissionTo()`. Elimina H-1/H-3 de raíz — no porque se "arreglen", sino porque la clase que los tiene desaparece. |
| `AssignsPermissions` (trait de `Usuario`) | Se reduce a casi nada — Spatie aporta `HasRoles`. Sobrevive `invalidatePermission()`/`invalidateRole()` con la regla *"sólo el asignador original revoca"*: Spatie no registra quién asignó cada fila, así que esa regla exige seguir guardando `creado_por` en una tabla propia (o extender el modelo de pivote de Spatie, que es personalización, no algo de fábrica). |
| `Permissions.php` (enum autogenerado) + `scripts/permissions_config.php`/`generate_models.php` | Redundante si los permisos pasan a ser strings planos en BD al estilo Spatie — se pierde el `ValueError` en tiempo de ejecución que hoy da el enum ante un typo, a cambio de un fallo silencioso ("permiso no encontrado" en vez de "slug inválido"). Se puede *conservar* el enum como capa de conveniencia sobre Spatie (`$user->can(Permissions::CURSOS_VER->value)`), pero entonces **no se elimina** el pipeline de generación que esta misma sección invita a tirar — es una decisión, no una ganancia gratuita. |
| `PermissionContextConstraints` (qué contexto acepta cada permiso) | Sin equivalente en Spatie. Sigue siendo 100% propio si se quiere conservar esa validación (p. ej. impedir asignar `CURSOS_CREAR` sobre un Curso individual). |
| `WildcardMatcher` | Spatie trae wildcards nativos (`posts.*`, con subpartes por coma), pero exige **pre-crear cada patrón como fila `Permission`** antes de poder asignarlo o consultarlo — no genera "ancestros de recurso" dinámicamente como `generatePermissionPatterns()`. Y no tiene la regla de empate-gana-DENY (no aplica: Spatie no tiene DENY). |
| `HasBasePolicyMethods` / Policies generadas | Se simplifican **de verdad**, pero sólo en la medida en que se acepte la limitación de §1: si se resuelve herencia con un loop de ancestros, las Policies siguen necesitando ese loop igual que hoy — la simplificación de "`before()` = un solo `hasRole()`" sólo es cierta para habilidades sin jerarquía. |
| `ContextResolver` / `GlobalContextService` / `fn_obtener_ids_contexto_ancestros` | **Sin cambio.** Nada en Spatie los reemplaza; seguirían alimentando el loop de `setPermissionsTeamId()` en vez de alimentar `resolveTargetContexts()`. |

---

## 5. Estrategia de migración (si se decide avanzar pese a lo anterior)

1. **Config.** `'teams' => true`, `'team_foreign_key' => 'id_contexto'` para reusar la columna semántica ya
   existente en vez de introducir una nueva. Nota: esto sólo tiene sentido para la Opción B de §1 (loop de
   ancestros); usar `id_contexto` como team plano sin resolver ancestros pierde la herencia por completo.
2. **Esquema.** Publicar las migraciones estándar del paquete (`roles`, `permissions`, `model_has_roles`,
   `model_has_permissions`, `role_has_permissions`) — vía el flujo de migraciones vigente del proyecto
   (`scripts/migracion_desde_diff.ps1`, no editando `01-sql_def.sql` a mano, igual que se señaló para R-2 en la
   auditoría previa).
3. **Backfill de catálogos:**
   ```sql
   INSERT INTO roles (name, team_id, guard_name)
     SELECT nombre, NULL, 'web' FROM usuario.rol;

   INSERT INTO permissions (name, guard_name)
     SELECT slug, 'web' FROM usuario.permiso;
   ```
4. **Backfill de `role_has_permissions`** desde `usuario.asignacion_rol_permiso` — **se pierde**
   `puede_delegar_permiso` en el traspaso (no hay columna equivalente en Spatie); debe migrar a la tabla de
   delegación propia de §3, no al storage de Spatie.
5. **Backfill de `model_has_roles`** desde `usuario_rol_asignacion` — sólo filas **vigentes**
   (`esta_activo AND fecha_inicio_planificada <= now() <= fecha_fin_planificada`). Decisión pendiente: Spatie no
   expira roles solo, así que `->for($dias)` no tiene equivalente nativo — o se resigna esa función (roles
   indefinidos hasta que alguien los quite a mano) o se agrega un job programado que llame `removeRole()` al
   vencer, con su propia tabla de "fecha de expiración" paralela al storage de Spatie. Las asignaciones ya
   vencidas o revocadas no se migran al storage activo — si se necesita el historial, va a una tabla de auditoría
   aparte.
6. **Backfill de `model_has_permissions`** desde `usuario_permiso_especial WHERE esta_permitido = true AND
   <vigente>`. Las filas con `esta_permitido = false` (los DENY) **no van a Spatie** — van a la tabla
   `permission_denials` de §3.
7. **Doble escritura y verificación en paralelo.** Dado el volumen de reglas que sobreviven fuera del paquete
   (jerarquía, DENY, delegación), esto no es "cambiar un driver" — es reescribir el motor de resolución
   manteniendo el storage nuevo. Antes de apagar `PermissionValidator`, correr ambos caminos sobre el mismo
   tráfico real, comparando veredictos en un log de discrepancias, durante un período de observación.
8. **Cutover.** Retirar `PermissionValidator`/`PermissionCache`/`usuario_rol_asignacion`/`usuario_permiso_especial`
   sólo después de que el log de discrepancias esté limpio.

---

## 6. Riesgos y fricciones específicas de este dominio

- **Estado global mutable.** `setPermissionsTeamId()` no es un parámetro explícito — es exactamente el tipo de
  variable de proceso que causa fugas de contexto entre requests en workers de vida larga (colas, Octane) si algo
  olvida resetearla. RelBAC hoy no tiene esta clase de riesgo porque el contexto viaja siempre como argumento.
- **El ahorro real es más chico de lo que parece.** Todo lo que hace a RelBAC un sistema *hecho a medida* —
  herencia automática, DENY que gana sobre GRANT heredado, delegación acotada por `puede_delegar` y contexto
  exacto — no tiene equivalente nativo y se reconstruye a mano encima de Spatie. El resultado no es "menos
  código propio", es "el mismo código propio, más una dependencia externa cuyas asunciones (team plano,
  allow-list puro) hay que rodear en cada punto donde no calzan".
- **Tensión enum-vs-storage-plano.** No se puede simultáneamente "eliminar el pipeline de generación de
  `Permissions.php`" (§4) y "conservar el chequeo de tipos en compile-time" — son objetivos en conflicto, hay que
  elegir uno.
- **Vigencia temporal no nativa.** `->for($dias)`/`->waitFor($dias)` son parte real del negocio actual (accesos
  temporales, delegaciones con fecha de corte) y exigen infraestructura propia de expiración si se migra.
- **Resultado de facto: un sistema híbrido**, no una simplificación. Spatie para el storage plano y el caché;
  2-3 tablas y servicios propios para jerarquía, delegación y DENY. Más piezas moviéndose en conjunto, aunque cada
  pieza individual sea más simple de leer. Vale la pena únicamente si el objetivo explícito es reducir el código
  de "guardar y cachear roles/permisos" en sí mismo — y ese código ya tiene un plan de arreglo de menor riesgo en
  `auditoria_relbac_autorizacion.md` (R-1 a R-4).

## Recomendación final

No reemplazar el motor completo. Priorizar cerrar H-1, H-2, P-1, P-2 y P-3 del informe previo — son de esfuerzo
acotado y no ponen en riesgo la semántica de herencia/DENY/delegación que es el motivo de fondo por el que este
sistema es propio y no un paquete de catálogo. Si en el futuro se quiere explorar Spatie, limitar su uso al
subconjunto de roles verdaderamente **globales y sin jerarquía** (p. ej. SuperAdmin, roles de sistema sin
contexto) — ahí Teams no aporta nada que RelBAC no tenga ya, y tampoco hace falta ninguna de las reconstrucciones
de §3.
