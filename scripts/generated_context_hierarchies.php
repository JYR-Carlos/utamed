<?php

// GENERADO AUTOMÁTICAMENTE - REVISAR Y AJUSTAR SI ES NECESARIO

$contextHierarchies = [
    'context_column' => 'id_contexto',
    'direct' => [
        'administrativo.carrera' => 'carrera',
        'administrativo.departamento' => 'departamento',
        'administrativo.facultad' => 'facultad',
        'agenda.actividad' => 'actividad',
        'curso.curso' => 'curso',
    ],
    'hierarchical' => [
        'administrativo.asignacion_plan' => [
            ['plan', 'carrera']
        ],
        'administrativo.plan' => [
            ['carrera']
        ],
        'administrativo.programa' => [
            ['curso']
        ],
        'agenda.actividad_asignada' => [
            ['actividad']
        ],
        'agenda.asignado_actividad' => [
            ['actividad_asignada', 'actividad'],
            ['estudiante', 'carrera']
        ],
        'curso.asistencia' => [
            ['inscripcion_seccion', 'estudiante', 'carrera'],
            ['inscripcion_seccion', 'seccion', 'curso']
        ],
        'curso.inscripcion_curso' => [
            ['curso'],
            ['estudiante', 'carrera']
        ],
        'curso.inscripcion_seccion' => [
            ['estudiante', 'carrera'],
            ['seccion', 'curso']
        ],
        'curso.seccion' => [
            ['curso']
        ],
        'curso.unidad' => [
            ['curso']
        ],
    ],
    'global' => [
        'administrativo.asignatura' => 'asignatura',
        'usuario.docente' => 'docente',
        'usuario.estudiante' => 'estudiante',
        'usuario.rol' => 'rol',
        'usuario.usuario' => 'usuario',
    ],
    'complex' => [
        // 'agenda.agenda', // TODO: revisar manualmente
    ],
];

/*
// TABLAS FILTRADAS (Excluidas por prefijo o configuración):
// - administrativo.vw_usuarios_completo: Prefijo filtrado
// - agenda.estado_actividad: Prefijo filtrado
// - curso.tipo_seccion: Prefijo filtrado
// - usuario.asignacion_rol_permiso: Tabla filtrada
// - usuario.contexto: Tabla filtrada
// - usuario.permiso: Tabla filtrada
// - usuario.tipo_contexto: Prefijo filtrado
// - usuario.usuario_permiso_especial: Tabla filtrada
// - usuario.usuario_rol_asignacion: Tabla filtrada
// - usuario.vw_permisos_usuario: Prefijo filtrado
*/
