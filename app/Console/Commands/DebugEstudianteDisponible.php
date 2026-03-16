<?php

namespace App\Console\Commands;

use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugEstudianteDisponible extends Command
{
    protected $signature = 'debug:estudiante-disponible {id_curso} {id_estudiante}';
    protected $description = 'Debug por qué un estudiante no aparece en lista de disponibles';

    public function handle()
    {
        $idCurso = $this->argument('id_curso');
        $idEstudiante = $this->argument('id_estudiante');

        $this->info("\n===========================================");
        $this->line("🔍 DEBUG: Estudiante en lista de disponibles");
        $this->info("===========================================\n");

        // 1. Verificar curso
        $curso = Curso::with('contexto')->find($idCurso);
        if (!$curso) {
            $this->error("❌ Curso no encontrado: $idCurso");
            return;
        }
        $this->line("✅ Curso encontrado: {$curso->nombre}");
        $this->line("   ID Contexto: {$curso->id_contexto}");

        // 2. Verificar estudiante
        $estudiante = Estudiante::find($idEstudiante);
        if (!$estudiante) {
            $this->error("❌ Estudiante no encontrado: $idEstudiante");
            return;
        }
        $this->line("✅ Estudiante encontrado");
        $this->line("   ID: {$estudiante->id_estudiante}");
        $this->line("   ID Usuario: {$estudiante->id_usuario}");
        $this->line("   ID Carrera: {$estudiante->id_carrera}");
        $this->line("   Nombre: {$estudiante->usuario->nombre1} {$estudiante->usuario->apellido1}");

        // 3. Verificar carrera del curso
        $this->newLine();
        $this->line("🔍 Contexto del curso:");
        $contexto = $curso->contexto;
        $maxIter = 0;
        while ($contexto && $maxIter < 10) {
            $this->line("   - Contexto {$contexto->id_contexto}: {$contexto->tipoContexto?->tipo}");
            if ($contexto->tipoContexto && strtolower($contexto->tipoContexto->tipo) === 'carrera') {
                $carrera = $contexto->carrera;
                $this->line("      → CARRERA encontrada: {$carrera->nombre} (ID: {$carrera->id_carrera})");
                break;
            }
            $contexto = $contexto->contextoPadre;
            $maxIter++;
        }

        // 4. Verificar si ya está inscrito
        $yainscrito = InscripcionCurso::where('id_curso', $idCurso)
            ->where('id_estudiante', $idEstudiante)
            ->first();

        $this->newLine();
        if ($yainscrito) {
            $this->warn("⚠️  Estudiante YA está inscrito en el curso");
            $this->line("   Inscripción ID: {$yainscrito->id_inscripcion_curso}");
            $this->line("   Estado: {$yainscrito->estado_inscripcion}");
            $this->line("   Fecha: {$yainscrito->fecha_inscripcion}");
        } else {
            $this->line("✅ Estudiante NO está inscrito (OK para inscribir)");
        }

        // 5. Verificar roles
        $this->newLine();
        $this->line("🔍 Roles del estudiante:");
        $roles = DB::table('usuario_rol_asignacion')
            ->join('usuario.rol', 'usuario_rol_asignacion.id_rol', '=', 'usuario.rol.id_rol')
            ->leftJoin('usuario.contexto', 'usuario_rol_asignacion.id_contexto', '=', 'usuario.contexto.id_contexto')
            ->where('usuario_rol_asignacion.id_usuario', $estudiante->id_usuario)
            ->where('usuario_rol_asignacion.esta_activo', true)
            ->where('usuario_rol_asignacion.fue_eliminado', false)
            ->select(
                'usuario.rol.nombre as rol',
                'usuario.contexto.id_contexto',
                'usuario.contexto.tipoContexto',
                'usuario_rol_asignacion.esta_activo as activo'
            )
            ->get();

        if ($roles->isEmpty()) {
            $this->warn("   ⚠️  Estudiante SIN ROLES asignados");
        } else {
            foreach ($roles as $rol) {
                $this->line("   - {$rol->rol} en contexto {$rol->id_contexto}");
            }
        }

        // 6. Verificar si cumple criterios
        $this->newLine();
        $this->info("=== ANÁLISIS DE DISPONIBILIDAD ===\n");

        $inscritosEnCurso = InscripcionCurso::where('id_curso', $idCurso)
            ->pluck('id_estudiante')
            ->toArray();

        $check1 = !in_array($idEstudiante, $inscritosEnCurso);
        $this->line("1. ¿NO está inscrito en este curso?");
        $this->line("   " . ($check1 ? "✅ SÍ" : "❌ NO (ya inscrito)"));

        $carrreraDelCurso = null;
        if ($curso->contexto) {
            $ctx = $curso->contexto;
            $maxIter = 0;
            while ($ctx && $maxIter < 10) {
                if ($ctx->tipoContexto && strtolower($ctx->tipoContexto->tipo) === 'carrera') {
                    $carrreraDelCurso = $ctx->carrera;
                    break;
                }
                $ctx = $ctx->contextoPadre;
                $maxIter++;
            }
        }

        $check2 = $estudiante->id_carrera && $carrreraDelCurso && ($estudiante->id_carrera === $carrreraDelCurso->id_carrera);
        $this->line("2. ¿Es de la carrera del curso?");
        if ($carrreraDelCurso) {
            $this->line("   Carrera estudiante: {$estudiante->id_carrera}");
            $this->line("   Carrera curso: {$carrreraDelCurso->id_carrera}");
            $this->line("   " . ($check2 ? "✅ SÍ" : "❌ NO (carreras diferentes)"));
        } else {
            $this->line("   ❌ NO SE PUDO DETERMINAR carrera del curso");
        }

        $this->newLine();
        if ($check1 && $check2) {
            $this->line("✅ RESULTADO: Estudiante DEBERÍA aparecer en lista");
            $this->line("   Si no aparece, hay un BUG en la query");
        } else {
            $this->line("❌ RESULTADO: Estudiante NO debería aparecer");
            if (!$check1) {
                $this->line("   → Ya está inscrito");
            }
            if (!$check2) {
                $this->line("   → No es de la carrera del curso");
            }
        }

        $this->info("\n===========================================\n");
    }
}
