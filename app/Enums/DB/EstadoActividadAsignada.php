<?php

namespace App\Enums\DB;

/**
 * Enum EstadoActividadAsignada
 * 
 * Generado automáticamente desde PostgreSQL ENUM en schema 'agenda'
 * NO EDITAR MANUALMENTE - Se regenera en cada ejecución
 */
enum EstadoActividadAsignada: string
{
  case PLANIFICADA = 'PLANIFICADA';
  case ACTIVA = 'ACTIVA';
  case CERRADA = 'CERRADA';
}
