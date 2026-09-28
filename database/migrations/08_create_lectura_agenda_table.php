<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Confirmación de lectura («Visto») de la agenda (T07). Una fila por lector:
 * en una actividad grupal leen varios integrantes y docentes, así que no
 * alcanza con una columna en agenda.agenda. El UNIQUE permite registrar la
 * lectura con ON CONFLICT DO NOTHING cada vez que se abre la agenda.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS agenda.lectura_agenda (
                id_lectura_agenda integer NOT NULL GENERATED ALWAYS AS IDENTITY,
                id_agenda integer NOT NULL,
                id_usuario_lector integer NOT NULL,
                fecha_lectura timestamp NOT NULL DEFAULT now(),
                CONSTRAINT pk_lectura_agenda PRIMARY KEY (id_lectura_agenda),
                CONSTRAINT uq_id_agenda_id_usuario_lector UNIQUE (id_agenda, id_usuario_lector)
            );
        ');

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_lectura_agenda_agenda'
                               AND conrelid = 'agenda.lectura_agenda'::regclass) THEN
                    ALTER TABLE agenda.lectura_agenda ADD CONSTRAINT fk_lectura_agenda_agenda FOREIGN KEY (id_agenda)
                    REFERENCES agenda.agenda (id_agenda) MATCH SIMPLE
                    ON DELETE CASCADE ON UPDATE NO ACTION;
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_lectura_agenda_usuario'
                               AND conrelid = 'agenda.lectura_agenda'::regclass) THEN
                    ALTER TABLE agenda.lectura_agenda ADD CONSTRAINT fk_lectura_agenda_usuario FOREIGN KEY (id_usuario_lector)
                    REFERENCES usuario.usuario (id_usuario) MATCH SIMPLE
                    ON DELETE CASCADE ON UPDATE NO ACTION;
                END IF;
            END \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS agenda.lectura_agenda CASCADE;');
    }
};
