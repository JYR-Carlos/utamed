<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Estructura académica (Facultad, Departamento, Carrera, Plan, Asignaturas, AsignacionPlan)
        $this->call(CarreraDisenioMultimediaSeeder::class);
        $this->call(CarreraIngenieriaComercialSeeder::class);

        // 2. Usuarios
        $this->call(EquipoDesarrolloSeeder::class);
        $this->call(UsuariosDePruebaSeeder::class);

        // 3. Cursos y Actividades
        $this->call(BaseCursosSeeder::class);
        $this->call(ActividadesSeeder::class);
    }
}
