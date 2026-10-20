<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INS_ID de la Intranet a nivel de componente.
 *
 * Origen: database-model 5efb976 (feat: ins_id en inscripcion_componente). El cambio entró al
 * baseline init_scripts/01-sql_def.sql después del corte, por lo que una base creada antes no
 * lo recibe; en una base nueva esta migración no hace nada.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE curso.inscripcion_componente ADD COLUMN IF NOT EXISTS cod_inscripcion_curso_uta numeric(8)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.inscripcion_componente DROP COLUMN IF EXISTS cod_inscripcion_curso_uta');
    }
};
