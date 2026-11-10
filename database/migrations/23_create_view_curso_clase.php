<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BD-02: Creación de vista curso.vw_clase con correlativo virtual nro_clase.
 *
 * Genera una vista relacional en PostgreSQL sobre la tabla curso.clase,
 * replicando todos sus atributos e incorporando el atributo virtual nro_clase
 * calculado como un correlativo ordenado cronológicamente por id_componente.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW curso.vw_clase AS
            SELECT
                id_clase,
                dia,
                hora_inicio,
                hora_fin,
                id_componente,
                asunto,
                descripcion,
                ROW_NUMBER() OVER (
                    PARTITION BY id_componente
                    ORDER BY dia ASC, hora_inicio ASC, id_clase ASC
                ) AS nro_clase
            FROM curso.clase;
        ');

        DB::statement('ALTER VIEW curso.vw_clase OWNER TO utamed;');
        DB::statement('GRANT SELECT ON curso.vw_clase TO utamed;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS curso.vw_clase CASCADE;');
    }
};
