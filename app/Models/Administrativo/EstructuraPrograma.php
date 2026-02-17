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
     * Override primary data for Eloquent compatibility
     */
    protected $primaryKey = 'id_estructura_programa';
    public $incrementing = true;

    // Base defines it as 'id_seccion' incorrectly, so we override it here.
    // Also need to check if fillable needs update, but Base looks OK for fillable.

    /**
     * Fix HasMany relationship to ContenidoPrograma
     * Base likely uses 'id_seccion' which is wrong.
     */
    public function contenidos_programa()
    {
        return $this->hasMany(\App\Models\Administrativo\ContenidoPrograma::class, 'id_estructura_programa', 'id_estructura_programa');
    }

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
