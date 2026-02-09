<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseTipoSeccion;

/**
 * Modelo TipoSeccion
 * 
 * Extiende de BaseTipoSeccion (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class TipoSeccion extends BaseTipoSeccion
{
    // Fix for double quoting issue in Base model
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

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}