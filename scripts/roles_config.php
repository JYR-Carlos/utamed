<?php

/**
 * Definición Centralizada de Roles del Sistema
 *
 * Define roles base con sus permisos asociados usando el Enum Permissions
 * para hinting completo en el IDE.
 *
 * Estructura:
 *   'NombreRol' => [
 *     Permissions::PERMISSION_CASE,
 *     Permissions::ANOTHER_PERMISSION,
 *     // ...
 *   ],
 *
 * Nota: Este archivo retorna un array que es consumido por generate_roles_sql.php.
 * Para agregar/modificar roles: editar este archivo y ejecutar:
 *   php scripts/generate_permissions_sql.php
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
    Permissions::GLOBAL_WILDCARD,
  ],

  // ===========================================================================
  // DOCENTE
  // ===========================================================================
  // Gestiona sus cursos, secciones, unidades, actividades y programas
  // NOTA: No incluye cursos:crear/crear_plantilla porque son _parent_actions
  //       (solo válidos en global o carrera, no en curso específico)
  'Docente' => [
      // Cursos: ver y gestionar propios
    Permissions::CURSOS_VER,
    Permissions::CURSOS_EDITAR,
    Permissions::CURSOS_ELIMINAR,

      // Cursos > Inscripciones
    Permissions::CURSOS_INSCRIPCIONES_VER,
    Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS,
    Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES,

      // Cursos > Secciones
    Permissions::CURSOS_SECCIONES_VER,
    Permissions::CURSOS_SECCIONES_CREAR,
    Permissions::CURSOS_SECCIONES_CREAR_PLANTILLA,
    Permissions::CURSOS_SECCIONES_EDITAR,
    Permissions::CURSOS_SECCIONES_ELIMINAR,

      // Cursos > Unidades
    Permissions::CURSOS_UNIDADES_VER,
    Permissions::CURSOS_UNIDADES_CREAR,
    Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA,
    Permissions::CURSOS_UNIDADES_EDITAR,
    Permissions::CURSOS_UNIDADES_ELIMINAR,

      // Cursos > Actividades
    Permissions::CURSOS_ACTIVIDADES_VER,
    Permissions::CURSOS_ACTIVIDADES_CREAR,
    Permissions::CURSOS_ACTIVIDADES_CREAR_PLANTILLA,
    Permissions::CURSOS_ACTIVIDADES_EDITAR,
    Permissions::CURSOS_ACTIVIDADES_ELIMINAR,
    Permissions::CURSOS_ACTIVIDADES_EVALUAR,
    Permissions::CURSOS_ACTIVIDADES_DAR_FEEDBACK,
    Permissions::CURSOS_ACTIVIDADES_DESCARGAR_ENTREGAS,
    Permissions::CURSOS_ACTIVIDADES_ENVIAR_RECORDATORIOS,
    Permissions::CURSOS_ACTIVIDADES_SUBIR_ENTREGAS,

      // Cursos > Actividades > Grupos
    Permissions::CURSOS_ACTIVIDADES_GRUPOS_VER,
    Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR,
    Permissions::CURSOS_ACTIVIDADES_GRUPOS_EDITAR,
    Permissions::CURSOS_ACTIVIDADES_GRUPOS_ELIMINAR,

      // Cursos > Programas
    Permissions::CURSOS_PROGRAMAS_VER,
    Permissions::CURSOS_PROGRAMAS_AGREGAR,
    Permissions::CURSOS_PROGRAMAS_ELIMINAR,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8,
    Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9,
  ],

  // ===========================================================================
  // AYUDANTE
  // ===========================================================================
  // Asiste al docente en gestión de contenido y evaluación
  'Ayudante' => [
      // Cursos: solo ver
    Permissions::CURSOS_VER,

      // Cursos > Inscripciones: ver y gestionar
    Permissions::CURSOS_INSCRIPCIONES_VER,
    Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS,
    Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES,

      // Cursos > Secciones y Unidades: ver
    Permissions::CURSOS_SECCIONES_VER,
    Permissions::CURSOS_UNIDADES_VER,

      // Cursos > Actividades: ver y evaluar
    Permissions::CURSOS_ACTIVIDADES_VER,
    Permissions::CURSOS_ACTIVIDADES_EVALUAR,
    Permissions::CURSOS_ACTIVIDADES_DAR_FEEDBACK,
    Permissions::CURSOS_ACTIVIDADES_DESCARGAR_ENTREGAS,

      // Cursos > Actividades > Grupos: ver
    Permissions::CURSOS_ACTIVIDADES_GRUPOS_VER,

      // Cursos > Programas: ver
    Permissions::CURSOS_PROGRAMAS_VER,
  ],

  // ===========================================================================
  // JEFE DE CARRERA
  // ===========================================================================
  // Administra carrera y sus planes de estudio
  // NOTA: cursos:crear/crear_plantilla removidos (son _parent_actions, solo para carrera context)
  //       asignaturas:* movido a AsignaturasManager (es global-only)
  'Jefe de Carrera' => [
      // Carreras
    Permissions::CARRERAS_VER,
    Permissions::CARRERAS_EDITAR,

      // Carreras > Planes
    Permissions::CARRERAS_PLANES_VER_VER_DETALLES,
    Permissions::CARRERAS_PLANES_VER_VER_MALLA,
    Permissions::CARRERAS_PLANES_EDITAR,
    Permissions::CARRERAS_PLANES_ASIGNACION_ASIGNATURAS,

      // Cursos: ver y crear (solo en carrera context, parent-only actions)
    Permissions::CURSOS_VER,
    Permissions::CURSOS_CREAR,
    Permissions::CURSOS_CREAR_PLANTILLA,
  ],

  // ===========================================================================
  // DIRECTOR DE DEPARTAMENTO
  // ===========================================================================
  // Supervisa el departamento académico y su estructura
  // NOTA: asignaturas:* movido a AsignaturasManager (global-only)
  //       facultades:ver removido (no es válido en departamento context)
  'Director de Departamento' => [
      // Departamentos
    Permissions::DEPARTAMENTOS_VER,
    Permissions::DEPARTAMENTOS_EDITAR,

      // Carreras: ver y editar
    Permissions::CARRERAS_VER,
    Permissions::CARRERAS_EDITAR,

      // Carreras > Planes
    Permissions::CARRERAS_PLANES_VER_VER_DETALLES,
    Permissions::CARRERAS_PLANES_VER_VER_MALLA,
    Permissions::CARRERAS_PLANES_EDITAR,

      // Cursos: ver
    Permissions::CURSOS_VER,
  ],

  // ===========================================================================
  // SUPERVISOR
  // ===========================================================================
  // Audita y revisa el sistema de permisos y roles
  'Supervisor' => [
      // Usuarios: ver
    Permissions::USUARIOS_VER,

      // Usuarios > Permisos: ver totales asignados
    Permissions::USUARIOS_PERMISOS_VER_PERMISOS_TOTALES_ASIGNADOS,
    Permissions::USUARIOS_PERMISOS_VER_CONTEXTOS_DISPONIBLES,

      // Usuarios > Permisos > Roles: ver
    Permissions::USUARIOS_PERMISOS_ROLES_VER,

      // Usuarios > Permisos > Individuales: ver disponibles y quién tiene
    Permissions::USUARIOS_PERMISOS_INDIVIDUALES_VER_DISPONIBLES,
    Permissions::USUARIOS_PERMISOS_INDIVIDUALES_VER_QUIEN_TIENE,

      // Carreras: ver
    Permissions::CARRERAS_VER,
    Permissions::CARRERAS_PLANES_VER_VER_DETALLES,
    Permissions::CARRERAS_PLANES_VER_VER_MALLA,

      // Cursos: ver
    Permissions::CURSOS_VER,
    Permissions::CURSOS_INSCRIPCIONES_VER,
  ],

  // ===========================================================================
  // ESTUDIANTE
  // ===========================================================================
  // Accede a sus cursos inscritos y materiales de estudio
  'Estudiante' => [
      // Cursos: solo ver
    Permissions::CURSOS_VER,

      // Cursos > Secciones y Unidades: ver
    Permissions::CURSOS_SECCIONES_VER,
    Permissions::CURSOS_UNIDADES_VER,

      // Cursos > Actividades: ver y subir entregas
    Permissions::CURSOS_ACTIVIDADES_VER,
    Permissions::CURSOS_ACTIVIDADES_SUBIR_ENTREGAS,

      // Cursos > Actividades > Grupos: ver
    Permissions::CURSOS_ACTIVIDADES_GRUPOS_VER,

      // Cursos > Programas: ver
    Permissions::CURSOS_PROGRAMAS_VER,
  ],

  // ===========================================================================
  // ASIGNATURAS MANAGER
  // ===========================================================================
  // Gestor central de asignaturas (global-only)
  // Rol especializado para gestionar asignaturas a nivel global.
  // No puede asignarse a contextos específicos (carrera, departamento, etc)
  // porque todos sus permisos (asignaturas:*) son global-only.
  'AsignaturasManager' => [
      // Asignaturas: gestión completa
    Permissions::ASIGNATURAS_VER,
    Permissions::ASIGNATURAS_CREAR,
    Permissions::ASIGNATURAS_EDITAR,
    Permissions::ASIGNATURAS_ELIMINAR,
  ],

];

