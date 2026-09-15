<?php
/**
 * UTAMED - Script Worker Autónomo de Producción para Reseteo Forzado de Contraseña
 *
 * Este script se ejecuta en el servidor de producción (vía SSH) con acceso a Laravel.
 * No depende de comandos Artisan externos ni requiere despliegues previos en producción.
 */

// Asegurar ejecución solo en CLI
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: Este script debe ser ejecutado en línea de comandos (CLI).\n");
    exit(1);
}

// Colores ANSI
$cReset  = "\033[0m";
$cBold   = "\033[1m";
$cRed    = "\033[31m";
$cGreen  = "\033[32m";
$cYellow = "\033[33m";
$cBlue   = "\033[34m";
$cCyan   = "\033[36m";
$cWhite  = "\033[37m";

// Detectar directorio base de Laravel
$appDir = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--app-dir=')) {
        $appDir = substr($arg, 10);
        break;
    }
}

if (!$appDir || !is_dir($appDir)) {
    $candidatosDirs = [
        '/var/www/prod_utamed',
        '/home/utamed/utamed',
        getcwd(),
        dirname(__DIR__),
    ];
    foreach ($candidatosDirs as $dir) {
        if ($dir && is_file("{$dir}/vendor/autoload.php") && is_file("{$dir}/bootstrap/app.php")) {
            $appDir = $dir;
            break;
        }
    }
}

if (!$appDir || !is_file("{$appDir}/vendor/autoload.php") || !is_file("{$appDir}/bootstrap/app.php")) {
    fwrite(STDERR, "{$cRed}Error: No se pudo localizar la instalación de Laravel (vendor/autoload.php y bootstrap/app.php).{$cReset}\n");
    fwrite(STDERR, "Especifique el directorio con: --app-dir=/ruta/a/laravel\n");
    exit(1);
}

// Inicializar Laravel
echo "{$cCyan}Iniciando entorno Laravel desde: {$appDir}...{$cReset}\n";

require $appDir . '/vendor/autoload.php';
$app = require_once $appDir . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Usuario\Usuario;
use App\Support\Rut;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Función auxiliar para leer entrada del usuario por consola
 */
function promptInput(string $prompt): string
{
    echo $prompt;
    $handle = fopen('php://stdin', 'r');
    $line = fgets($handle);
    fclose($handle);
    return trim((string) $line);
}

/**
 * Extrae el cuerpo numérico del RUT sin DV, sin puntos y sin guion.
 */
function extraerPasswordDesdeRut(string $rut): string
{
    $normalizado = Rut::normalizar($rut);

    if ($normalizado && str_contains($normalizado, '-')) {
        $partes = explode('-', $normalizado);
        return preg_replace('/\D/', '', $partes[0]);
    }

    $limpio = Rut::soloDigitos($rut);
    if (strlen($limpio) >= 2) {
        return substr($limpio, 0, -1);
    }

    return $limpio;
}

/**
 * Busca candidatos según ID, RUT o búsqueda por nombres/email/username.
 */
function buscarUsuarios(string $termino)
{
    $candidatos = collect();

    // 1. Si es numérico puro, buscar por ID exacto
    if (ctype_digit($termino) && (int) $termino > 0) {
        $porId = Usuario::find((int) $termino);

        // Si tiene menos de 7 dígitos y existe por ID, es búsqueda directa por PK
        if ($porId && strlen($termino) < 7) {
            return collect([$porId]);
        }

        if ($porId) {
            $candidatos->push($porId);
        }
    }

    // 2. Si tiene formato de RUT o dígitos de RUT (7+ dígitos), buscar por RUT
    $rutNormalizado = Rut::normalizar($termino);
    if ($rutNormalizado && Rut::esValido($rutNormalizado)) {
        $porRut = Usuario::where('rut', $rutNormalizado)->get();
        $candidatos = $candidatos->merge($porRut);
    }

    $soloDigitos = Rut::soloDigitos($termino);
    if (strlen($soloDigitos) >= 7) {
        $porRutLimpio = Usuario::whereRaw(
            "regexp_replace(lower(rut), '[^0-9k]', '', 'g') = ?",
            [strtolower($soloDigitos)]
        )->get();
        $candidatos = $candidatos->merge($porRutLimpio);

        $porRutPrefijo = Usuario::whereRaw(
            "regexp_replace(lower(rut), '[^0-9k]', '', 'g') like ?",
            [strtolower($soloDigitos) . '%']
        )->get();
        $candidatos = $candidatos->merge($porRutPrefijo);
    }

    // 3. Buscar usando scopeBuscar de Usuario
    $porTexto = Usuario::buscar($termino)->limit(25)->get();
    $candidatos = $candidatos->merge($porTexto);

    return $candidatos->unique('id_usuario')->sortBy('id_usuario')->values();
}

// Bucle interactivo principal
while (true) {
    echo "\n";
    echo "{$cBold}{$cCyan}======================================================================{$cReset}\n";
    echo "{$cBold}{$cWhite}  UTAMED - Reseteo Forzado de Contraseña (Producción){$cReset}\n";
    echo "{$cBold}{$cCyan}======================================================================{$cReset}\n";
    echo "  • Búsqueda por: Nombre, Apellido, RUT (ej. 12.345.678-9 o 12345678) o ID\n";
    echo "  • Escriba 'q' o 'salir' para terminar el script.\n\n";

    $termino = promptInput("{$cBold}Ingrese Nombre, RUT o ID de usuario: {$cReset}");

    if ($termino === '' || strtolower($termino) === 'q' || strtolower($termino) === 'salir') {
        echo "{$cYellow}Saliendo del asistente de reseteo. ¡Hasta luego!{$cReset}\n";
        exit(0);
    }

    $candidatos = buscarUsuarios($termino);

    if ($candidatos->isEmpty()) {
        echo "{$cRed}✖ No se encontraron usuarios coincidentes con '{$termino}'.{$cReset}\n";
        echo "Intente nuevamente con otro nombre, RUT o ID.\n";
        continue;
    }

    /** @var Usuario|null $usuario */
    $usuario = null;

    if ($candidatos->count() === 1) {
        $usuario = $candidatos->first();
        echo "{$cGreen}✔ Se encontró 1 usuario coincidente.{$cReset}\n";
    } else {
        echo "{$cYellow}⚠️ Se encontraron {$candidatos->count()} usuarios coincidentes (posibles colisiones):{$cReset}\n\n";

        printf(" %-4s | %-6s | %-13s | %-32s | %-20s | %-8s\n", '#', 'ID', 'RUT', 'Nombre Completo', 'Username', 'Estado');
        echo str_repeat('-', 95) . "\n";

        foreach ($candidatos as $idx => $cand) {
            $num = $idx + 1;
            $nombreComp = trim("{$cand->nombre1} {$cand->nombre2} {$cand->apellido1} {$cand->apellido2}");
            $estadoStr = $cand->esta_activo ? "{$cGreen}ACTIVO{$cReset}" : "{$cRed}INACTIVO{$cReset}";
            $rutStr = $cand->rut ?? 'S/RUT';

            printf(" [%-2d] | %-6d | %-13s | %-32s | %-20s | %s\n",
                $num,
                $cand->id_usuario,
                $rutStr,
                mb_strimwidth($nombreComp, 0, 32, '...'),
                mb_strimwidth($cand->username ?? '', 0, 20, '...'),
                $estadoStr
            );
        }
        echo str_repeat('-', 95) . "\n";
        echo " [ 0] | Cancelar y volver a buscar\n\n";

        $seleccion = promptInput("{$cBold}Seleccione el número de usuario a modificar (1 - {$candidatos->count()}, 0 para cancelar): {$cReset}");

        if (!ctype_digit($seleccion) || (int) $seleccion === 0) {
            echo "{$cYellow}Operación cancelada. Volviendo al buscador...{$cReset}\n";
            continue;
        }

        $idxSel = (int) $seleccion - 1;
        if ($idxSel < 0 || $idxSel >= $candidatos->count()) {
            echo "{$cRed}Opción inválida. Volviendo al buscador...{$cReset}\n";
            continue;
        }

        $usuario = $candidatos->get($idxSel);
    }

    if (!$usuario) {
        continue;
    }

    // Validar existencia de RUT
    if (empty($usuario->rut)) {
        echo "{$cRed}✖ Error: El usuario ID {$usuario->id_usuario} no tiene RUT registrado. No es posible generar su contraseña basada en RUT.{$cReset}\n";
        continue;
    }

    $nuevaPassword = extraerPasswordDesdeRut($usuario->rut);
    if (empty($nuevaPassword)) {
        echo "{$cRed}✖ Error: No se pudo derivar la contraseña desde el RUT '{$usuario->rut}'.{$cReset}\n";
        continue;
    }

    $nombreCompleto = trim("{$usuario->nombre1} {$usuario->nombre2} {$usuario->apellido1} {$usuario->apellido2}");

    // Ficha de confirmación
    echo "\n";
    echo "{$cBold}{$cYellow}----------------------------------------------------------------------{$cReset}\n";
    echo "{$cBold}CONFIRMACIÓN DE RESETEO DE CONTRASEÑA EN PRODUCCIÓN{$cReset}\n";
    echo "{$cBold}{$cYellow}----------------------------------------------------------------------{$cReset}\n";
    echo "  • ID Usuario:           {$usuario->id_usuario}\n";
    echo "  • Nombre Completo:      {$nombreCompleto}\n";
    echo "  • RUT Registrado:       {$usuario->rut}\n";
    echo "  • Username:             {$usuario->username}\n";
    echo "  • Email:                {$usuario->email}\n";
    echo "  • Estado:               " . ($usuario->esta_activo ? "{$cGreen}ACTIVO{$cReset}" : "{$cRed}INACTIVO{$cReset}") . "\n";
    echo "  • Fecha passhash actual: " . ($usuario->fecha_cambio_passhash?->toDateTimeString() ?? "{$cRed}NULL (Requiere cambio){$cReset}") . "\n";
    echo "  --------------------------------------------------------------------\n";
    echo "  • {$cBold}NUEVA CONTRASEÑA:     {$cGreen}{$nuevaPassword}{$cReset} (RUT sin puntos, guion ni DV)\n";
    echo "  • {$cBold}fecha_cambio_passhash: {$cCyan}NULL{$cReset} (Triggerea cambio obligatorio al ingresar)\n";
    echo "{$cBold}{$cYellow}----------------------------------------------------------------------{$cReset}\n\n";

    $confirmacion = promptInput("{$cBold}{$cRed}¿Confirma forzar el reseteo de este usuario en PRODUCCIÓN? (s/n): {$cReset}");

    if (strtolower($confirmacion) !== 's' && strtolower($confirmacion) !== 'si' && strtolower($confirmacion) !== 'y') {
        echo "{$cYellow}Reseteo cancelado por el operador.{$cReset}\n";
        continue;
    }

    // Aplicar reseteo
    try {
        DB::transaction(function () use ($usuario, $nuevaPassword) {
            $usuario->passhash = Hash::make($nuevaPassword);
            $usuario->fecha_cambio_passhash = null;
            $usuario->save();
        });

        $usuario->refresh();

        echo "\n";
        echo "{$cBold}{$cGreen}======================================================================{$cReset}\n";
        echo "{$cBold}{$cGreen}  ✔ CONTRASEÑA RESETEADA CON ÉXITO{$cReset}\n";
        echo "{$cBold}{$cGreen}======================================================================{$cReset}\n";
        echo "  • Usuario:               {$cBold}{$nombreCompleto}{$cReset} (ID: {$usuario->id_usuario})\n";
        echo "  • RUT:                   {$usuario->rut}\n";
        echo "  • Contraseña Provisoria: {$cBold}{$cGreen}{$nuevaPassword}{$cReset}\n";
        echo "  • Exigir cambio clave:   {$cCyan}ACTIVADO (fecha_cambio_passhash = NULL){$cReset}\n";
        echo "  • Motivo en sistema:     {$cCyan}" . ($usuario->motivoCambioPasswordObligatorio() ?? 'primer_ingreso') . "{$cReset}\n";
        echo "{$cBold}{$cGreen}======================================================================{$cReset}\n\n";
    } catch (\Throwable $e) {
        echo "{$cRed}✖ ERROR al actualizar usuario: " . $e->getMessage() . "{$cReset}\n";
    }

    $otro = promptInput("¿Desea resetear la contraseña de otro usuario? (s/n): ");
    if (strtolower($otro) !== 's' && strtolower($otro) !== 'si' && strtolower($otro) !== 'y') {
        echo "{$cGreen}Finalizando sesión. ¡Operación completada!{$cReset}\n";
        exit(0);
    }
}
