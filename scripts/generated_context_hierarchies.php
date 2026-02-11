<?php

// GENERADO AUTOMÁTICAMENTE - REVISAR Y AJUSTAR SI ES NECESARIO

$contextHierarchies = [
    'context_column' => 'id_contexto',
    'direct' => [
        'utamed.Administrativo.Carrera' => 'carrera',
        'utamed.Administrativo.Departamento' => 'departamento',
        'utamed.Administrativo.Facultad' => 'facultad',
        'utamed.Agenda.Actividad' => 'actividad',
        'utamed.Curso.Curso' => 'curso',
    ],
    'hierarchical' => [
        'utamed.Administrativo.Asignacion_Plan' => [
            ['Plan', 'Carrera']
        ],
        'utamed.Administrativo.Plan' => [
            ['Carrera']
        ],
        'utamed.Administrativo.Programa' => [
            ['Curso']
        ],
        'utamed.Agenda.Actividad_Asignada' => [
            ['Actividad']
        ],
        'utamed.Agenda.Asignado_Actividad' => [
            ['Actividad_Asignada', 'Actividad'],
            ['Estudiante', 'Carrera']
        ],
        'utamed.Curso.Asistencia' => [
            ['Inscripcion_Seccion', 'Estudiante', 'Carrera'],
            ['Inscripcion_Seccion', 'Seccion', 'Curso']
        ],
        'utamed.Curso.Inscripcion_Curso' => [
            ['Curso'],
            ['Estudiante', 'Carrera']
        ],
        'utamed.Curso.Inscripcion_Seccion' => [
            ['Estudiante', 'Carrera'],
            ['Seccion', 'Curso']
        ],
        'utamed.Curso.Seccion' => [
            ['Curso']
        ],
        'utamed.Curso.Unidad' => [
            ['Curso']
        ],
    ],
    'global' => [
        'utamed.Administrativo.Asignatura' => 'asignatura',
        'utamed.Usuario.Docente' => 'docente',
        'utamed.Usuario.Estudiante' => 'estudiante',
        'utamed.Usuario.Rol' => 'rol',
        'utamed.Usuario.Usuario' => 'usuario',
    ],
    'complex' => [
        // 'utamed.Agenda.Agenda', // TODO: revisar manualmente
    ],
];

/*
// TABLAS FILTRADAS (Excluidas por prefijo o configuración):
// - utamed.Administrativo.vw_usuarios_completo: Prefijo filtrado
// - utamed.Agenda.Estado_Actividad: Prefijo filtrado
// - utamed.Curso.Tipo_Seccion: Prefijo filtrado
// - utamed.Usuario.Asignación_Rol_Permiso: Tabla filtrada
// - utamed.Usuario.Contexto: Tabla filtrada
// - utamed.Usuario.Permiso: Tabla filtrada
// - utamed.Usuario.Tipo_Contexto: Prefijo filtrado
// - utamed.Usuario.Usuario_Permiso_Especial: Tabla filtrada
// - utamed.Usuario.Usuario_Rol_Asignación: Tabla filtrada
// - utamed.Usuario.cache: Tabla filtrada
// - utamed.Usuario.cache_locks: Tabla filtrada
// - utamed.Usuario.failed_jobs: Tabla filtrada
// - utamed.Usuario.job_batches: Tabla filtrada
// - utamed.Usuario.jobs: Tabla filtrada
// - utamed.Usuario.migrations: Tabla filtrada
// - utamed.Usuario.password_reset_tokens: Tabla filtrada
// - utamed.Usuario.sessions: Tabla filtrada
// - utamed.Usuario.vw_permisos_usuario: Prefijo filtrado
*/
