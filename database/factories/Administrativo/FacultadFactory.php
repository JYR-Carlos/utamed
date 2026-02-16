<?php

namespace Database\Factories\Administrativo;

use App\Models\Administrativo\Facultad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Administrativo\Facultad>
 */
class FacultadFactory extends Factory
{
    protected $model = Facultad::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
        ];
    }
}
