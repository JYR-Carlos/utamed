<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseDepartamento;

/**
 * Modelo Departamento
 * 
 * Extiende de BaseDepartamento (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Departamento extends BaseDepartamento
{
    /**
     * Override primary key to use single identity column for Eloquent compatibility
     */
    protected $primaryKey = 'id_departamento';
    public $incrementing = true;
    protected $fillable = ['nombre', 'id_facultad', 'id_contexto'];

    /**
     * Wayfinder/Eloquent workaround: return a single key name instead of the composite array
     * to prevent Reflection errors in Wayfinder.
     */
    public function getRouteKeyName()
    {
        return 'id_departamento';
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.

    /**
     * Fix for double quoting issue in BaseDepartamento.
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