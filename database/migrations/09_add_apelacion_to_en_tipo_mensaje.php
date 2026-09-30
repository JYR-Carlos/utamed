<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de mensaje para la apelación de entregas (T48). Tras aplicarla hay que
 * regenerar app/Enums/DB/TipoMensaje.php con scripts/generate_models.php.
 *
 * Los valores nuevos no se pueden usar dentro de la misma transacción que los
 * crea, así que esta migración no debe insertar filas que los ocupen.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TYPE agenda.en_tipo_mensaje ADD VALUE IF NOT EXISTS 'Solicitud de apelación'");
        DB::statement("ALTER TYPE agenda.en_tipo_mensaje ADD VALUE IF NOT EXISTS 'Resolución de apelación'");
    }

    /**
     * Reverse the migrations.
     *
     * PostgreSQL no permite quitar valores de un ENUM. Se deja sin efecto: los
     * valores sobrantes no molestan mientras ninguna fila los use.
     */
    public function down(): void
    {
        //
    }
};
