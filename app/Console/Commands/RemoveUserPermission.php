<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveUserPermission extends Command
{
    protected $signature = 'fix:remove-permission {id_upe}';
    protected $description = 'Remover un registro específico de usuario_permiso_especial';

    public function handle()
    {
        $id_upe = (int)$this->argument('id_upe');

        $this->info("Removiendo registro id_upe = {$id_upe}...");

        $registro = DB::table('usuario.usuario_permiso_especial')
            ->where('id_upe', $id_upe)
            ->first();

        if (!$registro) {
            $this->error("❌ Registro no encontrado");
            return 1;
        }

        // Mostrar detalles antes de remover
        $contexto = DB::table('contexto')->where('id_contexto', $registro->id_contexto)->first();
        $permiso = DB::table('usuario.permiso')->where('id_permiso', $registro->id_permiso)->first();

        $this->line("📋 Removiendo:");
        $this->line("   Usuario ID: {$registro->id_usuario}");
        $this->line("   Permiso: {$permiso?->slug}");
        $this->line("   Contexto: {$contexto?->contexto_display}");

        // Remover
        DB::table('usuario.usuario_permiso_especial')
            ->where('id_upe', $id_upe)
            ->delete();

        $this->info("✅ Registro removido exitosamente");

        return 0;
    }
}
