<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega los atributos opcionales asunto y descripcion a curso.clase.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE curso.clase ADD COLUMN IF NOT EXISTS asunto varchar(150);');
        DB::statement('ALTER TABLE curso.clase ADD COLUMN IF NOT EXISTS descripcion text;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.clase DROP COLUMN IF EXISTS asunto;');
        DB::statement('ALTER TABLE curso.clase DROP COLUMN IF EXISTS descripcion;');
    }
};
