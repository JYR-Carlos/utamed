<?php

namespace App\Extensions\Compoships;

use Awobaz\Compoships\Database\Eloquent\Relations\HasMany as BaseHasMany;

class HasMany extends BaseHasMany
{
    /**
     * Override addEagerConstraints to ensure keys are correctly quoted for PostgreSQL.
     */
    public function addEagerConstraints(array $models)
    {
        if (is_array($this->foreignKey)) { // Check for multi-columns relationship
            $keys = [];

            foreach ($this->foreignKey as $key) {
                // foreignKey comes as 'table.column' from sanitizeKey
                // Extract just the column name and quote it for PostgreSQL
                $parts = explode('.', $key);
                $columnName = end($parts);
                $keys[] = '"' . $columnName . '"';
            }

            $whereIn = $this->whereInMethod($this->parent, $this->localKey);
            $this->query->{$whereIn}($keys, $this->getKeys($models, $this->localKey));
        } else {
            parent::addEagerConstraints($models);
        }
    }
}
