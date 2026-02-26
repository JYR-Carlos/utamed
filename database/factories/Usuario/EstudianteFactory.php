<?php

namespace Database\Factories\Usuario;

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario\Estudiante>
 */
class EstudianteFactory extends Factory
{
    protected $model = Estudiante::class;

    public function definition(): array
    {
        return [
            'agno_ingreso' => $this->faker->numberBetween(2017, 2026),
            'id_carrera' => Carrera::inRandomOrder()->first()?->id_carrera ?? 1,
        ];
    }
}
