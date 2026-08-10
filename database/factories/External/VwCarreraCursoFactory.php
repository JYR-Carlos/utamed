<?php

namespace Database\Factories\External;

use App\Models\External\VwCarreraCurso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VwCarreraCurso>
 */
class VwCarreraCursoFactory extends Factory
{
    protected $model = VwCarreraCurso::class;

    public function definition(): array
    {
        return [
            'ASIG_CODIGO'         => 'INF-' . $this->faker->numberBetween(100, 999), // varchar(10)
            'CURSO_TIPO_ASIG'     => $this->faker->randomElement(['C', 'T', 'L']),  // varchar(1)
            'CURSO_GRUPO_ASIG'    => $this->faker->randomElement(['A', 'B', 'C']),  // varchar(1)
            'CURSO_SEMESTRE_ASIG' => $this->faker->numberBetween(1, 2),             // number(1)
            'CURSO_ANO'           => 2026,                                          // number(4)
            'CARRERA_COD'         => 123,                                           // number(3)
            'PLAN_ANO'            => 2020,                                          // number(4)
            'CUR_CODIGO'          => (int) ($this->faker->numerify('20261') . $this->faker->numerify('######')), // number(12)
        ];
    }

    /**
     * Cátedra
     */
    public function catedra(): static
    {
        return $this->state(fn(array $attributes) => [
            'CURSO_TIPO_ASIG' => 'C',
        ]);
    }

    /**
     * Taller
     */
    public function taller(): static
    {
        return $this->state(fn(array $attributes) => [
            'CURSO_TIPO_ASIG' => 'T',
        ]);
    }

    /**
     * Laboratorio
     */
    public function laboratorio(): static
    {
        return $this->state(fn(array $attributes) => [
            'CURSO_TIPO_ASIG' => 'L',
        ]);
    }
}
