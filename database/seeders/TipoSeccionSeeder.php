<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoSeccionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tipos = [
            [1, 'Cátedra'],
            [2, 'Taller'],
            [3, 'Laboratorio'],
            [4, 'Ayudantía'],
        ];

        foreach ($tipos as $tipo) {
            DB::statement('INSERT INTO "utamed.Curso"."Tipo_Seccion" (id_tipo_seccion, tipo) VALUES (?, ?) ON CONFLICT (id_tipo_seccion) DO UPDATE SET tipo = EXCLUDED.tipo', $tipo);
        }
    }
}
