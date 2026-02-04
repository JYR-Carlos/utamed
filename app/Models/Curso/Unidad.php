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
    /**
     * Override primary key to use single identity column for Eloquent compatibility
     */
    protected $primaryKey = 'id_unidad';
    public $incrementing = true;

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