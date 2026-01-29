<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\UsuarioRolAsignación;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Support\Facades\DB;

// Force real prepares
DB::connection()->getPdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

try {
    DB::beginTransaction();

    $time = time();
    $user = Usuario::create([
        'username' => 'testuser_' . $time,
        'passhash' => 'hash',
        'rut' => 'RUT_' . $time,
        'nombre1' => 'Test',
        'apellido1' => 'User',
        'esta_activo' => true
    ]);

    $context = Contexto::first() ?: Contexto::create(['nombre' => 'Global']);
    $perm = Permiso::create(['slug' => 'p1_' . $time, 'nombre' => 'Perm1']);
    $rol = Rol::create(['nombre' => 'Rol1', 'id_usuario_autor' => $user->id_usuario]);
    $rol->permisos()->attach($perm->id_permiso);

    echo "1. Role check: " . ($user->hasPermission($perm->slug, $context->id_contexto) ? 'FAILED' : 'SUCCESS') . "\n";

    UsuarioRolAsignación::create([
        'id_usuario_recipiente' => $user->id_usuario,
        'id_contexto' => $context->id_contexto,
        'id_rol' => $rol->id_rol,
        'id_usuario_asignador' => $user->id_usuario,
        'fecha_inicio_planificada' => now(),
    ]);

    echo "2. Assigned role check: " . ($user->hasPermission($perm->slug, $context->id_contexto) ? 'SUCCESS' : 'FAILED') . "\n";

    UsuarioPermisoEspecial::create([
        'id_usuario_recipiente' => $user->id_usuario,
        'id_permiso' => $perm->id_permiso,
        'id_contexto' => $context->id_contexto,
        'id_usuario_asignador' => $user->id_usuario,
        'esta_permitido' => false,
    ]);

    echo "3. Special revoke check: " . (!$user->hasPermission($perm->slug, $context->id_contexto) ? 'SUCCESS' : 'FAILED') . "\n";

    DB::rollBack();
    echo "Verification COMPLETE\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
