<?php

/**
 * Definición Centralizada de Roles del Sistema
 *
 * Define roles base con sus permisos asociados usando tuplas [Permission, PuedeDelegar]
 * para hinting completo en el IDE y control granular de delegabilidad.
 *
 * Estructura:
 *   'NombreRol' => [
 *     [Permissions::PERMISSION_CASE, true],        // Permission, puede_delegar boolean
 *     [Permissions::ANOTHER_PERMISSION, false],
 *     // ...
 *   ],
 *
 * Nota: Este archivo retorna un array que es consumido por generate_roles_sql.php.
 * Para agregar/modificar roles: editar este archivo y ejecutar:
 *   php scripts/generate_permissions_sql.php
 *
 * VALIDACIÓN REQUERIDA:
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
    [Permissions::GLOBAL_WILDCARD, true],  // SuperAdmin puede delegar el wildcard
  ],

  // ===========================================================================
  // DOCENTE
  // ===========================================================================
  // Gestiona sus cursos, secciones, unidades, actividades y programas
  // NOTA: No incluye cursos:crear/crear_plantilla porque son _parent_actions
  //       (solo válidos en global o carrera, no en curso específico)
  'Docente' => [
    // Cursos: ver y gestionar propios
    [Permissions::CURSOS_VER, false],
    [Permissions::CURSOS_EDITAR, false],
    [Permissions::CURSOS_ELIMINAR, false],

    // Cursos > Inscripciones
    [Permissions::CURSOS_INSCRIPCIONES_VER, false],

    // Cursos > Componentes
    [Permissions::CURSOS_COMPONENTES_VER, false],
    [Permissions::CURSOS_COMPONENTES_CREAR, false],
    [Permissions::CURSOS_COMPONENTES_CREAR_PLANTILLA, false],
    [Permissions::CURSOS_COMPONENTES_EDITAR, false],
    [Permissions::CURSOS_COMPONENTES_ELIMINAR, false],

    // Cursos > Unidades
    [Permissions::CURSOS_UNIDADES_VER, false],
    [Permissions::CURSOS_UNIDADES_CREAR, false],
    [Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA, false],
    [Permissions::CURSOS_UNIDADES_EDITAR, false],
    [Permissions::CURSOS_UNIDADES_ELIMINAR, false],

    // Cursos > Actividades
    [Permissions::CURSOS_ACTIVIDADES_VER, false],
    [Permissions::CURSOS_ACTIVIDADES_CREAR, false],
    [Permissions::CURSOS_ACTIVIDADES_CREAR_PLANTILLA, false],
    [Permissions::CURSOS_ACTIVIDADES_EDITAR, false],
    [Permissions::CURSOS_ACTIVIDADES_ELIMINAR, false],
    [Permissions::CURSOS_ACTIVIDADES_EVALUAR, false],
    [Permissions::CURSOS_ACTIVIDADES_DAR_FEEDBACK, false],
    [Permissions::CURSOS_ACTIVIDADES_DESCARGAR_ENTREGAS, false],
    [Permissions::CURSOS_ACTIVIDADES_ENVIAR_RECORDATORIOS, false],
    [Permissions::CURSOS_ACTIVIDADES_SUBIR_ENTREGAS, false],

    // Cursos > Actividades > Grupos
    [Permissions::CURSOS_ACTIVIDADES_GRUPOS_VER, false],
    [Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR, false],
    [Permissions::CURSOS_ACTIVIDADES_GRUPOS_EDITAR, false],
    [Permissions::CURSOS_ACTIVIDADES_GRUPOS_ELIMINAR, false],

    // Cursos > Programas
    [Permissions::CURSOS_PROGRAMAS_VER, false],
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

  // ===========================================================================
  // AYUDANTE
  // ===========================================================================
  // Asiste al docente en gestión de contenido y evaluación
  'Ayudante' => [
    // Cursos: solo ver
    [Permissions::CURSOS_VER, false],

    // Cursos > Inscripciones: ver y gestionar
    [Permissions::CURSOS_INSCRIPCIONES_VER, false],
    [Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS, false],
    [Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES, false],

    // Cursos > Componentes y Unidades: ver
    [Permissions::CURSOS_COMPONENTES_VER, false],
    [Permissions::CURSOS_UNIDADES_VER, false],

    // Cursos > Actividades: ver y evaluar
    [Permissions::CURSOS_ACTIVIDADES_VER, false],
    [Permissions::CURSOS_ACTIVIDADES_EVALUAR, false],
    [Permissions::CURSOS_ACTIVIDADES_DAR_FEEDBACK, false],
    [Permissions::CURSOS_ACTIVIDADES_DESCARGAR_ENTREGAS, false],

    // Cursos > Actividades > Grupos: ver
    [Permissions::CURSOS_ACTIVIDADES_GRUPOS_VER, false],

    // Cursos > Programas: ver
    [Permissions::CURSOS_PROGRAMAS_VER, false],
  ],

  // ===========================================================================
  // JEFE DE CARRERA
  // ===========================================================================
  // Administra carrera y sus planes de estudio
  // NOTA: cursos:crear/crear_plantilla removidos (son _parent_actions, solo para carrera context)
  //       asignaturas:* movido a AsignaturasManager (es global-only)
  'Jefe de Carrera' => [
    // Carreras
    [Permissions::CARRERAS_VER, false],
    [Permissions::CARRERAS_EDITAR, false],

    // Carreras > Planes
    [Permissions::CARRERAS_PLANES_VER_VER_DETALLES, false],
    [Permissions::CARRERAS_PLANES_VER_VER_MALLA, false],
    [Permissions::CARRERAS_PLANES_EDITAR, false],
    [Permissions::CARRERAS_PLANES_ASIGNACION_ASIGNATURAS, false],

    // Cursos: ver y crear (solo en carrera context, parent-only actions)
    [Permissions::CURSOS_VER, false],
    [Permissions::CURSOS_CREAR, false],
    [Permissions::CURSOS_CREAR_PLANTILLA, false],
  ],

  // ===========================================================================
  // DIRECTOR DE DEPARTAMENTO
  // ===========================================================================
  // Supervisa el departamento académico y su estructura
  // NOTA: asignaturas:* movido a AsignaturasManager (global-only)
  //       facultades:ver removido (no es válido en departamento context)
  'Director de Departamento' => [
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

  // ===========================================================================
  // SUPERVISOR
  // ===========================================================================
  // Audita y revisa el sistema de permisos y roles
  'Supervisor' => [
    // Usuarios: ver
    [Permissions::USUARIOS_VER, false],

    // Usuarios > Permisos: ver totales asignados
    [Permissions::USUARIOS_PERMISOS_VER_PERMISOS_TOTALES_ASIGNADOS, false],
    [Permissions::USUARIOS_PERMISOS_VER_CONTEXTOS_DISPONIBLES, false],

    // Usuarios > Permisos > Roles: ver
    [Permissions::USUARIOS_PERMISOS_ROLES_VER, false],

    // Usuarios > Permisos > Individuales: ver disponibles y quién tiene
    [Permissions::USUARIOS_PERMISOS_INDIVIDUALES_VER_DISPONIBLES, false],
    [Permissions::USUARIOS_PERMISOS_INDIVIDUALES_VER_QUIEN_TIENE, false],

    // Carreras: ver
    [Permissions::CARRERAS_VER, false],
    [Permissions::CARRERAS_PLANES_VER_VER_DETALLES, false],
    [Permissions::CARRERAS_PLANES_VER_VER_MALLA, false],

    // Cursos: ver
    [Permissions::CURSOS_VER, false],
    [Permissions::CURSOS_INSCRIPCIONES_VER, false],
  ],

  // ===========================================================================
  // ESTUDIANTE
  // ===========================================================================
  // Accede a sus cursos inscritos y materiales de estudio
  'Estudiante' => [
    // Cursos: solo ver
    [Permissions::CURSOS_VER, false],

    // Cursos > Componentes y Unidades: ver
    [Permissions::CURSOS_COMPONENTES_VER, false],
    [Permissions::CURSOS_UNIDADES_VER, false],

    // Cursos > Actividades: ver y subir entregas
    [Permissions::CURSOS_ACTIVIDADES_VER, false],
    [Permissions::CURSOS_ACTIVIDADES_SUBIR_ENTREGAS, false],

    // Cursos > Actividades > Grupos: ver
    [Permissions::CURSOS_ACTIVIDADES_GRUPOS_VER, false],

    // Cursos > Programas: ver
    [Permissions::CURSOS_PROGRAMAS_VER, false],
  ],

  // ===========================================================================
  // ASIGNATURAS MANAGER
  // ===========================================================================
  // Gestor central de asignaturas (global-only)
  // Rol especializado para gestionar asignaturas a nivel global.
  // No puede asignarse a contextos específicos (carrera, departamento, etc)
  // porque todos sus permisos (asignaturas:*) son global-only.
  'AsignaturasManagerDelegable' => [
    // Asignaturas: gestión completa
    [Permissions::ASIGNATURAS_VER, true],
    [Permissions::ASIGNATURAS_CREAR, true],
    [Permissions::ASIGNATURAS_EDITAR, true],
    [Permissions::ASIGNATURAS_ELIMINAR, true],
  ],

  'AsignaturasManager' => [
    // Asignaturas: gestión completa pero no delegable
    [Permissions::ASIGNATURAS_VER, false],
    [Permissions::ASIGNATURAS_CREAR, false],
    [Permissions::ASIGNATURAS_EDITAR, false],
    [Permissions::ASIGNATURAS_ELIMINAR, false],
  ],

];
