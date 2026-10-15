<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Holgura personal por grupo y refinamientos de modelo.
 *
 * Origen: database-model 4ea2f9b. Entró al baseline después del corte; en una base nueva esta
 * migración no hace nada.
 * - agenda.actividad.uuid_archivo pasa a llamarse uuid_archivo_enunciado (la FK fk_archivo
 *   sigue a la columna al renombrarla).
 * - agenda.actividad_asignada_grupo suma nro_dias_adicionales_para_bloqueo_personal.
 * - curso.mensaje no admite que emisor y receptor sean la misma persona.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns
                           WHERE table_schema = 'agenda' AND table_name = 'actividad'
                             AND column_name = 'uuid_archivo')
                   AND NOT EXISTS (SELECT 1 FROM information_schema.columns
                           WHERE table_schema = 'agenda' AND table_name = 'actividad'
                             AND column_name = 'uuid_archivo_enunciado') THEN
                    ALTER TABLE agenda.actividad RENAME COLUMN uuid_archivo TO uuid_archivo_enunciado;
                END IF;
            END \$\$;
        ");

        DB::statement('ALTER TABLE agenda.actividad_asignada_grupo ADD COLUMN IF NOT EXISTS nro_dias_adicionales_para_bloqueo_personal smallint DEFAULT 0');

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_emisor_no_es_igual_que_receptor'
                               AND conrelid = 'curso.mensaje'::regclass) THEN
                    ALTER TABLE curso.mensaje ADD CONSTRAINT ck_emisor_no_es_igual_que_receptor
                    CHECK (id_usuario_emisor != id_usuario_receptor);
                END IF;
            END \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.mensaje DROP CONSTRAINT IF EXISTS ck_emisor_no_es_igual_que_receptor');
        DB::statement('ALTER TABLE agenda.actividad_asignada_grupo DROP COLUMN IF EXISTS nro_dias_adicionales_para_bloqueo_personal');
        DB::statement('ALTER TABLE agenda.actividad RENAME COLUMN uuid_archivo_enunciado TO uuid_archivo');
    }
};
