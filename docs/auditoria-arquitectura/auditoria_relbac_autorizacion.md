# Auditoría de arquitectura: motor de autorización RelBAC

> Alcance: `app/Services/Authorization/*`, `app/Traits/{AssignsPermissions,ContextAware,GlobalContextAware}.php`,
> `app/Contracts/PermissionBuilder*.php`, `app/Policies/Base/Traits/HasBasePolicyMethods.php`,
> `app/Models/Usuario/*`, `database-model/init_scripts/02-other_objects/D/packages/verificacion_permisos.sql`,
> `database-model/init_scripts/02-other_objects/B/functions/get_ancestor_context_ids.sql`, `scripts/permissions_config.php`,
> `scripts/generate_models.php`. Código leído en `main`, verificado contra `git log`/`git diff` para separar lo ya
> remediado de lo pendiente.
>
> **Relación con la auditoría previa** (`_estado-auditoria.md`, rama `admin`, 86 hallazgos): esa auditoría ya cubrió
> y **cerró** tres cosas que este documento habría vuelto a señalar si no se verificara el código actual:
> `checkSpecialPermission` decidiendo por orden de filas (A-2, commit `747631c`), la ausencia total de caché en el
> motor (Fase 2, commit `747631c`), y `syncPermissions` esquivando el guard del builder para auto-asignarse
> SuperAdmin (D-1/D-2, commit `7f89c26`). Los tres se verificaron leyendo el código vigente y **se confirma que
> siguen arreglados**; no se repiten aquí salvo donde queda un hueco residual (ver H-2). Este informe se concentra
> en ángulos que esa auditoría no tocó: ciclo de vida de los builders (`__destruct`), atomicidad transaccional,
> concurrencia de caché y generación de código.

---

## 1. Hallazgos Críticos

### H-1. `__destruct()` como único punto de persistencia + escritura multi-contexto sin transacción → grants parciales invisibles

**Dónde:** `RoleAssignmentBuilder.php:317-379` (`save()` + `__destruct()`), `PermissionAssignmentBuilder.php:478-548` (ídem).

Esta es la pregunta que pide el enunciado — "cómo afecta esto a `DB::transaction`" — y la respuesta es concreta:
**no la hay**. Cuando el builder resuelve a más de un contexto (`onAll()`, `onAllCurrentInstances()`), `save()` hace:

```php
// RoleAssignmentBuilder::save() — App\Services\Authorization\RoleAssignmentBuilder:357-364
$records = collect();
foreach ($this->contextIds as $contextId) {
  $records->push(
    UsuarioRolAsignacion::create([...$payload, 'id_contexto' => $contextId])
  );
}
return $records;
```

Sin `DB::transaction()` alrededor del `foreach`. Si el registro 3 de 5 viola una constraint (FK a un
`id_contexto` que fue eliminado entre la resolución de `onAll()` y el `create()`, o una fila duplicada por una
carrera entre dos requests), **los registros 1 y 2 quedan comprometidos en la base de datos** y no hay rollback:
el usuario recibió el rol en 2 de 5 facultades, sin que nada lo revierta ni lo reporte como éxito parcial. Idéntico
patrón en `PermissionAssignmentBuilder::save()` (línea 525-533) para permisos especiales.

El `__destruct()` no causa este bug — lo hace invisible:

```php
public function __destruct() {
  if (!$this->saved && !empty($this->contextIds)) {
    $this->save();
  }
}
```

`$user->giveRole($rol)->onAll(ContextualModelType::FACULTAD)->for(30);` — sin variable que retenga el objeto — es
basura con refcount 0 al terminar la sentencia: PHP invoca `__destruct()` de inmediato, de forma determinista (no
hay ciclo de referencias entre el builder y sus dependencias, todas *singletons* vía el contenedor — ver `H-1
addendum`). Eso significa que, en el caso común, la excepción de `save()` sí se propaga hacia arriba con normalidad.
El problema es que **la mayoría de los call-sites de este patrón fluido no envuelven la sentencia en `try/catch`**
porque leen como una instrucción imperativa simple ("asignar el rol"), no como algo que puede fallar. El resultado:

1. **Caso más común (con éxito):** la excepción de `save()` (`DontHavePermissionException`,
   `InvalidArgumentException`, `RecordNotFoundException` de `Permiso::firstOrFail()`) escapa como excepción no
   capturada de nivel superior → el `Handler` de Laravel devuelve 500. Correcto desde "fail-closed", pero **los
   registros ya insertados antes del fallo (punto anterior) siguen en la base de datos** pese al 500: el usuario ve
   un error, pero parte del grant ya existe.
2. **Caso con referencia retenida:** si el builder queda referenciado más tiempo del esperado (asignado a una
   variable local que sobrevive el resto de la función, guardado en un array, capturado por un closure diferido —
   nada de esto es exótico en este código: `RoleAssignmentBuilder` se construye y se pasa a `->on()`/`->for()` en
   cadenas que a veces sí se trocean en variables intermedias para logging o testing), la destrucción se difiere
   hasta el final del scope o hasta el *shutdown* del request. Una excepción lanzada por un destructor invocado en
   fase de shutdown **no tiene ningún frame de PHP al que propagarse** — se convierte en un fatal error que ni el
   `try/catch` que rodeaba la sentencia original, ni el `Handler` de Laravel, pueden interceptar. Si esto ocurre
   después de que la respuesta ya se envió (buffer flushed / `fastcgi_finish_request`), el usuario ve un 200 "éxito"
   mientras el registro real quedó a medias y el error solo llega al log de PHP, no al canal `seguridad` de la
   aplicación.
3. El flag `$saved` que existe únicamente para no persistir dos veces si alguien ya llamó a `save()` explícito es,
   en sí mismo, una admisión de que el momento exacto de la destrucción no está bajo control del autor — es un
   parche para el síntoma (doble persistencia), no para la causa (persistencia dependiente del ciclo de vida del
   objeto en vez de una acción explícita).

**Por qué esto es crítico y no solo un purismo de estilo:** el propio validador de negocio
(`validateActorAuthorization()`) es el control anti-escalada de privilegios del sistema. Su fallo se comunica
exclusivamente lanzando `DontHavePermissionException` desde dentro de `save()`. Atarlo a la semántica de
destructores de PHP significa que la fiabilidad de ese control de seguridad depende de un detalle de
implementación (si el caller retuvo una variable, si el objeto muere en esta sentencia o en shutdown) que ningún
lector del código de negocio puede ver ni testear con confianza.

### H-2. `assertPuedeSincronizarPermisos()` es una lista negra por *nombre* de rol, no una comprobación de contenido de permisos — hueco residual tras el fix de D-1/D-2

**Dónde:** `UsuarioController.php:1459-1500`.

El fix de `7f89c26` (Fase 0 de la auditoría previa) cerró correctamente la auto-asignación y la modificación de
otro SuperAdmin. El guard #4 — el que decide si un no-SuperAdmin puede conceder un rol a un tercero — es este:

```php
// UsuarioController::assertPuedeSincronizarPermisos:1486-1500
if (!$actorEsSuperAdmin && !empty($rolesSolicitados)) {
    $aliasAdmin = array_map('strtolower', Usuario::ROLES_ADMINISTRATIVOS);
    $administrativos = Rol::whereIn('id_rol', $rolesSolicitados)
        ->pluck('nombre')
        ->filter(fn($nombre) => in_array(strtolower(trim($nombre)), $aliasAdmin, true));
    if ($administrativos->isNotEmpty()) {
        abort(403, '...');
    }
}
```

Compárese con `RoleAssignmentBuilder::validateActorAuthorization()` (la vía "correcta", usada por el resto del
sistema): esa exige que el actor posea *ese permiso concreto*, con `puede_delegar=true`, *en ese contexto exacto*.
El guard de `syncPermissions` no comprueba nada de eso para roles: sólo bloquea los nombres que aparezcan
literalmente en `Usuario::ROLES_ADMINISTRATIVOS`. Cualquier rol que **no** esté en esa lista de alias —
"Docente Titular", "Jefe de Carrera", o cualquier rol nuevo que un futuro desarrollador cree con permisos amplios
(`cursos:*`, `usuarios:ver`, lo que sea) y `puede_delegar_permiso=true` en `asignacion_rol_permiso` — puede ser
concedido por **cualquier actor que sólo tenga el `create` genérico** sobre `UsuarioRolAsignacion` (guard #2,
`$this->authorize('create', UsuarioRolAsignacion::class)`), sin que se compruebe si el actor mismo posee ese rol o
esos permisos. Y siempre en el **contexto global** (`$idContexto = GlobalContextService::getContextId()`, con el
TODO explícito "Ampliar para soportar sincronización multi-contexto" en la línea 1308) — es decir, no hay siquiera
el consuelo de que el daño quede acotado a la carrera/facultad del actor.

Esto reproduce, un nivel más abajo, exactamente el patrón que el informe anterior llamó *"Dos puertas para la misma
operación, una endurecida y otra no"* (D-1): la vía builder exige delegación probada permiso-por-permiso y
contexto-por-contexto; la vía `syncPermissions` exige solo "no está en esta lista de nombres". Un actor con permisos
administrativos limitados (p. ej. "gestionar cuentas de usuario" sin ser SuperAdmin) puede usar `syncPermissions`
para blanquear una escalada: conceder a un cómplice un rol no listado pero potente, y desde ahí ese cómplice ya
opera con la vía builder "legítima".

**Propuesta:** que `syncPermissions` valide roles con la misma regla que usa el builder (¿el actor tiene, para
cada permiso que ese rol otorga, `puede_delegar=true` en el contexto de destino?) en vez de una lista de alias por
nombre — ver §4, R-5.

### H-3. Actor nulo diferido a `__destruct()` en `RoleAssignmentBuilder` — inconsistente con `PermissionAssignmentBuilder`

**Dónde:** `AssignsPermissions::giveRole():62-68` vs `AssignsPermissions::givePermission():40-50`.

```php
public function givePermission(Permissions $permissionSlug): PermissionBuilderStart {
  $actor = Auth::user();
  if (!$actor) { throw new \RuntimeException(...); }   // falla YA, en el call-site
  return new PermissionAssignmentBuilder($this, $permissionSlug, $actor);
}

public function giveRole(Rol $rol): RoleAssignmentBuilder {
  $actor = Auth::user(); // "For seeding purposes, we allow passing null and handle it in the builder"
  return new RoleAssignmentBuilder($this, $rol, $actor);   // NO valida aquí
}
```

`giveRole()` permite `$actor = null` a propósito para seeders, pero "manejarlo en el builder" significa lanzar
`RuntimeException` dentro de `validateActorAuthorization()`, invocado desde `save()`, invocado desde `__destruct()`
si nadie llamó `->as($admin)` ni `->save()` explícito. En un comando de consola o un job en cola sin sesión HTTP,
ese `RuntimeException` hereda todos los problemas de H-1: puede aparecer como fatal de shutdown en vez de como un
fallo claro en el punto de la llamada. Además, `PermissionAssignmentBuilder` sí hace *fallback* a
`auth()->user()` en su propio constructor (línea 86) — dos builders hermanos con dos políticas distintas para el
mismo problema (actor ausente) es, en sí, una fuente de bugs por sorpresa al migrar código entre uno y otro.

---

## 2. Cuellos de Botella de Rendimiento

### P-1. Sin índice compuesto en las tablas calientes del motor — cada cache-miss es un *sequential scan*

**Dónde:** `01-sql_def.sql:397-469`.

```sql
CREATE TABLE usuario.usuario_rol_asignacion (
    id_ura integer ... GENERATED ALWAYS AS IDENTITY,
    ...
    id_contexto integer,
    id_rol smallint NOT NULL,
    id_usuario integer NOT NULL,
    ...
    CONSTRAINT pk_usuario_rol_asignacion PRIMARY KEY (id_ura)
);
-- misma historia en usuario_permiso_especial (id_upe como PK)
```

Sólo hay índice en la PK (`id_ura`/`id_upe`) y en las FKs hacia `contexto`/`permiso`/`rol` (que en Postgres **no**
crean índice automático sobre la columna que referencia, solo sobre la referenciada). Cada consulta caliente del
motor — `checkSpecialPermission`, `checkRolePermission`, `getDeniedContexts`, `getGrantedContextsFromSpecial`,
`getGrantedContextsFromRoles` en `PermissionValidator.php` — filtra por `id_usuario` + `id_contexto` (+
`tipo_asignacion`/`slug`) contra la vista `vw_permisos_usuario`, que es un `UNION ALL` de joins sobre exactamente
estas dos tablas. Sin índice compuesto, cada miss de caché es un *seq scan* de ambas tablas completas.

Esto no se nota con pocos datos, pero las dos tablas son de solo-crecimiento: los registros no se borran, se
cierran (`esta_activo=false`, `fecha_fin_real=now()`) — cada cambio de rol o permiso de cada usuario en la historia
del sistema deja una fila. Y el punto donde más duele: `syncPermissions()` invalida explícitamente la caché del
usuario al terminar (`PermissionCache::olvidarUsuario()`, línea 1417) — así que la siguientísima carga de página del
usuario que el admin acaba de editar (o el propio admin recargando para confirmar el cambio) paga el costo
completo: N contextos ancestro × 2 (UPE + URA) *seq scans*, en el momento en que alguien está mirando activamente
si el cambio surtió efecto.

**Verificado correcto, no repetir esfuerzo:** el árbol de contexto (`fn_obtener_ids_contexto_ancestros`, la CTE
recursiva) **no** es el cuello de botella que el enunciado sugiere. Cada paso recursivo hace `JOIN` por
`c.id_contexto = cadena.id_contexto_padre`, y `id_contexto` es la PK de `usuario.contexto` — cada salto usa el
índice de PK. La profundidad del árbol está acotada (~5 niveles: global→facultad→carrera→plan→curso). Y
`ContextResolver::getAncestorContextsWithType()` (líneas 217-233) ya cachea el resultado en memoria de request
*y* en el store con TTL de 1 hora — el "combinado con `vw_permisos_usuario` en cada validación" del enunciado ya
no ocurre en cada validación real, solo en cold-start. El hallazgo real está en P-1, no en la función SQL.

### P-2. `PermissionCache` depende de una propiedad de `CACHE_STORE` que nada obliga a mantener

**Dónde:** `PermissionCache.php:100-107` (usa la fachada `Cache::` genérica).

`Cache::increment()` sobre el driver `database` (confirmado: `config/cache.php:18` → `CACHE_STORE=database` en
`.env.example`) **sí es atómico** — `Illuminate\Cache\DatabaseStore::incrementOrDecrement()` envuelve la operación
en `$this->connection->transaction()` con `lockForUpdate()`. Esto es correcto y ya resuelve la carrera
escritura-escritura en el caso estable (clave de generación ya existente). Pero esa garantía **no está anclada en
el código** — `PermissionCache` llama a la fachada `Cache::` sin `Cache::store('database')` explícito, así que
hereda silenciosamente lo que sea que `CACHE_STORE` resuelva en cada entorno. Si alguna vez se cambia a `file` o
`array` (p. ej. para simplificar un entorno de test, o por una migración de infraestructura), `increment()` en
esos drivers es lectura-modificación-escritura sin lock entre procesos — la propiedad de atomicidad que hoy
sostiene la invalidación por generación desaparece sin ningún error visible, solo entradas de caché obsoletas que
sobreviven más de lo esperado bajo escritura concurrente.

Hay un hueco residual, menor, incluso con `database`: `olvidarUsuario()` hace *check-then-act* antes de decidir
entre inicializar o incrementar:

```php
public function olvidarUsuario(int $userId): void {
    $clave = $this->claveGeneracion($userId);
    if (Cache::get($clave) === null) {   // lectura sin lock
        Cache::forever($clave, 1);       // escritura sin lock
        return;
    }
    Cache::increment($clave);            // esta sí es atómica
}
```

Dos invalidaciones concurrentes sobre un usuario que **nunca antes se invalidó** pueden pasar ambas por la rama
`=== null` y escribir `1` las dos — se "pierde" un incremento. En la práctica esto es inofensivo: en generación
"inexistente" no hay nada cacheado que proteger (el propio método de lectura `generacion()` inicializa la clave en
1 la primera vez que se *lee*, con el mismo patrón). El riesgo real sería en estado estable, y ahí sí está cubierto
por el `lockForUpdate` de `DatabaseStore`. Se documenta como *hallazgo menor* — ver R-3 para un cierre barato con
`Cache::add()`.

### P-3. Sin poda periódica de filas de caché huérfanas

Cada bump de generación abandona de golpe todas las entradas `perm:g{N}:...` previas de ese usuario. El driver
`database` no las borra activamente: Laravel las purga de forma perezosa (al leer una clave y notar que expiró) o
requiere una limpieza manual. No se encontró ningún job programado que las pode (`grep` de `cache:prune`,
`DELETE FROM cache`, `Schedule::command('cache` en todo el repo: sin resultados). Con el TTL de 300s la ventana de
basura por usuario es corta, pero la cardinalidad es alta (usuarios × permisos × contextos ancestro, multiplicada
por cuántas veces un admin edita permisos), y cada `syncPermissions()` genera un lote nuevo de filas huérfanas
inmediatamente. No es una fuga catastrófica, pero sí una tabla `cache` que crece sin que nada la controle entre
`php artisan cache:clear` manuales.

---

## 3. Observaciones Arquitectónicas

### A-1. Dos vías de escritura, dos niveles de rigor — patrón recurrente, no aislado a `syncPermissions`

`RoleAssignmentBuilder`/`PermissionAssignmentBuilder` centralizan `validateActorAuthorization()` como único punto
de verdad de "quién puede delegar qué, dónde". `UsuarioController::syncPermissions()` escribe directo contra
Eloquent (`UsuarioRolAsignacion::updateOrCreate`, `UsuarioPermisoEspecial::updateOrCreate`) y reimplementa su
propio guard (`assertPuedeSincronizarPermisos`, ver H-2) porque estructuralmente **no puede** llamar al builder: el
builder asume que *el propio usuario objetivo* invoca la cadena (`$user->giveRole(...)`), no que un admin la
invoca *sobre* otro usuario con reemplazo masivo de asignaciones. El riesgo arquitectónico no es que exista una
segunda vía — la sincronización masiva por naturaleza necesita `where()->update()` en lote, algo que el builder
fluido no está diseñado para hacer — sino que **la lógica de "quién puede delegar qué" vive duplicada en dos
sitios con distinto nivel de detalle**, y nada impide que una tercera vía futura (un endpoint de API, un comando de
importación) reinvente el guard por tercera vez, probablemente peor.

### A-2. `AssignsPermissions` mezcla responsabilidades de negocio dentro del modelo `Usuario`

El trait no solo ofrece factories de builders (`givePermission()`/`giveRole()`, razonable como *entry point*
fluido) sino que además implementa la regla de negocio "sólo el asignador original o un SuperAdmin puede revocar"
directamente en `invalidatePermission()`/`invalidateRole()` (líneas 92-106, 136-147). Esa es una decisión de
autorización — el mismo tipo de decisión que en el resto del sistema vive en `HasBasePolicyMethods` +
`Base*Policy`, no en un trait mixeado al modelo ORM. No es un defecto grave por sí solo (Laravel mezcla
`Authorizable` en el modelo de usuario por convención), pero rompe la consistencia: un desarrollador que busca
"dónde se decide quién puede hacer X" tiene que saber que para roles/permisos la respuesta está en un trait del
modelo, y para todo lo demás está en `app/Policies/`. La inconsistencia entre `giveRole()` y `givePermission()`
sobre actor nulo (H-3) es síntoma de lo mismo: es lógica que se fue acumulando en el trait sin una revisión única
de coherencia.

### A-3. Acoplamiento `HasBasePolicyMethods` ↔ `Permissions` enum por convención de string, sin verificación en tiempo de generación

```php
protected function buildPermissionSlug(string $resource, string $action): Permissions {
  return Permissions::from("{$resource}:{$action}");   // ValueError en runtime si no existe
}
```

Las Policies autogeneradas y el enum `Permissions` son dos representaciones de la misma tabla de verdad
(`scripts/permissions_config.php`), mantenidas en sincronía por convención de nombres, no por el compilador: si
una Policy generada llama `buildPermissionSlug('cursos', 'archivar')` y ese slug no existe en `Permissions.php`
(porque se agregó la acción al recurso equivocado, o se generó una sola de las dos piezas), el error es un
`ValueError` en producción la primera vez que alguien ejerce esa habilidad — no algo que `php -l` ni un CI típico
detecten. Es un acoplamiento razonable dado el diseño (evita una tabla de mapeo manual), pero sin una
comprobación cruzada en CI es un desajuste silencioso esperando a pasar en el primer *code review* apurado.

### A-4. Código SQL muerto y más débil que el que sí se usa: `fn_verificar_permiso`

**Dónde:** `verificacion_permisos.sql:75-104`. Confirmado sin ningún llamador en `app/` (`grep -r
fn_verificar_permiso app/` → vacío). Es una función completa, paralela a la lógica real
(`PermissionValidator` + `WildcardMatcher` en PHP), que:

- no implementa la regla "empate → gana el DENY" que sí tiene `PermissionValidator::resolveSpecialPermission()`
  (la que se arregló en la auditoría previa, A-2);
- trata `id_contexto IS NULL` como comodín ("matchea cualquier contexto"), lo cual es directamente peligroso si
  alguna vez se conecta, porque `id_contexto` es nullable en ambas tablas de asignación (`ON DELETE SET NULL`
  hacia `contexto`) — una fila que perdió su contexto por un borrado en cascada pasaría a autorizar
  *globalmente* bajo esta función, en vez de quedar huérfana e inerte.

El comentario "NOTA: No se debe utilizar en queries individuales" es la única barrera, y es un comentario, no un
control. Vive en el mismo script que sí se ejecuta al provisionar la base (`02-other_objects/D/packages/`), así
que cualquier reconstrucción de esquema la vuelve a instalar. Recomendado: eliminarla, o si se conserva por razón
histórica, sacarla de la ruta de `init_scripts` que se aplica automáticamente.

### A-5. Generación de código sin protección más allá del comentario

`Permissions.php`, `config/permission-context-metadata.php`, `config/permission-no-inherit.php` y
`config/generated-context-mappings.php` llevan cabeceras "AUTOGENERADO — NO EDITAR", pero no se encontró ningún
hook de git, *script* de Composer, ni paso de CI que compare estos archivos contra lo que regeneraría
`scripts/permissions_config.php`/`scripts/generate_models.php`. Un desarrollador (o un asistente de IA sin este
contexto) que edite `Permissions.php` a mano para "agregar un permiso rápido" no tiene ninguna señal automática de
que ese cambio se perderá en la próxima regeneración, ni de que puede desincronizar el enum respecto a
`permission-context-metadata.php` (que si no se regenera junto, deja ese permiso nuevo sin entrada — cae al
default `['global']` de `PermissionContextConstraints::validContextTypesFor()`, línea 90, silenciosamente más
restrictivo de lo que el desarrollador esperaba).

---

## 4. Propuestas de Refactorización

### R-1. Eliminar el auto-guardado en `__destruct()`; hacer `save()` explícito y transaccional

```php
// App\Services\Authorization\RoleAssignmentBuilder.php

public function save(): UsuarioRolAsignacion|Collection
{
    if ($this->saved) {
        return collect();
    }

    $this->validateActorAuthorization();

    if (empty($this->contextIds)) {
        throw new \InvalidArgumentException(
            'Debe especificar un contexto usando ->on($recurso) o ->onAll($class) antes de guardar.'
        );
    }

    $this->validateRoleContextCompatibility();
    $this->saved = true;

    $payload = [ /* ...igual que hoy... */ ];

    // Atomicidad: o se crean todas las filas o ninguna.
    return DB::transaction(function () use ($payload) {
        if (\count($this->contextIds) === 1) {
            return UsuarioRolAsignacion::create([...$payload, 'id_contexto' => $this->contextIds[0]]);
        }

        return collect($this->contextIds)->map(
            fn ($contextId) => UsuarioRolAsignacion::create([...$payload, 'id_contexto' => $contextId])
        );
    });
}

// __destruct() se elimina por completo. Sin auto-guardado, un ->on(...)->for(...) sin ->save()
// explícito simplemente no persiste nada — falla de forma silenciosa pero SEGURA (no falla de forma
// impredecible como hoy), y es detectable en tests/QA porque el registro nunca aparece.
```

Todos los *call-sites* (`grep -rn '\->giveRole(\|->givePermission(' app/`) deben auditarse una vez para agregar el
`->save()` final que hoy provee el destructor implícitamente. Es trabajo mecánico y acotado; el resultado es que
`save()` lanza sus excepciones exactamente donde el código las espera, con la transacción como red de seguridad
real ante fallos parciales.

Si se prefiere no tocar cada *call-site* de inmediato, un paso intermedio de menor riesgo es mantener el
`__destruct()` **sólo como red de seguridad que registra, no que persiste silenciosamente una operación de
seguridad crítica**:

```php
public function __destruct()
{
    if ($this->saved || empty($this->contextIds)) {
        return;
    }

    try {
        $this->save();
    } catch (\Throwable $e) {
        // No relanzar: un throw en __destruct() puede ser un fatal no capturable.
        // Se deja constancia explícita en vez de fallar silenciosamente por completo.
        report($e);
        Log::channel('seguridad')->error('RoleAssignmentBuilder: falló el auto-guardado en destructor', [
            'exception' => $e->getMessage(),
        ]);
    }
}
```

Esto no arregla la falta de atomicidad (para eso sigue haciendo falta R-1 en `save()`), pero convierte un fallo
silencioso-y-a-veces-invisible en un fallo silencioso-pero-siempre-registrado, mientras se migra el resto del
código a `->save()` explícito.

### R-2. Índices compuestos en las tablas calientes

**Nota de proceso:** desde el 2026-07-29 el mecanismo oficial para cambios de esquema son las migraciones de
Laravel vía `scripts/migracion_desde_diff.ps1` (`docs/FLUJO_MIGRACIONES_ESQUEMA.md`) — `01-sql_def.sql` es
baseline congelado del submódulo `database-model` y **no se edita directamente** (si el cambio entra ahí *y* en
una migración, se aplica dos veces). El DDL de abajo es el contenido a introducir por ese flujo, no un edit
directo al init script.

```sql
-- Vía database/migrations/ (generada con scripts/migracion_desde_diff.ps1, no editando 01-sql_def.sql):
-- índices compuestos para el motor de permisos. Cubren el patrón
-- WHERE id_usuario = ? AND id_contexto = ? de checkSpecialPermission/checkRolePermission.
CREATE INDEX idx_ura_usuario_contexto_activo
    ON usuario.usuario_rol_asignacion (id_usuario, id_contexto)
    WHERE esta_activo = TRUE AND fecha_fin_real IS NULL;

CREATE INDEX idx_upe_usuario_contexto_activo
    ON usuario.usuario_permiso_especial (id_usuario, id_contexto)
    WHERE esta_activo = TRUE AND fecha_fin_real IS NULL;
```

Índices parciales (`WHERE esta_activo = TRUE AND fecha_fin_real IS NULL`) porque las filas cerradas/históricas —
que son la mayoría a medida que el sistema envejece — nunca participan en `vw_permisos_usuario`; no vale la pena
indexarlas.

### R-3. Cerrar el hueco de `Cache::add()` en `olvidarUsuario()` y anclar el store

```php
public function olvidarUsuario(int $userId): void
{
    $clave = $this->claveGeneracion($userId);

    // Cache::add() es atómico ("set si no existe") en los drivers que soportan lock —
    // evita el check-then-act de Cache::get()===null seguido de forever().
    if (Cache::add($clave, 1)) {
        return;
    }

    Cache::increment($clave);
}
```

Y anclar explícitamente el store para que la propiedad de atomicidad no dependa de `CACHE_STORE` global:

```php
// PermissionCache.php
private function store(): \Illuminate\Contracts\Cache\Repository
{
    // Ancla el store: la atomicidad de increment()/add() que este servicio necesita
    // sólo está garantizada en drivers con lock (database, redis). Si CACHE_STORE
    // global cambia a 'file'/'array', este servicio no debe heredar ese cambio en silencio.
    return Cache::store(config('cache.permissions_store', 'database'));
}
```

### R-4. Job programado para podar filas de caché expiradas

```php
// routes/console.php o App\Console\Kernel
Schedule::command('cache:prune-stale-tags')->hourly(); // si se migra a un driver con soporte de tags

// Con el driver `database` actual, no hay comando nativo — un cierre corto:
Schedule::call(function () {
    DB::table('cache')->where('expiration', '<', now()->getTimestamp())->delete();
})->hourly()->name('prune-expired-cache-rows');
```

### R-5. Un único punto de verdad para "¿puede este actor delegar este rol/permiso, en este contexto?"

Extraer la lógica de `RoleAssignmentBuilder::validateActorAuthorization()` /
`PermissionAssignmentBuilder::validateActorAuthorization()` a un servicio compartido, y hacer que
`syncPermissions()` lo use para roles (hoy sólo lo tiene, informalmente, la lista de alias de H-2):

```php
namespace App\Services\Authorization;

class DelegationAuthorizer
{
    public function __construct(private PermissionValidator $validator) {}

    /** @param Permissions[] $permissions Permisos que otorgaría el rol/permiso a asignar */
    public function actorPuedeDelegar(Usuario $actor, array $permissions, array $contextIds): bool
    {
        if ($this->validator->isSuperAdmin($actor)) {
            return true;
        }

        foreach ($permissions as $permission) {
            $delegables = $this->validator->getContextsWhereDelegablePermission($actor, $permission);
            if (!empty(array_diff($contextIds, $delegables))) {
                return false;
            }
        }

        return true;
    }
}
```

`RoleAssignmentBuilder`, `PermissionAssignmentBuilder` y `UsuarioController::assertPuedeSincronizarPermisos()`
pasan a llamar al mismo método, con los permisos reales del rol (`fetchRolePermissionsAsEnums()`, que
`RoleAssignmentBuilder` ya tiene) en vez de comparar nombres contra una lista de alias.

### R-6. Verificación de hash para los artefactos autogenerados

```bash
# scripts/verify_generated.sh — se corre en CI antes de cualquier otro chequeo
php scripts/permissions_config.php --check-only   # o el flag equivalente que emita a stdout sin escribir
sha256sum app/Support/Permissions.php config/generated-context-mappings.php > /tmp/actual.sha256

php scripts/permissions_config.php
php scripts/generate_models.php
sha256sum app/Support/Permissions.php config/generated-context-mappings.php > /tmp/regenerated.sha256

diff /tmp/actual.sha256 /tmp/regenerated.sha256 || {
    echo "::error::Permissions.php / generated-context-mappings.php están desincronizados de sus fuentes."
    echo "::error::Corré 'php scripts/permissions_config.php && php scripts/generate_models.php' y comiteá el resultado."
    exit 1
}
```

Complementable con un *pre-commit hook* liviano que sólo avisa (no bloquea) si se detecta un `git diff` en esos
archivos sin el correspondiente diff en `scripts/permissions_config.php` en el mismo commit.

---

## Resumen para quien retome esto

| # | Hallazgo | Severidad | Estado |
|---|---|---|---|
| H-1 | `__destruct()` + `save()` multi-contexto sin `DB::transaction` | Crítico | Pendiente |
| H-2 | `syncPermissions` valida roles por nombre, no por contenido de permisos | Alto (residual de D-1/D-2) | Pendiente |
| H-3 | Actor nulo diferido a `__destruct` en `RoleAssignmentBuilder` | Medio | Pendiente |
| P-1 | Sin índice compuesto `(id_usuario, id_contexto)` en URA/UPE | Medio-Alto | Pendiente |
| P-2 | `PermissionCache` no ancla el store; `check-then-act` menor en bootstrap | Bajo | Pendiente |
| P-3 | Sin poda de filas de caché huérfanas | Bajo | Pendiente |
| A-1…A-5 | Observaciones de acoplamiento/SRP/codegen | — | Para discusión de equipo |

Ya verificado como correcto (no reabrir): resolución DENY-en-empate (A-2 previo), caché del motor (Fase 2 previa),
guard de auto-escalada/SuperAdmin en `syncPermissions` (D-1/D-2 previo), costo de la CTE recursiva de ancestros.
