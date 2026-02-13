<?php

namespace Database\Factories\Usuario;

use App\Models\Usuario\Usuario;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rut' => $this->faker->unique()->numerify('########-#'),
            'username' => $this->faker->userName(),
            'nombre1' => $this->faker->firstName(),
            'nombre2' => null,
            'apellido1' => $this->faker->lastName(),
            'apellido2' => $this->faker->lastName(),
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
}
