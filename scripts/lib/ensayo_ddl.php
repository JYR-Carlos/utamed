<?php

/**
 * Ensayo del DDL de un diff de pgModeler contra la base real.
 *
 * Lo usa scripts/migracion_desde_diff.ps1 para decidir que bloques del diff son
 * incrementos genuinos y cuales ya existen en la base. La autoridad es la base,
 * no un archivo de referencia: preguntarle a un dump si el objeto existe es
 * adivinar, y adivinar de mas hace que un cambio real se pierda en silencio.
 *
 * Todo corre dentro de una transaccion que SIEMPRE termina en ROLLBACK. La base
 * no se modifica: cada bloque se ejecuta tras un SAVEPOINT y, si falla, se
 * revierte solo ese bloque para que la transaccion siga usable. Los bloques que
 * tienen exito se dejan aplicados dentro de la transaccion, de modo que un
 * bloque que depende de otro anterior encuentre lo que necesita.
 *
 * Uso:
 *   php ensayo_ddl.php <entrada.json> <raiz_del_proyecto> [salida.json]
 *
 * Entrada (JSON):
 *   { "preludio": "SET LOCAL search_path=...", "bloques": [ {"id":0,"sql":"..."} ] }
 *
 * Salida (JSON; a <salida.json> si se pasa, si no a stdout):
 *   { "ok": true,
 *     "base": "host:puerto/nombre",
 *     "resultados": [ {"id":0,"estado":"nuevo|existe|error","sqlstate":"..","mensaje":".."} ] }
 *   { "ok": false, "error": "..." }
 */

declare(strict_types=1);

/** SQLSTATE que significan "el objeto ya esta ahi". */
const DUPLICADOS = [
    '42P07', // duplicate_table   (tambien indice, vista, secuencia)
    '42701', // duplicate_column
    '42710', // duplicate_object  (constraint, trigger, tipo)
    '42P06', // duplicate_schema
    '42723', // duplicate_function
    '42P16', // invalid_table_definition (p.ej. PK duplicada)
];

/** Ruta donde depositar el resultado; null = stdout. Se fija apenas se conocen los argumentos. */
$rutaSalida = null;

function emitir(array $carga, int $codigo): never
{
    global $rutaSalida;

    $json = json_encode($carga, JSON_UNESCAPED_UNICODE) . "\n";

    if ($rutaSalida !== null) {
        file_put_contents($rutaSalida, $json);
    } else {
        echo $json;
    }

    exit($codigo);
}

function fallar(string $mensaje): never
{
    emitir(['ok' => false, 'error' => $mensaje], 1);
}

/**
 * Parseo minimo de .env. No arranca Laravel a proposito: el ensayo tiene que
 * poder correr aunque el framework no bootee (cache corrupta, provider roto).
 * Replica el comportamiento de phpdotenv en lo que importa aca: comillas y
 * comentarios al final de la linea.
 */
function leerEnv(string $ruta): array
{
    if (! is_file($ruta)) {
        fallar("no se encontro $ruta");
    }

    $vars = [];
    foreach (file($ruta, FILE_IGNORE_NEW_LINES) as $linea) {
        if (! preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/', $linea, $m)) {
            continue;
        }
        $clave = $m[1];
        $valor = trim($m[2]);

        if (preg_match('/^"(.*)"/s', $valor, $c) || preg_match("/^'(.*)'/s", $valor, $c)) {
            $valor = $c[1];
        } else {
            // Sin comillas, un # inicia comentario.
            $valor = trim(preg_replace('/\s+#.*$/', '', $valor));
        }

        if (! array_key_exists($clave, $vars)) {
            $vars[$clave] = $valor;
        }
    }

    return $vars;
}

// Se fija antes de cualquier fallar() para que los errores tambien lleguen al
// archivo: en Windows el stdout de un .bat pasa por cmd y se come el encoding.
$rutaSalida = $argv[3] ?? null;

$entrada = $argv[1] ?? fallar('falta el argumento <entrada.json>');
$raiz    = $argv[2] ?? fallar('falta el argumento <raiz_del_proyecto>');

$crudo = @file_get_contents($entrada);
if ($crudo === false) {
    fallar("no se pudo leer $entrada");
}

$datos = json_decode($crudo, true);
if (! is_array($datos) || ! isset($datos['bloques'])) {
    fallar("entrada JSON invalida: $entrada");
}

// El entorno real gana sobre el archivo: en CI las credenciales llegan por
// variables de entorno y no hay .env.
$env = leerEnv(rtrim($raiz, '\\/') . '/.env');
$leer = static fn (string $clave, ?string $porDefecto = null): ?string
    => getenv($clave) !== false ? getenv($clave) : ($env[$clave] ?? $porDefecto);

$host   = $leer('DB_HOST', '127.0.0.1');
$puerto = $leer('DB_PORT', '5432');
$base   = $leer('DB_DATABASE');
$user   = $leer('DB_USERNAME');
$pass   = $leer('DB_PASSWORD', '');

if (! $base || ! $user) {
    fallar('faltan DB_DATABASE o DB_USERNAME');
}

if (! extension_loaded('pdo_pgsql')) {
    fallar('este binario de PHP no tiene pdo_pgsql');
}

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$puerto;dbname=$base",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
    );
} catch (PDOException $e) {
    fallar("no se pudo conectar a $host:$puerto/$base -> " . $e->getMessage());
}

$resultados = [];

$pdo->beginTransaction();
try {
    if (! empty($datos['preludio'])) {
        $pdo->exec($datos['preludio']);
    }

    foreach ($datos['bloques'] as $i => $bloque) {
        $id  = $bloque['id'] ?? $i;
        $sql = trim((string) ($bloque['sql'] ?? ''));

        if ($sql === '') {
            $resultados[] = ['id' => $id, 'estado' => 'vacio'];
            continue;
        }

        $punto = 'ensayo_' . $id;
        $pdo->exec("SAVEPOINT $punto");

        try {
            $pdo->exec($sql);
            // Queda aplicado dentro de la transaccion: un bloque posterior que
            // dependa de este tiene que poder verlo.
            $resultados[] = ['id' => $id, 'estado' => 'nuevo'];
        } catch (PDOException $e) {
            $pdo->exec("ROLLBACK TO SAVEPOINT $punto");

            $sqlstate = $e->errorInfo[0] ?? '';
            $resultados[] = [
                'id'       => $id,
                'estado'   => in_array($sqlstate, DUPLICADOS, true) ? 'existe' : 'error',
                'sqlstate' => $sqlstate,
                'mensaje'  => trim((string) ($e->errorInfo[2] ?? $e->getMessage())),
            ];
        }
    }
} finally {
    // Pase lo que pase, la base queda como estaba.
    $pdo->rollBack();
}

emitir([
    'ok'         => true,
    'base'       => "$host:$puerto/$base",
    'resultados' => $resultados,
], 0);
