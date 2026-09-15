<?php
/**
 * UTAMED - Lanzador Multiplataforma en PHP para Reseteo Forzado de Contraseña vía SSH
 *
 * Compatible con Windows, Linux y macOS.
 * Uso: php produccion/reset_password.php [opciones]
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: Este script debe ser ejecutado en línea de comandos (CLI).\n");
    exit(1);
}

$cReset  = "\033[0m";
$cBold   = "\033[1m";
$cRed    = "\033[31m";
$cGreen  = "\033[32m";
$cYellow = "\033[33m";
$cCyan   = "\033[36m";

echo "\n";
echo "{$cBold}{$cCyan}======================================================================{$cReset}\n";
echo "{$cBold}  UTAMED - Reseteo Forzado de Contraseña en Producción (SSH){$cReset}\n";
echo "{$cBold}{$cCyan}======================================================================{$cReset}\n";

$scriptDir = __DIR__;
$repoDir = dirname($scriptDir);
$envFile = is_file($repoDir . DIRECTORY_SEPARATOR . '.env.prod')
    ? $repoDir . DIRECTORY_SEPARATOR . '.env.prod'
    : $repoDir . DIRECTORY_SEPARATOR . '.env';

$serverIp = null;
$serverUser = null;
$remoteDir = '/var/www/prod_utamed';
$port = 22;
$identityFile = null;

// Parsear argumentos CLI simples
foreach ($argv as $i => $arg) {
    if (str_starts_with($arg, '--ip=')) {
        $serverIp = substr($arg, 5);
    } elseif (str_starts_with($arg, '--user=')) {
        $serverUser = substr($arg, 7);
    } elseif (str_starts_with($arg, '--dir=')) {
        $remoteDir = substr($arg, 6);
    } elseif (str_starts_with($arg, '--port=')) {
        $port = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--identity=')) {
        $identityFile = substr($arg, 11);
    }
}

// Cargar variables desde .env si existe
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim(trim($v), "\"'");
        if ($k === 'SERVER_IP' && empty($serverIp)) {
            $serverIp = $v;
        }
        if ($k === 'SERVER_USER' && empty($serverUser)) {
            $serverUser = $v;
        }
    }
}

// Solicitar si falta algún dato
if (empty($serverIp)) {
    echo "{$cBold}Ingrese la IP del servidor de producción (SERVER_IP): {$cReset}";
    $serverIp = trim((string) fgets(STDIN));
}

if (empty($serverUser)) {
    echo "{$cBold}Ingrese el usuario SSH del servidor (SERVER_USER): {$cReset}";
    $serverUser = trim((string) fgets(STDIN));
}

if (empty($serverIp) || empty($serverUser)) {
    fwrite(STDERR, "{$cRed}Error: Se requiere SERVER_IP y SERVER_USER para continuar.{$cReset}\n");
    exit(1);
}

$workerFile = $scriptDir . DIRECTORY_SEPARATOR . 'reset_password_worker.php';
if (!is_file($workerFile)) {
    fwrite(STDERR, "{$cRed}Error: No se encontró el worker en: {$workerFile}{$cReset}\n");
    exit(1);
}

echo "• Servidor destino : {$cYellow}{$serverUser}@{$serverIp}{$cReset} (Puerto: {$port})\n";
echo "• Directorio Laravel: {$cYellow}{$remoteDir}{$cReset}\n";
echo "• Preparando sesión interactiva SSH...\n\n";

$workerBytes = file_get_contents($workerFile);
$workerB64 = base64_encode($workerBytes);

$identityArg = ($identityFile && is_file($identityFile)) ? "-i " . escapeshellarg($identityFile) : '';

$remoteCmd = "echo '{$workerB64}' | base64 -d > /tmp/utamed_reset_pwd_worker.php && chmod 644 /tmp/utamed_reset_pwd_worker.php && sudo php /tmp/utamed_reset_pwd_worker.php --app-dir='{$remoteDir}'; rm -f /tmp/utamed_reset_pwd_worker.php";

$sshCmd = sprintf(
    'ssh -t -p %d %s -o StrictHostKeyChecking=accept-new %s@%s %s',
    $port,
    $identityArg,
    escapeshellarg($serverUser),
    escapeshellarg($serverIp),
    escapeshellarg($remoteCmd)
);

// Passthru para heredar directamente STDIN, STDOUT y STDERR del terminal
$exitCode = 0;
passthru($sshCmd, $exitCode);
exit($exitCode);
