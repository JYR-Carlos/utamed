<?php

namespace Database\Factories\External;

use App\Models\External\VwInscripcion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VwInscripcion>
 */
class VwInscripcionFactory extends Factory
{
    protected $model = VwInscripcion::class;

    public function definition(): array
    {
        return [
            'ASIG_CODIGO'                  => 'INF-' . $this->faker->numberBetween(100, 999), // varchar(10)
            'CURSO_TIPO_ASIG'              => $this->faker->randomElement(['C', 'T', 'L']),  // varchar(1)
            'CURSO_GRUPO_ASIG'             => $this->faker->randomElement(['A', 'B', 'C']),  // varchar(2)
            'CURSO_SEMESTRE_ASIG'          => $this->faker->numberBetween(1, 2),             // number(1)
            'CURSO_ANO'                    => 2026,                                          // number(4)
            'ALUM_RUT'                     => $this->faker->numberBetween(10000000, 25000000),// number(9)
            'CARRERA_COD'                  => 123,                                           // number(3)
            'INSCRIP_FECHA'                => now()->toDateTimeString(),                     // date
            'INSCRIP_BANDERA_RATIFICACION' => 1,                                             // number(1)
            'INSCRIP_FLAG_PROCESADA'       => 1,                                             // number(1)
            'INSCRIP_OPORTUNIDAD_INS'      => 1,                                             // number
            'INSCRIP_NOTA'                 => $this->faker->randomFloat(1, 1, 7),           // number(2,1)
            'INSCRIP_POSICION'             => 1,                                             // number(4)
            'INSCRIP_TER_ASIG'             => 1,                                             // number
            'INSCRIP_CNDIC'                => 1,                                             // number
            'TIPOAPROB_COD'                => 1,                                             // number
            'INSCRIP_ORIGEN'               => 1,                                             // number
            'INSCRIP_PLAN'                 => 2020,                                          // number(4)
            'INSCRIP_NIVEL'                => 1,                                             // number
            'SEDE_CODIGO'                  => 1,                                             // number(6)
            'GRUPO_CARRERA'                => 1,                                             // number(3)
            'RUT_DIGITADOR'                => $this->faker->numberBetween(10000000, 25000000),// number
            'SESION_WEB'                   => 12345,                                         // number
            'ACTFOLIO_FOLIO'               => 1,                                             // number
            'INS_ID'                       => $this->faker->numberBetween(1000000, 9999999),  // number(7)
            'CUR_CODIGO'                   => (int) ($this->faker->numerify('20261') . $this->faker->numerify('######')), // number(12)
        ];
    }
}
