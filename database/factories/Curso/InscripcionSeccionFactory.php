<?php

namespace Database\Factories\Curso;

use App\Models\Curso\InscripcionSeccion;
use App\Models\Usuario\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Curso\InscripcionSeccion>
 */
class InscripcionSeccionFactory extends Factory
{
    protected $model = InscripcionSeccion::class;

    public function definition(): array
    {
        // Las claves compuestas se asignan en el test o con state()
        return [
            'id_estudiante' => Estudiante::factory(),
        ];
    }
}

