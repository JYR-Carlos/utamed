<?php

namespace Database\Seeders;

use App\Models\Usuario\Usuario;
use Illuminate\Database\Seeder;

/**
 * Seeder para crear usuarios de prueba en todas las categorías.
 * 
 * Utiliza las factories con datos chilenos realistas.
 * 
 * Uso:
 *   php artisan db:seed --class=UsuariosDePruebaSeeder
 *   
 * Cantidad personalizable en el método run().
 */
class UsuariosDePruebaSeeder extends Seeder
{
    /**
     * Ejecutar el seeder.
     */
    public function run(): void
    {
        $this->command->info('Iniciando seeding de usuarios de prueba...');

        // 1. Bolsas de estudiantes por año de carrera (10 alumnos por bolsa)
        $carrerasConPlanes = \App\Models\Administrativo\Carrera::whereHas('planes.asignacionPlanes')
            ->with('planes.asignacionPlanes')
            ->get();

        $agnoActual = now()->year;
        $totalEstudiantes = 0;

        if ($carrerasConPlanes->isEmpty()) {
            $cantidadFallback = 30;
            Usuario::factory($cantidadFallback)
                ->estudiante()
                ->withRolEstudiante()
                ->create();
            $this->command->info("{$cantidadFallback} Estudiantes creados (fallback sin planes).");
        } else {
            foreach ($carrerasConPlanes as $carrera) {
                foreach ($carrera->planes as $plan) {
                    $agnosPlanificados = $plan->asignacionPlanes
                        ->pluck('agno_planificado')
                        ->unique()
                        ->sort()
                        ->values();

                    foreach ($agnosPlanificados as $agno) {
                        $agnoIngreso = $agnoActual - ($agno - 1);
                        $existentes = \App\Models\Usuario\Estudiante::where('id_carrera', $carrera->id_carrera)
                            ->where('agno_ingreso', $agnoIngreso)
                            ->count();

                        $faltantes = max(0, 10 - $existentes);
                        if ($faltantes > 0) {
                            $this->command->line("  -> Creando {$faltantes} estudiantes para completar bolsa de 10: {$carrera->nombre} - Año {$agno} (Ingreso {$agnoIngreso})");

                            for ($i = 1; $i <= $faltantes; $i++) {
                                Usuario::factory()
                                    ->has(
                                        \Database\Factories\Usuario\EstudianteFactory::new()->state([
                                            'id_carrera' => $carrera->id_carrera,
                                            'agno_ingreso' => $agnoIngreso,
                                        ]),
                                        'estudiante'
                                    )
                                    ->withRolEstudiante()
                                    ->create();
                                $totalEstudiantes++;
                            }
                        } else {
                            $this->command->line("  -> Bolsa ya completa (10 estudiantes): {$carrera->nombre} - Año {$agno} (Ingreso {$agnoIngreso})");
                        }
                    }
                }
            }
            $this->command->info("✓ Total de {$totalEstudiantes} estudiantes creados en bolsas de 10 por nivel.");
        }

        // 2. Docentes
        $cantidadDocentes = 10;
        Usuario::factory($cantidadDocentes)
            ->docente()
            ->create();
        $this->command->info("✓ {$cantidadDocentes} docentes creados.");

        // 3. Estudiante y Docente
        $cantidadAmbos = 5;
        Usuario::factory($cantidadAmbos)
            ->estudianteYDocente()
            ->withRolEstudiante()
            ->create();
        $this->command->info("✓ {$cantidadAmbos} estudiante-docentes creados.");

        $this->command->info('✅ Seedeo de usuarios completado exitosamente!');
    }
}
