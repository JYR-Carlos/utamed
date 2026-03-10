<?php

namespace App\Traits;

use App\Services\Authorization\GlobalContextService;

use App\Enums\ContextType;

/**
 * Trait GlobalContextAware
 *
 * Implementa HasContext para modelos de tipo 'global' (sin contexto propio).
 *
 * Delega getContextId() directamente al GlobalContextService, evitando la cadena
 * completa de resolución jerárquica que usa ContextAware/ContextResolver.
 *
 * DIFERENCIA con ContextAware:
 *   - ContextAware  → modelos direct/hierarchical → implementan HasOwnedContext
 *   - GlobalContextAware → modelos global → implementan sólo HasContext
 *
 * Usado por: Usuario, Docente, Estudiante, Rol, Asignatura
 */
trait GlobalContextAware
{
  /**
   * Retorna el ID del contexto global del sistema.
   *
   * @return array<int>
   */
  public function getContextId(): array
  {
    $globalId = app(GlobalContextService::class)->getContextId();

    return $globalId !== null ? [$globalId] : [];
  }

  /**
   * Los modelos globales no tienen un tipo de contexto propio.
   *
   * @return array<ContextType> Siempre vacío para modelos globales.
   */
  public function getContextTypes(): array
  {
    return [];
  }

  /**
   * Los modelos globales no tienen un modelo padre de contexto.
   *
   * @return null
   */
  public function getParentContextModel(): ?\Illuminate\Database\Eloquent\Model
  {
    return null;
  }
}
