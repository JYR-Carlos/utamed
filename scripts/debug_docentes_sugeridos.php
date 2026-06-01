<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;

$asignaturaId = isset($argv[1]) ? (int) $argv[1] : 6;
$nombreBuscado = isset($argv[2]) ? trim((string) $argv[2]) : '';

function fullNameFromUsuario(?Usuario $usuario): string
{
    if (!$usuario) {
        return '';
    }

    return trim(implode(' ', array_filter([
        $usuario->nombre1,
        $usuario->nombre2,
        $usuario->apellido1,
        $usuario->apellido2,
    ], fn ($v) => !is_null($v) && $v !== '')));
}

function normalizeName(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value;
}

$historicos = Docente::with('usuario')
    ->whereHas('usuario', fn ($q) => $q->where('esta_activo', true))
    ->whereHas('docenteComponentes.componente.curso.asignacionPlan', fn ($q) =>
        $q->where('id_asignatura', $asignaturaId)
    )
    ->orderBy('id_docente')
    ->get();

$historicosIds = $historicos->pluck('id_docente');

$otros = Docente::with('usuario')
    ->whereHas('usuario', fn ($q) => $q->where('esta_activo', true))
    ->whereNotIn('id_docente', $historicosIds)
    ->orderBy('id_docente')
    ->get();

$toRow = function (Docente $d): array {
    return [
        'id_docente' => $d->id_docente,
        'id_usuario' => $d->id_usuario,
        'nombre_completo' => fullNameFromUsuario($d->usuario),
        'nombre1' => $d->usuario?->nombre1,
        'nombre2' => $d->usuario?->nombre2,
        'apellido1' => $d->usuario?->apellido1,
        'apellido2' => $d->usuario?->apellido2,
        'email' => $d->usuario?->email,
        'esta_activo' => (bool) ($d->usuario?->esta_activo ?? false),
        'usuario_soft_deleted' => (bool) (Usuario::withTrashed()->find($d->id_usuario)?->fecha_eliminacion ?? false),
    ];
};

$historicosRows = $historicos->map($toRow)->values();
$otrosRows = $otros->map($toRow)->values();

$allRows = $historicosRows->concat($otrosRows)->values();

echo str_repeat('=', 100) . PHP_EOL;
echo 'DEBUG ENDPOINT /admin/asignaturas/{id}/docentes-sugeridos' . PHP_EOL;
echo 'Asignatura ID: ' . $asignaturaId . PHP_EOL;
echo 'Historicos: ' . $historicosRows->count() . ' | Otros: ' . $otrosRows->count() . ' | Total: ' . $allRows->count() . PHP_EOL;
echo str_repeat('=', 100) . PHP_EOL;

if ($nombreBuscado !== '') {
    $needle = normalizeName($nombreBuscado);

    $inEndpoint = $allRows->filter(function (array $row) use ($needle) {
        return normalizeName((string) ($row['nombre_completo'] ?? '')) === $needle;
    })->values();

    $inUsuario = Usuario::withTrashed()
        ->whereRaw("LOWER(TRIM(CONCAT_WS(' ', nombre1, nombre2, apellido1, apellido2))) = ?", [$needle])
        ->get(['id_usuario', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'rut', 'esta_activo', 'fecha_eliminacion']);

    echo 'Nombre buscado: ' . $nombreBuscado . PHP_EOL;
    echo 'Coincidencias exactas en salida endpoint: ' . $inEndpoint->count() . PHP_EOL;
    echo 'Coincidencias exactas en tabla usuario (incluyendo soft-deleted): ' . $inUsuario->count() . PHP_EOL;
    echo str_repeat('-', 100) . PHP_EOL;

    if ($inEndpoint->isNotEmpty()) {
        echo 'Salida endpoint para ese nombre:' . PHP_EOL;
        echo json_encode($inEndpoint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo str_repeat('-', 100) . PHP_EOL;
    }

    if ($inUsuario->isNotEmpty()) {
        echo 'Registros usuario para ese nombre:' . PHP_EOL;
        echo json_encode($inUsuario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo str_repeat('-', 100) . PHP_EOL;
    }
}

$sospechosos = $allRows->filter(function (array $row) {
    return empty($row['id_usuario']) || empty($row['nombre_completo']) || !$row['esta_activo'];
})->values();

echo 'Docentes sospechosos (sin usuario activo/nombre): ' . $sospechosos->count() . PHP_EOL;
if ($sospechosos->isNotEmpty()) {
    echo json_encode($sospechosos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo str_repeat('=', 100) . PHP_EOL;
