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
    protected $table = 'Departamento'; // Defined in Base
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


    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}