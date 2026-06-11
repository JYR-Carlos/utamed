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

        // Configurar cantidades

        $cantidadEstudiantes = 30;
        $cantidadDocentes = 10;
        $cantidadAmbos = 5;

        // Creación de usuarios de prueba

        Usuario::factory($cantidadEstudiantes)
            ->estudiante()
            ->withRolEstudiante()
            ->create();
        $this->command->info("{$cantidadEstudiantes} Estudiantes creados");

        Usuario::factory($cantidadDocentes)
            ->docente()
            ->withRolDocente()
            ->create();
        $this->command->info("{$cantidadDocentes} docentes creados");

        Usuario::factory($cantidadAmbos)
            ->estudianteYDocente()
            ->withRolEstudiante()
            ->withRolDocente()
            ->create();
        $this->command->info("{$cantidadAmbos} estudiante-docentes creados");

        $this->command->info('✅ Seedeo completado exitosamente!');
    }
}
