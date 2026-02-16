<?php

namespace Database\Factories\Curso;

use App\Models\Curso\TipoSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Curso\TipoSeccion>
 */
class TipoSeccionFactory extends Factory
{
    protected $model = TipoSeccion::class;

    public function definition(): array
    {
        return [
            'tipo' => $this->faker->randomElement(['TEORÍA', 'PRÁCTICA', 'LABORATORIO']),
        ];
    }
}
