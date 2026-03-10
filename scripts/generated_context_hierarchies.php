<?php

// GENERADO AUTOMÁTICAMENTE - REVISAR Y AJUSTAR SI ES NECESARIO
// Para regenerar: php scripts/analyze_context_hierarchies.php -o scripts/generated_context_hierarchies.php

return [
    'context_column' => 'id_contexto',

    // Tablas que poseen id_contexto directamente.
    // 'parent': null (raíz), string (FK directa, schema.tabla), o array (ruta indirecta hasta padre).
    // 'contextTypeName': nombre corto del tipo de contexto (usarse en lugar de extraer de schema.tabla).
    'direct' => [
        'administrativo.carrera' => [
            'contextTypeName' => 'carrera',
            'parent' => 'administrativo.departamento', // auto (FK directa)
        ],
        'administrativo.departamento' => [
            'contextTypeName' => 'departamento',
            'parent' => 'administrativo.facultad', // auto (FK directa)
        ],
        'administrativo.facultad' => [
            'contextTypeName' => 'facultad',
            'parent' => null, // raíz
        ],
        'agenda.actividad' => [
            'contextTypeName' => 'actividad',
            'parent' => ['curso.seccion', 'curso.curso'], // manual (ruta indirecta)
        ],
        'curso.curso' => [
            'contextTypeName' => 'curso',
            'parent' => ['administrativo.asignacion_plan', 'administrativo.plan', 'administrativo.carrera'], // manual (ruta indirecta)
        ],
    ],

    // Tablas sin id_contexto propias que aplican a nivel global.
    // Lista de nombres completos schema.tabla.
    'global' => [
        'administrativo.asignatura',
        'usuario.docente',
        'usuario.estudiante',
        'usuario.rol',
        'usuario.usuario',
    ],

    // Tablas sin id_contexto propio que llegan a un contexto vía FK chain.
    // Los pasos de cada camino usan nombres completos schema.tabla.
    'hierarchical' => [
        'administrativo.asignacion_plan' => [
            ['administrativo.plan', 'administrativo.carrera']
        ],
        'administrativo.plan' => [
            ['administrativo.carrera']
        ],
        'administrativo.programa' => [
            ['curso.curso']
        ],
        'agenda.actividad_asignada' => [
            ['agenda.actividad']
        ],
        'agenda.asignado_actividad' => [
            ['agenda.actividad_asignada', 'agenda.actividad'],
            ['usuario.estudiante', 'administrativo.carrera']
        ],
        'curso.asistencia' => [
            ['curso.inscripcion_seccion', 'usuario.estudiante', 'administrativo.carrera'],
            ['curso.inscripcion_seccion', 'curso.seccion', 'curso.curso']
        ],
        'curso.inscripcion_curso' => [
            ['curso.curso'],
            ['usuario.estudiante', 'administrativo.carrera']
        ],
        'curso.inscripcion_seccion' => [
            ['usuario.estudiante', 'administrativo.carrera'],
            ['curso.seccion', 'curso.curso']
        ],
        'curso.seccion' => [
            ['curso.curso']
        ],
        'curso.unidad' => [
            ['curso.curso']
        ],
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
