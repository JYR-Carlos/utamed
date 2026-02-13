<?php

namespace Database\Factories\Administrativo;

use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Administrativo\Programa>
 */
class ProgramaFactory extends Factory
{
    protected $model = Programa::class;

    public function definition(): array
    {
        return [
            'version_programa' => 1,
            'unc_programa' => 1,
            'id_curso' => Curso::factory(),
            'es_plantilla' => false,
            'es_actual' => true,
            'fecha_creacion' => now(),
            'creado_por' => Usuario::factory(),
        ];
    }

    /**
     * Indicate that the programa is a template.
     */
    public function template(): static
    {
        return $this->state(fn(array $attributes) => [
            'es_plantilla' => true,
        ]);
    }

    /**
     * Indicate that the programa is not current.
     */
    public function notCurrent(): static
    {
        return $this->state(fn(array $attributes) => [
            'es_actual' => false,
        ]);
    }

    /**
     * Set a specific version.
     */
    public function version(int $version): static
    {
        return $this->state(fn(array $attributes) => [
            'version_programa' => $version,
        ]);
    }
}
