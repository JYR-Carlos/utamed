# Flujo de cambios de base de datos en utamed — dossier para análisis con IA

> Documento generado para alimentar una IA externa con el objetivo de **identificar
> puntos de optimización** del proceso de cambio de esquema de base de datos.
> Contiene arquitectura, mecánica exacta de las herramientas (con fragmentos de
> código reales, no resumidos), fricciones conocidas y deuda documentada.
> Fuente primaria: `docs/FLUJO_MIGRACIONES_ESQUEMA.md` del repo + inspección directa
> de `scripts/migracion_desde_diff.ps1`, `scripts/db_reset.ps1`,
> `tests/Unit/MigracionesTest.php`, `config/database.php`, `composer.json` y el
> submódulo `database-model`. Estado verificado: 2026-08-27.

---

## 0. Resumen ejecutivo (para orientar el análisis)

El equipo diseña el esquema en una GUI de modelado (pgModeler), y desde ahí un
pipeline de PowerShell/PHP convierte el diff resultante en una migración de
Laravel, con varias capas de seguridad automatizadas (filtrado de ruido, bloqueo
de sentencias destructivas, ensayo transaccional contra la base real). El diseño
resuelve un problema real (pérdida de datos por reset destructivo) pero tiene
fricción operativa considerable: dependencia de un binario de PHP específico no
descubrible por defecto, dos bases de datos Docker que divergen solas con el
tiempo, un flujo con 9 pasos manuales por cambio de esquema, y al menos una
migración fantasma ya detectada (registrada en la tabla `migrations` sin archivo
correspondiente). Todo esto se detalla abajo con evidencia directa del código.

---

## 1. Arquitectura: las piezas y quién manda sobre qué

El esquema **no vive únicamente en `database/migrations/`** como sería lo estándar
en un proyecto Laravel. Está repartido en dos repositorios git:

- **Repo principal** (Laravel, este repo): `database/migrations/*.php`.
- **Submódulo `database-model`** (repo aparte: `codeberg.org/dyrion/test-db-utamed.git`,
  anclado hoy en `22b29a5b`): contiene el diseño canónico y el baseline.

| Pieza | Rol | Cuándo se ejecuta |
|---|---|---|
| `database-model/source/modelo_pgmodeler.dbm` | Diseño canónico. Toda modificación de esquema **empieza** acá, en la GUI de pgModeler. | nunca directamente |
| `database-model/init_scripts/01-sql_def.sql` | Baseline congelado: estado del esquema al corte. | sólo al crear una base nueva |
| `database/migrations/*.php` | Incrementos. Único mecanismo de cambio desde el corte. | `php artisan migrate` |
| `database-model/init_scripts/02-other_objects/` | Vistas, funciones, triggers (objetos sin estado, `CREATE OR REPLACE`). | re-aplicable siempre |
| `database-model/init_scripts/03-inserts/` | Datos semilla (incluye `00-truncar_todo.sql`, que hace `DELETE FROM` de cada tabla). | sólo en desarrollo |
| `database-model/init_scripts/04-grants.sql` | Privilegios `GRANT ... ON ALL TABLES` — sólo alcanza tablas que existían al ejecutarlo. | al crear una base nueva |
| `docs/bd/modelo-foto.png`, `diccionario_datos-AUTOGENERADO.md`, `dump.sql` | Documentación derivada, se regenera en cada commit del submódulo. | nunca a mano |

Migraciones existentes en el repo principal hoy (sólo 4, y sólo una es cambio de
esquema real vía este flujo — las otras 3 son infraestructura estándar de Laravel):

```
database/migrations/01_sessions_and_passwd_reset_tokens.php
database/migrations/02_create_jobs_table.php
database/migrations/03_create_cache_table.php
database/migrations/04_normaliza_formato_rut_usuario.php   ← data-fix, no DDL de esquema
```

Es decir: **desde que existe el flujo de migraciones, todavía no se ha generado
ninguna migración real de cambio de esquema con `migracion_desde_diff.ps1`** —
el pipeline completo (§3) está construido y documentado pero su primer uso en
producción de código sigue pendiente. (Sí se usó en modo `-SoloSql` para la puesta
al día del 2026-07-29, ver §1.2.)

### 1.1. Por qué existe este diseño

Decisión del usuario, 2026-07-29: **nunca más borrar y recrear la base** para
aplicar un cambio de modelo, porque en producción eso destruye datos. Antes de
esta fecha, la convención documentada en
`database-model/docs/permisos/CAMBIOS_CODIGO_NECESARIOS.md:227` decía que los
cambios de estructura se hacían en SQL puro dentro de `init_scripts/`, lo cual
sólo era viable mientras recrear la base desde cero fuera aceptable.

El baseline quedó congelado en el commit **`625c076`** del submódulo (*feat: tablas
de mensajeria docente-alumno*). Regla que sostiene todo el diseño: **baseline e
incrementos son conjuntos disjuntos**. Desde el corte, `01-sql_def.sql` no se
vuelve a editar a mano ni se le agregan bloques DDL. Motivo: si un cambio entra a
la vez en `01-sql_def.sql` y en una migración, una base nueva lo recibe dos veces
— la migración falla con "ya existe", o peor, aplica algo parcialmente.

Los dos caminos de creación de base convergen al mismo estado final:

```
Base NUEVA:      init_scripts (baseline) ──> artisan migrate (incrementos) ──> db:seed
Base EXISTENTE:                             artisan migrate (incrementos)
```

### 1.2. El precedente: la puesta al día del 2026-07-29

Antes del corte, la base de desarrollo estaba atrasada respecto al `.dbm`: el
diff reportaba 11 objetos "Created" (`idx_data_programa`,
`curso.en_tipo_mensaje_curso`, `curso.mensaje`, `curso.interaccion_mensaje`,
`fn_cascade_fecha_eliminacion`, un trigger y FKs) que en realidad **ya estaban en
el baseline** — no eran incremento genuino, era la base pidiendo alcanzar el
corte. Se aplicó con `-SoloSql` (no como migración versionada, porque hacerlo así
habría duplicado esos objetos en cualquier base nueva) y dejó la base en 43 tablas
(desde 41), sin perder los 141 usuarios existentes.

---

## 2. Qué entra en una migración y qué no

Distinción central: **si el objeto tiene estado que se puede perder**.

- **Con estado → migración incremental obligatoria.** Esquemas, tablas, columnas,
  tipos (`CREATE TYPE`), constraints, índices, secuencias.
- **Sin estado → se regeneran completos.** Vistas, funciones, triggers — son
  `CREATE OR REPLACE` y se pueden reaplicar sobre una base viva sin tocar datos.
  Dos excepciones a vigilar:
  - Cambiar la **firma** de una función exige `DROP FUNCTION` previo (`CREATE OR
    REPLACE` no puede cambiar tipos de parámetro ni de retorno) → eso sí va en
    migración.
  - `datos_usuarios_roles.sql` y `inscribir_estudiante_componente_curso.sql`
    **no** usan `CREATE OR REPLACE` — hay que revisarlos antes de confiar en
    reaplicarlos ciegamente.
- **Datos semilla → nunca en producción.** Un cambio de datos de catálogo que
  deba llegar a producción va en migración con `INSERT ... ON CONFLICT DO
  NOTHING`, no en `03-inserts/`.

### 2.1. Objetos deliberadamente fuera del modelo pgModeler

Dos constraints `EXCLUDE USING gist` (`uq_no_solapar_roles` sobre
`usuario.usuario_rol_asignacion`, `uq_no_solapar_permisos` sobre
`usuario.usuario_permiso_especial`, definidos en
`02-other_objects/D/packages/no_solapamiento_roles_y_permisos.sql`) dependen de
la extensión `btree_gist` y **pgModeler no puede importarlos** al comparar contra
la base — falla con un error de import esperado (`could not be imported due to
one or more errors`). Es inofensivo mientras nadie active "importing of
system/extension objects" en pgModeler: si se activa, pgModeler vería el
constraint, no lo encontraría en el modelo, y generaría un `DROP CONSTRAINT` que
eliminaría una garantía de integridad real (el bloqueo de drops del script lo
frenaría, pero es mejor no acercarse).

---

## 3. El flujo paso a paso (documentado en `docs/FLUJO_MIGRACIONES_ESQUEMA.md`)

1. Modificar el `.dbm` en pgModeler (GUI, manual).
2. Generar el diff contra la base viva: *Database → Compare (Diff)*, origen =
   modelo, destino = base → guardar en `database-model/docs/bd/diff.sql`. Revisar
   el bloque `[ Diff summary ]`: si `Dropped objects: N` con N > 0, parar y leer §4.
3. Generar la migración filtrada:
   ```powershell
   pwsh -File scripts/migracion_desde_diff.ps1 -Nombre agregar_tabla_mensaje
   ```
4. Escribir el `down()` a mano — la plantilla generada lanza una excepción
   deliberadamente y hay un test que falla mientras eso siga así (§5).
5. Revisar el SQL abriendo el archivo de migración directamente. **No usar
   `php artisan migrate --pretend`**: Laravel colapsa la consulta a una línea y
   se vuelve ilegible.
6. Aplicar: `php artisan migrate`. Actualiza en sitio, no borra nada.
7. Verificar convergencia (§4.4): un nuevo Compare/Diff del `.dbm` contra la
   base recién migrada debe dar `Created: 0 / Dropped: 0 / Changed: 0`.
8. Regenerar los artefactos de documentación en el submódulo (foto, `.dbm`,
   diccionario autogenerado, `dump.sql`) y commitear ahí. `01-sql_def.sql` **no
   se toca**.
9. Commitear en el repo principal la migración nueva + el puntero actualizado
   del submódulo.

**9 pasos manuales por cambio de esquema**, de los cuales 3 (1, 2 y parte del 7)
dependen de operar una GUI de escritorio (pgModeler) — no son scriptables desde
CI.

---

## 4. Las salvaguardas de `scripts/migracion_desde_diff.ps1` (mecánica exacta)

Este es el componente más elaborado del sistema. No es un simple "diff → PHP":

### 4.1. Tokenizador SQL propio

El script implementa un parser de sentencias SQL a mano (función
`Split-SentenciasSql`) porque un barrido línea por línea confunde un `ON DELETE
CASCADE` de una foreign key con un `DELETE`, y puede partir un `DROP` en dos
líneas sin darse cuenta. Reconoce: comentarios `--` y `/* */` (anidables),
cadenas `'...'` con escapes `\` y comillas dobladas `''`, identificadores
`"..."`, y cuerpos delimitados por dólar (`$$...$$` / `$tag$...$tag$`, para
cuerpos de función).

### 4.2. Clasificación por lista de PERMITIDOS (no de prohibidos)

```powershell
$verbosSeguros = @(
    'CREATE', 'COMMENT', 'SET', 'GRANT', 'REVOKE', 'INSERT', 'UPDATE',
    'DO', 'SELECT', 'WITH', 'ANALYZE', 'REFRESH', 'CALL', 'BEGIN', 'COMMIT', 'SAVEPOINT'
)
```

Cualquier verbo no reconocido bloquea. Antes era una lista de 6 prohibidos y
dejaba pasar `DROP CONSTRAINT`, `DROP FUNCTION ... CASCADE` y `DROP OWNED BY`
sin avisar. Bloquean salvo `-PermitirDrops`: `DROP *`, `TRUNCATE`, `DELETE
FROM`, `ALTER ... DROP COLUMN`, `ALTER ... DROP CONSTRAINT`. **No** bloquean
(relajan una restricción sin perder datos): `DROP DEFAULT`, `DROP NOT NULL`,
`DROP IDENTITY`, `DROP EXPRESSION`. Motivo del bloqueo: pgModeler representa un
*rename* de columna como `DROP COLUMN` + `ADD COLUMN` — exactamente la pérdida
de datos que el flujo existe para prevenir. **Los renames se hacen siempre a
mano** con `ALTER TABLE ... RENAME COLUMN ...`, nunca aceptando lo que propone
el diff.

### 4.3. Filtrado de ruido de pgModeler

Aparece en **todos** los diffs generados hasta ahora (2026-07-13 y 2026-07-29):

```sql
ALTER ROLE utamed PASSWORD 'utamed';             -- credencial en texto plano
ALTER DATABASE utamed_1ra_fase OWNER TO utamed;  -- rompe en cualquier otro entorno
ALTER TABLE curso.mensaje OWNER TO postgres;     -- dueño del entorno de modelado
SET check_function_bodies = false;
```

- `ALTER ROLE ... PASSWORD` es lo más peligroso: **resetearía la contraseña del
  entorno destino** al valor local. El script lo elimina con regex.
- `ALTER DATABASE ... OWNER TO` asume nombre de base y rol locales — eliminado.
- `ALTER <objeto> ... OWNER TO`: el baseline tiene 12 objetos `OWNER TO postgres`
  y 37 `OWNER TO utamed`, inconsistente. El dueño correcto en una migración es
  quien conecta Laravel en cada entorno — que es justamente lo que queda si no
  se dice nada. Eliminado por regex (cuenta reportada en consola).
- `ALTER TABLE ... ALTER COLUMN ... SET DEFAULT NULL`: Postgres **descarta** un
  `DEFAULT NULL` explícito (no llega a `pg_attrdef`) — es un no-op eterno que
  reaparece en todos los diffs. Eliminado.
- `SET check_function_bodies`: ajuste de sesión de pgModeler. Eliminado.
- `SET search_path=...`: el diff trae el `search_path` del entorno de modelado
  (empieza por `public`); la app usa
  `usuario, agenda, administrativo, curso, public, auditoria, operaciones`
  (`config/database.php:49`, con override por `DB_SEARCH_PATH`). En una
  **migración** se elimina (Laravel ya abre la conexión con el search_path
  correcto). En modo `-SoloSql` se conserva como `SET` normal — no `SET LOCAL`,
  porque `psql` aplica el archivo fuera de una transacción y `SET LOCAL` ahí
  sólo emite un warning sin efecto.

### 4.4. Ensayo transaccional contra la base real (el diseño central)

Reemplazó una comparación textual contra `dump.sql` que **perdía cambios en
silencio**: un objeto presente en el dump pero ausente de la base se descartaba
como "ya existe", la migración se registraba como aplicada y el objeto nunca se
creaba — sin ningún error visible. Regla resultante: *"para saber qué tiene la
base, hay que preguntarle a la base"*.

Mecánica (`scripts/lib/ensayo_ddl.php`, invocado como proceso PHP separado con
input/output JSON por archivos temporales):

- Cada bloque de DDL va detrás de un `SAVEPOINT` dentro de una transacción que
  **siempre** termina en `ROLLBACK` — nada se compromete de verdad.
- Precede con `SET LOCAL search_path=<el de la app>`.
- Los bloques con `CREATE INDEX CONCURRENTLY` se excluyen del ensayo (no puede
  correr dentro de una transacción) — quedan marcados `sin-ensayar`, no se
  descartan de la migración.
- Según la respuesta de la base:

  | Resultado | Interpretación | Acción del script |
  |---|---|---|
  | se aplica sin error | incremento genuino | va a la migración |
  | error de duplicado (`42P07`, `42701`, `42710`, `42P06`, `42723`) | el objeto ya existe | queda fuera, listado en el docblock |
  | cualquier otro error | la migración no funcionaría | **aborta la generación entera**, exit 1 |

- El ensayo se salta (con aviso, sin descartar nada) si: `-SinEnsayo`, no hay
  PHP con `pdo_pgsql`, no hay conexión, o el diff trae destructivas con
  `-PermitirDrops` (ejecutar DDL destructivo tomaría locks pesados incluso
  revirtiendo después — no vale el riesgo en una base compartida).

### 4.5. Heurísticas de advertencia (no bloquean, sólo avisan)

El script detecta por patrón y avisa en consola/docblock, sin frenar la
generación:

- `ALTER COLUMN ... TYPE`: puede truncar o fallar según los datos existentes.
- `SET NOT NULL`: falla si hay filas `NULL` — puede necesitar `UPDATE` previo en
  la misma migración.
- `ADD CONSTRAINT`: falla si los datos existentes lo violan.
- `CREATE INDEX` (sin `CONCURRENTLY`): bloquea escrituras en la tabla; en
  producción evaluar `CONCURRENTLY` (requiere migración fuera de transacción).
- `CREATE TABLE`: aviso explícito de que `04-grants.sql` usa `GRANT ... ON ALL
  TABLES`, que sólo alcanza las tablas que existían al ejecutarlo — **hay que
  verificar a mano los privilegios de la tabla nueva**.
- `ADD COLUMN ... NOT NULL` sin `DEFAULT`: falla si la tabla ya tiene filas.

### 4.6. Otras protecciones del script

- Detecta colisión con el delimitador heredoc (`SQL_PGMODELER`) antes de
  generar, para no producir PHP inválido.
- Verifica que no exista ya una migración con el mismo slug de nombre.
- Evita pisar un archivo generado en el mismo segundo (sufijo numérico).
- **Hace lint del PHP generado** (`php -l`) antes de dar por exitosa la
  generación: si no compila, borra el archivo y falla.

---

## 5. El `down()` obligatorio

`php artisan migrate:rollback` es la red de seguridad de un despliegue fallido;
un `down()` vacío la anula. Las migraciones generadas salen con:

```php
public function down(): void
{
    throw new RuntimeException(
        'down() sin implementar en '.basename(__FILE__).': el rollback no esta disponible.'
    );
}
```

Y **`tests/Unit/MigracionesTest.php`** hace cumplir esto automáticamente con 3
tests sobre *todas* las migraciones del proyecto:

1. Ninguna contiene la cadena `down() sin implementar` (detecta la plantilla
   sin completar).
2. Toda migración define `up()` y `down()` (regex sobre la firma).
3. Ninguna llama a `DB::unprepared()` **a secas** (código sin comentarios,
   tokenizado con `token_get_all` para no confundirse con el texto del
   docblock que menciona el propio patrón prohibido) — deben usar
   `DB::connection($this->getConnection())->unprepared(...)`, porque con
   `artisan migrate --database=otra` la forma corta manda el DDL a la conexión
   por defecto, fuera de la transacción que Laravel abrió para esa migración.

Para obtener el DDL inverso: invertir el Compare en pgModeler (origen = base ya
migrada, destino = `.dbm` anterior). Si el cambio es irreversible (columna
eliminada con datos), el `down()` debe restaurar la **estructura** y documentar
en comentario que los datos no vuelven — ver ejemplo real en
`database/migrations/04_normaliza_formato_rut_usuario.php`, cuyo `down()` es un
no-op documentado a propósito.

---

## 6. Producción vs. entornos desechables

**Permitido en producción:** sólo `php artisan migrate`.

**Prohibido en producción:** `soft_reset.ps1`, `reset.ps1`, `rebuild.ps1`,
`XX-reset_db.sql`, `03-inserts/00-truncar_todo.sql`, `migrate:fresh`, `db:seed`.
El nombre "soft" es engañoso: internamente corre `XX-reset_db.sql`, cuyo propio
encabezado dice *"Script de Limpieza (Hard Reset)"* y hace `DROP DATABASE`. Lo
único "suave" es que no borra el volumen Docker — los datos se pierden igual.

### 6.1. La guarda del wrapper `soft_reset.ps1`

Falla cerrado: sólo corre si `APP_ENV` está en `local, development, dev,
testing, test`; cualquier otro valor (incluido "no se pudo determinar") aborta
— antes se asumía desarrollo por defecto y seguía hasta el `DROP DATABASE`. El
valor de `APP_ENV` se lee replicando el parseo de phpdotenv (para que
`APP_ENV=production # despliegue` cuente como `production`, no matchee ningún
patrón por accidente y quede sin protección). También aborta si `DB_HOST` no es
local, e imprime el destino antes de tocar nada.

**Los scripts del submódulo** (`reset.ps1`, `soft_reset.ps1`, `rebuild.ps1` en
`database-model/scripts/`) **no tienen esta protección** — están cableados a
`docker exec test_pgdb`, así que no pueden alcanzar una base remota, pero el
gap de diseño está documentado como pendiente (§8).

### 6.2. `scripts/db_reset.ps1` — reconstrucción de bases desechables

Punto de entrada de `composer db:soft-reset` / `db:soft-reset-testing`. Antes
eran líneas sueltas en `composer.json` que invocaban `php` pelado (del PATH,
sin `pdo_pgsql`) y en modo testing **reseteaban el contenedor de integración
pero migraban y sembraban el de desarrollo** — bug de diseño ya corregido: hoy
el binario de PHP se resuelve **antes** de tocar la base (no tiene sentido
destruirla y descubrir después que no hay con qué migrar), y las tres fases
(reset → `artisan migrate --force` → `artisan db:seed --force`) usan
consistentemente el mismo PHP y la misma base.

```powershell
pwsh -File scripts/db_reset.ps1              # desarrollo
pwsh -File scripts/db_reset.ps1 -Testing      # integración (lee phpunit.xml)
```

---

## 7. Verificación de convergencia

El chequeo es el propio Compare/Diff de pgModeler tras migrar:

```
Compare (Diff): origen = modelo_pgmodeler.dbm, destino = base migrada
Esperado: Created: 0 / Dropped: 0 / Changed: 0
```

### 7.1. Falsos positivos permanentes (4, documentados y verificados 2026-07-29)

| Lo que propone | Por qué es falso |
|---|---|
| `CREATE INDEX idx_data_programa` | Ya existe idéntico (`USING gin`); pgModeler no logra importar índices GIN y los ve como faltantes. |
| `ALTER COLUMN fecha_verificacion_email SET DEFAULT NULL` | Postgres descarta un `DEFAULT NULL` explícito — no-op eterno (ver §4.3). |
| `CREATE OR REPLACE TRIGGER tr_programa_modificado` | Ya existe idéntico; inocuo si se aplica por ser `CREATE OR REPLACE`. |
| Excepción de import de `uq_no_solapar_roles` / `uq_no_solapar_permisos` | Ver §2.1. |

Desde que existe el ensayo (§4.4) no hace falta reconocerlos a mano — el
script ya los descarta al recibir el error de duplicado de la base. La tabla
queda como explicación, no como checklist.

### 7.2. Chequeo complementario recomendado (más fuerte, no automatizado)

Construir una base limpia (`init_scripts` + `migrate`), `pg_dump --schema-only`,
y compararlo contra el `pg_dump --schema-only` de la base migrada
incrementalmente. Deben ser equivalentes. Esto detecta divergencias que el
diff de pgModeler puede pasar por alto (orden de columnas, nombres de
constraints autogenerados). **No hay script ni CI que automatice esto hoy.**

---

## 8. Deuda y pendientes explícitamente documentados en el flujo

Extraídos literalmente de `docs/FLUJO_MIGRACIONES_ESQUEMA.md` §9:

1. **Credencial expuesta:** `database-model/docs/bd/diff.sql` tiene `ALTER ROLE
   utamed PASSWORD 'utamed';` commiteado (commit `cb54b51`) en el repo del
   submódulo. Pendiente: rotar la contraseña y limpiar el archivo del historial.
2. **Sin protección en el submódulo:** los scripts de reset del submódulo no
   verifican `APP_ENV` (mitigado hoy sólo porque apuntan a un contenedor local
   fijo).
3. **`utamed` es `SUPERUSER`:** creado así en `00-db_init.sql`
   (`SUPERUSER CREATEROLE REPLICATION BYPASSRLS`), y la aplicación conecta con
   ese rol. Para producción hace falta un rol acotado, y al crearlo revisar los
   `GRANT` sobre tablas nuevas de migraciones (relacionado con la advertencia
   de `CREATE TABLE` en §4.5 — `04-grants.sql` no cubre tablas creadas después).
4. **`search_path` divergente por diseño:** Laravel usa
   `usuario, agenda, administrativo, curso, public, auditoria, operaciones`;
   `00-db_init.sql` fija `pg_catalog, public, administrativo, agenda, curso,
   usuario, utamed` para conexiones `psql`/pgModeler. Consecuencia concreta: la
   tabla `migrations` la crea Laravel en el esquema **`usuario`**, no en
   `public`. Funciona, pero es carga estructural: los 94 modelos de
   `app/Models/` declaran `$table` sin calificar esquema, así que el orden del
   `search_path` no se puede reordenar sin auditar todos.
5. **Dependencia de un PHP no-default:** el `php` del PATH es *herd-lite*
   (`~/.config/herd-lite/bin/php.exe`) y no trae `pdo_pgsql` — no puede conectar
   a Postgres. Hay que usar el de Herd completo
   (`~/.config/herd/bin/php.bat`). Los scripts del flujo (`db_reset.ps1`,
   `migracion_desde_diff.ps1`) ya resuelven esto solos (buscan un binario con
   `pdo_pgsql`, o aceptan `-Php <ruta>` / variable `PHP_BINARIO`), pero
   cualquier invocación manual de `artisan` queda a criterio de cada persona —
   fuente frecuente de falsos "no se puede conectar a la base".
6. **Migración fantasma:** `usuario.migrations` tiene registrada
   `2026_07_30_100928_agregar_columna_tema_mensaje`, pero **el archivo no existe**
   en `database/migrations/`. `migrate:status` no expone migraciones que sólo
   están en la tabla, así que la inconsistencia es invisible por esa vía. Efecto
   medido: la base de desarrollo tiene `curso.mensaje.tema`, la de integración
   no — porque la columna no está ni en el baseline ni en ninguna migración
   versionada. Decisión pendiente: si `tema` entra al `.dbm` (→ va como
   migración real) o se descarta (→ hay que quitar la columna y la fila de
   `migrations` en desarrollo).

---

## 9. Fricciones operativas verificadas (no listadas en el documento del flujo, encontradas en sesiones de trabajo previas)

Estas provienen de experiencia directa trabajando con el sistema, no del
documento de diseño:

- **Dos contenedores Postgres que no comparten esquema y se desincronizan
  solos:** `test_pgdb` (puerto 15432, el que usa la app vía `.env`) y
  `test_pgdb_integration` (puerto 16666, el que usa Pest vía `phpunit.xml` con
  `force="true"`). El volumen Docker sólo corre `init_scripts` en su primera
  creación — cualquier cambio posterior al baseline deja la base de integración
  atrasada hasta el próximo `composer db:soft-reset-testing` manual. **No
  existe forma de aplicar sólo la columna faltante con `artisan migrate`**
  porque el esquema base no vive en migraciones, vive en `init_scripts`. Se
  verificó el gap concreto dos veces (2026-08-10 y 2026-08-14): faltaba
  `curso.mensaje.tema` y `curso.inscripcion_componente.cod_inscripcion_curso_uta`.
  El segundo gap rompía en cascada toda la inscripción automática con
  `SQLSTATE[25P02] transacción abortada` en consultas posteriores al error real,
  disfrazando la causa.
- **El propio comando de resincronización falla en su último paso:**
  `composer db:soft-reset-testing` termina con exit code 1 por
  `SQLSTATE[23505] llave duplicada («uq_plan_asignatura», ...)` en
  `CarreraDisenioMultimediaSeeder` — bug de idempotencia del seeder, no
  relacionado con el reset de esquema. El esquema sí queda al día antes de
  llegar a ese paso, pero el código de salida no distingue "falló el reset" de
  "falló sólo el seed cosmético", lo que puede generar falsos negativos en
  cualquier automatización que mire el exit code.
- **Línea base de fallos de test que no son regresión:** la suite Unit tiene
  21 fallos permanentes (12 en `WildcardMatcherTest`, 9 en
  `ContextTypeEnumTest`, por casos de enum inexistentes) independientes de
  cualquier cambio de BD. Sin este dato documentado en algún lugar visible
  para el equipo, cualquier persona nueva puede perder tiempo diagnosticando
  fallos preexistentes como si los hubiera causado.
- **Medición 2026-08-16** (rama `admin`, árbol limpio) como referencia de la
  magnitud del problema de desincronización:

  | Suite | Resultado | Causa dominante |
  |---|---|---|
  | Unit | 204 pasan / 21 fallan / 2 omitidos | enums inexistentes (preexistente) |
  | Feature | 43 pasan / 44 fallan | `mensaje.tema` (10), columna `id` inexistente (6), resto aserciones |
  | Integration | 40 pasan / 251 fallan | 225 por violaciones de unicidad (**la suite no reinicia datos entre corridas**) + 36 por tabla `archivo` inexistente |

  La suite Integration en particular no es sólo un problema de esquema — no
  hay reset de datos entre corridas, así que reejecutarla acumula estado.

---

## 10. Preguntas abiertas sugeridas para el análisis de optimización

Para orientar a la IA que reciba este documento, algunos ángulos concretos que
vale la pena que explore:

1. **¿Puede eliminarse la dependencia de una GUI de escritorio (pgModeler) del
   camino crítico?** Los pasos 1, 2 y 7 del flujo (§3) requieren operar
   pgModeler manualmente — no es scriptable, no corre en CI, y es un cuello de
   botella de una sola persona con esa herramienta instalada.
2. **¿El ensayo transaccional (§4.4) podría correr automáticamente en un hook
   pre-commit o en CI**, en vez de depender de que alguien corra el script a
   mano antes de subir la migración?
3. **¿Vale la pena automatizar la resincronización de `test_pgdb_integration`**
   (por ejemplo, en un hook de arranque de test o un healthcheck de CI) en vez
   de depender de que alguien note el desfase y corra
   `db:soft-reset-testing` manualmente?
4. **¿El bug de idempotencia de `CarreraDisenioMultimediaSeeder` (§9) debería
   arreglarse antes que cualquier otra optimización**, dado que hoy contamina
   el exit code de la herramienta de resincronización oficial?
5. **¿Conviene resolver ya el re-baseline pendiente** (columna `tema`
   fantasma, §8.6) antes de que se acumulen más migraciones huérfanas de este
   tipo, en vez de dejarlo como deuda?
6. **¿El chequeo complementario de `pg_dump --schema-only` (§7.2), hoy manual
   y no automatizado, debería ser parte de CI** para dar la garantía fuerte de
   convergencia que el diff de pgModeler no siempre da?
7. **¿La resolución de "qué PHP usar" (§8.5) debería estandarizarse** con una
   variable de entorno de proyecto o un `.tool-versions`, en vez de que cada
   persona lo descubra por error de conexión?

---

## Referencias de archivos citados en este documento

- `docs/FLUJO_MIGRACIONES_ESQUEMA.md` — documento fuente del flujo completo.
- `scripts/migracion_desde_diff.ps1` — generador de migraciones desde el diff.
- `scripts/lib/ensayo_ddl.php` — ejecuta el ensayo transaccional.
- `scripts/db_reset.ps1` — reset + migrate + seed unificado.
- `soft_reset.ps1` (raíz) — wrapper con la guarda de `APP_ENV`/`DB_HOST`.
- `tests/Unit/MigracionesTest.php` — enforcement automático del `down()`.
- `database/migrations/04_normaliza_formato_rut_usuario.php` — ejemplo real de
  migración con `down()` irreversible documentado.
- `config/database.php:49` — `search_path` de la conexión de Laravel.
- `composer.json` — scripts `db:soft-reset`, `db:soft-reset-testing`,
  `db:soft-reset-linux`.
- `database-model/` (submódulo) — `init_scripts/`, `source/modelo_pgmodeler.dbm`,
  `docs/bd/diff.sql`.
