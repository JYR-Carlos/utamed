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
        DB::statement("
            CREATE OR REPLACE FUNCTION curso.fn_indice_grupo_auto() 
            RETURNS TRIGGER AS \$\$
            DECLARE
                _nuevo_indice INT;
            BEGIN
                -- Si viene un índice explícito válido (> 0), se respeta
                IF NEW.indice_grupo IS NOT NULL AND NEW.indice_grupo > 0 THEN
                    RETURN NEW;
                END IF;

                -- Rellenar el menor hueco positivo disponible (>= 1) entre cursos activos y no plantilla
                SELECT COALESCE(
                    (
                        SELECT s.n
                        FROM generate_series(1, (
                            SELECT COALESCE(MAX(indice_grupo), 0) + 1
                            FROM curso.curso
                            WHERE id_asignacion_plan = NEW.id_asignacion_plan
                              AND agno_real IS NOT DISTINCT FROM NEW.agno_real
                              AND semestre_real IS NOT DISTINCT FROM NEW.semestre_real
                              AND fecha_eliminacion IS NULL
                              AND es_plantilla = false
                        )) AS s(n)
                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM curso.curso c
                            WHERE c.id_asignacion_plan = NEW.id_asignacion_plan
                              AND c.agno_real IS NOT DISTINCT FROM NEW.agno_real
                              AND c.semestre_real IS NOT DISTINCT FROM NEW.semestre_real
                              AND c.fecha_eliminacion IS NULL
                              AND c.es_plantilla = false
                              AND c.indice_grupo = s.n
                        )
                        ORDER BY s.n ASC
                        LIMIT 1
                    ),
                    1
                ) INTO _nuevo_indice;

                NEW.indice_grupo := _nuevo_indice;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION curso.fn_indice_grupo_auto() 
            RETURNS TRIGGER AS \$\$
            DECLARE
                _ultimo_indice INT;
            BEGIN
                SELECT COALESCE(MAX(indice_grupo), 0) 
                INTO _ultimo_indice
                FROM curso.curso
                WHERE id_asignacion_plan = NEW.id_asignacion_plan
                  AND agno_real = NEW.agno_real
                  AND semestre_real = NEW.semestre_real;

                NEW.indice_grupo := _ultimo_indice + 1;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }
};

