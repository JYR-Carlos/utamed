<?php

namespace Database\Factories\Usuario;

use App\Models\Usuario\Usuario;
use App\Support\DataGenerators\ChileanNameGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario\Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * Define the model's default state with Chilean data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = ChileanNameGenerator::generarNombre();

        return [
            'rut' => ChileanNameGenerator::generarRUT(),
            'username' => $this->faker->unique()->userName(),
            'nombre1' => $nombre['nombre1'],
            'nombre2' => $nombre['nombre2'],
            'apellido1' => $nombre['apellido1'],
            'apellido2' => $nombre['apellido2'],
            'email' => $this->faker->unique()->safeEmail(),
            'fecha_verificacion_email' => now(),
            'passhash' => Hash::make('password'),
            'token_recuerdame_sesion' => Str::random(10),
            'esta_activo' => true,
        ];
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'esta_activo' => false,
        ]);
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'fecha_verificacion_email' => null,
        ]);
    }

    /**
     * Crear usuario con perfil de estudiante.
     */
    public function estudiante(): static
    {
        return $this->has(
            \Database\Factories\Usuario\EstudianteFactory::new(),
            'estudiante'
        );
    }

    /**
     * Crear usuario con perfil de docente.
     */
    public function docente(): static
    {
        return $this->has(
            \Database\Factories\Usuario\DocenteFactory::new(),
            'docente'
        );
    }

    /**
     * Crear usuario con ambos perfiles (estudiante y docente).
     */
    public function estudianteYDocente(): static
    {
        return $this->has(
            \Database\Factories\Usuario\EstudianteFactory::new(),
            'estudiante'
        )->has(
                \Database\Factories\Usuario\DocenteFactory::new(),
                'docente'
            );
    }
}

