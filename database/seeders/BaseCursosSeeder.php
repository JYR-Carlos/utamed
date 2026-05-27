<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Curso\Curso;
use App\Models\Curso\Componente;
use App\Models\Curso\Unidad;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;

class BaseCursosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los tipos de componente disponibles
        $tiposComponente = TipoComponente::all();

        if ($tiposComponente->isEmpty()) {
            $this->command->warn('No hay tipos de componente en la BD. Ejecuta TipoComponenteSeeder primero.');
            return;
        }

        // Obtener todas las asignaciones de plan válidas
        $asignacionesPlanes = AsignacionPlan::all();

        if ($asignacionesPlanes->isEmpty()) {
            $this->command->warn('No hay asignaciones de plan en la BD.');
            return;
        }

        $cursosCreados = 0;
        $componentesCreados = 0;
        $unidadesCreadas = 0;

        // Por cada AsignacionPlan, crear dos Cursos con Componentes y Unidades
        foreach ($asignacionesPlanes as $asignacionPlan) {
            try {
                // Crear 2 cursos por asignación
                for ($c = 1; $c <= 2; $c++) {
                    $docente = Docente::inRandomOrder()->firstOrFail();

                    // Crear el Curso
                    $curso = Curso::create([
                        'cod_curso' => random_int(100000000, 999999999),
                        'nombre' => 'Curso ' . $c . ' para ' . $asignacionPlan->asignatura?->nombre ?? 'Asignatura',
                        'fecha_inicio' => now()->subMonths(random_int(1, 6)),
                        'fecha_fin' => now()->addMonths(random_int(1, 6)),
                        'agno_real' => $asignacionPlan->agno_planificado,
                        'semestre_real' => $asignacionPlan->semestre_planificado,
                        'estado_interno' => 'activo',
                        'estado_acta' => 'pendiente',
                        'es_plantilla' => $c === 2, // El segundo es plantilla
                        'es_colegiado' => false,
                        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
                        'id_docente_titular' => $docente->id_docente,
                    ]);

                    $cursosCreados++;

                    // Crear 2 Componentes al azar
                    $tiposSeleccionados = $tiposComponente->random(min(2, $tiposComponente->count()));

                    foreach ($tiposSeleccionados as $tipoComponente) {
                        Componente::create([
                            'genera_acta' => true,
                            'porcentaje_aprobacion' => 60,
                            'aprobacion_obligatoria' => false,
                            'porcentaje_asistencia_obligatoria' => 0,
                            'id_tipo_componente' => $tipoComponente->id_tipo_componente,
                            'id_curso' => $curso->id_curso,
                        ]);

                        $componentesCreados++;
                    }

                    // Crear 3 Unidades
                    for ($i = 1; $i <= 3; $i++) {
                        Unidad::create([
                            'num_unidad' => $i,
                            'nombre' => "Unidad {$i}",
                            'descripcion' => "Descripción de la Unidad {$i}",
                            'id_curso' => $curso->id_curso,
                        ]);

                        $unidadesCreadas++;
                    }

                    $this->command->info("✓ Curso {$curso->cod_curso} creado con 2 componentes y 3 unidades.");
                }
            } catch (\Exception $e) {
                $this->command->error("✗ Error al procesar AsignacionPlan {$asignacionPlan->id_asignacion_plan}: " . $e->getMessage());
                break; // Detener el proceso si ocurre un error para evitar datos inconsistentes
            }
        }

        $this->command->info("\n📊 Resumen:");
        $this->command->info("   Cursos creados: {$cursosCreados}");
        $this->command->info("   Componentes creados: {$componentesCreados}");
        $this->command->info("   Unidades creadas: {$unidadesCreadas}");
    }
}
