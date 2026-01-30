<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Usuario\Rol;
use App\Models\Usuario\Permiso;

$roles = Rol::orderBy('nombre')->get();
echo "Total Roles: " . $roles->count() . "\n";
foreach ($roles as $r) {
    echo "- Rol: {$r->nombre} (ID: {$r->id_rol})\n";
}

$perms = Permiso::orderBy('modulo')->orderBy('slug')->get();
echo "Total Permisos: " . $perms->count() . "\n";
$grouped = $perms->groupBy('modulo');
echo "Modules: " . implode(", ", $grouped->keys()->toArray()) . "\n";
foreach ($grouped as $mod => $ps) {
    echo "  Module '{$mod}': " . $ps->count() . " perms\n";
}
