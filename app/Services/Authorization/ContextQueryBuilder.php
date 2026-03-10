<?php

namespace App\Services\Authorization;

use App\Contracts\IContextResultStep;
use App\Enums\ContextType;
use App\Models\Usuario\Contexto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builder fluido para consultas sobre instancias de contexto por tipo (usuario.contexto).
 *
 * @internal Usar ContextType::query() para obtener una instancia de este builder
 *
 * @see \App\Contracts\IContextScopeStep   — interface del paso 1 (alcance)
 * @see \App\Contracts\IContextResultStep  — interface del paso 2 (terminales)
 * @see \App\Enums\ContextType::query()    — entry point
 */
final class ContextQueryBuilder implements IContextResultStep
{
  private readonly Builder $builder;

  /**
   * Constructor privado 
   * 
   * Es obligación usar los factory methods (ContextType::CASE->query()->...).
   */
  private function __construct(private readonly ContextType $type, Builder $builder)
  {
    $this->builder = $builder;
  }

  // ──────────────────────────────────────────────────────────────────────────
  // FACTORIES — entry points para cada tipo de alcance
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Crea un builder para instancias SOLO de este tipo.
   */
  public static function strict(ContextType $type): IContextResultStep
  {
    $builder = Contexto::query()
      ->whereHas(
        'tipoContexto',
        fn(Builder $q) => $q->where('categoria', $type->value)
      );

    return new self($type, $builder);
  }

  /**
   * Crea un builder para este tipo + todos sus descendientes.
   */
  public static function withDescendants(ContextType $type): IContextResultStep
  {
    $categories = [
      $type->value,
      ...array_map(fn(ContextType $t) => $t->value, $type->descendantTypes()),
    ];

    $builder = Contexto::query()
      ->whereHas(
        'tipoContexto',
        fn(Builder $q) => $q->whereIn('categoria', $categories)
      );

    return new self($type, $builder);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // PASO 2 — Terminales (IContextResultStep)
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * @inheritDoc
   */
  public function toBuilder(): Builder
  {
    return $this->builder->clone();
  }

  /**
   * @inheritDoc
   */
  public function toIds(): array
  {
    return $this->builder->pluck('id_contexto')->all();
  }

  /**
   * @inheritDoc
   */
  public function count(): int
  {
    return $this->builder->count();
  }
}
