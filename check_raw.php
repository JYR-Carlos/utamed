<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Usuario\UsuarioPermisoEspecial;

$records = UsuarioPermisoEspecial::all();
foreach ($records as $r) {
    $v = $r->getAttributes()['esta_permitido'] ?? 'NULL';
    echo "User: {$r->id_usuario_recipiente}, Perm: {$r->id_permiso}, Value: {$v}\n";
}
