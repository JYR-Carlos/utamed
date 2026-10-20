<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Foto de perfil del usuario.
 *
 * Origen: database-model d2f080c (Añadido Lectura Agenda, Foto de Usuario...). Las demás piezas
 * de ese commit ya tienen migración (06 a 09); faltaba esta columna.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE usuario.usuario ADD COLUMN IF NOT EXISTS foto_url varchar DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE usuario.usuario DROP COLUMN IF EXISTS foto_url');
    }
};
