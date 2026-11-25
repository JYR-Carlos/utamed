<?php

/**
 * Definición Centralizada de Roles del Sistema
 *
 * Define roles base con sus permisos asociados usando tuplas [Permission, PuedeDelegar]
 * para hinting completo en el IDE y control granular de delegabilidad.
 *
 * Estructura:
 *   'NombreRol' => [
 *     'es_administrativo' => boolean,               // true para roles administrativos
 *     'permisos' => [
 *       [Permissions::PERMISSION_CASE, true],      // Permission, puede_delegar boolean
 *       [Permissions::ANOTHER_PERMISSION, false],
 *       // ...
 *     ],
 *   ],
 *
 * Nota: Este archivo retorna un array que es consumido por generate_roles_sql.php.
 * Para agregar/modificar roles: editar este archivo y ejecutar:
 *   php scripts/generate_permissions_sql.php
 *
 * VALIDACIÓN REQUERIDA:
 * - Cada rol DEBE tener 'es_administrativo' (boolean) y 'permisos' (array)
 * - Cada permiso DEBE ser una tupla [Permission enum, boolean]
 * - El boolean define si el permiso puede ser delegado por usuarios con este rol
 * - Ejemplo: SuperAdmin con true (puede delegar permisos), Docente con false (no puede)
 *
 * (El generador de roles se ejecuta automáticamente después del generador de permisos)
 */

// Cargar autoloaders y bootstrap de Laravel para acceso al Enum
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

// Cargar el Enum de permisos para hinting
use App\Support\Permissions;

return [

  // ===========================================================================
  // SUPER ADMINISTRADOR
  // ===========================================================================
  // Acceso total al sistema con wildcard global
  'SuperAdmin' => [
    'es_administrativo' => true,
    'permisos' => [
      [Permissions::GLOBAL_WILDCARD, true],  // SuperAdmin puede delegar el wildcard
    ],
  ],

  // ===========================================================================
  // DOCENTE TITULAR (Gobierno a nivel de Curso)
  // ===========================================================================
  // Unifica el antiguo 'Docente Titular Restringido' con el ownership del curso:
  // cursos, inscripciones, unidades, programas/syllabus, visualización de componentes
  // e inscripciones de componente. La gestión interna de componentes/actividades
  // vive en 'Docente Componente' sobre el id_contexto del componente correspondiente.
  'Docente Titular' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos: ver y gestionar propios
      [Permissions::CURSOS_VER, true],
      [Permissions::CURSOS_EDITAR, true],
      [Permissions::CURSOS_ELIMINAR, false],

      // Cursos > Inscripciones
      [Permissions::CURSOS_INSCRIPCIONES_VER, true],

      // Cursos > Unidades
      [Permissions::CURSOS_UNIDADES_VER, true],
      [Permissions::CURSOS_UNIDADES_CREAR, true],
      [Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA, true],
      [Permissions::CURSOS_UNIDADES_EDITAR, true],
      [Permissions::CURSOS_UNIDADES_ELIMINAR, true],

      // Cursos > Programas
      [Permissions::CURSOS_PROGRAMAS_VER_TODOS, true],
      [Permissions::CURSOS_PROGRAMAS_AGREGAR, true],
      [Permissions::CURSOS_PROGRAMAS_ELIMINAR, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8, true],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9, true],

      // Componentes: visibilidad e inscripciones de sus secciones
      [Permissions::COMPONENTES_VER, true],
      [Permissions::COMPONENTES_INSCRIPCIONES_VER, true],
    ],
  ],

  // ===========================================================================
  // DOCENTE (Gestor / Titular de Componente)
  // ===========================================================================
  // Solo asignable a contexto COMPONENTE.
  // Gestiona integralmente un componente específico: inscripciones, asistencia,
  // actividades, entregas, evaluaciones y grupos, y puede delegarlos a colegiados.
  'Docente Componente' => [
    'es_administrativo' => false,
    'permisos' => [
      // Componente
      [Permissions::COMPONENTES_VER, true],
      [Permissions::COMPONENTES_EDITAR, true],

      // Componente > Inscripciones
      [Permissions::COMPONENTES_INSCRIPCIONES_VER, true],

      // Componente > Asistencia
      [Permissions::COMPONENTES_ASISTENCIA_VER, true],
      [Permissions::COMPONENTES_ASISTENCIA_REGISTRAR, true],
      [Permissions::COMPONENTES_ASISTENCIA_EDITAR, true],
      [Permissions::COMPONENTES_ASISTENCIA_ELIMINAR, false],

      // Actividades: gestión completa en el componente
      [Permissions::ACTIVIDADES_VER, true],
      [Permissions::ACTIVIDADES_CREAR, true],
      [Permissions::ACTIVIDADES_CREAR_PLANTILLA, true],
      [Permissions::ACTIVIDADES_EDITAR, true],
      [Permissions::ACTIVIDADES_ELIMINAR, true],
      [Permissions::ACTIVIDADES_EVALUAR, true],
      [Permissions::ACTIVIDADES_DAR_FEEDBACK, true],
      [Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS, true],
      [Permissions::ACTIVIDADES_ENVIAR_RECORDATORIOS, true],
      [Permissions::ACTIVIDADES_SUBIR_ENTREGAS, true],

      // Actividades > Grupos
      [Permissions::ACTIVIDADES_GRUPOS_VER, true],
      [Permissions::ACTIVIDADES_GRUPOS_CREAR, true],
      [Permissions::ACTIVIDADES_GRUPOS_EDITAR, true],
      [Permissions::ACTIVIDADES_GRUPOS_ELIMINAR, true],
    ],
  ],
  // ===========================================================================
  // DOCENTE COMPONENTE COLEGIADO
  // ===========================================================================
  // Piso mínimo sin capacidades operativas por defecto: solo visualización base
  // del curso/componente. Sus permisos internos deben ser delegados explícitamente
  // por el titular de su componente (o por el DT si posee capacidades delegables).
  'Docente Componente Colegiado' => [
    'es_administrativo' => false,
    'permisos' => [
      [Permissions::CURSOS_VER, false],
      [Permissions::COMPONENTES_VER, false],
    ],
  ],

  // ==========================================================================
  // DOCENTE VISUALIZADOR (curso específico)
  // ==========================================================================
  // Solo asignable a contexto CURSO, no a COMPONENTE
  // Permisos de solo visualización a nivel de curso, unidades y programas
  'Docente Visualizador' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos: solo ver (no editar ni eliminar)
      [Permissions::CURSOS_VER, false],
      // Cursos > Inscripciones: solo ver
      [Permissions::CURSOS_INSCRIPCIONES_VER, false],
      // Cursos > Unidades: solo ver (no crear/editar/eliminar)
      [Permissions::CURSOS_UNIDADES_VER, false],
      // Cursos > Programas: solo ver (no agregar/eliminar/modificar)
      [Permissions::CURSOS_PROGRAMAS_VER_ULTIMO, false],
    ],
  ],

  // ===========================================================================
  // AYUDANTE
  // ===========================================================================
  // Asiste al docente en gestión de contenido y evaluación
  'Ayudante' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos > Componentes: solo ver (no editar ni eliminar)
      [Permissions::COMPONENTES_VER, false],
      
      // Componentes > Inscripciones: ver a nivel de componente
      [Permissions::COMPONENTES_INSCRIPCIONES_VER, false],

      // Actividades: ver, evaluar, dar feedback y descargar entregas (no crear/editar/eliminar)
      [Permissions::ACTIVIDADES_VER, false],
      [Permissions::ACTIVIDADES_EVALUAR, false],
      [Permissions::ACTIVIDADES_DAR_FEEDBACK, false],
      [Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS, false],

      // Actividades > Grupos: ver
      [Permissions::ACTIVIDADES_GRUPOS_VER, false],
    ],
  ],

  'Ayudante Visualizar Curso' => [
    'es_administrativo' => true,
    'permisos' => [
      // Cursos: solo ver (no editar ni eliminar)
      [Permissions::CURSOS_VER, false],

      // Cursos > Unidades: solo ver (no crear/editar/eliminar)
      [Permissions::CURSOS_UNIDADES_VER, false],

      // Cursos > Programas: solo ver (no agregar/eliminar/modificar)
      [Permissions::CURSOS_PROGRAMAS_VER_ULTIMO, false],
    ],
  ],

  // ===========================================================================
  // JEFE DE CARRERA
  // ===========================================================================
  // Administra carrera y sus planes de estudio
  // NOTA: cursos:crear/crear_plantilla removidos (son _parent_actions, solo para carrera context)
  //       asignaturas:* movido a AsignaturasManager (es global-only)
  'Jefe de Carrera' => [
    'es_administrativo' => false,
    'permisos' => [
      // Carreras
      [Permissions::CARRERAS_VER, false],

      // Carreras > Planes: ver, editar y ver detalles/malla (no crear/eliminar planes)
      [Permissions::CARRERAS_PLANES_VER_ALL, false],

      // Cursos: ver (todos los cursos de la carrera)
      [Permissions::CURSOS_VER, false],
    ],
  ],

  // ===========================================================================
  // DIRECTOR DE DEPARTAMENTO
  // ===========================================================================
  // Supervisa el departamento académico y su estructura
  // NOTA: asignaturas:* movido a AsignaturasManager (global-only)
  //       facultades:ver removido (no es válido en departamento context)
  'Director de Departamento' => [
    'es_administrativo' => false,
    'permisos' => [
      // Departamentos
      [Permissions::DEPARTAMENTOS_VER, false],
      [Permissions::DEPARTAMENTOS_EDITAR, false],

      // Carreras: ver y editar
      [Permissions::CARRERAS_VER, false],
      [Permissions::CARRERAS_EDITAR, false],

      // Carreras > Planes
      [Permissions::CARRERAS_PLANES_VER_VER_DETALLES, false],
      [Permissions::CARRERAS_PLANES_VER_VER_MALLA, false],
      [Permissions::CARRERAS_PLANES_EDITAR, false],

      // Cursos: ver
      [Permissions::CURSOS_VER, false],
    ],
  ],

  // ===========================================================================
  // SUPERVISOR
  // ===========================================================================
  // Supervisa cursos
  'Supervisor' => [
    'es_administrativo' => false,
    'permisos' => [
      // TODO: definir
    ],
  ],

  // ===========================================================================
  // ESTUDIANTE
  // ===========================================================================
  // Accede a sus cursos inscritos y materiales de estudio
  'Estudiante' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos: solo ver
      [Permissions::CURSOS_VER, false],

      // Cursos > Componentes y Unidades: ver
      [Permissions::COMPONENTES_VER, false],
      [Permissions::CURSOS_UNIDADES_VER, false],

      // Cursos > Programas: ver
      [Permissions::CURSOS_PROGRAMAS_VER_ULTIMO, false],
    ],
  ],

  'Estudiante Participa en Actividad' => [
    'es_administrativo' => true,
    'permisos' => [
      // Asignable solo en actividad específica donde el estudiante participa

      // Actividades: ver y subir entregas
      [Permissions::ACTIVIDADES_VER, false],
      [Permissions::ACTIVIDADES_SUBIR_ENTREGAS, false],
      [Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS, false],

      // Actividades > Grupos: ver
      [Permissions::ACTIVIDADES_GRUPOS_VER, false],
      // TODO: ver si faltan permisos de grupo
    ],
  ],

  // ===========================================================================
  // ASIGNATURAS MANAGER
  // ===========================================================================
  // Gestor central de asignaturas (global-only)
  // Rol especializado para gestionar asignaturas a nivel global.
  // No puede asignarse a contextos específicos (carrera, departamento, etc)
  // porque todos sus permisos (asignaturas:*) son global-only.
  'AsignaturasManagerDelegable' => [
    'es_administrativo' => false,
    'permisos' => [
      // Asignaturas: gestión completa
      [Permissions::ASIGNATURAS_VER, true],
      [Permissions::ASIGNATURAS_CREAR, true],
      [Permissions::ASIGNATURAS_EDITAR, true],
      [Permissions::ASIGNATURAS_ELIMINAR, true],
    ],
  ],

  'AsignaturasManager' => [
    'es_administrativo' => false,
    'permisos' => [
      // Asignaturas: gestión completa pero no delegable
      [Permissions::ASIGNATURAS_VER, false],
      [Permissions::ASIGNATURAS_CREAR, false],
      [Permissions::ASIGNATURAS_EDITAR, false],
      [Permissions::ASIGNATURAS_ELIMINAR, false],
    ],
  ],
];
