<?php
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignación;

$adminRole = Rol::firstOrCreate(['nombre' => 'Administrador'], ['id_usuario_autor' => 1]);
$docenteRole = Rol::firstOrCreate(['nombre' => 'Docente'], ['id_usuario_autor' => 1]);
$alumnoRole = Rol::firstOrCreate(['nombre' => 'Alumno'], ['id_usuario_autor' => 1]);
$ayudanteRole = Rol::firstOrCreate(['nombre' => 'Ayudante'], ['id_usuario_autor' => 1]);

echo "Roles ensures: Admin ({$adminRole->id_rol}), Docente ({$docenteRole->id_rol})\n";

$user = Usuario::find(5); // system_admin (ID 5)

if ($user) {
    UsuarioRolAsignación::updateOrCreate(
        [
            'id_usuario_recipiente' => $user->id_usuario,
            'id_contexto' => 5,
            'id_rol' => $adminRole->id_rol,
            'id_usuario_asignador' => 1
        ],
        [
            'esta_activo' => true,
            'fue_eliminado' => false,
            'fecha_inicio_planificada' => now()
        ]
    );
    echo "Assigned Administrator role to User 5.\n";

    // Assign Docente too for testing
    UsuarioRolAsignación::updateOrCreate(
        [
            'id_usuario_recipiente' => $user->id_usuario,
            'id_contexto' => 5,
            'id_rol' => $docenteRole->id_rol,
            'id_usuario_asignador' => 1
        ],
        [
            'esta_activo' => true,
            'fue_eliminado' => false,
            'fecha_inicio_planificada' => now()
        ]
    );
    echo "Assigned Docente role to User 5.\n";
} else {
    echo "User 5 not found.\n";
}
