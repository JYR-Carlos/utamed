<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EquipoDesarrolloSeeder::class);
        $this->call(UsuariosDePruebaSeeder::class);
        $this->call(BaseCursosSeeder::class);
        $this->call(ActividadesSeeder::class);
    }
}
