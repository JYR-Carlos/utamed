<?php

namespace App\Traits\QueryScopes;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait para scopes de filtrado por contexto.
 */
trait FiltersContextScope
{
    /**
     * Scope global para filtrar por contexto.
     */
    public function scopeWhereContext(Builder $query, $contextIds): Builder
    {
        $mapping = $this->resolveContextMapping();
        $type = $mapping['type'] ?? null;
        $contextIds = $this->normalizeContextIds($contextIds);

        if ($type === 'global') {
            return $query;
        }

        if (empty($contextIds)) {
            return $query->whereRaw('1 = 0');
        }

        if ($type === 'direct') {
            $contextColumn = config('context-hierarchies.context_column', 'id_contexto');
            return $query->whereIn($this->qualifyColumn($contextColumn), $contextIds);
        }

        if ($type === 'hierarchical' && method_exists($this, 'scopeWhereContextHierarchy')) {
            return $this->scopeWhereContextHierarchy($query, $contextIds);
        }

        return $query;
    }

    protected function normalizeContextIds($contextIds): array
    {
        if ($contextIds === null) {
            return [];
        }

        $ids = is_array($contextIds) ? $contextIds : [$contextIds];

        return array_values(array_unique(array_filter($ids, fn($id) => $id !== null)));
    }

    protected function resolveContextMapping(): ?array
    {
        $mappings = $this->loadContextMappings();
        $modelKey = $this->getContextMappingKey();

        return $mappings[$modelKey] ?? null;
    }

    protected function loadContextMappings(): array
    {
        static $mappings = null;

        if ($mappings !== null) {
            return $mappings;
        }

        $configPath = config_path('generated-context-mappings.php');
        if (!file_exists($configPath)) {
            $mappings = [];
            return $mappings;
        }

        $mappings = include $configPath;

        return $mappings;
    }

    protected function getContextMappingKey(): string
    {
        $class = static::class;
        $parts = explode('\\', $class);
        $schema = $parts[count($parts) - 2] ?? 'Unknown';
        $modelName = $parts[count($parts) - 1] ?? 'Unknown';

        return "utamed.{$schema}.{$modelName}";
    }
}
