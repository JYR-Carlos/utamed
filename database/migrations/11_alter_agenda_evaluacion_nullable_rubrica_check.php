<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Flexibiliza agenda.evaluacion para permitir evaluaciones de actividades formativas
 * (las cuales no llevan rúbrica ni puntaje numérico).
 *
 * Mantiene la integridad de las actividades sumativas mediante un CHECK constraint parcial:
 * si hay rúbrica, debe haber puntaje; si es formativa (sin rúbrica), id_rubrica queda en NULL.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE agenda.evaluacion ALTER COLUMN id_rubrica DROP NOT NULL');
        DB::statement('ALTER TABLE agenda.evaluacion ALTER COLUMN puntaje_obtenido DROP NOT NULL');
        DB::statement('ALTER TABLE agenda.evaluacion ALTER COLUMN resultado DROP NOT NULL');

        DB::statement('
            ALTER TABLE agenda.evaluacion 
            ADD CONSTRAINT chk_evaluacion_rubrica_o_formativa 
            CHECK (
                (id_rubrica IS NOT NULL AND puntaje_obtenido IS NOT NULL) 
                OR 
                (id_rubrica IS NULL)
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE agenda.evaluacion DROP CONSTRAINT IF EXISTS chk_evaluacion_rubrica_o_formativa');
        DB::statement('ALTER TABLE agenda.evaluacion ALTER COLUMN id_rubrica SET NOT NULL');
        DB::statement('ALTER TABLE agenda.evaluacion ALTER COLUMN puntaje_obtenido SET NOT NULL');
        DB::statement('ALTER TABLE agenda.evaluacion ALTER COLUMN resultado SET NOT NULL');
    }
};
