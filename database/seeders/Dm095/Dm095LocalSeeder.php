<?php

namespace Database\Seeders\Dm095;

use Illuminate\Database\Seeder;

/**
 * SÓLO LOCAL: corre la secuencia completa para ver cómo queda DM095 A y B
 * en el «estado correcto» partiendo de una copia del estado de producción.
 *
 *   1. Dm095EscenarioProduccionSeeder  → reproduce producción (cursos, alumnos, Laboratorio por error, Tarea 1, programa de A)
 *   2. Dm095LimpiarLaboratorioSeeder   → mueve Tarea 1 al Taller y borra el Laboratorio (requiere DM095_BORRAR_LABORATORIO=1)
 *   3. Dm095ContenidoSeeder            → unidades, programa, bibliografía y las 6 actividades en A y B
 *
 * Uso:
 *   DM095_BORRAR_LABORATORIO=1 DM095_BIBLIO_URL_PLACEHOLDER=https://evea.uta.cl/pendiente \
 *     php artisan db:seed --class="Database\Seeders\Dm095\Dm095LocalSeeder"
 *
 * En producción NO se usa este orquestador: allá los pasos 2 y 3 se corren por
 * separado y el 1 no aplica.
 */
class Dm095LocalSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command->error('Dm095LocalSeeder es sólo para local/testing.');
            return;
        }

        $this->call([
            Dm095EscenarioProduccionSeeder::class,
            Dm095LimpiarLaboratorioSeeder::class,
            Dm095ContenidoSeeder::class,
        ]);
    }
}
