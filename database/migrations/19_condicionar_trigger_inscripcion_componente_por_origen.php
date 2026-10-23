<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Condiciona el trigger tr_inscribir_componente_curso_automaticamente para que
 * no inscriba a ciegas en todas las componentes si la inscripción proviene de
 * Intranet (cod_inscripcion_uta IS NOT NULL o sesión de sincronización activa).
 *
 * En altas federadas desde Intranet, la responsabilidad de matricular al alumno
 * en la componente con acta oficial corresponde a IntranetService.
 * En inscripciones manuales de UTAMED (sin código UTA), se mantiene la autoinscripción
 * en todas las componentes del curso.
 *
 * NOTA SOBRE CUPOS (Deuda Técnica / Arquitectura):
 * El modelo actual de UTAMED no contempla cupos por curso ni por componente.
 * Si en el futuro se implementa un atributo de cupo, se deberá revisar o suprimir
 * este trigger de base de datos para delegar la autoinscripción a la capa de
 * aplicación (servicios Laravel), garantizando que no se sobrepasen los aforos
 * permitidos antes de confirmar la matrícula.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION curso.fn_inscribir_componente_curso_automaticamente()
            RETURNS trigger AS \$\$
            DECLARE
                ids_componente integer[];
            BEGIN
                -- Si la inscripción proviene de Intranet (trae código UTA o flag de sincronización),
                -- no autoinscribir ciegamente en todas las componentes locales.
                -- IntranetService inscribe explícitamente en la componente con acta oficial.
                IF NEW.cod_inscripcion_uta IS NOT NULL OR current_setting('utamed.skip_componente_trigger', true) = 'on' THEN
                    RETURN NEW;
                END IF;

                -- Origen manual en UTAMED: recuperar las componentes definidas por el curso
                SELECT array_agg(s.id_componente) INTO ids_componente
                FROM curso.componente s
                WHERE s.id_curso = NEW.id_curso;

                -- Insertar inscripción en componente automáticamente por cada componente del curso
                IF ids_componente IS NOT NULL AND array_length(ids_componente, 1) > 0 THEN
                    INSERT INTO curso.inscripcion_componente (
                        id_estudiante,
                        id_componente
                    ) SELECT
                        NEW.id_estudiante,
                        unnest(ids_componente);
                END IF;

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
            CREATE OR REPLACE FUNCTION curso.fn_inscribir_componente_curso_automaticamente()
            RETURNS trigger AS \$\$
            DECLARE
                ids_componente integer[];
            BEGIN
                SELECT array_agg(s.id_componente) INTO ids_componente
                FROM curso.componente s
                WHERE s.id_curso = NEW.id_curso;

                IF ids_componente IS NOT NULL AND array_length(ids_componente, 1) > 0 THEN
                    INSERT INTO curso.inscripcion_componente (
                        id_estudiante,
                        id_componente
                    ) SELECT
                        NEW.id_estudiante,
                        unnest(ids_componente);
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }
};
