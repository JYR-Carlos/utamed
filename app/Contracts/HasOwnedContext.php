<?php

namespace App\Contracts;

/**
 * Marker interface para modelos que poseen un contexto propio (tipo 'direct' o 'hierarchical').
 *
 * Los modelos que implementan esta interfaz garantizan que resolverán al menos un
 * id_contexto concreto y son por tanto argumentos válidos de ->on($model).
 *
 * Los modelos de tipo 'global' (Usuario, Docente, Estudiante, Rol, Asignatura) implementan
 * únicamente la interfaz padre HasContext, NO esta interfaz, impidiendo que sean
 * pasados a ->on() en tiempo de análisis estático (PHPStan/Psalm) y en los builders.
 *
 * Jerarquía de interfaces:
 *   HasContext          ← todos los modelos context-aware
 *     └─ HasOwnedContext ← sólo direct + hierarchical (los que tienen contexto propio)
 */
interface HasOwnedContext extends HasContext
{
  // Sin métodos adicionales: la interfaz existe como distinción de tipo.
  // HasOwnedContext ──► safe for ->on($model) and ->onAll(ContextualModelType)
  // HasContext only ──► global context (rejected by ->on() at compile time)
}
