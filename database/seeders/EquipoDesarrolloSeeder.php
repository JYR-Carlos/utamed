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
    // Arreglo modificable con los datos de los usuarios.
    //
    // OJO CON LOS RUT DE RELLENO: el de Rodrigo era '11111111-1' con
    // es_superadmin => true, y ese RUT ya lo tenia un alumno real. Como el alta de
    // mas abajo es un firstOrCreate por RUT, el seeder no creaba la cuenta del
    // equipo: encontraba al alumno y le entregaba SuperAdmin global. Se saca hasta
    // tener su RUT verdadero; el guardia de abajo impide que vuelva a pasar con
    // otro, pero un RUT inventado sigue siendo una mala idea aqui.
    $equipo = [
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
      // Cuenta institucional solicitada por la contraparte. El RUT es el dato real
      // y vive únicamente aquí; nombre y credenciales son de arranque y se corrigen
      // desde el panel cuando la persona toma posesión de la cuenta.
      [
        'rut' => '70770800-K',
        'nombre1' => 'Cuenta',
        'nombre2' => '',
        'apellido1' => 'Institucional',
        'apellido2' => '',
        'es_superadmin' => true,
      ],
    ];

    // Buscar el rol SuperAdmin en la base de datos
    $rolSuperAdmin = Rol::where('nombre', 'SuperAdmin')->first();
    $superAdmin = Usuario::where('username', 'superadmin')->first();
    if (!$superAdmin || !$rolSuperAdmin) {
      throw new \Exception("El usuario 'superadmin' no existe o el rol 'SuperAdmin' no existe. Asegúrate de ejecutar los seeders correspondientes antes de este.");
    }

    // Asegurar que el usuario base superadmin tenga su rol asignado para permitir autorizaciones en el resto del seeding
    // `fue_eliminado => false` explicito: esto escribe por DB::table y no por
    // Eloquent, asi que no pasa por el default del modelo. Sin fijarla, la columna
    // queda en NULL y la fila se vuelve un fantasma —concede permisos pero no
    // aparece en el listado ni en la baja de roles del panel.
    DB::table('usuario.usuario_rol_asignacion')->updateOrInsert(
      ['id_usuario' => $superAdmin->id_usuario, 'id_rol' => $rolSuperAdmin->id_rol],
      [
        'id_contexto' => 1,
        'esta_activo' => true,
        'fue_eliminado' => false,
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
        // El firstOrCreate de arriba busca por RUT: si el RUT ya existia, este
        // $usuario NO es la cuenta del equipo, es la de otra persona. Conceder
        // SuperAdmin global a una cuenta que tiene perfil de estudiante nunca es
        // lo que este seeder quiere, y es exactamente lo que paso con
        // '11111111-1'. Se avisa fuerte y se sigue sin asignar, en vez de dejarlo
        // pasar en silencio.
        $esAlumno = DB::table('usuario.estudiante')
          ->where('id_usuario', $usuario->id_usuario)
          ->exists();

        if ($esAlumno) {
          $this->command->error(
            "  ! OMITIDO: el RUT {$datos['rut']} corresponde a {$usuario->nombre1} {$usuario->apellido1}, "
              . "que tiene perfil de estudiante. NO se le asigna SuperAdmin. Revisa el RUT en el arreglo \$equipo."
          );
          continue;
        }

        DB::table('usuario.usuario_rol_asignacion')->updateOrInsert(
          [
            'id_usuario' => $usuario->id_usuario,
            'id_rol' => $rolSuperAdmin->id_rol,
          ],
          [
            'id_contexto' => 1,
            'esta_activo' => true,
            'fue_eliminado' => false,
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
