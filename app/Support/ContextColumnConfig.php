<?php

namespace App\Support;

/**
 * Configuración centralizada para contextos.
 * 
 * Proporciona acceso a valores de configuración de contextos
 * desde múltiples puntos del código de forma consistente.
 */
final class ContextColumnConfig
{
  /**
   * Obtener el nombre de la columna que almacena contextos.
   * 
   * @return string Nombre de la columna (ej: 'id_contexto')
   */
  public static function contextColumn(): string
  {
    return config('context-hierarchies.context_column', 'id_contexto');
  }
}
