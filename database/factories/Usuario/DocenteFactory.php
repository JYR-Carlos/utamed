<?php

namespace Database\Factories\Usuario;

use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario\Docente>
 */
class DocenteFactory extends Factory
{
    protected $model = Docente::class;

    public function definition(): array
    {
        return [
            'grado' => $this->faker->randomElement(['Licenciado', 'Magíster', 'Doctor']),
            'titulo' => $this->faker->randomElement([
                'Ingeniero Civil',
                'Diseñador Gráfico',
                'Arquitecto',
                'Profesor de Estado'
            ]),
            'cargo' => $this->faker->randomElement(['Profesor', 'Profesor Asociado', 'Profesor Titular']),
            'id_usuario' => Usuario::factory(),
        ];
    }
}
