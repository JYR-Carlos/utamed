<?php

namespace App\Support;

use App\Services\Authorization\WildcardMatcher;

/**
 * Slugs de permisos centralizados, estandarizados y a prueba de errores.
 * AUTOGENERADO desde scripts/permissions_config.php — NO EDITAR.
 *
 * Uso: Permissions::FACULTAD_VER->value (ej: 'facultad:ver')
 * Para agregar permisos, editar scripts/permissions_config.php y regenerar.
 * 
 * WILDCARD ESPECIAL: Permissions::GLOBAL_WILDCARD = '*' (acceso a todo)
 */
enum Permissions: string
{
    // Wildcard global — acceso a cualquier permiso en cualquier recurso
    case GLOBAL_WILDCARD = WildcardMatcher::GLOBAL_WILDCARD;


    // ACTIVIDADES
    case ACTIVIDADES_ALL = 'actividades:*';
    case ACTIVIDADES_CREAR = 'actividades:crear';
    case ACTIVIDADES_CREAR_PLANTILLA = 'actividades:crear_plantilla';
    case ACTIVIDADES_DAR_FEEDBACK = 'actividades:dar_feedback';
    case ACTIVIDADES_DESCARGAR_ENTREGAS = 'actividades:descargar_entregas';
    case ACTIVIDADES_EDITAR = 'actividades:editar';
    case ACTIVIDADES_ELIMINAR = 'actividades:eliminar';
    case ACTIVIDADES_ENVIAR_RECORDATORIOS = 'actividades:enviar_recordatorios';
    case ACTIVIDADES_EVALUAR = 'actividades:evaluar';
    case ACTIVIDADES_SUBIR_ENTREGAS = 'actividades:subir_entregas';
    case ACTIVIDADES_VER = 'actividades:ver';

    // ACTIVIDADES > GRUPOS
    case ACTIVIDADES_GRUPOS_ALL = 'actividades/grupos:*';
    case ACTIVIDADES_GRUPOS_CREAR = 'actividades/grupos:crear';
    case ACTIVIDADES_GRUPOS_EDITAR = 'actividades/grupos:editar';
    case ACTIVIDADES_GRUPOS_ELIMINAR = 'actividades/grupos:eliminar';
    case ACTIVIDADES_GRUPOS_VER = 'actividades/grupos:ver';

    // ASIGNATURAS
    case ASIGNATURAS_ALL = 'asignaturas:*';
    case ASIGNATURAS_CREAR = 'asignaturas:crear';
    case ASIGNATURAS_EDITAR = 'asignaturas:editar';
    case ASIGNATURAS_ELIMINAR = 'asignaturas:eliminar';
    case ASIGNATURAS_VER = 'asignaturas:ver';

    // CARRERAS
    case CARRERAS_ALL = 'carreras:*';
    case CARRERAS_CREAR = 'carreras:crear';
    case CARRERAS_EDITAR = 'carreras:editar';
    case CARRERAS_ELIMINAR = 'carreras:eliminar';
    case CARRERAS_VER = 'carreras:ver';

    // CARRERAS > PLANES
    case CARRERAS_PLANES_ALL = 'carreras/planes:*';
    case CARRERAS_PLANES_ASIGNACION_ASIGNATURAS = 'carreras/planes:asignacion_asignaturas';
    case CARRERAS_PLANES_CREAR = 'carreras/planes:crear';
    case CARRERAS_PLANES_EDITAR = 'carreras/planes:editar';
    case CARRERAS_PLANES_ELIMINAR = 'carreras/planes:eliminar';

    // CARRERAS > PLANES > VER
    case CARRERAS_PLANES_VER_ALL = 'carreras/planes/ver:*';
    case CARRERAS_PLANES_VER_VER_DETALLES = 'carreras/planes/ver:ver_detalles';
    case CARRERAS_PLANES_VER_VER_MALLA = 'carreras/planes/ver:ver_malla';

    // COMPONENTES
    case COMPONENTES_ALL = 'componentes:*';
    case COMPONENTES_CREAR = 'componentes:crear';
    case COMPONENTES_CREAR_PLANTILLA = 'componentes:crear_plantilla';
    case COMPONENTES_EDITAR = 'componentes:editar';
    case COMPONENTES_ELIMINAR = 'componentes:eliminar';
    case COMPONENTES_VER = 'componentes:ver';

    // COMPONENTES > ASISTENCIA
    case COMPONENTES_ASISTENCIA_ALL = 'componentes/asistencia:*';
    case COMPONENTES_ASISTENCIA_EDITAR = 'componentes/asistencia:editar';
    case COMPONENTES_ASISTENCIA_ELIMINAR = 'componentes/asistencia:eliminar';
    case COMPONENTES_ASISTENCIA_REGISTRAR = 'componentes/asistencia:registrar';
    case COMPONENTES_ASISTENCIA_VER = 'componentes/asistencia:ver';

    // COMPONENTES > DOCENTESCOLEGIADOS
    case COMPONENTES_DOCENTESCOLEGIADOS_ALL = 'componentes/docentesColegiados:*';
    case COMPONENTES_DOCENTESCOLEGIADOS_AGREGAR = 'componentes/docentesColegiados:agregar';
    case COMPONENTES_DOCENTESCOLEGIADOS_ELIMINAR = 'componentes/docentesColegiados:eliminar';
    case COMPONENTES_DOCENTESCOLEGIADOS_VER = 'componentes/docentesColegiados:ver';

    // COMPONENTES > INSCRIPCIONES
    case COMPONENTES_INSCRIPCIONES_ALL = 'componentes/inscripciones:*';
    case COMPONENTES_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES = 'componentes/inscripciones:eliminar_inscripciones';
    case COMPONENTES_INSCRIPCIONES_INSCRIBIR_ALUMNOS = 'componentes/inscripciones:inscribir_alumnos';
    case COMPONENTES_INSCRIPCIONES_VER = 'componentes/inscripciones:ver';

    // CURSOS
    case CURSOS_ALL = 'cursos:*';
    case CURSOS_CREAR = 'cursos:crear';
    case CURSOS_CREAR_PLANTILLA = 'cursos:crear_plantilla';
    case CURSOS_EDITAR = 'cursos:editar';
    case CURSOS_ELIMINAR = 'cursos:eliminar';
    case CURSOS_VER = 'cursos:ver';

    // CURSOS > INSCRIPCIONES
    case CURSOS_INSCRIPCIONES_ALL = 'cursos/inscripciones:*';
    case CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES = 'cursos/inscripciones:eliminar_inscripciones';
    case CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS = 'cursos/inscripciones:inscribir_alumnos';
    case CURSOS_INSCRIPCIONES_VER = 'cursos/inscripciones:ver';

    // CURSOS > PROGRAMAS
    case CURSOS_PROGRAMAS_ALL = 'cursos/programas:*';
    case CURSOS_PROGRAMAS_AGREGAR = 'cursos/programas:agregar';
    case CURSOS_PROGRAMAS_ELIMINAR = 'cursos/programas:eliminar';
    case CURSOS_PROGRAMAS_VER_TODOS = 'cursos/programas:ver_todos';
    case CURSOS_PROGRAMAS_VER_ULTIMO = 'cursos/programas:ver_ultimo';

    // CURSOS > PROGRAMAS > MODIFICAR
    case CURSOS_PROGRAMAS_MODIFICAR_ALL = 'cursos/programas/modificar:*';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_1 = 'cursos/programas/modificar:modulo_1';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_2 = 'cursos/programas/modificar:modulo_2';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_3 = 'cursos/programas/modificar:modulo_3';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_4 = 'cursos/programas/modificar:modulo_4';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_5 = 'cursos/programas/modificar:modulo_5';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_6 = 'cursos/programas/modificar:modulo_6';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_7 = 'cursos/programas/modificar:modulo_7';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_8 = 'cursos/programas/modificar:modulo_8';
    case CURSOS_PROGRAMAS_MODIFICAR_MODULO_9 = 'cursos/programas/modificar:modulo_9';

    // CURSOS > UNIDADES
    case CURSOS_UNIDADES_ALL = 'cursos/unidades:*';
    case CURSOS_UNIDADES_CREAR = 'cursos/unidades:crear';
    case CURSOS_UNIDADES_CREAR_PLANTILLA = 'cursos/unidades:crear_plantilla';
    case CURSOS_UNIDADES_EDITAR = 'cursos/unidades:editar';
    case CURSOS_UNIDADES_ELIMINAR = 'cursos/unidades:eliminar';
    case CURSOS_UNIDADES_VER = 'cursos/unidades:ver';

    // DEPARTAMENTOS
    case DEPARTAMENTOS_ALL = 'departamentos:*';
    case DEPARTAMENTOS_CREAR = 'departamentos:crear';
    case DEPARTAMENTOS_EDITAR = 'departamentos:editar';
    case DEPARTAMENTOS_ELIMINAR = 'departamentos:eliminar';
    case DEPARTAMENTOS_VER = 'departamentos:ver';

    // FACULTADES
    case FACULTADES_ALL = 'facultades:*';
    case FACULTADES_CREAR = 'facultades:crear';
    case FACULTADES_EDITAR = 'facultades:editar';
    case FACULTADES_ELIMINAR = 'facultades:eliminar';
    case FACULTADES_VER = 'facultades:ver';

    // USUARIOS
    case USUARIOS_ALL = 'usuarios:*';
    case USUARIOS_CREAR = 'usuarios:crear';
    case USUARIOS_DESHABILITAR = 'usuarios:deshabilitar';
    case USUARIOS_EDITAR = 'usuarios:editar';
    case USUARIOS_RESTABLECER_CONTRASENA = 'usuarios:restablecer_contrasena';
    case USUARIOS_VER = 'usuarios:ver';

    // USUARIOS > PERMISOS
    case USUARIOS_PERMISOS_ALL = 'usuarios/permisos:*';
    case USUARIOS_PERMISOS_VER_CONTEXTOS_DISPONIBLES = 'usuarios/permisos:ver_contextos_disponibles';
    case USUARIOS_PERMISOS_VER_PERMISOS_TOTALES_ASIGNADOS = 'usuarios/permisos:ver_permisos_totales_asignados';

    // USUARIOS > PERMISOS > INDIVIDUALES
    case USUARIOS_PERMISOS_INDIVIDUALES_ALL = 'usuarios/permisos/individuales:*';
    case USUARIOS_PERMISOS_INDIVIDUALES_GESTIONAR = 'usuarios/permisos/individuales:gestionar';
    case USUARIOS_PERMISOS_INDIVIDUALES_VER_DISPONIBLES = 'usuarios/permisos/individuales:ver_disponibles';
    case USUARIOS_PERMISOS_INDIVIDUALES_VER_QUIEN_TIENE = 'usuarios/permisos/individuales:ver_quien_tiene';

    // USUARIOS > PERMISOS > ROLES
    case USUARIOS_PERMISOS_ROLES_ALL = 'usuarios/permisos/roles:*';
    case USUARIOS_PERMISOS_ROLES_CREAR = 'usuarios/permisos/roles:crear';
    case USUARIOS_PERMISOS_ROLES_EDITAR = 'usuarios/permisos/roles:editar';
    case USUARIOS_PERMISOS_ROLES_ELIMINAR = 'usuarios/permisos/roles:eliminar';
    case USUARIOS_PERMISOS_ROLES_GESTIONAR = 'usuarios/permisos/roles:gestionar';
    case USUARIOS_PERMISOS_ROLES_VER = 'usuarios/permisos/roles:ver';
}