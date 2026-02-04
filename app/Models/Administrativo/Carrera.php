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
}