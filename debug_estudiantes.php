<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Usuario\Estudiante;

$estudiantes = Estudiante::query()
    ->with('usuario:id_usuario,nombre1,apellido1,username')
    ->orderBy('id_estudiante')
    ->get();

echo "Count: " . $estudiantes->count() . "\n";
foreach ($estudiantes as $estudiante) {
    echo "ID: " . $estudiante->id_estudiante . "\n";
    echo "Usuario: " . ($estudiante->usuario ? $estudiante->usuario->username : 'NULL') . "\n";
}
