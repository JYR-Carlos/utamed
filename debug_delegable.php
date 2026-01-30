<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioPermisoEspecial;

$user = Usuario::find(2);
if (!$user) {
    echo "User 2 not found\n";
    exit;
}

// 1. Roles
$rolePerms = $user->rolesAsignados()
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->with([
        'rol.permisos' => function ($query) {
            $query->wherePivot('puede_delegar_permisos', true);
        }
    ])
    ->get()
    ->pluck('rol.permisos')
    ->flatten()
    ->unique('id_permiso');

echo "Role delegable perms count: " . $rolePerms->count() . "\n";

// 2. Special
$specialPerms = UsuarioPermisoEspecial::where('id_usuario_recipiente', $user->id_usuario)
    ->where('esta_activo', true)
    ->where('fue_borrado', false)
    ->where('esta_permitido', true)
    ->where('puede_delegar', true)
    ->with('permiso')
    ->get()
    ->pluck('permiso');

echo "Special delegable perms count: " . $specialPerms->count() . "\n";
foreach ($specialPerms as $p) {
    if ($p) {
        echo "- Perm: {$p->slug} (ID: {$p->id_permiso})\n";
    } else {
        echo "- NULL Perm found!\n";
    }
}

$all = $rolePerms->concat($specialPerms)->unique('id_permiso')->values();
echo "Total delegable perms: " . $all->count() . "\n";
