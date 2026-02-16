<?php

namespace Database\Factories\Curso;

use App\Models\Curso\Curso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Curso\Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'cod_curso' => $this->faker->unique()->numerify('#########'),
            'nombre' => $this->faker->sentence(3),
            'fecha_inicio' => now()->startOfYear(),
            'fecha_fin' => now()->endOfYear(),
            'agno_real' => now()->year,
            'semestre_real' => $this->faker->randomElement([1, 2]),
            'estado_interno' => 'ABIERTO',
            'estado_acta' => 'NO_ENVIADO',
            'es_plantilla' => false,
            'id_asignatura' => null,
            'id_plan' => null,
        ];
    }

    /**
     * Indicate that the curso is a template.
     */
    public function template(): static
    {
        return $this->state(fn(array $attributes) => [
            'es_plantilla' => true,
        ]);
    }

    /**
     * Indicate that the curso is closed.
     */
    public function closed(): static
    {
        return $this->state(fn(array $attributes) => [
            'estado_interno' => 'CERRADO',
        ]);
    }
}
