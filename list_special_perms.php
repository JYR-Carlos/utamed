<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Usuario\UsuarioPermisoEspecial;
use App\Models\Usuario\Contexto;

$global = Contexto::where('nombre', 'Global')->first();
echo "Global Context ID: " . ($global ? $global->id_contexto : 'NOT FOUND') . "\n";

$records = UsuarioPermisoEspecial::all();
echo "Total records: " . $records->count() . "\n";
foreach ($records as $r) {
    echo "User: {$r->id_usuario_recipiente}, Perm: {$r->id_permiso}, Context: {$r->id_contexto}, Allow: " . ($r->esta_permitido ? 'Y' : 'N') . ", Delegate: " . ($r->puede_delegar ? 'Y' : 'N') . ", Active: " . ($r->esta_activo ? 'Y' : 'N') . "\n";
}
