<?php

namespace App\Enums\DB;

/**
 * Enum TipoMensajeCurso
 * 
 * Generado automáticamente desde PostgreSQL ENUM en schema 'curso'
 * NO EDITAR MANUALMENTE - Se regenera en cada ejecución
 */
enum TipoMensajeCurso: string
{
  case MENSAJE_INDIVIDUAL = 'MENSAJE_INDIVIDUAL';
  case MENSAJE_PARA_TODO_EL_CURSO = 'MENSAJE_PARA_TODO_EL_CURSO';
}
