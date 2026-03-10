<?php

namespace App\Contracts;

/**
 * Paso 1 del builder de contextos: selección de alcance.
 *
 * Obtenido desde ContextType::query(). Obliga a elegir un alcance
 * antes de acceder a los métodos terminales, previniendo cadenas inválidas.
 *
 * Flujos válidos:
 *   ContextType::CARRERA->query()->strict()->toIds()
 *   ContextType::CARRERA->query()->withDescendants()->toBuilder()
 *
 * Flujo inválido (error en tiempo de compilación/IDE):
 *   ContextType::CARRERA->query()->toIds()  ← IContextScopeStep no tiene toIds()
 *
 * @see \App\Contracts\IContextResultStep
 * @see \App\Services\Authorization\ContextQueryBuilder
 * @see \App\Enums\ContextType::query()
 */
interface IContextScopeStep
{
  /**
   * Restringe la consulta exactamente a instancias de ESTE tipo de contexto.
   *
   * EJEMPLO:
   *   ContextType::CARRERA->query()->strict()->toIds()
   *   → Solo IDs de contextos cuyo tipo es 'carrera'
   *
   * Equivale al antiguo onEveryInstance() / onAllCurrentInstances()
   */
  public function strict(): IContextResultStep;

  /**
   * Incluye instancias de ESTE tipo y TODOS sus subordinados en la jerarquía.
   *
   * EJEMPLO:
   *   ContextType::FACULTAD->query()->withDescendants()->toIds()
   *   → IDs de contextos de tipo facultad, departamento, carrera, curso y actividad
   *
   * Equivale al antiguo onAllChildrenOf($type)
   */
  public function withDescendants(): IContextResultStep;
}
