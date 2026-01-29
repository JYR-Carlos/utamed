<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseContexto;

/**
 * Modelo Contexto
 * 
 * Extiende de BaseContexto (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Contexto extends BaseContexto
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'contexto_display' // Keep from base just in case
    ];
    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.
}