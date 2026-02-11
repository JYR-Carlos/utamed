-- Fix for fn_inscribir_seccion_curso_automaticamente function
-- This fixes the SQL error: "falta una entrada para la tabla «s» en la cláusula FROM"

CREATE OR REPLACE FUNCTION fn_inscribir_seccion_curso_automaticamente()
RETURNS trigger AS $$
DECLARE
    ids_seccion integer[];
BEGIN
    -- Recuperar las secciones definidas por el curso (ids_seccion)
    SELECT array_agg(s.id_seccion) INTO ids_seccion
    FROM "utamed.Curso"."Seccion" s
    WHERE s.id_curso = NEW.id_curso;

    -- Insertar inscripción en seccion automáticamente por cada sección del curso
    IF ids_seccion IS NOT NULL AND array_length(ids_seccion, 1) > 0 THEN
        INSERT INTO "utamed.Curso"."Inscripcion_Seccion" (
            id_estudiante,
            id_seccion,
            id_curso
        ) VALUES (
            NEW.id_estudiante,
            unnest(ids_seccion),
            NEW.id_curso
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;