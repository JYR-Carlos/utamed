-- ============================================================================
-- Verificar estado actual de permisos del rol Docente en la BD
-- ============================================================================

-- 1. Contar total de permisos asignados al rol Docente
SELECT
    r.nombre as rol,
    COUNT(p.id_permiso) as cantidad_permisos_asignados
FROM usuario.rol r
    LEFT JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
    LEFT JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE
    r.nombre = 'Docente'
GROUP BY
    r.nombre;

-- 2. Listar TODOS los permisos del rol Docente
SELECT r.nombre as rol, p.slug as permiso, p.descripcion
FROM usuario.rol r
    JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
    JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE
    r.nombre = 'Docente'
ORDER BY p.slug;

-- 3. Verificar específicamente los permisos de PROGRAMAS
SELECT r.nombre as rol, p.slug as permiso, p.descripcion
FROM usuario.rol r
    JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
    JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE
    r.nombre = 'Docente'
    AND p.slug LIKE '%programa%'
ORDER BY p.slug;

-- 4. Verificar si falta el permiso "(cursos_programas:ver"
SELECT
    'cursos/programas:ver' as permiso_buscado,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM usuario.rol r
                JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
                JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
            WHERE
                r.nombre = 'Docente'
                AND p.slug = 'cursos/programas:ver'
        ) THEN 'SÍ EXISTE'
        ELSE 'NO EXISTE'
    END as estado;

-- 5. Comparar: ¿Qué permisos DEBERÍA tener vs tiene?
WITH
    docente_should_have AS (
        -- Los 42 permisos que define roles_config.php
        SELECT 'cursos:ver' as slug
        UNION ALL
        SELECT 'cursos:editar'
        UNION ALL
        SELECT 'cursos:eliminar'
        UNION ALL
        SELECT 'cursos/inscripciones:ver'
        UNION ALL
        SELECT 'cursos/inscripciones:inscribir_alumnos'
        UNION ALL
        SELECT 'cursos/inscripciones:eliminar_inscripciones'
        UNION ALL
        SELECT 'cursos/secciones:ver'
        UNION ALL
        SELECT 'cursos/secciones:crear'
        UNION ALL
        SELECT 'cursos/secciones:crear_plantilla'
        UNION ALL
        SELECT 'cursos/secciones:editar'
        UNION ALL
        SELECT 'cursos/secciones:eliminar'
        UNION ALL
        SELECT 'cursos/unidades:ver'
        UNION ALL
        SELECT 'cursos/unidades:crear'
        UNION ALL
        SELECT 'cursos/unidades:crear_plantilla'
        UNION ALL
        SELECT 'cursos/unidades:editar'
        UNION ALL
        SELECT 'cursos/unidades:eliminar'
        UNION ALL
        SELECT 'cursos/actividades:ver'
        UNION ALL
        SELECT 'cursos/actividades:crear'
        UNION ALL
        SELECT 'cursos/actividades:crear_plantilla'
        UNION ALL
        SELECT 'cursos/actividades:editar'
        UNION ALL
        SELECT 'cursos/actividades:eliminar'
        UNION ALL
        SELECT 'cursos/actividades:evaluar'
        UNION ALL
        SELECT 'cursos/actividades:dar_feedback'
        UNION ALL
        SELECT 'cursos/actividades:descargar_entregas'
        UNION ALL
        SELECT 'cursos/actividades:enviar_recordatorios'
        UNION ALL
        SELECT 'cursos/actividades:subir_entregas'
        UNION ALL
        SELECT 'cursos/actividades/grupos:ver'
        UNION ALL
        SELECT 'cursos/actividades/grupos:crear'
        UNION ALL
        SELECT 'cursos/actividades/grupos:editar'
        UNION ALL
        SELECT 'cursos/actividades/grupos:eliminar'
        UNION ALL
        SELECT 'cursos/programas:ver'
        UNION ALL
        SELECT 'cursos/programas:agregar'
        UNION ALL
        SELECT 'cursos/programas:eliminar'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_1'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_2'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_3'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_4'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_5'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_6'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_7'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_8'
        UNION ALL
        SELECT 'cursos/programas/modificar:modulo_9'
    ),
    docente_has AS (
        SELECT p.slug
        FROM usuario.rol r
            JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
            JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
        WHERE
            r.nombre = 'Docente'
    )
SELECT
    sh.slug as permiso,
    CASE
        WHEN dh.slug IS NOT NULL THEN '✅ TIENE'
        ELSE '❌ FALTA'
    END as estado
FROM
    docente_should_have sh
    LEFT JOIN docente_has dh ON sh.slug = dh.slug
ORDER BY permiso;