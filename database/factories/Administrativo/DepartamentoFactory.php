<?php

namespace Database\Factories\Administrativo;

use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Administrativo\Departamento>
 */
class DepartamentoFactory extends Factory
{
    protected $model = Departamento::class;

    public function definition(): array
    {
        return [
            'id_facultad' => Facultad::factory(),
            'nombre' => $this->faker->word(),
        ];
    }
}
