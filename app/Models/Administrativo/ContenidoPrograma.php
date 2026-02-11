<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseContenidoPrograma;

/**
 * Modelo ContenidoPrograma
 * 
 * Extiende de BaseContenidoPrograma (auto-generado)
 */
class ContenidoPrograma extends BaseContenidoPrograma
{
    protected $table = 'Contenido_Programa';

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
