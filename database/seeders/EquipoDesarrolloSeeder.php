<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipoDesarrolloSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Arreglo modificable con los datos de los usuarios
    $equipo = [
      [
        'rut' => '11111111-1',
        'nombre1' => 'Rodrigo',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => true,
      ],
      [
        'rut' => '22222222-2',
        'nombre1' => 'Christian',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
      [
        'rut' => '33333333-3',
        'nombre1' => 'Francisco',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
      [
        'rut' => '44444444-4',
        'nombre1' => 'Juan',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
      [
        'rut' => '55555555-5',
        'nombre1' => 'Tomás',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
    ];

    // Buscar el rol SuperAdmin en la base de datos
    $rolSuperAdmin = Rol::where('nombre', 'SuperAdmin')->first();
    $superAdmin = Usuario::where('username', 'superadmin')->first();
    if (!$superAdmin || !$rolSuperAdmin) {
      throw new \Exception("El usuario 'superadmin' no existe o el rol 'SuperAdmin' no existe. Asegúrate de ejecutar los seeders correspondientes antes de este.");
    }

    // Asegurar que el usuario base superadmin tenga su rol asignado para permitir autorizaciones en el resto del seeding
    DB::table('usuario.usuario_rol_asignacion')->updateOrInsert(
      ['id_usuario' => $superAdmin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol],
      [
        'id_contexto' => 1,
        'esta_activo' => true,
        'creado_por' => $superAdmin->id_usuario,
        'asignado_por' => $superAdmin->id_usuario,
        'fecha_inicio_planificada' => now(),
        'fecha_fin_planificada' => now()->addYears(10),
      ]
    );

    foreach ($equipo as $datos) {
      // Generar credenciales base para que pase la validación del modelo
      $username = strtolower(substr($datos['nombre1'], 0, 1) . $datos['apellido1']);
      $email = $username . '@example.com';

      $usuario = Usuario::firstOrCreate(
        ['rut' => $datos['rut']],
        [
          'username' => $username,
          'email' => $email,
          'nombre1' => $datos['nombre1'],
          'nombre2' => $datos['nombre2'],
          'apellido1' => $datos['apellido1'],
          'apellido2' => $datos['apellido2'],
          'passhash' => Hash::make('password'),
          'fecha_verificacion_email' => now(),
          'esta_activo' => true,
          'token_recuerdame_sesion' => Str::random(10),
        ]
      );

      $this->command->info("✓ Usuario {$usuario->nombre1} creado/actualizado.");

      // Asignación directa de rol SuperAdmin en la BD para superadministradores del equipo
      if ($datos['es_superadmin'] && $rolSuperAdmin) {
        DB::table('usuario.usuario_rol_asignacion')->updateOrInsert(
          [
            'id_usuario' => $usuario->id_usuario,
            'id_rol' => $rolSuperAdmin->id_rol,
          ],
          [
            'id_contexto' => 1,
            'esta_activo' => true,
            'creado_por' => $superAdmin->id_usuario,
            'asignado_por' => $superAdmin->id_usuario,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada' => now()->addYears(10),
          ]
        );
        $this->command->info("  - Rol 'SuperAdmin' asignado exitosamente.");
      }
    }

    $this->command->info("\n📊 Seeder de equipo finalizado.");
  }
}
