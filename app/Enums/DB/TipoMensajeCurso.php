<?php

namespace App\Enums\DB;

/**
 * Enum TipoMensajeCurso
 *
 * Refleja el ENUM PostgreSQL curso.en_tipo_mensaje_curso.
 *
 * - MENSAJE_INDIVIDUAL         → conversación entre un alumno y el equipo docente
 *                                del componente. El canal es (componente, alumno);
 *                                id_usuario_receptor sólo indica a quién iba
 *                                dirigido ese mensaje puntual, no a qué canal
 *                                pertenece.
 * - MENSAJE_PARA_TODO_EL_CURSO → difusión a todos los inscritos del componente.
 *                                id_usuario_receptor va NULL.
 */
enum TipoMensajeCurso: string
{
    case MENSAJE_INDIVIDUAL = 'MENSAJE_INDIVIDUAL';
    case MENSAJE_PARA_TODO_EL_CURSO = 'MENSAJE_PARA_TODO_EL_CURSO';
}
