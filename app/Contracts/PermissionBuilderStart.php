<?php

namespace App\Contracts;

use App\Enums\ContextualModelType;
use App\Models\Usuario\Usuario;

/**
 * Paso 1 del step builder de permisos: selección de contexto.
 *
 * **Estado inicial del builder**
 * 
 * El IDE ofrece SOLO los métodos de contexto y `as()`. 
 * Una vez invocado cualquier método de contexto, se transita
 * a PermissionBuilderReady, donde ya no es posible cambiar el scope.
 *
 * Esto previene encadenamientos absurdos como:
 * - `->on($carrera)->onEveryInstance()` // ya pusiste un contexto específico
 * - `->onEveryInstance()->on($carrera)` // contradictorio
 *
 * ---
 * @see PermissionBuilderReady.php  Paso 2 (configuración + guardado)
 * @see ..\..\app\Services\Authorization\PermissionAssignmentBuilder.php  Implementación concreta
 */
interface PermissionBuilderStart
{
  /**
   * Fijar el contexto a una o más instancias concretas de modelos con contexto propio.
   *
   * @param HasOwnedContext|HasOwnedContext[] $resources Instancia o arreglo de modelos
   */
  public function on(HasOwnedContext|array $resources): PermissionBuilderReady;

  /**
   * Asignar a TODAS las instancias ACTUALES en BD del tipo dado.
   * 
   * Crea un registro UPE por cada contexto existente del tipo.
   *
   * @param ContextualModelType $type Tipo de modelo contextual
   */
  public function onAllCurrentInstances(ContextualModelType $type): PermissionBuilderReady;

  /**
   * Asignar al contexto global. Cubre por herencia TODAS las jerarquías.
   * 
   * Crea 1 solo registro UPE en el contexto global.
   */
  public function onEveryInstance(): PermissionBuilderReady;

  /**
   * Asignar sobre el contexto del padre. La herencia propaga a todos
   * los $childType que pertenezcan a $parent.
   *
   * Valida que $childType sea descendiente del tipo de $parent en la jerarquía.
   *
   * @param HasOwnedContext     $parent    Modelo padre (ej: $facultad)
   * @param ContextualModelType $childType Tipo hijo (ej: ContextualModelType::CARRERA)
   * @throws \InvalidArgumentException Si $childType no es descendiente del tipo de $parent
   */
  public function onAllChildrenOf(HasOwnedContext $parent, ContextualModelType $childType): PermissionBuilderReady;

  /**
   * Fijar el contexto directamente por ID(s).
   *
   * Escape hatch para casos donde se tiene el ID del contexto
   * pero no la instancia del modelo.
   *
   * @param int|int[] $contextIds ID o IDs del contexto
   */
  public function inContext(int|array $contextIds): PermissionBuilderReady;

  /**
   * Especificar quién realiza la asignación (sobrescribe Auth::user()).
   * El retorno `static` permite encadenar este método antes de elegir contexto, 
   * o después sin perder la configuración.
   * 
   * Se mantiene en paso 1. El actor puede fijarse antes de elegir contexto.
   *
   * @param Usuario $actor Usuario que realiza la asignación
   */
  public function as(Usuario $actor): static;
}
