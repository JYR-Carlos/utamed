<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDocenteSeccionAssignments extends Command
{
    protected $signature = 'check:docente-seccion-assignments';
    protected $description = 'Verifica asignaciones de docentes a secciones y permisos en contextos';

    public function handle()
    {
        $this->info("\n===================================================================================");
        $this->line("🔍 ASIGNACIONES DE DOCENTES A SECCIONES Y PERMISOS EN CONTEXTO");
        $this->info("===================================================================================\n");

        // Obtener docentes con sus secciones
        $query = DB::table('docente')
            ->join('usuario.usuario', 'docente.id_usuario', '=', 'usuario.id_usuario')
            ->leftJoin('curso.seccion', 'docente.id_docente', '=', 'curso.seccion.id_docente')
            ->leftJoin('curso.curso', 'curso.seccion.id_curso', '=', 'curso.curso.id_curso')
            ->select(
                'docente.id_docente',
                'usuario.name',
                'curso.seccion.id_seccion',
                'curso.seccion.id_curso',
                'curso.curso.nombre AS curso_nombre'
            )
            ->orderBy('docente.id_docente')
            ->orderBy('curso.curso.nombre')
            ->get();

        $docentes_data = $query->groupBy('id_docente');

        foreach ($docentes_data as $id_docente => $registros) {
            $nombre = $registros->first()->name;
            
            $this->line("📖 DOCENTE ID: {$id_docente} ({$nombre})");
            
            // Secciones asignadas
            $secciones = $registros->whereNotNull('id_seccion');
            
            if ($secciones->count() > 0) {
                $this->line("   ✅ Asignado a " . $secciones->count() . " secciones:");
                foreach ($secciones as $reg) {
                    $this->line("      - Curso: {$reg->curso_nombre} (ID: {$reg->id_curso})");
                }
            } else {
                $this->line("   ❌ NO asignado a ninguna sección");
            }
            
            // Verificar roles en contextos
            $roles = DB::table('usuario.usuario_rol_asignacion')
                ->join('usuario.rol', 'usuario_rol_asignacion.id_rol', '=', 'usuario.rol.id_rol')
                ->leftJoin('usuario.contexto', 'usuario_rol_asignacion.id_contexto', '=', 'usuario.contexto.id_contexto')
                ->where('usuario_rol_asignacion.id_usuario', DB::table('docente')->where('id_docente', $id_docente)->value('id_usuario'))
                ->where('usuario_rol_asignacion.esta_activo', true)
                ->select(
                    'usuario.rol.nombre AS rol_nombre',
                    'usuario.contexto.tipo AS contexto_tipo',
                    'usuario.contexto.nombre AS contexto_nombre'
                )
                ->get();
            
            if ($roles->count() > 0) {
                $this->line("   Permisos en contextos:");
                foreach ($roles as $rol) {
                    $contexto_info = $rol->contexto_tipo ? "{$rol->contexto_tipo}: {$rol->contexto_nombre}" : "GLOBAL";
                    $this->line("      - Rol: {$rol->rol_nombre} | Contexto: {$contexto_info}");
                }
            } else {
                $this->warn("   ⚠️  NO tiene roles asignados en contextos");
            }
            
            $this->newLine();
        }

        $this->info("===================================================================================");
        $this->info("Total docentes: " . count($docentes_data));
        $this->info("===================================================================================\n");
    }
}
