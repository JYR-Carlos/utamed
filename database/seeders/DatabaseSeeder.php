<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default context
        DB::table('utamed.Contexto')->insertOrIgnore([
            'nombre' => 'Default',
            'descripcion' => 'Contexto por defecto del sistema',
            'fecha_creacion' => now(),
            'fecha_modificacion' => now(),
        ]);
    }
}
