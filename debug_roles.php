<?php

use App\Models\Usuario\UsuarioRolAsignación;
use App\Models\Usuario\Usuario;

// Get user ID 9 based on recent logs, or find by username/rut if known. 
// Let's just dump all assignments for User 9 and User 2 (previous test).

$userIds = [2, 9];

foreach ($userIds as $uid) {
    echo "\n--- Assignments for User ID {$uid} ---\n";
    $user = Usuario::find($uid);
    if (!$user) {
        echo "User {$uid} not found.\n";
        continue;
    }
    echo "Name: {$user->nombre1} {$user->apellido1}\n";

    $assignments = UsuarioRolAsignación::where('id_usuario_recipiente', $uid)
        ->get();

    if ($assignments->isEmpty()) {
        echo "No roles assigned.\n";
    } else {
        foreach ($assignments as $asig) {
            echo "Role ID: {$asig->id_rol} | Context ID: {$asig->id_contexto} | Active: " . ($asig->esta_activo ? 'YES' : 'NO') . " | Assigner: {$asig->id_usuario_asignador} | Deleted: " . ($asig->fue_eliminado ? 'YES' : 'NO') . "\n";
        }
    }
}
