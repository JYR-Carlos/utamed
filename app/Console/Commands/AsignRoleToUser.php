<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;

class AsignRoleToUser extends Command
{
    protected $signature = 'user:assign-role {user_id} {role_id} {--context_id=0}';
    protected $description = 'Asigna un rol a un usuario';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $roleId = $this->argument('role_id');
        $contextId = $this->option('context_id');

        $user = Usuario::find($userId);
        $role = Rol::find($roleId);

        if (!$user) {
            $this->error("Usuario con ID {$userId} no encontrado");
            return 1;
        }

        if (!$role) {
            $this->error("Rol con ID {$roleId} no encontrado");
            return 1;
        }

        // Crear asignación
        $assignment = UsuarioRolAsignacion::create([
            'id_usuario' => $userId,
            'id_rol' => $roleId,
            'id_contexto' => $contextId,
            'esta_activo' => true,
            'fue_eliminado' => false,
            'creado_por' => 1, // Admin
            'fecha_inicio_planificada' => now(),
        ]);

        $this->info("✅ Rol '{$role->nombre}' asignado a '{$user->nombre1}' exitosamente");
        return 0;
    }
}
