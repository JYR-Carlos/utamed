<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Estado inicial
$initialCount = \App\Models\Curso\Curso::where('estado_interno', 'ABIERTO')
    ->where(function ($query) {
        $query->where('estado_acta', '!=', 'ENVIADO')
            ->orWhereNull('estado_acta');
    })
    ->count();
echo "Inicial: " . $initialCount . "\n";

// 2. Modificar un curso
$curso = \App\Models\Curso\Curso::first();
$originalEstado = $curso->estado_acta;
$curso->estado_acta = 'ENVIADO';
$curso->save();
echo "Modificado curso {$curso->id_curso} a ENVIADO.\n";

// 3. Verificar nuevo conteo
$newCount = \App\Models\Curso\Curso::where('estado_interno', 'ABIERTO')
    ->where(function ($query) {
        $query->where('estado_acta', '!=', 'ENVIADO')
            ->orWhereNull('estado_acta');
    })
    ->count();
echo "Nuevo conteo: " . $newCount . "\n";

// 4. Revertir
$curso->estado_acta = $originalEstado;
$curso->save();
echo "Revertido curso {$curso->id_curso} a {$originalEstado}.\n";

if ($newCount === $initialCount - 1) {
    echo "PRUEBA EXITOSA: El filtro excluyó correctamente el curso.\n";
} else {
    echo "PRUEBA FALLIDA: El conteo no cambió.\n";
}
