<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixUserPermissions extends Command
{
    protected $signature = 'fix:permissions {userId=4} {cursoId=1}';
    protected $description = 'Asignar permisos correctos al usuario para crear programas';

    public function handle()
    {
        $userId = (int)$this->argument('userId');
        $cursoId = (int)$this->argument('cursoId');

        $this->info("=".str_repeat("=", 89));
        $this->info("Asignando permisos correctos al Usuario {$userId} para el Curso {$cursoId}");
        $this->info("=".str_repeat("=", 89));

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

        // 2. Obtener contextos globales
        $contextosGlobales = DB::table('contexto')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('tipo_contexto')
                    ->whereColumn('contexto.id_tipo_contexto', 'tipo_contexto.id_tipo_contexto')
                    ->where('tabla_referenciada', 'GLOBAL');
            })
            ->pluck('id_contexto')
            ->toArray();

        $this->info("\n📍 Contextos GLOBALES: [" . implode(", ", $contextosGlobales) . "]");

        // 3. Asignar permisos
        $this->line("\n🔧 Asignando permisos...\n");

        // Obtener permisos actuales
        $permisosActuales = DB::table('vw_permisos_usuario')
            ->where('id_usuario', $userId)
            ->where('slug', 'like', '%programa%')
            ->select('id_contexto', 'slug')
            ->distinct()
            ->get();

        $this->line("Permisos actuales de PROGRAMA:");
        foreach ($permisosActuales as $p) {
            $this->line("  - Contexto {$p->id_contexto}: {$p->slug}");
        }

        // 4. Verificar si ya tiene permisos en el contexto del curso
        $tienePermisoEnContextoCurso = $permisosActuales
            ->where('id_contexto', $curso->id_contexto)
            ->count() > 0;

        if ($tienePermisoEnContextoCurso) {
            $this->warn("\n⚠️  El usuario YA tiene permisos en el contexto del curso");
            $this->line("   Los permisos debería funcionar");
        } else {
            $this->line("\n❌ El usuario NO tiene permisos en el contexto del curso");
            $this->line("   Contexto requerido: {$curso->id_contexto}");
            
            // 5. Opción: Assignar permisos en contexto global
            if (in_array(1, $contextosGlobales)) {
                $this->warn("\n💡 OPCIÓN 1: Asignar permisos en contexto GLOBAL (recomendado)");
                $this->warn("   Esto permitirá al usuario crear programas en TODOS los cursos");
                
                if ($this->confirm("   ¿Deseas hacerlo?")) {
                    $this->assignGlobalPermissions($userId);
                    return 0;
                }
            }
            
            // 6. Opción: Assignar permisos en contexto específico del curso
            $this->warn("\n💡 OPCIÓN 2: Asignar permisos SOLO en este curso");
            $this->warn("   Contexto en el que se asignará: {$curso->id_contexto}");
            
            if ($this->confirm("   ¿Deseas hacerlo?")) {
                $this->assignCourseContextPermissions($userId, $curso->id_contexto);
                return 0;
            }
        }

        return 0;
    }

    private function assignGlobalPermissions($userId)
    {
        // Asignar permisos globales
        $this->line("\n📝 Asignando permisos en contexto GLOBAL...");
        
        // Obtener contexto global
        $contextoGlobal = DB::table('contexto')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('tipo_contexto')
                    ->whereColumn('contexto.id_tipo_contexto', 'tipo_contexto.id_tipo_contexto')
                    ->where('tabla_referenciada', 'GLOBAL');
            })
            ->first();

        if (!$contextoGlobal) {
            $this->error("❌ No se encontró contexto GLOBAL");
            return;
        }

        $this->line("   ID Contexto Global: {$contextoGlobal->id_contexto}");

        // Obtener el id_permiso para cursos/programas:*
        $permiso = DB::table('usuario.permiso')
            ->where('slug', 'cursos/programas:*')
            ->first();

        if (!$permiso) {
            $this->error("❌ No se encontró el permiso 'cursos/programas:*'");
            return;
        }

        $this->line("   ID Permiso: {$permiso->id_permiso}");

        // Verificar si ya tiene estos permisos
        $existing = DB::table('usuario.usuario_permiso_especial')
            ->where('id_usuario', $userId)
            ->where('id_contexto', $contextoGlobal->id_contexto)
            ->where('id_permiso', $permiso->id_permiso)
            ->first();

        if ($existing) {
            $this->warn("   ⚠️  El usuario ya tiene este permiso asignado");
            return;
        }

        // Crear el permiso
        DB::table('usuario.usuario_permiso_especial')->insert([
            'id_usuario' => $userId,
            'id_contexto' => $contextoGlobal->id_contexto,
            'id_permiso' => $permiso->id_permiso,
            'esta_permitido' => true,
            'puede_delegar' => false,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada' => now()->addYears(10),
            'esta_activo' => true,
            'creado_por' => 1, // Admin user
        ]);

        $this->info("   ✅ Permiso asignado correctamente");
        $this->info("   El usuario ahora puede crear programas en TODOS los cursos");
    }

    private function assignCourseContextPermissions($userId, $contextoId)
    {
        $this->line("\n📝 Asignando permisos en contexto específico...");
        $this->line("   ID Contexto: {$contextoId}");

        // Obtener el id_permiso para cursos/programas:*
        $permiso = DB::table('usuario.permiso')
            ->where('slug', 'cursos/programas:*')
            ->first();

        if (!$permiso) {
            $this->error("❌ No se encontró el permiso 'cursos/programas:*'");
            return;
        }

        $this->line("   ID Permiso: {$permiso->id_permiso}");

        // Verificar si ya tiene estos permisos
        $existing = DB::table('usuario.usuario_permiso_especial')
            ->where('id_usuario', $userId)
            ->where('id_contexto', $contextoId)
            ->where('id_permiso', $permiso->id_permiso)
            ->first();

        if ($existing) {
            $this->warn("   ⚠️  El usuario ya tiene este permiso asignado");
            return;
        }

        // Crear el permiso
        DB::table('usuario.usuario_permiso_especial')->insert([
            'id_usuario' => $userId,
            'id_contexto' => $contextoId,
            'id_permiso' => $permiso->id_permiso,
            'esta_permitido' => true,
            'puede_delegar' => false,
            'fecha_inicio_planificada' => now(),
            'fecha_fin_planificada' => now()->addYears(10),
            'esta_activo' => true,
            'creado_por' => 1, // Admin user
        ]);

        $this->info("   ✅ Permiso asignado correctamente");
        $this->info("   El usuario ahora puede crear programas en este curso");
    }
}
