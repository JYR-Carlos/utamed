-- ============================================================
-- Verificar y Asignar permisos necesarios al rol AYUDANTE
-- ============================================================

-- 1. Ver el rol AYUDANTE
SELECT 
    id_rol,
    nombre,
    descripcion,
    es_default
FROM usuario.rol
WHERE nombre = 'ayudante';

-- 2. Ver QUÉ permisos tiene actualmente el rol AYUDANTE
SELECT 
    r.nombre as rol,
    p.id_permiso,
    p.nombre,
    p.slug,
    arp.puede_delegar_permisos,
    COUNT(*) OVER () as total_permisos_del_rol
FROM usuario.rol r
LEFT JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
LEFT JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE r.nombre = 'ayudante'
ORDER BY p.slug;

-- 3. Ver qué permisos EXISTEN para programa
SELECT 
    id_permiso,
    nombre,
    slug
FROM usuario.permiso
WHERE slug LIKE '%programa%' OR slug LIKE '%actividad%' OR slug LIKE '%curso%'
ORDER BY slug;

-- 4. Verificar si los permisos necesarios EXISTEN en la DB
SELECT 
    CASE WHEN id_permiso IS NOT NULL THEN '✓ EXISTE' ELSE '✗ NO EXISTE' END as estado,
    id_permiso,
    nombre,
    slug
FROM usuario.permiso
WHERE slug IN ('curso/programa:ver', 'curso/programa:editar')
   OR slug IN ('cursos/programas:ver', 'cursos/actividades:ver')
ORDER BY slug;

-- 5. Ver si AYUDANTE tiene permisos de ACTIVIDADES (que son similares a programa)
SELECT 
    r.nombre,
    COUNT(DISTINCT CASE WHEN p.slug LIKE '%actividad%' THEN p.id_permiso END) as permisos_actividades,
    COUNT(DISTINCT CASE WHEN p.slug LIKE '%programa%' THEN p.id_permiso END) as permisos_programa,
    STRING_AGG(DISTINCT p.slug, ', ') FILTER (WHERE p.slug LIKE '%actividad%' OR p.slug LIKE '%programa%') as permisos
FROM usuario.rol r
LEFT JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
LEFT JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE r.nombre = 'ayudante'
GROUP BY r.nombre;

-- 6. SQL para AGREGAR los permisos necesarios si NO LOS TIENE
-- (Esto es INFORMATIVO - revisar antes de ejecutar)
-- SELECT 'INSERT' as accion,
-- 'usuario.asignacion_rol_permiso (id_rol, id_permiso, puede_delegar_permisos)' as tabla_campos,
-- (SELECT id_rol FROM usuario.rol WHERE nombre = 'ayudante'),
-- (SELECT id_permiso FROM usuario.permiso WHERE slug = 'curso/programa:ver'),
-- false
-- FROM usuario.rol
-- WHERE nombre = 'ayudante'
-- AND NOT EXISTS (
--     SELECT 1 FROM usuario.asignacion_rol_permiso arp
--     JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
--     WHERE arp.id_rol = (SELECT id_rol FROM usuario.rol WHERE nombre = 'ayudante')
--       AND p.slug = 'curso/programa:ver'
-- );
