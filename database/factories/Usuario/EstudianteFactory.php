<?php

namespace Database\Factories\Usuario;

use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
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
            'id_usuario' => Usuario::factory(),
            'agno_ingreso' => now()->year,
        ];
    }
}
