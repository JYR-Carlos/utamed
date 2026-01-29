<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario\Permiso;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define Permissions by Module
        $permissionsByModule = [
            'Administrativo' => [
                'curso:*' => 'Control total de cursos',
                'curso:crear' => 'Crear cursos',
                'curso:editar' => 'Editar cursos',
                'curso:eliminar' => 'Eliminar cursos',
                'usuario:crear' => 'Crear usuarios',
                'usuario:editar' => 'Editar usuarios',
                'carrera:*' => 'Control total de carreras',
            ],
            'Docencia' => [
                'actividad:*' => 'Gestionar todas las actividades',
                'actividad:crear' => 'Crear actividades de evaluación',
                'actividad:calificar' => 'Calificar actividades',
                'actividad:revisar_alumno' => 'Ver entregas de alumnos',
                'asistencia:registrar' => 'Registrar asistencia',
            ],
            'Ayudantía' => [
                'actividad:calificar_parcial' => 'Calificar ayudantías',
                'asistencia:ver' => 'Ver asistencia',
            ]
        ];

        // 2. Create Permissions
        $allPermissions = [];
        foreach ($permissionsByModule as $module => $perms) {
            foreach ($perms as $slug => $nombre) {
                // Ensure unique slug
                if (!Permiso::where('slug', $slug)->exists()) {
                    $allPermissions[$slug] = Permiso::create([
                        'slug' => $slug,
                        'nombre' => $nombre,
                        'descripcion' => "Permiso del módulo $module ($slug)",
                        'modulo' => $module
                    ]);
                    $this->command->info("Permiso creado: $slug");
                } else {
                    $allPermissions[$slug] = Permiso::where('slug', $slug)->first();
                }
            }
        }

        // 3. Create Roles
        // Ensure we have an admin user to authorize role creation
        $adminUser = Usuario::firstOrCreate(
            ['username' => 'system_admin'],
            [
                'passhash' => bcrypt('admin123'),
                'rut' => '00000000-1',
                'nombre1' => 'System',
                'apellido1' => 'Admin'
            ]
        );

        $rolesDefinitions = [
            'Super Admin' => ['*'], // Wildcard for everything
            'Coordinador' => ['curso:*', 'usuario:crear', 'usuario:editar', 'carrera:*'],
            'Docente' => ['actividad:*', 'asistencia:registrar', 'curso:editar'],
            'Ayudante' => ['actividad:revisar_alumno', 'asistencia:ver', 'actividad:calificar_parcial'],
            'Estudiante' => [] // Students usually don't have dashboard permissions, or very limited ones
        ];

        foreach ($rolesDefinitions as $roleName => $permSlugs) {
            $rol = Rol::firstOrCreate(
                ['nombre' => $roleName],
                ['id_usuario_autor' => $adminUser->id_usuario]
            );

            // Sync permissions
            $permIds = [];
            foreach ($permSlugs as $slug) {
                // If special wildcard '*' used for Admin, give all permissions locally or handle in logic
                // For this seeder, we will attach specific wildcards defined in $allPermissions
                // or creating new if logic requires.
                // NOTE: 'hasPermission' supports matching user's wildcard against requested slug.
                // So here we assign the wildcard permission itself to the role.

                if ($slug === '*') {
                    // Create a system-wide wildcard permission if not exists
                    $wildcard = Permiso::firstOrCreate(['slug' => '*'], [
                        'nombre' => 'Super Admin Access',
                        'descripcion' => 'Acceso total al sistema',
                        'modulo' => 'Sistema'
                    ]);
                    $permIds[] = $wildcard->id_permiso;
                } else {
                    if (isset($allPermissions[$slug])) {
                        $permIds[] = $allPermissions[$slug]->id_permiso;
                    }
                }
            }

            if (!empty($permIds)) {
                $rol->permisos()->syncWithoutDetaching($permIds);
            }
            $this->command->info("Rol configurado: $roleName");
        }
    }
}
