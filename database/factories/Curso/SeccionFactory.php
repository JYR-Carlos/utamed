<?php

namespace Database\Factories\Curso;

use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\TipoSeccion;
use App\Models\Usuario\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Curso\Seccion>
 */
class SeccionFactory extends Factory
{
    protected $model = Seccion::class;
    
    public function definition(): array
    {
        return [
            'id_curso' => Curso::factory(),
            // TipoSeccion y Docente son opcionales en tests
            // Los crear explícitamente si se necesitan
        ];
    }
}