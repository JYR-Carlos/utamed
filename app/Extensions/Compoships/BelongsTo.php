<?php

namespace App\Extensions\Compoships;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo as BaseBelongsTo;

class BelongsTo extends BaseBelongsTo
{
    /**
     * Override addEagerConstraints to ensure keys are correctly quoted.
     */
    public function addEagerConstraints(array $models): void
    {
        if (is_array($this->ownerKey)) { // Check for multi-columns relationship
            $keys = [];

            foreach ($this->ownerKey as $key) {
                // Manually quote the table and column to safe strings
                // We avoid using qualifyColumn if it returns an Expression, 
                // instead we construct the quoted string directly: "Table"."Column"
                $table = $this->related->getTable();
                $keys[] = '"' . $table . '"."' . $key . '"';
            }

            $this->query->whereIn($keys, $this->getEagerModelKeys($models));
        } else {
            parent::addEagerConstraints($models);
        }
    }
}
