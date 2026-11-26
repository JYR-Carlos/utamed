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
  // DOCENTE TITULAR (Completo)
  // ===========================================================================
  // Gestiona sus cursos, secciones, unidades, actividades y programas
  // Máximos permisos para docente titular
  // NOTA: No incluye cursos:crear/crear_plantilla porque son _parent_actions
  //       (solo válidos en global o carrera, no en curso específico)
  'Docente Titular' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos: ver y gestionar propios
      [Permissions::CURSOS_VER, false],
      [Permissions::CURSOS_EDITAR, false],
      [Permissions::CURSOS_ELIMINAR, false],

      // Cursos > Inscripciones
      [Permissions::CURSOS_INSCRIPCIONES_VER, false],

      // Cursos > Unidades
      [Permissions::CURSOS_UNIDADES_VER, false],
      [Permissions::CURSOS_UNIDADES_CREAR, false],
      [Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA, false],
      [Permissions::CURSOS_UNIDADES_EDITAR, false],
      [Permissions::CURSOS_UNIDADES_ELIMINAR, false],

      // Cursos > Programas
      [Permissions::CURSOS_PROGRAMAS_VER_TODOS, false],
      [Permissions::CURSOS_PROGRAMAS_AGREGAR, false],
      [Permissions::CURSOS_PROGRAMAS_ELIMINAR, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9, false],

       // Componentes
      [Permissions::COMPONENTES_VER, false],
      [Permissions::COMPONENTES_EDITAR, false],

      // Actividades
      [Permissions::ACTIVIDADES_VER, false],
      [Permissions::ACTIVIDADES_CREAR, false],
      [Permissions::ACTIVIDADES_CREAR_PLANTILLA, false],
      [Permissions::ACTIVIDADES_EDITAR, false],
      [Permissions::ACTIVIDADES_ELIMINAR, false],
      [Permissions::ACTIVIDADES_EVALUAR, false],
      [Permissions::ACTIVIDADES_DAR_FEEDBACK, false],
      [Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS, false],
      [Permissions::ACTIVIDADES_ENVIAR_RECORDATORIOS, false],
      [Permissions::ACTIVIDADES_SUBIR_ENTREGAS, false],

      // Actividades > Grupos
      [Permissions::ACTIVIDADES_GRUPOS_VER, false],
      [Permissions::ACTIVIDADES_GRUPOS_CREAR, false],
      [Permissions::ACTIVIDADES_GRUPOS_EDITAR, false],
      [Permissions::ACTIVIDADES_GRUPOS_ELIMINAR, false],
    ],
  ],
  // ===========================================================================
  // DOCENTE TITULAR (Restringido)
  // ===========================================================================
  // Gestiona sus cursos pero sin permiso sobre componentes, actividades ni programas
  // Para docentes que solo negocia contenido a nivel general del curso
  // Este rol se aplica cuando hay más de un docente en el mismo curso, para evitar conflictos de edición en componentes y actividades.
  'Docente Titular Restringido' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos: ver y gestionar propios
      [Permissions::CURSOS_VER, false],

      // Cursos > Inscripciones
      [Permissions::CURSOS_INSCRIPCIONES_VER, false],

      // Cursos > Unidades
      [Permissions::CURSOS_UNIDADES_VER, false],
      [Permissions::CURSOS_UNIDADES_CREAR, false],
      [Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA, false],
      [Permissions::CURSOS_UNIDADES_EDITAR, false],
      [Permissions::CURSOS_UNIDADES_ELIMINAR, false],

      // Cursos > Programas
      [Permissions::CURSOS_PROGRAMAS_VER_TODOS, false],
      [Permissions::CURSOS_PROGRAMAS_AGREGAR, false],
      [Permissions::CURSOS_PROGRAMAS_ELIMINAR, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8, false],
      [Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9, false],
    ],
  ],

  // ===========================================================================
  // DOCENTE (Gestor de Componente) - Titular
  // ===========================================================================
  // Solo asignable a contexto COMPONENTE, no a CURSO
  // Gestiona un componente específico: actividades, entregas y evaluaciones
  // NO tiene permisos a nivel de curso (no puede editar/eliminar curso)
  // NO tiene permisos de programas
  'Docente Componente' => [
    'es_administrativo' => false,
    'permisos' => [
      // Cursos: solo ver (no editar ni eliminar)
      [Permissions::CURSOS_VER, false],

      // Componente > Inscripciones: ver a nivel de componente
      [Permissions::COMPONENTES_INSCRIPCIONES_VER, false],

      // Componente
      [Permissions::COMPONENTES_VER, false],
      [Permissions::COMPONENTES_EDITAR, false],

      // Actividades: gestión completa a nivel de componente
      [Permissions::ACTIVIDADES_VER, false],
      [Permissions::ACTIVIDADES_CREAR, false],
      [Permissions::ACTIVIDADES_CREAR_PLANTILLA, false],
      [Permissions::ACTIVIDADES_EDITAR, false],
      [Permissions::ACTIVIDADES_ELIMINAR, false],
      [Permissions::ACTIVIDADES_EVALUAR, false],
      [Permissions::ACTIVIDADES_DAR_FEEDBACK, false],
      [Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS, false],
      [Permissions::ACTIVIDADES_ENVIAR_RECORDATORIOS, false],
      [Permissions::ACTIVIDADES_SUBIR_ENTREGAS, false],

      // Actividades > Grupos: gestión a nivel de componente
      [Permissions::ACTIVIDADES_GRUPOS_VER, false],
      [Permissions::ACTIVIDADES_GRUPOS_CREAR, false],
      [Permissions::ACTIVIDADES_GRUPOS_EDITAR, false],
      [Permissions::ACTIVIDADES_GRUPOS_ELIMINAR, false],
    ],
  ],
  // ===========================================================================
  // DOCENTE COMPONENTE COLEGIADO ()
  // ===========================================================================
  // Posee permisos bajo su propio componente, lo que gestiona:
  // - Actividades: ver
  // - Grupos de actividades: ver
  // No tiene permisos a nivel de curso ni programas, los permisos son dado por el titular (docente componente)
  'Docente Componente Colegiado' => [
    'es_administrativo' => false,
    'permisos' => [
      // Componente > Inscripciones: ver a nivel de componente
      [Permissions::COMPONENTES_INSCRIPCIONES_VER, false],
      // Componente
      [Permissions::COMPONENTES_VER, false],
      // Actividades: solo ver a nivel de componente
      [Permissions::ACTIVIDADES_VER, false],
      // Actividades > Grupos: solo ver a nivel de componente
      [Permissions::ACTIVIDADES_GRUPOS_VER, false],
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
