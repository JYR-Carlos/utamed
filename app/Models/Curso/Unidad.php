<?php

namespace App\Models\Curso;

use App\Models\Base\Curso\BaseUnidad;

/**
 * Modelo Unidad
 * 
 * Extiende de BaseUnidad (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Unidad extends BaseUnidad
{
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

    public function getRouteKeyName()
    {
        return 'id_unidad';
    }

    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}
