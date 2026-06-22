<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use Illuminate\Support\Facades\Hash;
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
        'rut' => '11.111.111-1',
        'nombre1' => 'Rodrigo',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => true,
      ],
      [
        'rut' => '22.222.222-2',
        'nombre1' => 'Christian',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
      [
        'rut' => '33.333.333-3',
        'nombre1' => 'Francisco',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
      [
        'rut' => '44.444.444-4',
        'nombre1' => 'Juan',
        'nombre2' => '',
        'apellido1' => 'PA',
        'apellido2' => 'SA',
        'es_superadmin' => false,
      ],
      [
        'rut' => '55.555.555-5',
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

      // Lógica de asignación de rol usando los traits de tu sistema
      if ($datos['es_superadmin'] && $rolSuperAdmin) {

        // Si Rodrigo es el primer SuperAdmin, se asigna a sí mismo el rol
        // para evitar depender de un 'superadmin' genérico que podría no existir aún
        $asignacion = $usuario->giveRole($rolSuperAdmin)
          ->as($superAdmin)
          ->inGlobalContext();

        if ($asignacion) {
          $this->command->info("  - Rol 'SuperAdmin' asignado exitosamente.");
        } else {
          $this->command->error("  - Error al asignar el rol SuperAdmin.");
        }
      }
    }

    $this->command->info("\n📊 Seeder de equipo finalizado.");
  }
}
