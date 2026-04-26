<?php
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Curso;
use App\Services\SyllabusStructure;

try {
    $curso = Curso::first();
    if (!$curso) {
        echo "Error: No se encontró ningún curso en la base de datos.\n";
        exit(1);
    }
    echo "Curso encontrado: ID " . $curso->id . "\n";
    
    $structure = SyllabusStructure::for($curso);
    echo "Éxito: SyllabusStructure::for(\$curso) ejecutado correctamente.\n";
} catch (\Throwable $e) {
    echo "Error atrapado: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if (strpos($e->getMessage(), "relation") !== false || strpos($e->getMessage(), "property") !== false) {
        echo "Posible error de relación o propiedad faltante.\n";
    }
}

