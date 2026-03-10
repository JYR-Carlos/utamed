<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Paso 2 del builder de contextos: operaciones terminales.
 *
 * Obtenido tras llamar strict() o withDescendants() sobre IContextScopeStep.
 * Solo expone operaciones de extracción de datos — no permite cambiar el alcance.
 *
 * Flujo inválido (error en tiempo de compilación/IDE):
 *   ContextType::CARRERA->query()->strict()->strict()  ← IContextResultStep no tiene strict()
 *
 * @see \App\Contracts\IContextScopeStep
 * @see \App\Services\Authorization\ContextQueryBuilder
 */
interface IContextResultStep
{
  /**
   * Retorna un Eloquent Builder con el alcance aplicado.
   *
   * Útil para agregar filtros adicionales antes de ejecutar la query.
   *
   * EJEMPLO:
   *   ContextType::CARRERA->query()->strict()->toBuilder()->where('activo', true)->get()
   */
  public function toBuilder(): Builder;

  /**
   * Retorna array de IDs de contexto (id_contexto).
   *
   * Eficiente para uso en whereIn() u otras queries indexed.
   * No carga los modelos completos — solo extrae la clave primaria.
   *
   * EJEMPLO:
   *   $ids = ContextType::CARRERA->query()->strict()->toIds();
   *   // → [42, 43, 44]
   *   Contexto::whereIn('id_contexto_padre', $ids)->get();
   *
   * @return int[]
   */
  public function toIds(): array;

  /**
   * Retorna el conteo de instancias sin cargar los modelos.
   *
   * EJEMPLO:
   *   ContextType::CARRERA->query()->withDescendants()->count()
   *   // → 23
   */
  public function count(): int;
}
