<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Forzando población de Roles y Permisos...\n";

try {
    DB::transaction(function () {
        // 1. Usuario Autor
        $authorId = DB::table(DB::raw('"utamed.Usuario"."Usuario"'))->where('username', 'system_author')->value('id_usuario');
        if (!$authorId) {
            $authorId = DB::table(DB::raw('"utamed.Usuario"."Usuario"'))->insertGetId([
                'username' => 'system_author',
                'passhash' => 'NO_LOGIN',
                'rut' => '00000000-0',
                'nombre1' => 'System',
                'apellido1' => 'Author',
                'esta_activo' => true
            ], 'id_usuario');
            echo "Usuario 'system_author' creado.\n";
        }

        // 2. Roles
        $roles = ['Super Admin', 'Docente', 'Estudiante', 'Ayudante'];
        foreach ($roles as $roleName) {
            $exists = DB::table(DB::raw('"utamed.Usuario"."Rol"'))->where('nombre', $roleName)->exists();
            if (!$exists) {
                DB::table(DB::raw('"utamed.Usuario"."Rol"'))->insert([
                    'nombre' => $roleName,
                    'id_usuario_autor' => $authorId
                ]);
                echo "Rol '$roleName' creado.\n";
            }
        }

        // 3. Permisos
        $perms = [
            ['slug' => 'curso:*', 'nombre' => 'Control total de cursos', 'descripcion' => 'Administrativo'],
            ['slug' => 'curso:crear', 'nombre' => 'Crear cursos', 'descripcion' => 'Administrativo'],
            ['slug' => 'actividad:*', 'nombre' => 'Gestionar actividades', 'descripcion' => 'Docencia'],
            ['slug' => '*', 'nombre' => 'Super Admin Access', 'descripcion' => 'Sistema']
        ];
        foreach ($perms as $perm) {
            $exists = DB::table(DB::raw('"utamed.Usuario"."Permiso"'))->where('slug', $perm['slug'])->exists();
            if (!$exists) {
                DB::table(DB::raw('"utamed.Usuario"."Permiso"'))->insert($perm);
                echo "Permiso '{$perm['slug']}' creado.\n";
            }
        }

        // 4. Asignación Rol-Permiso (Super Admin -> *)
        $rolId = DB::table(DB::raw('"utamed.Usuario"."Rol"'))->where('nombre', 'Super Admin')->value('id_rol');
        $permId = DB::table(DB::raw('"utamed.Usuario"."Permiso"'))->where('slug', '*')->value('id_permiso');

        if ($rolId && $permId) {
            $exists = DB::table(DB::raw('"utamed.Usuario"."Asignación_Rol_Permiso"'))
                ->where('id_rol', $rolId)
                ->where('id_permiso', $permId)
                ->exists();

            if (!$exists) {
                DB::table(DB::raw('"utamed.Usuario"."Asignación_Rol_Permiso"'))->insert([
                    'id_rol' => $rolId,
                    'id_permiso' => $permId,
                    'puede_delegar_permisos' => true
                ]);
                echo "Asociación Super Admin -> '*' creada.\n";
            }
        }
    });

    echo "Población completada con éxito.\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
