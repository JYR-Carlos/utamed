<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Total Cursos: " . \App\Models\Curso\Curso::count() . "\n";

$filtrados = \App\Models\Curso\Curso::where('estado_interno', 'ABIERTO')
    ->where(function ($query) {
        $query->where('estado_acta', '!=', 'ENVIADO')
            ->orWhereNull('estado_acta');
    })
    ->count();
echo "Cursos Filtrados (con lógica actual): " . $filtrados . "\n";

echo "\n--- Desglose por Estado Interno ---\n";
$porEstado = \App\Models\Curso\Curso::select('estado_interno', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
    ->groupBy('estado_interno')
    ->get();
foreach ($porEstado as $row) {
    echo " - " . ($row->estado_interno ?? 'NULL') . ": {$row->total}\n";
}

echo "\n--- Desglose por Estado Acta ---\n";
$porActa = \App\Models\Curso\Curso::select('estado_acta', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
    ->groupBy('estado_acta')
    ->get();
foreach ($porActa as $row) {
    echo " - " . ($row->estado_acta ?? 'NULL') . ": {$row->total}\n";
}

echo "\n--- Combinacion (Estado Interno + Estado Acta) ---\n";
$combinado = \App\Models\Curso\Curso::select('estado_interno', 'estado_acta', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
    ->groupBy('estado_interno', 'estado_acta')
    ->orderBy('estado_interno')
    ->get();

foreach ($combinado as $row) {
    echo " - Interno: " . ($row->estado_interno ?? 'NULL') .
        " | Acta: " . ($row->estado_acta ?? 'NULL') .
        " => {$row->total}\n";
}
