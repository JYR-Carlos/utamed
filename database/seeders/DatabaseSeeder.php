<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Siembra de DESARROLLO: estructura académica real más usuarios y cursos
 * inventados para poder trabajar en local.
 *
 * Es el seeder por defecto, o sea el que corre `php artisan db:seed` sin
 * `--class`. Por eso se niega a correr fuera de local/testing: en una base real
 * metería las cuentas del equipo con RUT de relleno, los usuarios de factory y
 * los cursos de demo con sus cohortes inscritas.
 *
 * Para una base real está ProduccionBaseSeeder, que deja sólo la estructura
 * académica, los roles y el superadmin:
 *
 *   php artisan db:seed --class=Database\\Seeders\\ProduccionBaseSeeder
 *
 * Si de verdad hace falta la data de demo en otro entorno (una copia de staging
 * para probar, por ejemplo), se habilita con UTAMED_SEED_DEMO=1.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing']) && !filter_var(env('UTAMED_SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->command->error(sprintf(
                'DatabaseSeeder crea usuarios y cursos de prueba y el entorno es «%s». Abortado.',
                app()->environment()
            ));
            $this->command->line('  Para una base real: php artisan db:seed --class=Database\\Seeders\\ProduccionBaseSeeder');
            $this->command->line('  Para forzar la data de demo igualmente: UTAMED_SEED_DEMO=1');

            return;
        }

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
