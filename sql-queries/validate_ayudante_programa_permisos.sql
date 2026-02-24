-- ============================================================
-- Validar que los permisos de PROGRAMA se asignen al ayudante
-- ============================================================

-- 1. Ver qué permisos DEBERIA tener el AYUDANTE para programa
SELECT 
    p.id_permiso,
    p.nombre,
    p.slug,
    'NECESARIO PARA PROGRAMA' as tipo
FROM usuario.permiso p
WHERE p.slug IN ('curso/programa:ver', 'curso/programa:editar')
ORDER BY p.slug;

-- 2. Ver si el rol AYUDANTE tiene estos permisos asignados
SELECT 
    r.id_rol,
    r.nombre as rol_nombre,
    p.id_permiso,
    p.nombre as permiso_nombre,
    p.slug,
    arp.puede_delegar_permisos,
    'ASIGNADO AL ROL' as tipo
FROM usuario.rol r
JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE r.nombre = 'ayudante'
  AND p.slug IN ('curso/programa:ver', 'curso/programa:editar')
ORDER BY r.nombre, p.slug;

-- 3. Ver todos los permisos del rol AYUDANTE (para contexto)
SELECT 
    r.id_rol,
    r.nombre as rol_nombre,
    r.descripcion,
    COUNT(DISTINCT arp.id_permiso) as total_permisos,
    STRING_AGG(DISTINCT p.slug, ', ' ORDER BY p.slug) as permisos
FROM usuario.rol r
LEFT JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
LEFT JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE r.nombre = 'ayudante'
GROUP BY r.id_rol, r.nombre, r.descripcion;

-- 4. Si queremos verificar para un ayudante específico en un curso específico
-- (User ID 5 y algún curso con contexto)
SELECT 
    u.id_usuario,
    u.nombre1 || ' ' || u.apellido1 as usuario_nombre,
    c.id_contexto,
    c.contexto_display as contexto,
    r.nombre as rol_nombre,
    p.slug,
    p.nombre as permiso_nombre,
    CASE 
        WHEN upe.id_upe IS NOT NULL THEN 'UPE'
        ELSE 'ROL'
    END as fuente,
    CASE 
        WHEN upe.esta_permitido = true THEN '✓ PERMITIDO'
        WHEN upe.esta_permitido = false THEN '✗ DENEGADO'
        ELSE 'POR ROL'
    END as estado
FROM usuario.usuario u
JOIN usuario.usuario_rol_asignacion ura ON u.id_usuario = ura.id_usuario
JOIN usuario.rol r ON ura.id_rol = r.id_rol
JOIN usuario.contexto c ON ura.id_contexto = c.id_contexto
LEFT JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
LEFT JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
LEFT JOIN usuario.usuario_permiso_especial upe 
    ON u.id_usuario = upe.id_usuario 
    AND p.id_permiso = upe.id_permiso 
    AND ura.id_contexto = upe.id_contexto
    AND upe.esta_activo = true
    AND upe.fue_borrado = false
WHERE u.id_usuario = 5  -- Cambiar ID según sea necesario
  AND r.nombre = 'ayudante'
  AND ura.esta_activo = true
  AND ura.fue_eliminado = false
  AND p.slug IN ('curso/programa:ver', 'curso/programa:editar')
ORDER BY c.contexto_display, p.slug;

-- 5. Resumen: ¿Puede el ayudante ID 5 ver y editar el programa en contextos?
SELECT 
    u.id_usuario,
    u.nombre1 || ' ' || u.apellido1 as usuario,
    c.contexto_display as contexto,
    MAX(CASE WHEN p.slug = 'curso/programa:ver' THEN 1 ELSE 0 END) as puede_ver_programa,
    MAX(CASE WHEN p.slug = 'curso/programa:editar' THEN 1 ELSE 0 END) as puede_editar_programa
FROM usuario.usuario u
JOIN usuario.usuario_rol_asignacion ura ON u.id_usuario = ura.id_usuario
JOIN usuario.rol r ON ura.id_rol = r.id_rol
JOIN usuario.contexto c ON ura.id_contexto = c.id_contexto
LEFT JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
LEFT JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
WHERE u.id_usuario = 5
  AND r.nombre = 'ayudante'
  AND ura.esta_activo = true
  AND ura.fue_eliminado = false
GROUP BY u.id_usuario, u.nombre1, u.apellido1, c.contexto_display
ORDER BY c.contexto_display;
