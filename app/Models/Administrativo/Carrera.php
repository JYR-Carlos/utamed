<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseCarrera;

/**
 * Modelo Carrera
 * 
 * Extiende de BaseCarrera (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Carrera extends BaseCarrera
{
    protected $table = 'Carrera';
    /**
     * Override primary key to use single identity column for Eloquent compatibility
     */
    protected $primaryKey = 'id_carrera';
    public $incrementing = true;
    protected $fillable = [
        'nombre',
        'jornada',
        'sede',
        'modalidad',
        'id_departamento',
        'id_facultad',
        'id_contexto'
    ];
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

    /**
     * Override Compoships newBelongsTo to use our custom BelongsTo relation
     * which fixes the eager loading quoting issue.
     */
    protected function newBelongsTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Database\Eloquent\Model $child, $foreignKey, $ownerKey, $relation)
    {
        return new \App\Extensions\Compoships\BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }
}