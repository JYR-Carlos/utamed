<?php

namespace Database\Factories\External;

use App\Models\External\VwAlumno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VwAlumno>
 */
class VwAlumnoFactory extends Factory
{
    protected $model = VwAlumno::class;

    public function definition(): array
    {
        return [
            'ALUM_RUT'              => $this->faker->numberBetween(10000000, 25000000),// number(9)
            'ALUM_DIGITO'           => $this->faker->randomElement(['0','1','2','3','4','5','6','7','8','9','K']), // char(1)
            'ALUM_NOMBRE'           => substr($this->faker->firstName(), 0, 35),     // varchar(35)
            'ALUM_APELLIDO_PAT'     => substr($this->faker->lastName(), 0, 25),      // varchar(25)
            'ALUM_APELLIDO_MAT'     => substr($this->faker->lastName(), 0, 25),      // varchar(25)
            'ALUM_SEXO'             => $this->faker->randomElement([1, 2]),          // number(1)
            'ALUM_PAIS_ORIGEN'      => 1,                                            // number(4)
            'ALUM_ESTADO_CIVIL'     => 1,                                            // number(1)
            'ALUM_FECHA_NACIMIENTO' => $this->faker->date(),                         // date
            'ALUM_LUGAR_NACIMIENTO' => '',                                           // char(0)
            'ALUM_NACIONALIDAD'     => '',                                           // char(0)
            'ALUM_APPATERNO_JEFE'   => '',                                           // char(0)
            'ALUM_APMATERNO_JEFE'   => '',                                           // char(0)
            'ALUM_NOMBRE_JEFE'      => '',                                           // char(0)
            'ETCO_CODIGO'           => '',                                           // char(0)
        ];
    }
}
