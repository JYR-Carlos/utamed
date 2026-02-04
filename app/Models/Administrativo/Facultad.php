<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseFacultad;

/**
 * Modelo Facultad
 * 
 * Extiende de BaseFacultad (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Facultad extends BaseFacultad
{
    protected $table = 'Facultad';
    protected $fillable = ['nombre', 'id_contexto'];
    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}