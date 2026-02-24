<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario\Usuario;

class DebugUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:user-roles {username : El username del usuario}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra los roles de un usuario para debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->argument('username');
        $user = Usuario::where('username', $username)->first();

        if (!$user) {
            $this->error("Usuario '$username' no encontrado");
            return 1;
        }

        $this->info("=== DEBUG DE ROLES ===\n");
        $this->info("Usuario: {$user->username} ({$user->nombre1} {$user->apellido1})\n");

        // Obtener todos los roles
        $allRoles = $user->getAllRoles();
        $this->info("Roles retornados por getAllRoles():");
        $this->table(['ID', 'Nombre (normalizado)'], array_map(fn($r) => [$r['id'], $r['nombre']], $allRoles));

        // Verificar hasRole
        $this->info("\nVerificaciones hasRole():");
        foreach (['SuperAdmin', 'Administrador', 'Admin', 'Docente', 'Estudiante', 'Ayudante'] as $role) {
            $result = $user->hasRole($role) ? '✓' : '✗';
            $this->line("  $result hasRole('$role')");
        }

        // Mostrar asignaciones directas de la BD
        $this->info("\nAsignaciones en usuario_rol_asignacion:");
        $assignments = $user->rolesAsignados()
            ->withPivot('esta_activo', 'fue_eliminado', 'id_contexto')
            ->get();

        if ($assignments->isEmpty()) {
            $this->warn("  No hay asignaciones de roles");
        } else {
            foreach ($assignments as $assignment) {
                $status = $assignment->pivot->fue_eliminado ? '[ELIMINADO]' : ($assignment->pivot->esta_activo ? '[ACTIVO]' : '[INACTIVO]');
                $this->line("  - {$assignment->nombre} $status (Context: {$assignment->pivot->id_contexto})");
            }
        }

        return 0;
    }
}
