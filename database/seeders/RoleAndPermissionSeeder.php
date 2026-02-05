<?php

namespace Database\Seeders;

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignación;
use App\Models\Usuario\Contexto;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Crear contexto Global (usado por permisos administrativos)
        $contextoGlobal = Contexto::firstOrCreate(
            ['contexto_display' => 'Global'],
        );


        // Crear permisos
        Permiso::firstOrCreate(
            ['slug' => 'curso:*'],
            ['nombre' => 'Control total de cursos', 'descripcion' => 'Administrativo']
        );
        Permiso::firstOrCreate(
            ['slug' => 'curso:crear'],
            ['nombre' => 'Crear cursos', 'descripcion' => 'Administrativo']
        );
        Permiso::firstOrCreate(
            ['slug' => 'actividad:*'],
            ['nombre' => 'Gestionar actividades', 'descripcion' => 'Docencia']
        );
        Permiso::firstOrCreate(
            ['slug' => '*'],
            ['nombre' => 'Super Admin Access', 'descripcion' => 'Sistema']
        );

        // Crear usuario admin
        $admin = Usuario::firstOrCreate(
            ['username' => 'system_admin'],
            [
                'rut' => '00000000-1',
                'nombre1' => 'System',
                'apellido1' => 'Admin',
                'passhash' => bcrypt('admin123'),
                'email' => 'admin@utamed.local',
                'esta_activo' => true,
            ]
        );

        // Crear rol Super Admin
        $rol = Rol::firstOrCreate(
            ['nombre' => 'Super Admin'],
            ['creado_por' => $admin->id_usuario]
        );

        // Asignar rol al admin
        $now = now();
        $future = now()->addYears(100);

        UsuarioRolAsignación::updateOrCreate(
            [
                'id_usuario' => $admin->id_usuario,
                'id_contexto' => $contextoGlobal->id_contexto,
                'id_rol' => $rol->id_rol,
            ],
            [
                'asignado_por' => $admin->id_usuario,
                'fecha_inicio_planificada' => $now,
                'fecha_fin_planificada' => $future,
                'esta_activo' => true,
                'fue_eliminado' => false,
                'creado_por' => $admin->id_usuario,
                'fecha_creacion' => $now,
            ]
        );
    }
}