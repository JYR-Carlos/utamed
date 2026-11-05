<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FEAT-12: Introducción de curso.clase y normalización de curso.asistencia.
 *
 * Transforma las sesiones implícitas (dia, hora_inicio, hora_fin) repetidas en cada fila
 * de asistencia en una entidad relacional de primer orden (curso.clase) vinculada al componente,
 * preservando todas las asistencias históricas mediante backfill relinked.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Crear tabla curso.clase
        DB::statement('
            CREATE TABLE IF NOT EXISTS curso.clase (
                id_clase integer NOT NULL GENERATED ALWAYS AS IDENTITY,
                dia date NOT NULL,
                hora_inicio time NOT NULL,
                hora_fin time NOT NULL,
                id_componente smallint NOT NULL,
                CONSTRAINT pk_clase PRIMARY KEY (id_clase),
                CONSTRAINT chk_clase_horario_valido CHECK (hora_fin > hora_inicio)
            );
        ');

        DB::statement('ALTER TABLE curso.clase OWNER TO utamed;');

        // Restricciones de curso.clase
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_componente'
                               AND conrelid = 'curso.clase'::regclass) THEN
                    ALTER TABLE curso.clase ADD CONSTRAINT fk_componente FOREIGN KEY (id_componente)
                    REFERENCES curso.componente (id_componente) MATCH FULL
                    ON DELETE RESTRICT ON UPDATE CASCADE;
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_clase_componente_horario'
                               AND conrelid = 'curso.clase'::regclass) THEN
                    ALTER TABLE curso.clase ADD CONSTRAINT uq_clase_componente_horario
                    UNIQUE (id_componente, dia, hora_inicio, hora_fin);
                END IF;
            END \$\$;
        ");

        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON curso.clase TO utamed;');

        // 2. Columna id_clase en curso.asistencia (inicialmente nullable para permitir backfill)
        DB::statement('ALTER TABLE curso.asistencia ADD COLUMN IF NOT EXISTS id_clase integer;');

        // 3. Backfill: Crear las clases a partir de las asistencias preexistentes
        DB::statement('
            INSERT INTO curso.clase (id_componente, dia, hora_inicio, hora_fin)
            SELECT DISTINCT ic.id_componente, a.dia, a.hora_inicio, a.hora_fin
            FROM curso.asistencia a
            JOIN curso.inscripcion_componente ic ON ic.id_inscripcion_componente = a.id_inscripcion_componente
            WHERE a.id_clase IS NULL
              AND a.hora_fin > a.hora_inicio
            ON CONFLICT (id_componente, dia, hora_inicio, hora_fin) DO NOTHING;
        ');

        // 4. Relinkear asistencia.id_clase con la clase correspondiente
        DB::statement('
            UPDATE curso.asistencia a
            SET id_clase = c.id_clase
            FROM curso.inscripcion_componente ic
            JOIN curso.clase c ON c.id_componente = ic.id_componente
            WHERE a.id_inscripcion_componente = ic.id_inscripcion_componente
              AND a.dia = c.dia
              AND a.hora_inicio = c.hora_inicio
              AND a.hora_fin = c.hora_fin
              AND a.id_clase IS NULL;
        ');

        // 5. Limpieza de seguridad: deduplicar eventuales colisiones previas y descartar huérfanas
        DB::statement('
            DELETE FROM curso.asistencia a
            WHERE a.id_clase IS NOT NULL
              AND a.id_asistencia < (
                  SELECT MAX(a2.id_asistencia)
                  FROM curso.asistencia a2
                  WHERE a2.id_clase = a.id_clase
                    AND a2.id_inscripcion_componente = a.id_inscripcion_componente
              );
        ');

        DB::statement('DELETE FROM curso.asistencia WHERE id_clase IS NULL;');

        // 6. Forzar NOT NULL y restricciones relacionales en curso.asistencia
        DB::statement('ALTER TABLE curso.asistencia ALTER COLUMN id_clase SET NOT NULL;');

        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_clase'
                               AND conrelid = 'curso.asistencia'::regclass) THEN
                    ALTER TABLE curso.asistencia ADD CONSTRAINT fk_clase FOREIGN KEY (id_clase)
                    REFERENCES curso.clase (id_clase) MATCH FULL
                    ON DELETE CASCADE ON UPDATE CASCADE;
                END IF;

                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_asistencia_clase_inscripcion'
                               AND conrelid = 'curso.asistencia'::regclass) THEN
                    ALTER TABLE curso.asistencia ADD CONSTRAINT uq_asistencia_clase_inscripcion
                    UNIQUE (id_clase, id_inscripcion_componente);
                END IF;
            END \$\$;
        ");

        // 7. Eliminar columnas redundantes
        DB::statement('ALTER TABLE curso.asistencia DROP COLUMN IF EXISTS dia;');
        DB::statement('ALTER TABLE curso.asistencia DROP COLUMN IF EXISTS hora_inicio;');
        DB::statement('ALTER TABLE curso.asistencia DROP COLUMN IF EXISTS hora_fin;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restaurar columnas en curso.asistencia
        DB::statement('ALTER TABLE curso.asistencia ADD COLUMN IF NOT EXISTS dia date;');
        DB::statement('ALTER TABLE curso.asistencia ADD COLUMN IF NOT EXISTS hora_inicio time;');
        DB::statement('ALTER TABLE curso.asistencia ADD COLUMN IF NOT EXISTS hora_fin time;');

        // 2. Repoblar horarios desde curso.clase
        DB::statement('
            UPDATE curso.asistencia a
            SET dia = c.dia,
                hora_inicio = c.hora_inicio,
                hora_fin = c.hora_fin
            FROM curso.clase c
            WHERE a.id_clase = c.id_clase;
        ');

        DB::statement('ALTER TABLE curso.asistencia ALTER COLUMN dia SET NOT NULL;');
        DB::statement('ALTER TABLE curso.asistencia ALTER COLUMN hora_inicio SET NOT NULL;');
        DB::statement('ALTER TABLE curso.asistencia ALTER COLUMN hora_fin SET NOT NULL;');

        // 3. Quitar restricciones y columna id_clase
        DB::statement('ALTER TABLE curso.asistencia DROP CONSTRAINT IF EXISTS uq_asistencia_clase_inscripcion;');
        DB::statement('ALTER TABLE curso.asistencia DROP CONSTRAINT IF EXISTS fk_clase;');
        DB::statement('ALTER TABLE curso.asistencia DROP COLUMN IF EXISTS id_clase;');

        // 4. Eliminar curso.clase
        DB::statement('DROP TABLE IF EXISTS curso.clase CASCADE;');
    }
};
