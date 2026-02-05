<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles y permisos administrativos
        $this->call(RoleAndPermissionSeeder::class);

        // Catálogos
        $this->call(TipoSeccionSeeder::class);
    }
}