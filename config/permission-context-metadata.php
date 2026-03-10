<?php

// AUTOGENERADO por scripts/generate_models.php — NO EDITAR MANUALMENTE.
// Fuente de verdad: scripts/permissions_config.php
//
// Mapa plano: slug_de_permiso => tipos_de_contexto_válidos[]
// El tipo sigue la convención definida seguida por la tabla usuario.contexto columna "categoria"
//
// Resolución:
//   _actions        → ['global', _valid_context del nodo/ancestro, ...cadena de ancestros]
//   _parent_actions → ['global', _valid_parent_context del nodo]
//   Sin contexto    → ['global']
//   Wildcards :*    → contexto propio del nodo + cadena de ancestros
//
// La cadena de ancestros se infiere recursivamente via _valid_parent_context de los nodos raíz.
// Ej: cursos._valid_parent_context = 'carrera', carreras._valid_parent_context = 'facultad'
//   → cursos:ver = ['global', 'curso', 'carrera', 'departamento', 'facultad']
//
// NOTA: Los permisos con contexto válido 'GLOBAL' (por defecto no poseen el atributo)
// NO pueden definir _parent_actions porque no existe un "padre del padre" en la jerarquía de contextos.
// Los _parent_actions solo tienen sentido en contextos jerárquicos donde hay ancestros definidos.
//
// Consumido por: App\Support\PermissionContextConstraints
return [
    'usuarios:*' => ['global'],
    'usuarios:ver' => ['global'],
    'usuarios:crear' => ['global'],
    'usuarios:editar' => ['global'],
    'usuarios:deshabilitar' => ['global'],
    'usuarios:restablecer_contrasena' => ['global'],
    'usuarios/permisos:*' => ['global'],
    'usuarios/permisos:ver_contextos_disponibles' => ['global'],
    'usuarios/permisos:ver_permisos_totales_asignados' => ['global'],
    'usuarios/permisos/roles:*' => ['global'],
    'usuarios/permisos/roles:gestionar' => ['global'],
    'usuarios/permisos/roles:ver' => ['global'],
    'usuarios/permisos/roles:crear' => ['global'],
    'usuarios/permisos/roles:editar' => ['global'],
    'usuarios/permisos/roles:eliminar' => ['global'],
    'usuarios/permisos/individuales:*' => ['global'],
    'usuarios/permisos/individuales:gestionar' => ['global'],
    'usuarios/permisos/individuales:ver_disponibles' => ['global'],
    'usuarios/permisos/individuales:ver_quien_tiene' => ['global'],
    'facultades:*' => ['global', 'facultad'],
    'facultades:ver' => ['global', 'facultad'],
    'facultades:editar' => ['global', 'facultad'],
    'facultades:crear' => ['global'],
    'facultades:eliminar' => ['global'],
    'departamentos:*' => ['global', 'departamento', 'facultad'],
    'departamentos:ver' => ['global', 'departamento', 'facultad'],
    'departamentos:editar' => ['global', 'departamento', 'facultad'],
    'departamentos:crear' => ['global', 'facultad'],
    'departamentos:eliminar' => ['global', 'facultad'],
    'carreras:*' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras:ver' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras:editar' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras:crear' => ['global', 'departamento'],
    'carreras:eliminar' => ['global', 'departamento'],
    'carreras/planes:*' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras/planes:editar' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras/planes:asignacion_asignaturas' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras/planes:crear' => ['global', 'carrera'],
    'carreras/planes:eliminar' => ['global', 'carrera'],
    'carreras/planes/ver:*' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras/planes/ver:ver_detalles' => ['global', 'carrera', 'departamento', 'facultad'],
    'carreras/planes/ver:ver_malla' => ['global', 'carrera', 'departamento', 'facultad'],
    'asignaturas:*' => ['global'],
    'asignaturas:ver' => ['global'],
    'asignaturas:crear' => ['global'],
    'asignaturas:editar' => ['global'],
    'asignaturas:eliminar' => ['global'],
    'cursos:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos:editar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos:eliminar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos:crear' => ['global', 'carrera'],
    'cursos:crear_plantilla' => ['global', 'carrera'],
    'cursos/inscripciones:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/inscripciones:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/inscripciones:inscribir_alumnos' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/inscripciones:eliminar_inscripciones' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/secciones:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/secciones:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/secciones:crear' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/secciones:crear_plantilla' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/secciones:editar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/secciones:eliminar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/unidades:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/unidades:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/unidades:crear' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/unidades:crear_plantilla' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/unidades:editar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/unidades:eliminar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:crear' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:crear_plantilla' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:editar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:eliminar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:subir_entregas' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:evaluar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:dar_feedback' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:enviar_recordatorios' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades:descargar_entregas' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades/grupos:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades/grupos:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades/grupos:crear' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades/grupos:editar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/actividades/grupos:eliminar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas:ver' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas:agregar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas:eliminar' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:*' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_1' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_2' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_3' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_4' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_5' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_6' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_7' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_8' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    'cursos/programas/modificar:modulo_9' => ['global', 'curso', 'carrera', 'departamento', 'facultad'],
    '*' => ['global'],
];