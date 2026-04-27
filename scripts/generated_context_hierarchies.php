<?php

// GENERADO AUTOMÁTICAMENTE - REVISAR Y AJUSTAR SI ES NECESARIO
// Para regenerar: php scripts/analyze_context_hierarchies.php

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
            'parent' => 'curso.componente', // auto (FK directa)
        ],
        'curso.componente' => [
            'contextTypeName' => 'componente',
            'parent' => 'curso.curso', // auto (FK directa)
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
        'operations.archivos',
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
        'agenda.actividad_asignada_grupo' => [
            ['agenda.actividad']
        ],
        'agenda.agenda' => [
            ['agenda.actividad_asignada_grupo', 'agenda.actividad']
        ],
        'agenda.evaluacion' => [
            ['agenda.agenda', 'agenda.actividad_asignada_grupo', 'agenda.actividad']
        ],
        'agenda.integrante_grupo' => [
            ['agenda.actividad_asignada_grupo', 'agenda.actividad'],
            ['usuario.estudiante', 'administrativo.carrera']
        ],
        'curso.asistencia' => [
            ['curso.inscripcion_componente', 'curso.componente'],
            ['curso.inscripcion_componente', 'usuario.estudiante', 'administrativo.carrera']
        ],
        'curso.docente_componente' => [
            ['curso.componente']
        ],
        'curso.inscripcion_componente' => [
            ['curso.componente'],
            ['usuario.estudiante', 'administrativo.carrera']
        ],
        'curso.inscripcion_curso' => [
            ['curso.curso'],
            ['usuario.estudiante', 'administrativo.carrera']
        ],
        'curso.programa' => [
            ['curso.curso']
        ],
        'curso.unidad' => [
            ['curso.curso']
        ],
    ],

    'complex' => [
        // 'agenda.rubrica', // TODO: revisar manualmente
    ],
];

/*
// TABLAS FILTRADAS (Excluidas por prefijo o configuración):
// - administrativo.vw_usuarios_completo: Prefijo filtrado
// - agenda.estado_actividad: Prefijo filtrado
// - agenda.estado_rubrica: Prefijo filtrado
// - agenda.tipo_registro_agenda: Prefijo filtrado
// - curso.tipo_componente: Prefijo filtrado
// - usuario.asignacion_rol_permiso: Tabla filtrada
// - usuario.cache: Tabla filtrada
// - usuario.cache_locks: Tabla filtrada
// - usuario.contexto: Tabla filtrada
// - usuario.failed_jobs: Tabla filtrada
// - usuario.job_batches: Tabla filtrada
// - usuario.jobs: Tabla filtrada
// - usuario.migrations: Tabla filtrada
// - usuario.password_reset_tokens: Tabla filtrada
// - usuario.permiso: Tabla filtrada
// - usuario.sessions: Tabla filtrada
// - usuario.tipo_contexto: Prefijo filtrado
// - usuario.usuario_permiso_especial: Tabla filtrada
// - usuario.usuario_rol_asignacion: Tabla filtrada
// - usuario.vw_permisos_usuario: Prefijo filtrado
*/
