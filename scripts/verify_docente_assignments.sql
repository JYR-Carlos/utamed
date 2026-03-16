-- Verificar asignaciones de docentes a secciones y permisos
-- Ejecutar con: psql -U postgres utamed -f scripts/verify_docente_assignments.sql

SET search_path TO public;

\echo ''
\echo '==================================================================================='
\echo '🔍 ASIGNACIONES DE DOCENTES A SECCIONES Y PERMISOS EN CONTEXTO'
\echo '==================================================================================='
\echo ''

-- Parte 1: Docentes asignados a secciones
\echo '📖 DOCENTES ASIGNADOS A SECCIONES (relación física en curso.seccion)'
\echo ''

SELECT
    d.id_docente,
    u.name AS docente_nombre,
    COUNT(DISTINCT s.id_seccion) AS total_secciones,
    STRING_AGG(
        DISTINCT c.nombre,
        ', '
        ORDER BY c.nombre
    ) AS cursos_asignados
FROM
    docente d
    JOIN usuario u ON d.id_usuario = u.id_usuario
    LEFT JOIN seccion s ON d.id_docente = s.id_docente
    LEFT JOIN curso c ON s.id_curso = c.id_curso
GROUP BY
    d.id_docente,
    u.name
ORDER BY d.id_docente;

\echo '' \echo '📋 ROLES Y CONTEXTOS ASIGNADOS A DOCENTES' \echo ''

SELECT
    d.id_docente,
    u.name AS docente_nombre,
    r.nombre AS rol_nombre,
    COALESCE(
        ct.tipo || ': ' || ct.nombre,
        'GLOBAL'
    ) AS contexto
FROM
    docente d
    JOIN usuario u ON d.id_usuario = u.id_usuario
    LEFT JOIN usuario_rol_asignacion ura ON u.id_usuario = ura.id_usuario
    AND ura.esta_activo = true
    LEFT JOIN rol r ON ura.id_rol = r.id_rol
    LEFT JOIN contexto ct ON ura.id_contexto = ct.id_contexto
WHERE
    EXISTS (
        SELECT 1
        FROM usuario_rol_asignacion
        WHERE
            id_usuario = u.id_usuario
            AND esta_activo = true
    )
ORDER BY d.id_docente, r.nombre;

\echo ''
\echo '⚠️  ANÁLISIS: DOCENTES CON SECCIONES PERO SIN PERMISOS EN CURSO'
\echo ''

-- Docentes que tienen secciones asignadas pero NO tienen rol en el CURSO específico
WITH
    docentes_secciones AS (
        SELECT
            d.id_docente,
            u.name,
            s.id_curso,
            c.nombre AS curso_nombre,
            ct.id_contexto,
            ct.tipo AS contexto_tipo
        FROM
            docente d
            JOIN usuario u ON d.id_usuario = u.id_usuario
            JOIN seccion s ON d.id_docente = s.id_docente
            JOIN curso c ON s.id_curso = c.id_curso
            LEFT JOIN contexto ct ON ct.contexto_mappeable_id = c.id_curso
            AND ct.contexto_mappeable_type = 'Curso'
    ),
    docentes_roles AS (
        SELECT DISTINCT
            d.id_docente,
            ura.id_contexto
        FROM
            docente d
            JOIN usuario_rol_asignacion ura ON d.id_usuario = ura.id_usuario
        WHERE
            ura.esta_activo = true
    )
SELECT
    ds.id_docente,
    ds.name,
    ds.curso_nombre,
    ds.id_curso,
    CASE
        WHEN dr.id_docente IS NULL THEN '❌ NO TIENE ROLES'
        WHEN ds.id_contexto IS NULL THEN '⚠️  Sección sin contexto mapeado'
        WHEN dr.id_contexto != ds.id_contexto THEN '⚠️  Rol NO está en este curso'
        ELSE '✅ OK'
    END AS estado_permisos
FROM
    docentes_secciones ds
    LEFT JOIN docentes_roles dr ON ds.id_docente = dr.id_docente
ORDER BY ds.id_docente, ds.curso_nombre;

\echo ''
\echo '==================================================================================='