# Flujo de cambios de esquema con migraciones

> **Regla fundamental:** un cambio en el modelo **nunca** se aplica borrando y recreando la base.
> En producción el borrado destruye datos irrecuperables. Todo cambio posterior al baseline
> llega a la base como una migración incremental.

Este documento reemplaza la convención anterior
(`database-model/docs/permisos/CAMBIOS_CODIGO_NECESARIOS.md:227`, «los cambios de estructura
deben hacerse en SQL puro dentro de `init_scripts/`, no como migraciones Laravel»),
que sólo era viable mientras recrear la base desde cero fuera aceptable.

---

## 1. Reparto de responsabilidades

| Pieza | Rol | Se ejecuta |
|---|---|---|
| `database-model/source/modelo_pgmodeler.dbm` | **Diseño canónico.** Toda modificación empieza acá. | nunca |
| `database-model/init_scripts/01-sql_def.sql` | **Baseline congelado.** Estado del esquema al corte. | sólo al crear una base nueva |
| `database/migrations/*.php` | **Incrementos.** Único mecanismo de aplicación desde el corte. | `php artisan migrate` |
| `database-model/init_scripts/02-other_objects/` | Objetos sin estado (vistas, funciones, triggers). | re-aplicable |
| `database-model/init_scripts/03-inserts/` | Datos semilla. | sólo en desarrollo |
| `docs/bd/modelo-foto.png`, `diccionario_datos-AUTOGENERADO.md`, `dump.sql` | Documentación derivada. | nunca |

### El corte (baseline)

El baseline es el estado del submódulo `database-model` en el commit **`625c076`**
(*feat: tablas de mensajeria docente-alumno*). A esa altura el `.dbm`, `01-sql_def.sql` y el
último `diff.sql` están convergidos: no hay cambios pendientes de aplicar.

**Regla que sostiene todo el diseño:** a partir del corte, `01-sql_def.sql` **no se vuelve a
editar a mano ni se le vuelven a agregar bloques DDL**. Queda congelado hasta el próximo
re-baseline (§6).

Motivo: si un cambio entra *a la vez* en `01-sql_def.sql` y en una migración, una base creada
desde cero lo recibe dos veces — la migración falla con «ya existe», o peor, aplica algo
parcialmente. El baseline y los incrementos tienen que ser conjuntos disjuntos.

### Poner la base al día con el corte (una sola vez)

La base de desarrollo que está corriendo puede estar **atrasada** respecto al baseline: se
construyó antes de que `01-sql_def.sql` recibiera los últimos bloques. Se detecta así — el
Compare/Diff reporta objetos «Created» que **ya existen** en `01-sql_def.sql`.

Fue el caso del diff del 2026-07-29 (`database-model/diff_change/diif_29-07-2026.sql`): sus 11
objetos «Created» (`idx_data_programa`, `curso.en_tipo_mensaje_curso`, `curso.mensaje`,
`curso.interaccion_mensaje`, `fn_cascade_fecha_eliminacion`, el trigger y las FK) están todos
en el baseline. Ese diff **no es un incremento**: es la base pidiendo alcanzar el corte.

**Un diff así no se convierte en migración.** Funcionaría en la base atrasada y fallaría con
«ya existe» en cualquier base nueva, porque ahí `init_scripts` ya los creó. Es exactamente la
doble aplicación que la regla del baseline congelado evita.

Se aplica como puesta al día, sin versionar:

```powershell
pwsh -File scripts/migracion_desde_diff.ps1 -Nombre al_dia -Diff <diff> -SoloSql tmp/al_dia.sql
# revisar tmp/al_dia.sql y aplicarlo con:
#   psql -U postgres -d utamed_1ra_fase -1 -v ON_ERROR_STOP=1 -f tmp/al_dia.sql
```

El `-SoloSql` filtra el mismo ruido que en una migración pero emite SQL en vez de PHP. Después
de esto la base queda igual al baseline, y **el siguiente diff ya es un incremento genuino**.

El archivo generado viene envuelto en `BEGIN; ... COMMIT;` y su encabezado repite el `-1
-v ON_ERROR_STOP=1`. No es decoración: sin eso, psql sigue adelante después del primer error
y deja la base aplicada por la mitad, sin forma de saber hasta dónde llegó.

Alternativa si no importa perder los datos de desarrollo: `composer db:soft-reset`, que
reconstruye desde `init_scripts` y deja la base en el baseline por definición.

### Los dos caminos, y por qué convergen

```
Base NUEVA:       init_scripts (baseline) ──> artisan migrate (incrementos) ──> db:seed
Base EXISTENTE:                              artisan migrate (incrementos)
```

Ambos terminan en el mismo estado porque el baseline es idéntico en los dos casos y los
incrementos son los mismos. `composer db:soft-reset` ya tenía esta forma
(`soft_reset.ps1` + `artisan migrate` + `db:seed`); lo que faltaba era que los cambios
efectivamente viajaran en las migraciones.

La convergencia depende de que las tres partes corran sobre la **misma** base y con el **mismo**
PHP, que es lo que garantiza `scripts/db_reset.ps1` (§8).

---

## 2. Qué va en una migración y qué no

La distinción es **si el objeto tiene estado que se puede perder**.

**Con estado → migración incremental obligatoria.** Esquemas, tablas, columnas, tipos
(`CREATE TYPE`), constraints, índices, secuencias. Recrearlos implica perder filas.

**Sin estado → se siguen regenerando completos.** Vistas, funciones, triggers, procedimientos.
Son `CREATE OR REPLACE` y se pueden volver a aplicar sobre una base viva sin tocar datos, así
que `02-other_objects/` sigue funcionando como hasta ahora. Dos excepciones a vigilar:

- Cambiar la **firma** de una función exige `DROP FUNCTION` previo: `CREATE OR REPLACE` no
  puede cambiar tipos de parámetros ni el tipo de retorno. Eso va en migración.
- `datos_usuarios_roles.sql` y `inscribir_estudiante_componente_curso.sql` **no** usan
  `CREATE OR REPLACE`; hay que revisarlos antes de confiar en re-aplicarlos.

**Datos semilla → nunca en producción.** `03-inserts/00-truncar_todo.sql` hace `DELETE FROM`
de cada tabla. Un cambio de datos de catálogo que deba llegar a producción va en una migración
con `INSERT ... ON CONFLICT DO NOTHING`, no en `03-inserts/`.

### Objetos deliberadamente fuera del modelo

Hay objetos que existen en la base pero **no** en `modelo_pgmodeler.dbm`. Hoy son los constraints
`EXCLUDE USING gist` de `02-other_objects/D/packages/no_solapamiento_roles_y_permisos.sql`:

| Objeto | Tabla |
|---|---|
| `uq_no_solapar_roles` | `usuario.usuario_rol_asignacion` |
| `uq_no_solapar_permisos` | `usuario.usuario_permiso_especial` |

Dependen de la extensión `btree_gist`, que el mismo paquete crea. pgModeler **no puede importar
estos constraints** y falla al leer la base antes de comparar:

```
The object usuario.usuario_rol_asignacion.uq_no_solapar_roles (Constraint), oid 17748,
could not be imported due to one or more errors!
HINT: if the object somehow references objects in pg_catalog or information_schema
consider enable the importing of system/extension objects.
```

El error es **esperable y no rompe el diff**: el constraint tampoco está en el modelo, así que
ambos lados coinciden en no tenerlo y el diff no propone nada sobre él. Se confirma en el
resumen: `Dropped objects: 0`. La tabla `usuario_rol_asignacion` sí se importa bien; sólo falla
el constraint.

> **No habilitar «importing of system/extension objects» para silenciar el error.** Con esa
> opción activa pgModeler *sí* vería el constraint en la base y, al no encontrarlo en el modelo,
> generaría `DROP CONSTRAINT uq_no_solapar_roles` — eliminando una garantía de integridad real.
> El bloqueo de drops de `migracion_desde_diff.ps1` lo detendría, pero es mejor no llegar ahí.

Dos formas de resolverlo de fondo, cuando se quiera:

1. **Modelarlos en pgModeler** (la extensión `btree_gist` y los dos constraints). Ambos lados
   coinciden, el import deja de fallar y desaparece la excepción. Es trabajo en la GUI.
2. **Dejarlos fuera a propósito**, como están, consistente con el diseño de
   `02-other_objects/D/packages/`. Cuesta cero y convive con el error de import.

Mientras se elija (2), esta tabla es la lista de exclusión: cualquier `DROP` que un diff proponga
sobre estos objetos es un falso positivo del flujo, no un cambio deseado.

---

## 3. El flujo, paso a paso

1. **Modificar el `.dbm`** en pgModeler.

2. **Generar el diff** contra la base viva: *Database → Compare (Diff)*, origen = el modelo,
   destino = la base. Guardar en `database-model/docs/bd/diff.sql`.
   Revisar el bloque `[ Diff summary ]` del encabezado: si dice `Dropped objects: N` con N > 0,
   parar y leer §4.

3. **Generar la migración** con el filtro (§4):

   ```powershell
   pwsh -File scripts/migracion_desde_diff.ps1 -Nombre agregar_tabla_mensaje
   ```

4. **Escribir el `down()`** a mano (§5). No dejarlo vacío: hay un test que lo exige.

5. **Revisar el SQL** abriendo el archivo de migración.
   **No usar `php artisan migrate --pretend` para esto:** Laravel colapsa la consulta en una
   sola línea, así que el SQL se vuelve ilegible. (Los comentarios que genera el script son de
   bloque `/* */` justamente para que esa línea no se coma el resto, pero seguir el SQL ahí es
   incómodo de todos modos.)

6. **Aplicar:** `php artisan migrate`. La base se actualiza en sitio; no se borra nada.

7. **Verificar convergencia** (§7): un nuevo Compare/Diff del `.dbm` contra la base recién
   migrada tiene que dar `Created: 0 / Dropped: 0 / Changed: 0`.

8. **Regenerar los artefactos de documentación** y commitear en el submódulo: foto
   (`modelo-foto.png`), doc (`.dbm`, `diccionario_datos-AUTOGENERADO.md`) y sql (`dump.sql`).
   **`01-sql_def.sql` no se toca.**

9. **Commitear en el repo principal** la migración nueva y el puntero actualizado del submódulo.

---

## 4. Filtrado obligatorio del diff

El diff de pgModeler incluye ruido que **no puede entrar en una migración**. Aparece en todos
los diffs generados hasta ahora (2026-07-13 y 2026-07-29):

```sql
ALTER ROLE utamed PASSWORD 'utamed';             -- credencial en texto plano
ALTER DATABASE utamed_1ra_fase OWNER TO utamed;  -- rompe en cualquier otro entorno
ALTER TABLE curso.mensaje OWNER TO postgres;     -- dueño del entorno de modelado
SET check_function_bodies = false;
```

- `ALTER ROLE ... PASSWORD` **resetearía la contraseña de producción** al valor del entorno
  local. Es el ítem más peligroso del diff.
- `ALTER DATABASE ... OWNER TO` asume el nombre de base y el rol locales.
- `ALTER <objeto> ... OWNER TO` arrastra el dueño que quedó en el modelo, que es inconsistente:
  el baseline tiene 12 objetos `OWNER TO postgres` y 37 `OWNER TO utamed`. En una migración el
  dueño correcto es el usuario con el que Laravel conecta en cada entorno, que es justamente el
  que queda si no se dice nada.
  Hoy `utamed` es `SUPERUSER` (`00-db_init.sql`), así que un dueño equivocado no se nota; el día
  que se le quiten esos privilegios para producción, sí.

`scripts/migracion_desde_diff.ps1` elimina estas tres formas y además:

### Bloqueo de sentencias destructivas — lista de permitidos

El script parsea el diff en sentencias (respetando cuerpos `$$...$$`, cadenas `E'...'` y
comentarios `/* */`, para no confundir el `ON DELETE CASCADE` de una FK con un `DELETE`) y
clasifica cada una. **Lo que no reconoce como seguro, bloquea.** No es una lista de prohibidos:
una lista de prohibidos sólo cubre lo que alguien se acordó de enumerar, y dejaba pasar
`ALTER ... DROP CONSTRAINT`, `DROP FUNCTION ... CASCADE` y hasta `DROP OWNED BY`.

Bloquean salvo `-PermitirDrops`: cualquier `DROP`, `TRUNCATE`, `DELETE FROM`,
`ALTER ... DROP COLUMN`, `ALTER ... DROP CONSTRAINT`, y cualquier verbo que el filtro no
conozca. Se avisa aparte cuando la sentencia lleva `CASCADE`.

No bloquean, por relajar una restricción sin perder datos: `ALTER ... DROP DEFAULT`,
`DROP NOT NULL`, `DROP IDENTITY`, `DROP EXPRESSION`.

Ese bloqueo es el punto central: un `DROP COLUMN` generado por un rename mal hecho en pgModeler
es exactamente la pérdida de datos que este flujo existe para prevenir. pgModeler tiende a
representar un rename de columna como drop + add.

> **Renames:** hacerlos siempre a mano como `ALTER TABLE ... RENAME COLUMN ...`, nunca
> aceptando el drop + add que propone el diff.

### Ensayo contra la base real

Después de filtrar, el script **ejecuta el DDL contra la base** dentro de una transacción que
siempre termina en `ROLLBACK` (`scripts/lib/ensayo_ddl.php`). Cada bloque va detrás de un
`SAVEPOINT`; si falla, se revierte sólo ese bloque y la transacción sigue usable. Según lo que
conteste la base:

| Resultado | Qué significa | Qué hace el script |
|---|---|---|
| se aplica sin error | incremento genuino | va a la migración |
| error de duplicado (`42P07`, `42701`, `42710`, `42P06`, `42723`) | el objeto ya existe | queda fuera, y se lista en el docblock |
| cualquier otro error | la migración no funcionaría | **aborta**, no se genera nada |

Esto reemplaza la comparación textual contra `dump.sql` que había antes, y no es un detalle de
implementación: **`dump.sql` describe el modelo, no la base**. Un objeto presente en el dump
pero ausente de la base se descartaba como «ya existe», la migración se registraba como
aplicada y el objeto no se creaba nunca — sin un solo error por ningún lado. La base es la
única autoridad sobre lo que la base tiene.

El ensayo se salta, con aviso, si: se pasa `-SinEnsayo`, no hay un PHP con `pdo_pgsql`, no se
puede conectar, o el diff trae destructivas con `-PermitirDrops` (ahí ejecutar el DDL tomaría
locks pesados sobre la base, aunque después se revierta). En todos esos casos **no se descarta
nada**: la migración sale completa y hay que revisarla entera a mano.

### search_path

El diff trae el `search_path` del entorno de modelado, que empieza por `public`; el de la
aplicación empieza por `usuario`. Inyectar el de pgModeler hacía que cualquier DDL sin calificar
aterrizara en un esquema distinto del que la aplicación espera.

- En una **migración** se elimina: Laravel ya abre la conexión con el `search_path` configurado.
- En **`-SoloSql`** se conserva como `SET search_path` normal. No como `SET LOCAL`: psql aplica
  el archivo fuera de un bloque de transacción, y ahí `SET LOCAL` sólo emite
  `WARNING: SET LOCAL can only be used in transaction blocks` y no hace nada.

---

## 5. El `down()`

`php artisan migrate:rollback` es la red de seguridad en un despliegue fallido. Un `down()`
vacío la anula.

Las migraciones generadas salen con un `down()` que **lanza una excepción a propósito**, para
que nadie lo deje vacío por olvido. Y `tests/Unit/MigracionesTest.php` falla mientras ese
`down()` de plantilla siga ahí, así que el recordatorio no se puede ignorar en silencio. El
mismo test verifica que toda migración defina `up()` y `down()`, y que ninguna llame a
`DB::unprepared()` sobre la conexión por defecto.

Para obtenerlo con pgModeler se invierte el Compare: origen = la base *ya migrada*,
destino = el `.dbm` *anterior* (o al revés, según cuál esté disponible). El diff resultante es
el DDL inverso.

Cuando el cambio es irreversible por naturaleza (se eliminó una columna con datos), el `down()`
debe restaurar la **estructura** y documentar en un comentario que los datos no vuelven.

> **Sobre la conexión:** las migraciones generadas usan
> `DB::connection($this->getConnection())->unprepared(...)` y no `DB::unprepared(...)`. Con
> `php artisan migrate --database=otra`, la segunda forma manda el DDL a la conexión por
> defecto — otra base, y fuera de la transacción que Laravel abrió para esa migración.

---

## 6. Re-baseline periódico

Con el tiempo, `init_scripts` + N migraciones se vuelve lento y difícil de leer. Cada tanto
(por ejemplo, al cerrar una fase) se hace un re-baseline:

1. Confirmar que **todos** los entornos, incluida producción, tienen las migraciones aplicadas
   (`php artisan migrate:status`). Este paso no es opcional: re-basear con un entorno atrasado
   deja ese entorno sin forma de actualizarse.
2. Regenerar `01-sql_def.sql` completo desde pgModeler.
3. Archivar las migraciones ya aplicadas en todas partes (moverlas fuera de
   `database/migrations/`, no borrarlas del historial de git).
4. Anotar el nuevo commit de corte en §1 de este documento.

Una base existente no necesita hacer nada en un re-baseline: ya tiene todo aplicado, y las
migraciones archivadas siguen registradas en la tabla `migrations`.

---

## 7. Verificación de convergencia

El chequeo es el propio Compare/Diff de pgModeler, y es el que cierra el círculo: si después de
migrar el diff del `.dbm` contra la base sale vacío, entonces el diseño y la base coinciden.

```
Compare (Diff): origen = modelo_pgmodeler.dbm, destino = base migrada
Esperado: Created: 0 / Dropped: 0 / Changed: 0
```

Un diff no vacío acá significa una de dos cosas: la migración no cubrió todo el cambio, o
alguien tocó la base por fuera del flujo — **salvo** los falsos positivos de abajo.

### Falsos positivos permanentes del diff

pgModeler propone estos cambios en **todos** los diffs, aunque la base ya esté correcta. No hay
que aplicarlos y no indican divergencia. Verificado el 2026-07-29 contra la base real:

> Desde que existe el ensayo (§4), **no hace falta reconocerlos a mano**: el script los ejecuta
> contra la base, recibe el error de duplicado y los deja fuera de la migración solo. Esta tabla
> queda como explicación de por qué aparecen, no como lista que haya que consultar. Y al revés:
> si alguno de estos objetos *no* estuviera en la base, el ensayo lo detectaría y lo incluiría —
> que es exactamente lo que la comparación contra `dump.sql` no hacía.

| Lo que propone | Por qué es falso |
|---|---|
| `CREATE INDEX idx_data_programa` | Ya existe con definición idéntica (`USING gin (data_syllabus)`). pgModeler no logra importar el índice GIN, así que lo ve como faltante. Aplicarlo aborta el script con «already exists». |
| `ALTER TABLE usuario.usuario ALTER COLUMN fecha_verificacion_email SET DEFAULT NULL` | Postgres **descarta** un `DEFAULT NULL` explícito: 0 entradas en `pg_attrdef`. El modelo lo declara, la base nunca lo guarda, y una construcción desde cero tampoco. Es un no-op eterno. |
| `CREATE OR REPLACE TRIGGER tr_programa_modificado` | Ya existe idéntico (verificado con `pg_get_triggerdef`). Inocuo si se aplica, porque es `CREATE OR REPLACE`. |
| Excepción de import de `uq_no_solapar_roles` / `uq_no_solapar_permisos` | Ver §2, «Objetos deliberadamente fuera del modelo». |

Con estos cuatro descontados, el criterio de convergencia real es: **el diff no propone nada más
que esto**.

Chequeo complementario, más fuerte, para antes de un despliegue: construir una base limpia
(`init_scripts` + `migrate`), hacerle `pg_dump --schema-only`, y compararlo con el
`pg_dump --schema-only` de la base migrada incrementalmente. Deben ser equivalentes. Esto
detecta divergencias que el diff de pgModeler puede pasar por alto (orden de columnas, nombres
de constraints autogenerados).

---

## 8. Producción

**Permitido:** `php artisan migrate`.

**Prohibido:** `soft_reset.ps1`, `reset.ps1`, `rebuild.ps1`, `XX-reset_db.sql`,
`03-inserts/00-truncar_todo.sql`, `php artisan migrate:fresh`, `php artisan db:seed`.

Que el script se llame `soft_reset` no lo hace suave: ejecuta `XX-reset_db.sql`, cuyo propio
encabezado dice *«Script de Limpieza (Hard Reset)»* y hace `DROP DATABASE`. Lo «suave» es que
no borra el volumen de Docker. Los datos se pierden igual.

### La guarda del wrapper

El wrapper `soft_reset.ps1` de la raíz **falla cerrada**: sólo corre si `APP_ENV` está en la
lista de entornos desechables (`local`, `development`, `dev`, `testing`, `test`). Cualquier otro
valor aborta, y un entorno que no se pueda determinar también — antes se asumía desarrollo y se
seguía hasta el `DROP DATABASE`.

El valor se lee replicando lo que hace phpdotenv: `APP_ENV=production # despliegue` vale
`production` para Laravel, y antes no coincidía con ningún patrón, así que la guarda lo dejaba
pasar.

Como lo que el script destruye lo decide `DB_*` y no `APP_ENV`, el wrapper también aborta si
`DB_HOST` no es local, e imprime el destino antes de tocarlo.

Los scripts del submódulo **no** tienen esa protección (ver §9), pero están cableados a
`docker exec test_pgdb`, así que no pueden alcanzar una base remota.

### Reconstrucción de bases desechables

`composer db:soft-reset` y `composer db:soft-reset-testing` pasan por
`scripts/db_reset.ps1`, que hace reset + `migrate` + `db:seed` con **el mismo PHP y la misma
base** en los tres pasos. Antes eran líneas sueltas en `composer.json`: la variante de testing
reseteaba el contenedor de integración y después migraba y sembraba el de **desarrollo**,
porque no existe `.env.testing` (`.env*` está en `.gitignore`) y `APP_ENV=testing` no cambiaba
las `DB_*`. Ahora la configuración de testing se lee de `phpunit.xml`, que sí está versionado.

El binario de PHP se resuelve **antes** de tocar la base, y se exige que tenga `pdo_pgsql`: no
tiene sentido destruirla y descubrir después que no hay con qué migrar (ver §9).

### Antes de migrar en producción

Respaldo, revisar el SQL abriendo el archivo de migración, y recién entonces
`php artisan migrate`.

---

## 9. Pendientes conocidos

- **Credencial expuesta:** `database-model/docs/bd/diff.sql` tiene
  `ALTER ROLE utamed PASSWORD 'utamed';` commiteado (`cb54b51`) en el repo del submódulo.
  Rotar la contraseña y limpiar el archivo.
- **Sin protección en el submódulo:** `reset.ps1`, `soft_reset.ps1` y `rebuild.ps1` de
  `database-model/scripts/` no verifican `APP_ENV`. El submódulo es otro repositorio
  (`codeberg.org/dyrion/test-db-utamed.git`), así que el arreglo se commitea aparte.
- **`utamed` es `SUPERUSER`:** `00-db_init.sql` lo crea con `SUPERUSER CREATEROLE REPLICATION
  BYPASSRLS`. La aplicación conecta con ese rol. Para producción hay que crear un rol acotado;
  al hacerlo, revisar los `GRANT` sobre las tablas nuevas creadas por migraciones (§4).
- **`search_path` divergente:** `config/database.php` usa
  `usuario, agenda, administrativo, curso, public, auditoria, operaciones` mientras
  `00-db_init.sql` fija `pg_catalog, public, administrativo, agenda, curso, usuario, utamed`.
  Las conexiones de Laravel usan el primero, las de `psql`/pgModeler el segundo. Consecuencia:
  la tabla `migrations` la crea Laravel en el esquema **`usuario`**, no en `public`.
  Funciona, pero conviene unificar y calificar explícitamente. **No reordenar sin cuidado:**
  los 94 modelos de `app/Models/` declaran `$table` sin calificar el esquema
  (`protected $table = 'asignatura'`), así que el orden del `search_path` es carga estructural.
- **Usar el PHP correcto para `artisan migrate`:** el `php` del PATH es **herd-lite**
  (`~/.config/herd-lite/bin/php.exe`) y **no tiene `pdo_pgsql`**, así que no puede conectarse a
  Postgres. El de Herd completo sí:

  ```
  C:\Users\yampa\.config\herd\bin\php.bat artisan migrate
  ```

  Con el `php` del PATH, `artisan migrate` falla por driver, no por la migración.

  Los scripts (`db_reset.ps1`, `migracion_desde_diff.ps1`) ya lo resuelven solos: buscan un
  binario con `pdo_pgsql` y abortan con un mensaje claro si no lo encuentran. Se puede fijar uno
  con la variable de entorno `PHP_BINARIO` o con `-Php <ruta>`. Al invocar `artisan` a mano,
  sigue siendo cosa de cada quien.

- **Migración registrada sin archivo en la base de desarrollo:** `usuario.migrations` tiene
  `2026_07_30_100928_agregar_columna_tema_mensaje`, pero el archivo no existe en
  `database/migrations/`. `migrate:status` no lista las migraciones que sólo están en la tabla,
  así que la inconsistencia es invisible. En la práctica: la base de desarrollo tiene la columna
  `curso.mensaje.tema` y la de integración no, porque no está ni en el baseline ni en ninguna
  migración. Hay que decidir si `tema` entra al `.dbm` (y entonces va como migración) o si se
  descarta (y entonces hay que quitar la columna y la fila de `migrations` en desarrollo).
