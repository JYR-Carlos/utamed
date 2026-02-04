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
        'contexto_display'
    ];

    protected $table = 'Contexto';
    protected $primaryKey = 'id_contexto';
    // Disable timestamps if not present
    public $timestamps = false;
}