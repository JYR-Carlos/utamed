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
        DB::statement('
            CREATE TABLE IF NOT EXISTS curso.bibliografia (
                uuid_bibliografia uuid NOT NULL DEFAULT gen_random_uuid(),
                id_programa integer NOT NULL,
                id_unidad smallint,
                titulo text NOT NULL,
                autor text NOT NULL,
                cita text,
                editorial text,
                agno smallint NOT NULL,
                url varchar,
                uuid_archivo uuid,
                es_bibliografia_uta boolean NOT NULL DEFAULT FALSE,
                agregado_por integer NOT NULL,
                fecha_creacion timestamp DEFAULT now(),
                CONSTRAINT pk_bibliografia PRIMARY KEY (uuid_bibliografia),
                CONSTRAINT chk_url_notrq_when_no_es_bibuta CHECK (es_bibliografia_uta = TRUE OR url IS NOT NULL OR uuid_archivo IS NOT NULL),
                CONSTRAINT chk_archivo_local_when_no_es_bibuta CHECK (uuid_archivo IS NULL OR es_bibliografia_uta = FALSE)
            );
        ');

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_programa') THEN
                    ALTER TABLE curso.bibliografia ADD CONSTRAINT fk_programa FOREIGN KEY (id_programa)
                    REFERENCES curso.programa (id_programa) MATCH FULL
                    ON DELETE RESTRICT ON UPDATE CASCADE;
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_unidad') THEN
                    ALTER TABLE curso.bibliografia ADD CONSTRAINT fk_unidad FOREIGN KEY (id_unidad)
                    REFERENCES curso.unidad (id_unidad) MATCH FULL
                    ON DELETE SET NULL ON UPDATE CASCADE;
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_usuario') THEN
                    ALTER TABLE curso.bibliografia ADD CONSTRAINT fk_usuario FOREIGN KEY (agregado_por)
                    REFERENCES usuario.usuario (id_usuario) MATCH FULL
                    ON DELETE RESTRICT ON UPDATE CASCADE;
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_archivo') THEN
                    ALTER TABLE curso.bibliografia ADD CONSTRAINT fk_archivo FOREIGN KEY (uuid_archivo)
                    REFERENCES operaciones.archivo (uuid_archivo) MATCH FULL
                    ON DELETE SET NULL ON UPDATE CASCADE;
                END IF;
            END \$\$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS curso.bibliografia CASCADE;');
    }
};
