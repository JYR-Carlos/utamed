<?php

namespace Database\Seeders;

use App\Models\Curso\TipoSeccion;
use Illuminate\Database\Seeder;

class TipoSeccionSeeder extends Seeder
{
    public function run()
    {
        // Insertar tipos de sección
        $tipos = [
            ['id_tipo_seccion' => 1, 'tipo' => 'Cátedra'],
            ['id_tipo_seccion' => 2, 'tipo' => 'Taller'],
            ['id_tipo_seccion' => 3, 'tipo' => 'Laboratorio'],
            ['id_tipo_seccion' => 4, 'tipo' => 'Ayudantía'],
        ];

        foreach ($tipos as $tipo) {
            TipoSeccion::updateOrCreate(
                ['id_tipo_seccion' => $tipo['id_tipo_seccion']],
                $tipo
            );
        }
    }
}
