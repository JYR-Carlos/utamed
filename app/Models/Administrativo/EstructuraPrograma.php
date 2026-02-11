<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseEstructuraPrograma;

/**
 * Modelo EstructuraPrograma
 * 
 * Extiende de BaseEstructuraPrograma (auto-generado)
 */
class EstructuraPrograma extends BaseEstructuraPrograma
{
    protected $table = 'Estructura_Programa';

    /**
     * Fix for double quoting issue with Compoships.
     * Reverts to standard Eloquent behavior.
     */
    public function qualifyColumn($column)
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    public function getQualifiedKeyName()
    {
        return $this->qualifyColumn($this->getKeyName());
    }
}
