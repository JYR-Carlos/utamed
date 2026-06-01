-- =============================================================================
-- DEBUG: Endpoint /admin/asignaturas/:id/docentes-sugeridos
-- Cambia el valor de :asignatura_id en cada bloque si necesitas otra asignatura.
-- =============================================================================

-- PARÁMETRO PRINCIPAL — ajustar aquí
-- Para filtrar por nombre, ajustar la cláusula ILIKE al final del archivo.
\set asignatura_id 6


-- =============================================================================
-- 1. INFORMACIÓN DE LA ASIGNATURA
-- =============================================================================
SELECT
    id_asignatura,
    cod_asignatura,
    nombre,
    fecha_eliminacion IS NOT NULL AS eliminada
FROM administrativo.asignatura
WHERE id_asignatura = :asignatura_id;


-- =============================================================================
-- 2. DOCENTES HISTÓRICOS
--    Son los que ya impartieron al menos un componente de un curso
--    cuya asignacion_plan apunta a esta asignatura.
--    Equivale al primer bloque whereHas del endpoint.
-- =============================================================================
SELECT
    d.id_docente,
    d.id_usuario,
    u.nombre1,
    u.nombre2,
    u.apellido1,
    u.apellido2,
    TRIM(CONCAT_WS(' ',
        u.nombre1,
        u.nombre2,
        u.apellido1,
        u.apellido2
    ))                          AS nombre_completo,
    u.rut,
    u.email,
    u.esta_activo,
    u.fecha_eliminacion IS NOT NULL AS usuario_soft_deleted,
    d.grado,
    d.titulo,
    d.cargo,
    'historico'                 AS tipo_docente
FROM usuario.docente d
JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
WHERE u.esta_activo = TRUE
  AND u.fecha_eliminacion IS NULL
  AND EXISTS (
      SELECT 1
      FROM curso.docente_componente dc
      JOIN curso.componente          co  ON co.id_componente = dc.id_componente
      JOIN curso.curso               c   ON c.id_curso       = co.id_curso
      JOIN administrativo.asignacion_plan ap
                                         ON ap.id_asignacion_plan = c.id_asignacion_plan
      WHERE dc.id_docente    = d.id_docente
        AND ap.id_asignatura = :asignatura_id
        AND ap.fecha_eliminacion IS NULL
  )
ORDER BY d.id_docente;


-- =============================================================================
-- 3. DOCENTES "OTROS" (nunca impartieron esta asignatura, pero están activos)
--    Equivale al segundo bloque del endpoint (whereNotIn sobre históricos).
-- =============================================================================
WITH historicos AS (
    SELECT DISTINCT d2.id_docente
    FROM usuario.docente d2
    JOIN usuario.usuario u2 ON u2.id_usuario = d2.id_usuario
    WHERE u2.esta_activo = TRUE
      AND u2.fecha_eliminacion IS NULL
      AND EXISTS (
          SELECT 1
          FROM curso.docente_componente dc
          JOIN curso.componente          co  ON co.id_componente = dc.id_componente
          JOIN curso.curso               c   ON c.id_curso       = co.id_curso
          JOIN administrativo.asignacion_plan ap
                                             ON ap.id_asignacion_plan = c.id_asignacion_plan
          WHERE dc.id_docente    = d2.id_docente
            AND ap.id_asignatura = :asignatura_id
            AND ap.fecha_eliminacion IS NULL
      )
)
SELECT
    d.id_docente,
    d.id_usuario,
    u.nombre1,
    u.nombre2,
    u.apellido1,
    u.apellido2,
    TRIM(CONCAT_WS(' ',
        u.nombre1,
        u.nombre2,
        u.apellido1,
        u.apellido2
    ))                          AS nombre_completo,
    u.rut,
    u.email,
    u.esta_activo,
    u.fecha_eliminacion IS NOT NULL AS usuario_soft_deleted,
    d.grado,
    d.titulo,
    d.cargo,
    'otro'                      AS tipo_docente
FROM usuario.docente d
JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
WHERE u.esta_activo = TRUE
  AND u.fecha_eliminacion IS NULL
  AND d.id_docente NOT IN (SELECT id_docente FROM historicos)
ORDER BY d.id_docente;


-- =============================================================================
-- 4. BÚSQUEDA ESPECÍFICA POR NOMBRE (insensible a mayúsculas)
--    Cambia el valor ILIKE para buscar otro nombre.
-- =============================================================================
SELECT
    d.id_docente,
    d.id_usuario,
    u.nombre1,
    u.nombre2,
    u.apellido1,
    u.apellido2,
    TRIM(CONCAT_WS(' ',
        u.nombre1,
        u.nombre2,
        u.apellido1,
        u.apellido2
    ))                          AS nombre_completo,
    u.rut,
    u.email,
    u.esta_activo,
    u.fecha_eliminacion IS NOT NULL AS usuario_soft_deleted,
    d.grado,
    d.titulo,
    d.cargo
FROM usuario.docente d
JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
WHERE TRIM(CONCAT_WS(' ',
        u.nombre1,
        u.nombre2,
        u.apellido1,
        u.apellido2
      )) ILIKE '%Marcela%Rojas%Medina%';


-- =============================================================================
-- 5. AUDITORÍA: docentes con usuario huérfano o inactivo
--    Estos NO deben aparecer en el endpoint tras el fix, pero conviene
--    verificar que no existan datos corruptos previos.
-- =============================================================================
SELECT
    d.id_docente,
    d.id_usuario,
    u.nombre1,
    u.apellido1,
    u.esta_activo,
    u.fecha_eliminacion IS NOT NULL  AS usuario_soft_deleted,
    CASE
        WHEN u.id_usuario IS NULL              THEN 'SIN USUARIO'
        WHEN u.fecha_eliminacion IS NOT NULL   THEN 'USUARIO ELIMINADO (soft)'
        WHEN u.esta_activo = FALSE             THEN 'USUARIO INACTIVO'
        ELSE 'OK'
    END AS estado
FROM usuario.docente d
LEFT JOIN usuario.usuario u ON u.id_usuario = d.id_usuario
WHERE u.id_usuario IS NULL
   OR u.fecha_eliminacion IS NOT NULL
   OR u.esta_activo = FALSE
ORDER BY d.id_docente;
