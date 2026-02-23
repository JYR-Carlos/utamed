<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MovePermissionToCourseContext extends Command
{
    protected $signature = 'fix:move-permission {userId=4} {cursoId=1}';
    protected $description = 'Mover permiso del contexto global al contexto específico del curso';

    public function handle()
    {
        $userId = (int)$this->argument('userId');
        $cursoId = (int)$this->argument('cursoId');

        $this->info(str_repeat("=", 90));
        $this->info("Moviendo permiso: Usuario {$userId} → Contexto específico del Curso {$cursoId}");
        $this->info(str_repeat("=", 90));

        // 1. Obtener el curso
        $curso = DB::table('curso')
            ->where('id_curso', $cursoId)
            ->first();

        if (!$curso) {
            $this->error("❌ Curso no encontrado");
            return 1;
        }

        $this->info("\n✅ Curso encontrado: {$curso->nombre}");
        $this->info("   Contexto del curso: {$curso->id_contexto}");

        // 2. Obtener el permiso para cursos/programas:*
        $permiso = DB::table('usuario.permiso')
            ->where('slug', 'cursos/programas:*')
            ->first();

        if (!$permiso) {
            $this->error("❌ Permiso 'cursos/programas:*' no encontrado");
            return 1;
        }

        $this->line("   Permiso: {$permiso->slug} (ID: {$permiso->id_permiso})");

        // 3. Remover del contexto global (1)
        $this->line("\n🗑️  Removiendo permiso del contexto GLOBAL (1)...");
        $removidos = DB::table('usuario.usuario_permiso_especial')
            ->where('id_usuario', $userId)
            ->where('id_contexto', 1)
            ->where('id_permiso', $permiso->id_permiso)
            ->delete();

        if ($removidos > 0) {
            $this->line("   ✅ {$removidos} permiso(s) removido(s)");
        } else {
            $this->warn("   ⚠️  No había permisos en el contexto global");
        }

        // 4. Verificar si ya existe en el contexto del curso
        $this->line("\n🔍 Verificando si existe en el contexto del curso ({$curso->id_contexto})...");
        $existing = DB::table('usuario.usuario_permiso_especial')
            ->where('id_usuario', $userId)
            ->where('id_contexto', $curso->id_contexto)
            ->where('id_permiso', $permiso->id_permiso)
            ->first();

        if ($existing) {
            $this->warn("   ⚠️  El usuario ya tiene este permiso en este contexto");
        } else {
            // 5. Asignar al contexto específico del curso
            $this->line("   Asignando al contexto del curso...");
            DB::table('usuario.usuario_permiso_especial')->insert([
                'id_usuario' => $userId,
                'id_contexto' => $curso->id_contexto,
                'id_permiso' => $permiso->id_permiso,
                'esta_permitido' => true,
                'puede_delegar' => false,
                'fecha_inicio_planificada' => now(),
                'fecha_fin_planificada' => now()->addYears(10),
                'esta_activo' => true,
                'creado_por' => 1,
            ]);

            $this->info("   ✅ Permiso asignado al contexto del curso ({$curso->id_contexto})");
        }

        // 6. Resumen
        $this->newLine();
        $this->info("📋 RESUMEN FINAL");
        $this->line(str_repeat("-", 90));

        $permisosActuales = DB::table('vw_permisos_usuario')
            ->where('id_usuario', $userId)
            ->where('slug', 'like', '%programa%')
            ->select('id_contexto', 'slug')
            ->distinct()
            ->get();

        if ($permisosActuales->count() > 0) {
            $this->line("✅ Permisos de PROGRAMA del usuario {$userId}:\n");
            foreach ($permisosActuales as $p) {
                $contexto = DB::table('contexto')->where('id_contexto', $p->id_contexto)->first();
                $this->line("   • Contexto {$p->id_contexto}: {$contexto?->contexto_display}");
                $this->line("     Permisos: {$p->slug}\n");
            }
        } else {
            $this->error("❌ El usuario NO tiene permisos de PROGRAMA");
        }

        $this->info(str_repeat("=", 90));

        return 0;
    }
}
