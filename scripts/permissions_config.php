<?php

// ==================================================================================
// DEFINICIÓN CENTRALIZADA DE PERMISOS
// ==================================================================================
//
// FORMATO ANIDADO:
//   Cada clave de array representa un segmento del recurso.
//   La clave especial '_actions' lista las acciones disponibles en ese nivel.
//   Los segmentos se unen con '/' para formar el slug completo:
//     curso/seccion:ver, usuario/permisos/roles:gestionar
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
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'eliminar'
    ],
  ],

  'departamentos' => [
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'eliminar'
    ],
  ],

  'carreras' => [
    '_actions' => [
      'ver',
      'crear',
      'editar',
      'eliminar'
    ],
  ],

  'planes' => [
    '_actions' => [
      'crear',
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
    '_actions' => [
      'ver',
      'crear',
      'crear_plantilla',
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
      '_actions' => ['ver', 'eliminar'],

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
