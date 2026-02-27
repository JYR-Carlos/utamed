<?php

// ==================================================================================
// DEFINICIÓN CENTRALIZADA DE PERMISOS
// ==================================================================================
//
// ESTRUCTURA ANIDADA DE PERMISOS:
//   Cada clave de array representa un segmento del recurso.
//   La clave especial '_actions' lista las acciones disponibles en ese nivel.
//   Los segmentos se unen con '/' para formar el slug completo:
//     curso/seccion:ver, usuario/permisos/roles:gestionar
//
// CONTEXTOS VÁLIDOS:
//   Por defecto, cada permiso es GLOBAL (sin contexto específico).
//   Para permisos que aplican a contextos específicos (ej: facultades, cursos; 
//   definidos de antemano en la tabla "tipo_contextos"), se indica el tipo 
//   de contexto en el atributo especial '_valid_contexts' del recurso raíz. 
//   
//   Esto habilita la asignación de permisos en contextos específicos de ese tipo, 
//   ej: asignar cursos:ver en el contexto de un Curso específico, 
//   lo que otorga el permiso solo para ese curso y no para todos los cursos del sistema. 
//   Ver `PermissionContextConstraints::validContextTypesFor()` para la lógica de 
//   validación de tipos de contexto permitidos por slug de permiso.
//
// FORMATO:
//   'recursoRaiz' => [
//     '_valid_contexts' => 'tipo_contexto',
//       // Tipo de contexto propio del recurso (nombre de tabla en generated_context_hierarchies.php).
//       // Todas las _actions de este grupo (y sub-grupos) se pueden asignar en ese tipo
//       // de contexto o en GLOBAL. Sin este atributo: solo GLOBAL.
//
//     '_valid_parent_context' => 'tipo_contexto_padre',
//       // Tipo de contexto del contenedor padre del recurso.
//       // Requerido cuando '_parent_actions' está definido en este recurso o sub-recursos.
//       // Ej: 'cursos' pertenecen a 'carrera' → _valid_parent_context = 'carrera'.
//
//     '_actions' => ['accion1', 'accion2'],
//       // Acciones que se realizan SOBRE una instancia de este recurso.
//       // Contexto válido: ['GLOBAL', _valid_contexts (propio o heredado del ancestro)].
//       // Ej: cursos:ver → válido en GLOBAL o en el contexto del Curso específico.
//
//     '_parent_actions' => ['accionX'],
//       // Acciones que se realizan DESDE el contexto padre (ej: crear un recurso
//       // dentro de un contenedor). Requiere '_valid_parent_context' definido.
//       // Contexto válido: ['GLOBAL', _valid_parent_context].
//       // Ej: cursos:crear → válido en GLOBAL o en el contexto de una Carrera.
//
//     'subrecurso' => [ '_actions' => [...] ],
//       // Sub-grupos anidados heredan el _valid_contexts del ancestro más cercano.
//   ],
//
// Para agregar permisos: editar este archivo y correr:
//   php scripts/generate_models.php
//
// El generador produce app/Support/Permissions.php con constantes PHP.
// Las constantes se nombran: strtoupper(recurso + '_' + accion) con '/' → '_'
//   facultad:ver                → Permissions::FACULTAD_VER
//   curso/seccion:crear         → Permissions::CURSO_SECCION_CREAR
//   usuario/permisos/roles:ver  → Permissions::USUARIO_PERMISOS_ROLES_VER
// ==================================================================================

return [

  // ===========================================================================
  // USUARIOS
  // ===========================================================================
  'usuarios' => [
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'deshabilitar',
      'restablecer_contrasena'
    ],

    'permisos' => [
      '_actions' => [
        'ver_contextos_disponibles',
        'ver_permisos_totales_asignados'
      ],

      'roles' => [
        '_actions' => [
          'gestionar',
          'ver',
          'crear',
          'editar',
          'eliminar'
        ],
      ],

      'individuales' => [
        '_actions' => [
          'gestionar',
          'ver_disponibles',
          'ver_quien_tiene'
        ],
      ],
    ],
  ],

  // ===========================================================================
  // ESTRUCTURA ACADÉMICA
  // ===========================================================================
  'facultades' => [
    '_valid_contexts' => 'facultad',
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'eliminar'
    ],
  ],

  'departamentos' => [
    '_valid_contexts' => 'departamento',
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'eliminar'
    ],
  ],

  'carreras' => [
    '_valid_contexts' => 'carrera',
    '_valid_parent_context' => 'facultad',
    '_parent_actions' => [
      'crear',
    ],
    '_actions' => [
      'ver',
      'editar',
      'eliminar'
    ],
    'planes' => [
      '_valid_parent_context' => 'carrera',
      '_parent_actions' => [
        'crear',
      ],
      '_actions' => [
        'editar',
        'eliminar',
        'asignacion_asignaturas'
      ],

      'ver' => [
        '_actions' => [
          'ver_detalles',
          'ver_malla'
        ],
      ],
    ],
  ],

  'asignaturas' => [
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'eliminar'
    ],
  ],

  // ===========================================================================
  // CURSOS
  // ===========================================================================
  'cursos' => [
    '_valid_contexts' => 'curso',
    '_valid_parent_context' => 'carrera',
    '_parent_actions' => [
      'crear',
      'crear_plantilla',
    ],
    '_actions' => [
      'ver',
      'editar',
      'eliminar'
    ],

    'inscripciones' => [
      '_actions' => [
        'ver',
        'inscribir_alumnos',
        'eliminar_inscripciones'
      ],
    ],

    'secciones' => [
      '_actions' => [
        'ver',
        'crear',
        'crear_plantilla',
        'editar',
        'eliminar'
      ],
    ],

    'unidades' => [
      '_actions' => [
        'ver',
        'crear',
        'crear_plantilla',
        'editar',
        'eliminar'
      ],
    ],

    'actividades' => [
      '_actions' => [
        'ver',
        'crear',
        'crear_plantilla',
        'editar',
        'eliminar',
        'subir_entregas',
        'evaluar',
        'dar_feedback',
        'enviar_recordatorios',
        'descargar_entregas',
      ],

      'grupos' => [
        '_actions' => ['ver', 'crear', 'editar', 'eliminar'],
      ],
    ],

    'programas' => [
      '_actions' => ['ver', 'agregar', 'eliminar'],

      'modificar' => [
        '_actions' => [
          'modulo_1',
          'modulo_2',
          'modulo_3',
          'modulo_4',
          'modulo_5',
          'modulo_6',
          'modulo_7',
          'modulo_8',
          'modulo_9',
        ],
      ]
    ],
  ],

];
