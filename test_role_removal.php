<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignación;

$id_usuario = 2; // Example user
$id_contexto = 5; // Global

echo "Roles for User 2 before:\n";
$roles = UsuarioRolAsignación::where('id_usuario_recipiente', $id_usuario)
    ->where('id_contexto', $id_contexto)
    ->where('esta_activo', true)
    ->get();
foreach ($roles as $r) {
    echo "- Rol ID: {$r->id_rol}, Admin: {$r->id_usuario_asignador}\n";
}

echo "Attempting to deactivate roles...\n";
UsuarioRolAsignación::where('id_usuario_recipiente', $id_usuario)
    ->where('id_contexto', $id_contexto)
    ->where('esta_activo', true)
    ->update(['esta_activo' => false, 'fue_eliminado' => true, 'fecha_fin_real' => now()]);

echo "Roles for User 2 after:\n";
$rolesAfter = UsuarioRolAsignación::where('id_usuario_recipiente', $id_usuario)
    ->where('id_contexto', $id_contexto)
    ->where('esta_activo', true)
    ->get();
echo "Count active: " . $rolesAfter->count() . "\n";
foreach ($rolesAfter as $r) {
    echo "- Rol ID: {$r->id_rol}, Admin: {$r->id_usuario_asignador}\n";
}
