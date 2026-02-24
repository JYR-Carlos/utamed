-- ========================================
-- Ver todos los permisos del usuario ID 5
-- ========================================

-- 1. PERMISOS ESPECIALES (UPE)
SELECT 
    'PERMISO ESPECIAL' as tipo,
    upe.id_upe,
    upe.id_usuario,
    u.nombre1 || ' ' || u.apellido1 as usuario_nombre,
    p.nombre as permiso_nombre,
    p.slug,
    c.contexto_display as contexto,
    CASE upe.esta_permitido 
        WHEN true THEN 'PERMITIDO ✓'
        WHEN false THEN 'DENEGADO ✗'
        ELSE 'HEREDADO ?'
    END as estado,
    upe.puede_delegar,
    upe.fecha_inicio_planificada,
    upe.fecha_fin_planificada,
    upe.esta_activo,
    upe.fue_borrado
FROM usuario.usuario_permiso_especial upe
JOIN usuario.usuario u ON upe.id_usuario = u.id_usuario
JOIN usuario.permiso p ON upe.id_permiso = p.id_permiso
LEFT JOIN usuario.contexto c ON upe.id_contexto = c.id_contexto
WHERE upe.id_usuario = 5
    AND upe.esta_activo = true
    AND upe.fue_borrado = false
ORDER BY c.contexto_display, p.nombre;

-- 2. PERMISOS POR ROLES
SELECT 
    'PERMISO POR ROL' as tipo,
    NULL::int as id_upe,
    ura.id_usuario,
    u.nombre1 || ' ' || u.apellido1 as usuario_nombre,
    p.nombre as permiso_nombre,
    p.slug,
    c.contexto_display as contexto,
    'POR ROL' as estado,
    arp.puede_delegar_permisos as puede_delegar,
    ura.fecha_inicio_planificada,
    ura.fecha_fin_planificada,
    ura.esta_activo,
    ura.fue_eliminado as fue_borrado
FROM usuario.usuario_rol_asignacion ura
JOIN usuario.usuario u ON ura.id_usuario = u.id_usuario
JOIN usuario.rol r ON ura.id_rol = r.id_rol
JOIN usuario.asignacion_rol_permiso arp ON r.id_rol = arp.id_rol
JOIN usuario.permiso p ON arp.id_permiso = p.id_permiso
LEFT JOIN usuario.contexto c ON ura.id_contexto = c.id_contexto
WHERE ura.id_usuario = 5
    AND ura.esta_activo = true
    AND ura.fue_eliminado = false
ORDER BY c.contexto_display, p.nombre;

-- 3. RESUMEN AGRUPADO
SELECT 
    COUNT(DISTINCT CASE WHEN upe.esta_activo AND NOT upe.fue_borrado THEN upe.id_upe END) as permisos_especiales_activos,
    COUNT(DISTINCT CASE WHEN upe.esta_permitido = true AND upe.esta_activo AND NOT upe.fue_borrado THEN upe.id_upe END) as permisos_especiales_permitidos,
    COUNT(DISTINCT CASE WHEN upe.esta_permitido = false AND upe.esta_activo AND NOT upe.fue_borrado THEN upe.id_upe END) as permisos_especiales_denegados,
    COUNT(DISTINCT CASE WHEN ura.esta_activo AND NOT ura.fue_eliminado THEN ura.id_rol END) as roles_asignados,
    COUNT(DISTINCT CASE WHEN ura.esta_activo AND NOT ura.fue_eliminado THEN arp.id_permiso END) as permisos_por_roles
FROM usuario.usuario u
LEFT JOIN usuario.usuario_permiso_especial upe ON u.id_usuario = upe.id_usuario
LEFT JOIN usuario.usuario_rol_asignacion ura ON u.id_usuario = ura.id_usuario
LEFT JOIN usuario.asignacion_rol_permiso arp ON ura.id_rol = arp.id_rol
WHERE u.id_usuario = 5;

-- 4. ROLES ASIGNADOS AL USUARIO
SELECT 
    'ROL ASIGNADO' as tipo,
    r.id_rol,
    r.nombre as rol_nombre,
    r.descripcion,
    c.contexto_display as contexto,
    ura.fecha_inicio_planificada,
    ura.fecha_fin_planificada,
    ura.esta_activo,
    ura.fue_eliminado
FROM usuario.usuario_rol_asignacion ura
JOIN usuario.rol r ON ura.id_rol = r.id_rol
LEFT JOIN usuario.contexto c ON ura.id_contexto = c.id_contexto
WHERE ura.id_usuario = 5
    AND ura.esta_activo = true
    AND ura.fue_eliminado = false
ORDER BY c.contexto_display, r.nombre;
