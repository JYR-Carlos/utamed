<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseDocente;

/**
 * Modelo Docente
 * 
 * Extiende de BaseDocente (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Docente extends BaseDocente
{
    protected $fillable = [
        'rut',
        'nombre_completo',
        'grado',
        'titulo',
        'cargo',
        'id_usuario',
        'id_contexto'
    ];
}