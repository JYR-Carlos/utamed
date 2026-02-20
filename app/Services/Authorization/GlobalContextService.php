<?php

namespace App\Services\Authorization;

use App\Models\Usuario\Contexto;

/**
 * Servicio singleton para resolver el ID del contexto global del sistema.
 *
 * El contexto global es el contexto raíz asociado al tipo_contexto con
 * tabla_referenciada = 'GLOBAL'. Se usa como contexto de fallback en todo
 * el sistema RBAC y como contexto de modelos sin contexto propio (global).
 *
 * La consulta a BD se realiza exactamente una vez por ciclo de vida del
 * proceso gracias al binding singleton en AppServiceProvider.
 */
class GlobalContextService
{
  private ?int $contextId = null;

  /**
   * Obtener el ID del contexto global.
   *
   * Lazy: consulta la BD la primera vez y lo cachea en memoria para
   * todas las llamadas posteriores dentro del mismo request/proceso.
   *
   * @return int
   * @throws \RuntimeException Si no existe o hay múltiples contextos globales.
   */
  public function getContextId(): int
  {
    if ($this->contextId === null) {
      $this->contextId = $this->load();
    }

    return $this->contextId;
  }

  private function load(): int
  {
    try {
      return Contexto::whereHas(
        'tipoContexto',
        fn($q) => $q->where('tabla_referenciada', 'GLOBAL')
      )->soleValue('id_contexto');
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
      throw new \RuntimeException(
        "Contexto global no encontrado. Verifica que exista un registro en " .
        "tipo_contexto con tabla_referenciada='GLOBAL' y su contexto asociado."
      );
    } catch (\Illuminate\Database\MultipleRecordsFoundException) {
      throw new \RuntimeException(
        "Múltiples contextos globales encontrados: configuración corrupta. " .
        "Solo puede existir UN registro en tipo_contexto con tabla_referenciada='GLOBAL'."
      );
    }
  }
}
