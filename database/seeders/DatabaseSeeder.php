<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UsuariosDePruebaSeeder::class);
        $this->call(BaseCursosSeeder::class);
        $this->call(ActividadesSeeder::class);
        $this->call(EquipoDesarrolloSeeder::class);
    }
}
