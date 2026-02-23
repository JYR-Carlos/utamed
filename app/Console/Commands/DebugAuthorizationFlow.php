<?php

namespace App\Console\Commands;

use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use App\Policies\ProgramaPolicy;
use Illuminate\Console\Command;
use illuminate\Support\Facades\DB;
class DebugAuthorizationFlow extends Command
{
    protected $signature = 'debug:authorization {userId=4} {cursoId=1}';
    protected $description = 'Debugar el flujo de autorización para crear programas';

    public function handle()
    {
        $userId = (int)$this->argument('userId');
        $cursoId = (int)$this->argument('cursoId');

        $this->info(str_repeat("=", 90));
        $this->info("DEBUG: Flujo de Autorización para Crear Programa");
        $this->info("Usuario {$userId} → Curso {$cursoId}");
        $this->info(str_repeat("=", 90));

        // 1. Obtener usuario y curso
        $user = Usuario::find($userId);
        $curso = Curso::find($cursoId);

        if (!$user) {
            $this->error("❌ Usuario no encontrado");
            return 1;
        }

        if (!$curso) {
            $this->error("❌ Curso no encontrado");
            return 1;
        }

        $this->newLine();
        $this->info("👤 USUARIO");
        $this->line(str_repeat("-", 90));
        $this->line("ID: {$user->id_usuario}");
        $this->line("Email: {$user->email}");
        $this->line("Es Admin: " . ($user->is_admin ? "✅ SÍ" : "❌ NO"));

        $this->newLine();
        $this->info("📚 CURSO");
        $this->line(str_repeat("-", 90));
        $this->line("ID: {$curso->id_curso}");
        $this->line("Nombre: {$curso->nombre}");
        $this->line("Contexto ID: {$curso->id_contexto}");

        // 2. Verificar docente
        $this->newLine();
        $this->info("👨‍🏫 DOCENTE");
        $this->line(str_repeat("-", 90));

        $docente = $user->docente;
        if (!$docente) {
            $this->error("❌ El usuario NO es docente");
            return 1;
        }

        $this->line("ID: {$docente->id_docente}");

        // 3. Verificar secciones en el curso
        $this->line("\n📋 SECCIONES EN CURSO {$cursoId}:");
        $seccionesEnCurso = $docente->secciones()
            ->where('id_curso', $cursoId)
            ->count();

        if ($seccionesEnCurso > 0) {
            $this->line("   ✅ Tiene {$seccionesEnCurso} sección(es)");
        } else {
            $this->error("   ❌ NO tiene secciones en este curso");
            return 1;
        }

        // 4. Verificar permisos
        $this->newLine();
        $this->info("🔐 PERMISOS");
        $this->line(str_repeat("-", 90));

        $permisos = DB::table('vw_permisos_usuario')
            ->where('id_usuario', $userId)
            ->where('id_contexto', $curso->id_contexto)
            ->where('slug', 'like', '%programa%')
            ->get();

        if ($permisos->count() > 0) {
            $this->line("✅ Tiene permisos en Contexto {$curso->id_contexto}:");
            foreach ($permisos as $p) {
                $estado = $p->esta_permitido ? "✅" : "❌";
                $this->line("   {$estado} {$p->slug}");
            }
        } else {
            $this->error("❌ NO tiene permisos en Contexto {$curso->id_contexto}");
            
            // Mostrar qué contextos tiene permisos
            $permisosOtros = DB::table('vw_permisos_usuario')
                ->where('id_usuario', $userId)
                ->where('slug', 'like', '%programa%')
                ->get();
            
            if ($permisosOtros->count() > 0) {
                $this->line("\n   📍 Pero tiene permisos en otros contextos:");
                foreach ($permisosOtros as $p) {
                    $this->line("      Contexto {$p->id_contexto}: {$p->slug}");
                }
            }
        }

        // 5. Test la autorización
        $this->newLine();
        $this->info("✅ TEST DE AUTORIZACIÓN");
        $this->line(str_repeat("-", 90));

        $policy = new ProgramaPolicy();

        // Test isAssignedDocenteToCurso (private, pero podemos testear create)
        try {
            $canCreate = $policy->create($user, $curso);
            if ($canCreate) {
                $this->info("✅ ProgramaPolicy::create() → PERMITIDO");
            } else {
                $this->error("❌ ProgramaPolicy::create() → RECHAZADO");
                
                // Debugging adicional
                $this->line("\n📍 Detalles del rechazo:");
                
                // Verificar isAdmin
                $reflection = new \ReflectionClass($policy);
                $method = $reflection->getMethod('isAdmin');
                $method->setAccessible(true);
                $isAdmin = $method->invoke($policy, $user);
                $this->line("  • isAdmin: " . ($isAdmin ? "true" : "false"));
                
                // Verificar isAssignedDocenteToCurso
                $method = $reflection->getMethod('isAssignedDocenteToCurso');
                $method->setAccessible(true);
                $isAssigned = $method->invoke($policy, $user, $curso);
                $this->line("  • isAssignedDocenteToCurso: " . ($isAssigned ? "true" : "false"));
                
                // Verificar getContextId del curso
                $this->line("  • Curso->id_contexto: " . $curso->id_contexto);
            }
        } catch (\Exception $e) {
            $this->error("❌ Error en ProgramaPolicy::create(): {$e->getMessage()}");
            $this->error("   Stack: " . $e->getTraceAsString());
        }

        $this->newLine();
        $this->info(str_repeat("=", 90));

        return 0;
    }
}
