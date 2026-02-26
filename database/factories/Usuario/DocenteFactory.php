<?php

namespace Database\Factories\Usuario;

use App\Models\Usuario\Docente;
use App\Support\DataGenerators\ChileanNameGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario\Docente>
 */
class DocenteFactory extends Factory
{
    protected $model = Docente::class;

    public function definition(): array
    {
        $atributos = ChileanNameGenerator::generarAtributosDocentes();

        return [
            'grado' => $atributos['grado'],
            'titulo' => $atributos['titulo'],
            'cargo' => $atributos['cargo'],
        ];
    }
}
