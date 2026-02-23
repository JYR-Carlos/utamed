<?php

namespace App\Support;

/**
 * Slugs de permisos centralizados, estandarizados y a prueba de errores.
 * AUTOGENERADO desde scripts/permissions_config.php — NO EDITAR.
 *
 * Uso: Permissions::FACULTAD_VER <- 'facultad:ver'
 * Para agregar permisos, editar scripts/permissions_config.php y regenerar.
 */
class Permissions
{
    const USUARIOS_VER = 'usuarios:ver';
    const USUARIOS_CREAR = 'usuarios:crear';
    const USUARIOS_EDITAR = 'usuarios:editar';
    const USUARIOS_DESHABILITAR = 'usuarios:deshabilitar';
    const USUARIOS_RESTABLECER_CONTRASENA = 'usuarios:restablecer_contrasena';
    const USUARIOS_PERMISOS_VER_CONTEXTOS_DISPONIBLES = 'usuarios/permisos:ver_contextos_disponibles';
    const USUARIOS_PERMISOS_VER_PERMISOS_TOTALES_ASIGNADOS = 'usuarios/permisos:ver_permisos_totales_asignados';
    const USUARIOS_PERMISOS_ROLES_GESTIONAR = 'usuarios/permisos/roles:gestionar';
    const USUARIOS_PERMISOS_ROLES_VER = 'usuarios/permisos/roles:ver';
    const USUARIOS_PERMISOS_ROLES_CREAR = 'usuarios/permisos/roles:crear';
    const USUARIOS_PERMISOS_ROLES_EDITAR = 'usuarios/permisos/roles:editar';
    const USUARIOS_PERMISOS_ROLES_ELIMINAR = 'usuarios/permisos/roles:eliminar';
    const USUARIOS_PERMISOS_INDIVIDUALES_GESTIONAR = 'usuarios/permisos/individuales:gestionar';
    const USUARIOS_PERMISOS_INDIVIDUALES_VER_DISPONIBLES = 'usuarios/permisos/individuales:ver_disponibles';
    const USUARIOS_PERMISOS_INDIVIDUALES_VER_QUIEN_TIENE = 'usuarios/permisos/individuales:ver_quien_tiene';

    const FACULTADES_VER = 'facultades:ver';
    const FACULTADES_CREAR = 'facultades:crear';
    const FACULTADES_EDITAR = 'facultades:editar';
    const FACULTADES_ELIMINAR = 'facultades:eliminar';

    const DEPARTAMENTOS_VER = 'departamentos:ver';
    const DEPARTAMENTOS_CREAR = 'departamentos:crear';
    const DEPARTAMENTOS_EDITAR = 'departamentos:editar';
    const DEPARTAMENTOS_ELIMINAR = 'departamentos:eliminar';

    const CARRERAS_VER = 'carreras:ver';
    const CARRERAS_CREAR = 'carreras:crear';
    const CARRERAS_EDITAR = 'carreras:editar';
    const CARRERAS_ELIMINAR = 'carreras:eliminar';

    const PLANES_CREAR = 'planes:crear';
    const PLANES_EDITAR = 'planes:editar';
    const PLANES_ELIMINAR = 'planes:eliminar';
    const PLANES_ASIGNACION_ASIGNATURAS = 'planes:asignacion_asignaturas';
    const PLANES_VER_VER_DETALLES = 'planes/ver:ver_detalles';
    const PLANES_VER_VER_MALLA = 'planes/ver:ver_malla';

    const ASIGNATURAS_VER = 'asignaturas:ver';
    const ASIGNATURAS_CREAR = 'asignaturas:crear';
    const ASIGNATURAS_EDITAR = 'asignaturas:editar';
    const ASIGNATURAS_ELIMINAR = 'asignaturas:eliminar';

    const CURSOS_VER = 'cursos:ver';
    const CURSOS_CREAR = 'cursos:crear';
    const CURSOS_CREAR_PLANTILLA = 'cursos:crear_plantilla';
    const CURSOS_EDITAR = 'cursos:editar';
    const CURSOS_ELIMINAR = 'cursos:eliminar';
    const CURSOS_INSCRIPCIONES_VER = 'cursos/inscripciones:ver';
    const CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS = 'cursos/inscripciones:inscribir_alumnos';
    const CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES = 'cursos/inscripciones:eliminar_inscripciones';
    const CURSOS_SECCIONES_VER = 'cursos/secciones:ver';
    const CURSOS_SECCIONES_CREAR = 'cursos/secciones:crear';
    const CURSOS_SECCIONES_CREAR_PLANTILLA = 'cursos/secciones:crear_plantilla';
    const CURSOS_SECCIONES_EDITAR = 'cursos/secciones:editar';
    const CURSOS_SECCIONES_ELIMINAR = 'cursos/secciones:eliminar';
    const CURSOS_UNIDADES_VER = 'cursos/unidades:ver';
    const CURSOS_UNIDADES_CREAR = 'cursos/unidades:crear';
    const CURSOS_UNIDADES_CREAR_PLANTILLA = 'cursos/unidades:crear_plantilla';
    const CURSOS_UNIDADES_EDITAR = 'cursos/unidades:editar';
    const CURSOS_UNIDADES_ELIMINAR = 'cursos/unidades:eliminar';
    const CURSOS_ACTIVIDADES_VER = 'cursos/actividades:ver';
    const CURSOS_ACTIVIDADES_CREAR = 'cursos/actividades:crear';
    const CURSOS_ACTIVIDADES_CREAR_PLANTILLA = 'cursos/actividades:crear_plantilla';
    const CURSOS_ACTIVIDADES_EDITAR = 'cursos/actividades:editar';
    const CURSOS_ACTIVIDADES_ELIMINAR = 'cursos/actividades:eliminar';
    const CURSOS_ACTIVIDADES_SUBIR_ENTREGAS = 'cursos/actividades:subir_entregas';
    const CURSOS_ACTIVIDADES_EVALUAR = 'cursos/actividades:evaluar';
    const CURSOS_ACTIVIDADES_DAR_FEEDBACK = 'cursos/actividades:dar_feedback';
    const CURSOS_ACTIVIDADES_ENVIAR_RECORDATORIOS = 'cursos/actividades:enviar_recordatorios';
    const CURSOS_ACTIVIDADES_DESCARGAR_ENTREGAS = 'cursos/actividades:descargar_entregas';
    const CURSOS_ACTIVIDADES_GRUPOS_VER = 'cursos/actividades/grupos:ver';
    const CURSOS_ACTIVIDADES_GRUPOS_CREAR = 'cursos/actividades/grupos:crear';
    const CURSOS_ACTIVIDADES_GRUPOS_EDITAR = 'cursos/actividades/grupos:editar';
    const CURSOS_ACTIVIDADES_GRUPOS_ELIMINAR = 'cursos/actividades/grupos:eliminar';
    const CURSOS_PROGRAMAS_VER = 'cursos/programas:ver';
    const CURSOS_PROGRAMAS_ELIMINAR = 'cursos/programas:eliminar';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_1 = 'cursos/programas/modificar:modulo_1';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_2 = 'cursos/programas/modificar:modulo_2';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_3 = 'cursos/programas/modificar:modulo_3';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_4 = 'cursos/programas/modificar:modulo_4';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_5 = 'cursos/programas/modificar:modulo_5';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_6 = 'cursos/programas/modificar:modulo_6';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_7 = 'cursos/programas/modificar:modulo_7';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_8 = 'cursos/programas/modificar:modulo_8';
    const CURSOS_PROGRAMAS_MODIFICAR_MODULO_9 = 'cursos/programas/modificar:modulo_9';
}