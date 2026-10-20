<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CUR_CODIGO de la Intranet por componente (number(12) en Oracle).
 *
 * Origen: database-model 314fb42 (agrega la columna y un índice) y 1a809a0 (retira el índice).
 * Queda nullable y sin índice: los componentes creados a mano no tienen acta en la Intranet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE curso.componente ADD COLUMN IF NOT EXISTS intranet_cur_codigo numeric(12) DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.componente DROP COLUMN IF EXISTS intranet_cur_codigo');
    }
};
