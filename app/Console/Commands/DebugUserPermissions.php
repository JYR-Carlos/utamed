<?php

namespace App\Console\Commands;

use App\Models\Usuario\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugUserPermissions extends Command
{
    protected $signature = 'debug:permissions {userId=4}';
    protected $description = 'Debug permisos y roles de un usuario';

    public function handle()
    {
        $userId = (int)$this->argument('userId');

        $this->info(str_repeat("=", 90));
        $this->info("DEBUG: Permisos y Roles del Usuario ID: {$userId}");
        $this->info(str_repeat("=", 90));

        // 1. Obtener usuario
        $user = Usuario::find($userId);
        if (!$user) {
            $this->error("❌ Usuario no encontrado");
            return 1;
        }

        $this->newLine();
        $this->info("📋 INFORMACIÓN DEL USUARIO");
        $this->line(str_repeat("-", 90));
        $this->line("  ID: {$user->id_usuario}");
        $this->line("  Nombre: {$user->nombre}");
        $this->line("  Email: {$user->email}");
        $this->line("  Es Admin: " . ($user->is_admin ? "✅ SÍ" : "❌ NO"));

        // 2. Verificar si es docente
        $docente = $user->docente;
        $this->newLine();
        $this->info("👨‍🏫 INFORMACIÓN DE DOCENTE");
        $this->line(str_repeat("-", 90));
        
        if ($docente) {
            $this->line("  ✅ Es DOCENTE");
            $this->line("  ID Docente: {$docente->id_docente}");
            
            // Obtener secciones asignadas
            $secciones = $docente->secciones()
                ->with('curso')
                ->get();
            
            $this->line("  Secciones Asignadas: {$secciones->count()}");
            
            if ($secciones->count() > 0) {
                $this->line("\n  📚 Secciones Detalle:");
                foreach ($secciones as $seccion) {
                    $this->line("     - Sección ID: {$seccion->id_seccion}");
                    $this->line("       Curso ID: {$seccion->id_curso}");
                    if ($seccion->curso) {
                        $this->line("       Curso Nombre: {$seccion->curso->nombre}");
                    }
                }
            } else {
                $this->warn("  ⚠️  El docente NO tiene secciones asignadas");
            }
        } else {
            $this->error("  ❌ NO es DOCENTE");
        }

        // 3. Roles activos
        $this->newLine();
        $this->info("🎭 ROLES ACTIVOS");
        $this->line(str_repeat("-", 90));
        
        $rolesActivos = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->get();

        if ($rolesActivos->count() > 0) {
            $this->line("  Cantidad de roles activos: {$rolesActivos->count()}");
            foreach ($rolesActivos as $rol) {
                $this->line("  ✅ {$rol->nombre}");
            }
        } else {
            $this->error("  ❌ El usuario NO tiene roles activos asignados");
        }

        // 4. Permisos especiales
        $this->newLine();
        $this->info("🔐 PERMISOS ESPECIALES");
        $this->line(str_repeat("-", 90));

        $permisos = DB::table('vw_permisos_usuario')
            ->where('id_usuario', $userId)
            ->get();

        if ($permisos->count() > 0) {
            $this->line("  Total de permisos: {$permisos->count()}");
            
            // Agrupar por contexto
            $porContexto = $permisos->groupBy('id_contexto');
            
            foreach ($porContexto as $contextoId => $permisosContexto) {
                $this->line("\n  📍 Contexto ID: {$contextoId}");
                
                $permitidos = $permisosContexto->where('esta_permitido', 1)->count();
                $denegados = $permisosContexto->where('esta_permitido', 0)->count();
                
                $this->line("     Permitidos: {$permitidos} | Denegados: {$denegados}");
                
                // Mostrar permisos relevantes para programa
                $programaPermisos = $permisosContexto->filter(fn($p) => str_contains($p->slug ?? '', 'programa'));
                
                if ($programaPermisos->count() > 0) {
                    $this->line("     📋 Permisos de PROGRAMA:");
                    foreach ($programaPermisos as $permiso) {
                        $estado = $permiso->esta_permitido ? "✅" : "❌";
                        $this->line("        {$estado} {$permiso->slug}");
                    }
                }
                
                // Mostrar permisos wildcard
                $wildcardPermisos = $permisosContexto->filter(fn($p) => ($p->slug ?? '') === '*');
                if ($wildcardPermisos->count() > 0) {
                    foreach ($wildcardPermisos as $permiso) {
                        $estado = $permiso->esta_permitido ? "✅" : "❌";
                        $this->line("     {$estado} Permiso WILDCARD (*) - Todos los permisos");
                    }
                }
            }
        } else {
            $this->warn("  ⚠️  El usuario NO tiene permisos especiales asignados");
        }

        // 5. Verificar acceso a Curso 1
        $this->newLine();
        $this->info("🎯 VERIFICACIÓN DE ACCESO A CURSO 1");
        $this->line(str_repeat("-", 90));

        $curso = DB::table('curso')
            ->where('id_curso', 1)
            ->where('fecha_eliminacion', null)
            ->first();

        if ($curso) {
            $this->line("  Curso encontrado: {$curso->nombre} (ID: {$curso->id_curso})");
            $this->line("  Contexto del curso: {$curso->id_contexto}");
            
            // Verificar si el docente tiene secciones en este curso
            if ($docente) {
                $seccionesEnCurso = $docente->secciones()
                    ->where('id_curso', $curso->id_curso)
                    ->count();
                
                $this->line("  Secciones del docente en este curso: {$seccionesEnCurso}");
                
                if ($seccionesEnCurso > 0) {
                    $this->line("  ✅ El docente ESTÁ asignado a este curso");
                } else {
                    $this->error("  ❌ El docente NO ESTÁ asignado a este curso");
                }
            }
            
            // Verificar permisos específicos del curso
            $permisosCurso = DB::table('vw_permisos_usuario')
                ->where('id_usuario', $userId)
                ->where('id_contexto', $curso->id_contexto)
                ->where('slug', 'like', '%programa%')
                ->get();
            
            if ($permisosCurso->count() > 0) {
                $this->line("\n  Permisos de PROGRAMA en este contexto:");
                foreach ($permisosCurso as $p) {
                    $estado = $p->esta_permitido ? "✅" : "❌";
                    $this->line("     {$estado} {$p->slug}");
                }
            }
        } else {
            $this->error("  ❌ Curso no encontrado");
        }

        // 6. Resumen de requisitos
        $this->newLine();
        $this->info("✅ RESUMEN DE REQUISITOS PARA CREAR PROGRAMA");
        $this->line(str_repeat("-", 90));

        $requisitos = [
            "Es Administrador" => $user->is_admin,
            "Es Docente" => $docente !== null,
            "Tiene secciones asignadas" => $docente && $docente->secciones()->count() > 0,
            "Tiene permiso 'curso/programa' (crear/editar/eliminar)" => $permisos
                ->filter(fn($p) => str_contains($p->slug ?? '', 'programa') && 
                    (str_contains($p->slug ?? '', 'crear') || 
                     str_contains($p->slug ?? '', 'editar') || 
                     str_contains($p->slug ?? '', 'eliminar') ||
                     str_contains($p->slug ?? '', '*')))
                ->count() > 0,
        ];

        foreach ($requisitos as $requisito => $cumplido) {
            $estado = $cumplido ? "✅" : "❌";
            $this->line("  {$estado} {$requisito}");
        }

        $this->newLine();
        $this->info(str_repeat("=", 90));
        $this->info("Fin del Debug");
        $this->info(str_repeat("=", 90));

        return 0;
    }
}
