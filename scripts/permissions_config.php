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
//   Para permisos que aplican a contextos específicos, se indica el tipo 
//   de contexto en el atributo especial '_valid_context' del recurso raíz.
//   Estos deben corresponder al mapeo generado/configurado en generated_context_hierarchies.php (contextTypeName) 
//   que a su vez, son los tipos definidos en '$ctxTypes' de este archivo. 
//   
//   Esto habilita la asignación de permisos en contextos específicos de ese tipo, 
//   ej: asignar cursos:ver en el contexto de un Curso específico, 
//   lo que otorga el permiso solo para ese curso y no para todos los cursos del sistema. 
//   Ver `PermissionContextConstraints::validContextTypesFor()` para la lógica de 
//   validación de tipos de contexto permitidos por slug de permiso.
//
// FORMATO:
//   'recursoRaiz' => [
//     '_valid_context' => $ctxTypes['carrera'],
//       // Tipo de contexto propio del recurso (contextTypeName en generated_context_hierarchies.php).
//       // Todas las _actions de este grupo (y sub-grupos) se pueden asignar en ese tipo
//       // de contexto, en su jerarquía superior y al contexto GLOBAL. 
//       // Si no se especifica este atributo: solo a GLOBAL.
//
//     '_actions' => ['accion1', 'accion2'],
//       // Acciones que se realizan SOBRE una instancia de este recurso.
//       // Contexto válido: ['GLOBAL', _valid_context (propio o heredado del ancestro)].
//       // Ej: cursos:ver → válido en GLOBAL o en el contexto del Curso específico.
//
//     '_parent_actions' => ['accion1, ...'],
//       // Acciones que se realizan DESDE el contexto padre (ej: crear un recurso
//       // dentro de un contenedor).
//       // Contexto válido: ['GLOBAL', _valid_context del padre inmediato y su jerarquía superior].
//       // Ej: cursos:crear → válido en GLOBAL, o en el contexto de una Carrera, o en cualquier contexto superior.
//
//     (NOTA) Los tipos de contexto válidos padre para cada permiso se derivan 
//            automáticamente de la jerarquía de contextos generada en generated_context_hierarchies.php.
//
//     'subrecurso' => [ '_actions' => [...] ],
//       // Sub-grupos anidados heredan el _valid_context del ancestro más cercano.
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

// Cargar la jerarquía generada para constantes de tipo de contexto.
/** @var array $hierarchies */
$hierarchies = require __DIR__ . '/generated_context_hierarchies.php';

// Derivar mapa tipo_corto => 'schema.tabla' para _valid_context_types.
// Auto-sincronizado con generated_context_hierarchies.php — no hay strings duplicados.

// Tipo global (Sistema) — fuente de verdad para el nombre del tipo raíz absoluto
$globalContextTypeName = 'global';
$contextTypeMappings = [$globalContextTypeName => 'GLOBAL']; // para el generador

// Arreglo para los tipos de contexto
$modelContextType = [];  // para esta configuracion. no especificar _valid_context para "GLOBAL"

// Tipos de contexto directo
foreach ($hierarchies['direct'] as $tableName => $tableContextConfig) {
  // Ejemplo: 'carrera' => 'administrativo.carrera'
  $contextTypeMappings[$tableContextConfig['contextTypeName']] = $tableName;
  // Ejemplo: 'administrativo.carrera' => 'carrera'
  $modelContextType[$tableName] = $tableContextConfig['contextTypeName']; // invertido
}

return [

  // ===========================================================================
  // TIPOS DE CONTEXTO VÁLIDOS
  // ===========================================================================
  // Fuente de verdad para todos los tipos de contexto del sistema.
  // Todos los valores en '_valid_context' y '_valid_parent_context' de este
  // archivo DEBEN estar definidos aquí. El generador lanzará error si no.
  //
  // Formato: 'tipo' => 'tabla_referenciada_en_bd'
  // Auto-derivado de generated_context_hierarchies.php (ver bloque de inicialización arriba).
  '_global_context_type' => $globalContextTypeName,
  '_valid_context_types' => $contextTypeMappings,

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
    '_valid_context' => $modelContextType['administrativo.facultad'],
    '_parent_actions' => [
      'crear',
      'eliminar'
    ],
    '_actions' => [
      'ver',
      'editar',
    ],
  ],

  'departamentos' => [
    '_valid_context' => $modelContextType['administrativo.departamento'],
    '_parent_actions' => [
      'crear',
      'eliminar'
    ],
    '_actions' => [
      'ver',
      'editar',
    ],
  ],

  'carreras' => [
    '_valid_context' => $modelContextType['administrativo.carrera'],
    '_parent_actions' => [
      'crear',
      'eliminar'
    ],
    '_actions' => [
      'ver',
      'editar',
    ],
    'planes' => [
      '_parent_actions' => [
        'crear',
        'eliminar'
      ],
      '_actions' => [
        'editar',
        'asignacion_asignaturas',
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
    '_valid_context' => $modelContextType['curso.curso'],
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
