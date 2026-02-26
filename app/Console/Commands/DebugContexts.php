<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugContexts extends Command
{
    protected $signature = 'debug:contexts';
    protected $description = 'Mostrar estructura de contextos y permisos';

    public function handle()
    {
        $this->info(str_repeat("=", 90));
        $this->info("DEBUG: Estructura de Contextos");
        $this->info(str_repeat("=", 90));

        // 1. Contextos globales
        $this->newLine();
        $this->info("🌍 CONTEXTOS GLOBALES");
        $this->line(str_repeat("-", 90));

        $globales = DB::table('contexto')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('tipo_contexto')
                    ->whereColumn('contexto.id_tipo_contexto', 'tipo_contexto.id_tipo_contexto')
                    ->where('tabla_referenciada', 'GLOBAL');
            })
            ->get();

        foreach ($globales as $ctx) {
            $nombre = $ctx->nombre ?? 'N/A';
            $this->line("  ID: {$ctx->id_contexto} | Nombre: {$nombre}");
        }

        // 2. Contextos de cursos
        $this->newLine();
        $this->info("📚 CONTEXTOS VINCULADOS A CURSOS");
        $this->line(str_repeat("-", 90));

        $cursos = DB::table('curso')
            ->where('fecha_eliminacion', null)
            ->select('id_curso', 'nombre', 'id_contexto')
            ->limit(10)
            ->get();

        foreach ($cursos as $curso) {
            $contexto = DB::table('contexto')
                ->where('id_contexto', $curso->id_contexto)
                ->first();
            
            $nombreContexto = $contexto?->nombre ?? 'N/A';
            $this->line("  Curso ID: {$curso->id_curso} | {$curso->nombre}");
            $this->line("    └─ Contexto ID: {$curso->id_contexto} | {$nombreContexto}");
        }

        // 3. Permisos especiales del usuario 4 por contexto
        $this->newLine();
        $this->info("🔐 PERMISOS DEL USUARIO 4 POR CONTEXTO");
        $this->line(str_repeat("-", 90));

        $permisosPorContexto = DB::table('vw_permisos_usuario')
            ->where('id_usuario', 4)
            ->select('id_contexto', 'slug', 'esta_permitido')
            ->orderBy('id_contexto')
            ->get();

        $agrupados = $permisosPorContexto->groupBy('id_contexto');

        foreach ($agrupados as $ctxId => $perms) {
            $contexto = DB::table('contexto')->where('id_contexto', $ctxId)->first();
            $nombreContexto = $contexto?->nombre ?? 'N/A';
            $this->line("  Contexto ID: {$ctxId} | {$nombreContexto}");
            foreach ($perms as $p) {
                $estado = $p->esta_permitido ? "✅" : "❌";
                $this->line("    {$estado} {$p->slug}");
            }
        }

        // 4. Análisis del problema
        $this->newLine();
        $this->warn("⚠️  ANÁLISIS DEL PROBLEMA");
        $this->line(str_repeat("-", 90));

        $curso1 = DB::table('curso')->where('id_curso', 1)->first();
        $permisosCurso1 = DB::table('vw_permisos_usuario')
            ->where('id_usuario', 4)
            ->where('id_contexto', $curso1->id_contexto)
            ->get();

        $this->line("\n  Curso 1 se encuentra en Contexto: {$curso1->id_contexto}");
        $this->line("  Permisos del usuario 4 en Contexto {$curso1->id_contexto}: {$permisosCurso1->count()}");

        if ($permisosCurso1->count() === 0) {
            $this->error("\n  ❌ PROBLEMA: El usuario NO tiene permisos en el contexto del curso");
            $this->line("\n  SOLUCIONES:");
            $this->line("  1. Asignar permiso 'cursos/programas:*' al usuario 4 en el contexto {$curso1->id_contexto}");
            $this->line("  2. O asignar permisos al usuario en un contexto GLOBAL");
            $this->line("  3. O verificar que el contexto {$curso1->id_contexto} sea el correcto");
        } else {
            $this->info("\n  ✅ Usuario tiene permisos en el contexto del curso");
            foreach ($permisosCurso1 as $p) {
                $this->line("     {$p->slug}");
            }
        }

        $this->newLine();
        return 0;
    }
}
