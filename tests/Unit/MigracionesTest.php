<?php

/**
 * Guardas sobre database/migrations/.
 *
 * `php artisan migrate:rollback` es la red de seguridad de un despliegue
 * fallido. Las migraciones que genera scripts/migracion_desde_diff.ps1 salen con
 * un down() que lanza una excepcion a proposito, para que nadie lo deje vacio
 * por olvido; este test es lo que hace que ese recordatorio no se pueda ignorar.
 */

use Illuminate\Support\Facades\File;

/** @return array<string, string> ruta relativa => contenido */
function migracionesDelProyecto(): array
{
    $dir = base_path('database/migrations');

    if (! is_dir($dir)) {
        return [];
    }

    $archivos = [];
    foreach (File::files($dir) as $archivo) {
        if ($archivo->getExtension() !== 'php') {
            continue;
        }
        $archivos['database/migrations/'.$archivo->getFilename()] = $archivo->getContents();
    }

    return $archivos;
}

test('ninguna migracion queda con el down() sin implementar', function () {
    $pendientes = [];

    foreach (migracionesDelProyecto() as $ruta => $contenido) {
        if (str_contains($contenido, 'down() sin implementar')) {
            $pendientes[] = $ruta;
        }
    }

    expect($pendientes)->toBe([], sprintf(
        "Estas migraciones todavia tienen el down() de plantilla:\n  - %s\n\n".
        "Completalo antes de mergear. Para obtener el DDL inverso, invertir el Compare\n".
        "en pgModeler: origen = la base ya migrada, destino = el .dbm anterior.\n".
        "Ver docs/FLUJO_MIGRACIONES_ESQUEMA.md seccion 5.",
        implode("\n  - ", $pendientes)
    ));
});

test('toda migracion define up() y down()', function () {
    $incompletas = [];

    foreach (migracionesDelProyecto() as $ruta => $contenido) {
        if (! preg_match('/function\s+up\s*\(/', $contenido)
            || ! preg_match('/function\s+down\s*\(/', $contenido)) {
            $incompletas[] = $ruta;
        }
    }

    expect($incompletas)->toBe([], 'Migraciones sin up() o sin down(): '.implode(', ', $incompletas));
});

/**
 * Codigo de un archivo PHP sin comentarios.
 *
 * Los docblocks de las migraciones generadas explican por que NO hay que llamar
 * a DB::unprepared() a secas, asi que un grep sobre el texto crudo se encuentra
 * a si mismo. Lo que importa es lo que se ejecuta.
 */
function codigoSinComentarios(string $contenido): string
{
    $codigo = '';

    foreach (token_get_all($contenido) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $codigo .= $token[1];
            continue;
        }
        $codigo .= $token;
    }

    return $codigo;
}

test('las migraciones generadas desde pgModeler usan la conexion de la migracion', function () {
    // DB::unprepared() a secas va a la conexion por defecto: con
    // `artisan migrate --database=otra` el DDL se ejecutaria en la base
    // equivocada y fuera de la transaccion que Laravel abrio.
    $sueltas = [];

    foreach (migracionesDelProyecto() as $ruta => $contenido) {
        if (preg_match('/\bDB\s*::\s*unprepared\s*\(/', codigoSinComentarios($contenido))) {
            $sueltas[] = $ruta;
        }
    }

    expect($sueltas)->toBe([], sprintf(
        "Estas migraciones llaman a DB::unprepared() sobre la conexion por defecto:\n  - %s\n\n".
        'Usar DB::connection($this->getConnection())->unprepared(...).',
        implode("\n  - ", $sueltas)
    ));
});
