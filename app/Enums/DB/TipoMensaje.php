<?php

namespace App\Enums\DB;

/**
 * Enum TipoMensaje
 * 
 * Generado automáticamente desde PostgreSQL ENUM en schema 'agenda'
 * NO EDITAR MANUALMENTE - Se regenera en cada ejecución
 */
enum TipoMensaje: string
{
  case MENSAJE_AL_PROFESOR = 'Mensaje al profesor';
  case FEEDBACK = 'Feedback';
  case ENTREGA_DE_ARCHIVO = 'Entrega de archivo';
  case CANCELACIÓN_DE_ENTREGA = 'Cancelación de entrega';
  case EVALUACIÓN = 'Evaluación';
  case CIERRE_DE_ACTIVIDAD = 'Cierre de actividad';
}
