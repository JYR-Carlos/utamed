<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use DB;

class DebugElisaRoles extends Command
{
    protected $signature = 'debug:elisa-roles';
    protected $description = 'Debug roles de Elisa para ver por qué sale "Sin roles asignados"';

    public function handle()
    {
        $this->info('🔍 Buscando usuario "Elisa"...');

        // Buscar por nombre
        $usuarios = Usuario::where('nombre1', 'ILIKE', '%elisa%')
            ->orWhere('email', 'ILIKE', '%elisa%')
            ->get();

        if ($usuarios->isEmpty()) {
            $this->error('❌ No se encontró usuario con nombre "Elisa"');
            return;
        }

        foreach ($usuarios as $user) {
            $this->line("\n" . str_repeat('=', 80));
            $this->info("Usuario: {$user->nombre1} {$user->apellido1}");
            $this->info("Email: {$user->email} | Username: {$user->username}");
            $this->info("ID Usuario: {$user->id_usuario}");

            // Cargar roles con toda la info
            $user->load([
                'rolesAsignados' => fn($q) => $q
                    ->with(['usuario' => fn($q) => $q->select('id_usuario', 'nombre1', 'apellido1')])
            ]);

            $this->line("\n📋 ROLES ASIGNADOS (sin filtrar):");
            if ($user->rolesAsignados->isEmpty()) {
                $this->warn("  ⚠️  Sin roles asignados");
            } else {
                foreach ($user->rolesAsignados as $rol) {
                    $this->line("  • {$rol->nombre}");
                    $this->line("    - ID Rol: {$rol->id_rol}");
                    $this->line("    - esta_activo: " . ($rol->pivot->esta_activo ? '✅ true' : '❌ false'));
                    $this->line("    - fue_eliminado: " . ($rol->pivot->fue_eliminado ? '❌ true' : '✅ false'));
                    $this->line("    - id_contexto: {$rol->pivot->id_contexto}");
                }
            }

            $this->line("\n🔎 REVISANDO tabla usuario_rol_asignacion directamente:");
            $asignaciones = DB::table('usuario_rol_asignacion')
                ->where('id_usuario', $user->id_usuario)
                ->get();

            if ($asignaciones->isEmpty()) {
                $this->warn("  ⚠️  No hay registros en usuario_rol_asignacion");
            } else {
                foreach ($asignaciones as $asig) {
                    $rol = Rol::find($asig->id_rol);
                    $this->line("  • Rol ID {$asig->id_rol}: " . ($rol?->nombre ?? 'DELETED'));
                    $this->line("    - esta_activo: " . ($asig->esta_activo ? '✅ true' : '❌ false'));
                    $this->line("    - fue_eliminado: " . ($asig->fue_eliminado ? '❌ true' : '✅ false'));
                    $this->line("    - id_contexto: {$asig->id_contexto}");
                }
            }

            $this->line("\n💼 PERFIL DOCENTE:");
            if ($user->docente) {
                $this->info("  ✅ SÍ es docente");
                $this->line("    - ID Docente: {$user->docente->id_docente}");
            } else {
                $this->warn("  ❌ NO es docente");
            }

            $this->line("\n🎓 PERFIL ESTUDIANTE:");
            if ($user->estudiante) {
                $this->info("  ✅ SÍ es estudiante");
                $this->line("    - ID Estudiante: {$user->estudiante->id_estudiante}");
            } else {
                $this->warn("  ❌ NO es estudiante");
            }

            // Mostrar qué se vería en el middleware Inertia
            $this->line("\n📱 LO QUE VE EL MIDDLEWARE INERTIA:");
            $user->load([
                'rolesAsignados' => fn($q) => $q->where('esta_activo', true)
                    ->where('fue_eliminado', false),
                'docente',
                'estudiante',
            ]);

            $roles = $user->rolesAsignados
                ->pluck('nombre')
                ->values()
                ->toArray();

            $this->info("  roles array: " . json_encode($roles));
            $this->info("  roles count: " . count($roles));

            if (empty($roles)) {
                $this->error("  ❌ RESULTADO: 'Sin roles asignados' (Empty roles array)");
            } else {
                $this->info("  ✅ RESULTADO: Se mostrarían los roles normalmente");
            }
        }

        $this->line("\n" . str_repeat('=', 80));
    }
}
