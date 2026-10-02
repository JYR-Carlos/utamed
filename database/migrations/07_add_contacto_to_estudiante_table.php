<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos de contacto que el propio estudiante mantiene (T10): correo personal,
 * celular y redes sociales. Sólo los ven él y sus docentes; nunca otros
 * alumnos. Los datos institucionales siguen viniendo de la Intranet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE usuario.estudiante ADD COLUMN IF NOT EXISTS correo_personal varchar(255) DEFAULT NULL');
        DB::statement('ALTER TABLE usuario.estudiante ADD COLUMN IF NOT EXISTS celular varchar(30) DEFAULT NULL');
        DB::statement('ALTER TABLE usuario.estudiante ADD COLUMN IF NOT EXISTS redes_sociales jsonb DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE usuario.estudiante DROP COLUMN IF EXISTS redes_sociales');
        DB::statement('ALTER TABLE usuario.estudiante DROP COLUMN IF EXISTS celular');
        DB::statement('ALTER TABLE usuario.estudiante DROP COLUMN IF EXISTS correo_personal');
    }
};
