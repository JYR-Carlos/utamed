<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE usuario.usuario ADD COLUMN IF NOT EXISTS fecha_cambio_passhash timestamp DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE usuario.usuario DROP COLUMN IF EXISTS fecha_cambio_passhash');
    }
};
