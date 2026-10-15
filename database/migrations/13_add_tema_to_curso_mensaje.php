<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * curso.mensaje suma `tema text NOT NULL` para titular un hilo.
 *
 * Origen: database-model ff91dea. Entró al baseline después del corte; en una base nueva esta
 * migración no hace nada. En una base existente con mensajes, el tema se rellena con el
 * comienzo del texto del mensaje, que es de donde se titulaba el hilo antes del cambio.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE curso.mensaje ADD COLUMN IF NOT EXISTS tema text');
        DB::statement('UPDATE curso.mensaje SET tema = left(mensaje, 120) WHERE tema IS NULL');
        DB::statement('ALTER TABLE curso.mensaje ALTER COLUMN tema SET NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.mensaje DROP COLUMN IF EXISTS tema');
    }
};
